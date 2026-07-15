<?php
/**
 * Meet & Greet content remediation regression checks.
 *
 * Run with:
 * wp --path=wordpress eval-file tests/content-remediation.php
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress must be loaded.\n");
    exit(1);
}

$checks = 0;
$failures = array();

$assert = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$get_page = static function (string $path): ?WP_Post {
    $page = get_page_by_path($path, OBJECT, 'page');
    return $page instanceof WP_Post ? $page : null;
};

$assert_contains = static function (?WP_Post $page, string $phrase, string $path) use ($assert): void {
    $assert($page instanceof WP_Post, sprintf('页面不存在: %s', $path));
    if ($page instanceof WP_Post) {
        $assert(str_contains($page->post_content, $phrase), sprintf('页面缺少整改要点“%s”: %s', $phrase, $path));
    }
};

$service = $get_page('service');
foreach (array('グリーター', '車両・乗務員', 'ご依頼元', 'ハイヤー・タクシーとの連携') as $phrase) {
    $assert_contains($service, $phrase, 'service');
}

$flow = $get_page('flow');
foreach (array('メール等の書面', '乗務員と連携', '異常時', '完了を報告') as $phrase) {
    $assert_contains($flow, $phrase, 'flow');
}
$assert_contains($flow, '送信時点では予約確定ではありません', 'flow');

$price = $get_page('price');
foreach (array('基本サービス料金', '日時・時期による変動', '追加支援料金', '実費・その他', '合計金額', 'メール等の書面') as $phrase) {
    $assert_contains($price, $phrase, 'price');
}
if ($price instanceof WP_Post) {
    $plainPrice = wp_strip_all_tags($price->post_content);
    preg_match_all('/([0-9]{1,3}(?:,[0-9]{3})*)円/u', $plainPrice, $priceMatches);
    $actualAmounts = array_values(array_unique($priceMatches[1] ?? array()));
    sort($actualAmounts, SORT_NATURAL);
    $approvedAmounts = array('3,000', '7,000', '12,000', '13,000', '15,000', '17,000', '18,000', '20,000');
    sort($approvedAmounts, SORT_NATURAL);
    $assert($actualAmounts === $approvedAmounts, sprintf('价格页金额集合超出授权白名单: %s', implode(', ', $actualAmounts)));
    $assert(str_contains($price->post_content, '基本料金（税込）'), '价格页缺少含税基本料金标识。');
    $assert(str_contains($price->post_content, '早朝・深夜料金（税込）'), '价格页缺少含税早朝・深夜料金标识。');
    $assert(str_contains($price->post_content, 'メール等の書面で予約確定'), '价格页缺少书面预约确认边界。');
}

$area = $get_page('area');
foreach (array('コア対応地点', '日本国内およびアジア', 'すべての地域での対応を保証するものではありません') as $phrase) {
    $assert_contains($area, $phrase, 'area');
}
$otherArea = $get_page('area/other-locations');
$assert_contains($otherArea, 'ネットワークを確認して対応可否を個別にご案内します', 'area/other-locations');
$assert_contains($otherArea, 'すべての地域での対応を保証するものではありません', 'area/other-locations');

$corporate = $get_page('corporate');
foreach (array('完了報告の項目例', '実際の報告項目、方法、送付時期はご依頼ごとの約定', '会合時刻', '特記事項') as $phrase) {
    $assert_contains($corporate, $phrase, 'corporate');
}

$legal = $get_page('legal');
$assert($legal instanceof WP_Post, '特商法页面不存在。');
if ($legal instanceof WP_Post) {
    $assert($legal->post_status === 'draft', '本地未核准特商法占位页不应公开。');
    $assert(str_contains($legal->post_content, '事業責任者および法務確認後に公開します'), '本地特商法草稿缺少发布门禁说明。');
}

$seedFile = dirname(__DIR__) . '/bin/seed-site.php';
$frontFile = dirname(__DIR__) . '/wp-content/themes/jat-meet-theme/templates/front-page.html';
$seed = file_get_contents($seedFile);
$front = file_get_contents($frontFile);
$assert(is_string($seed) && is_string($front), '内容数据源或首页模板无法读取。');
if (is_string($front)) {
    foreach (array('お客様、依頼元、乗務員、グリーター', 'オンライン申込は受付です') as $phrase) {
        $assert(str_contains($front, $phrase), sprintf('首页缺少既有业务边界“%s”。', $phrase));
    }
}
if (is_string($seed)) {
    $assert(str_contains($seed, "! empty(\$page['preserve_if_published'])"), '内容种子缺少已发布公司/法务页面保护逻辑。');
    $assert(substr_count($seed, "'preserve_if_published' => true") === 6, '需要保护的公司、联系和法务页面定义数量不是 6。');
    foreach (array('WhatsApp', 'LINEで', '24時間以内に返信', '全地域で対応可能') as $prohibited) {
        $assert(!str_contains($seed, $prohibited), sprintf('内容种子包含未授权承诺或渠道“%s”。', $prohibited));
    }
}

if ($failures !== array()) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    fwrite(STDERR, sprintf("CONTENT_REMEDIATION=FAIL (%d/%d failed)\n", count($failures), $checks));
    exit(1);
}

echo sprintf("CONTENT_REMEDIATION=PASS (%d checks)\n", $checks);
