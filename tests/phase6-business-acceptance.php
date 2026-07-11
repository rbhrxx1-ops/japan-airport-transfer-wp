<?php
/**
 * Phase 6 business acceptance checks.
 *
 * Run with:
 * wp --path=wordpress eval-file tests/phase6-business-acceptance.php
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

$assert(get_locale() === 'ja', '站点语言不是 ja。');
$assert(wp_timezone_string() === 'Asia/Tokyo', '站点时区不是 Asia/Tokyo。');
$assert((string) get_option('blog_public') === '0', '隔离测试站必须保持禁止索引。');
$assert((string) get_option('permalink_structure') === '/%postname%/', '固定链接结构不符合批准基线。');
$assert(wp_get_theme()->get_stylesheet() === 'jat-meet-theme', '专属主题未激活。');
$assert(is_plugin_active('jat-reservation/jat-reservation.php'), '预约插件未激活。');

$requiredPublished = array(
    'home' => 'ホーム',
    'service' => 'サービス',
    'service/airport-meet' => '空港お迎え',
    'service/airport-sending' => '空港お見送り',
    'service/station-meet' => '駅お迎え',
    'service/station-sending' => '駅お見送り',
    'service/transfer-support' => '乗継サポート',
    'service/attend' => 'アテンドサービス',
    'area' => '対応エリア',
    'area/haneda-airport' => '羽田空港',
    'area/narita-airport' => '成田空港',
    'area/tokyo-station' => '東京駅',
    'area/shinagawa-station' => '品川駅',
    'flow' => 'ご利用の流れ',
    'price' => '料金',
    'faq' => 'よくあるご質問',
    'corporate' => '法人のお客様',
    'reservation' => 'オンライン申込',
    'reservation/complete' => 'お申し込みを受け付けました',
    'accessibility' => 'アクセシビリティ',
    'news' => 'お知らせ',
);

foreach ($requiredPublished as $path => $title) {
    $page = $get_page($path);
    $assert($page !== null, sprintf('必須ページがありません: %s', $path));
    if ($page !== null) {
        $assert($page->post_status === 'publish', sprintf('必須ページが公開状態ではありません: %s', $path));
        $assert($page->post_title === $title, sprintf('ページタイトルが基线と不一致です: %s', $path));
    }
}

$coreServices = array(
    'service/airport-meet' => array('到着ロビー', 'サインボード', '予約確定ではありません'),
    'service/airport-sending' => array('チェックイン', '保安検査場', '予約確定ではありません'),
    'service/station-meet' => array('号車', '改札', '予約確定ではありません'),
    'service/station-sending' => array('改札', 'ホーム', '予約確定ではありません'),
);

foreach ($coreServices as $path => $phrases) {
    $page = $get_page($path);
    if ($page === null) {
        continue;
    }
    $assert(get_post_meta($page->ID, 'jat_content_role', true) === 'service', sprintf('服务页面角色错误: %s', $path));
    foreach ($phrases as $phrase) {
        $assert(str_contains($page->post_content, $phrase), sprintf('服务页缺少业务要点“%s”: %s', $phrase, $path));
    }
}

$consultationServices = array(
    'service/transfer-support' => array('個別', 'オンライン送信だけでは予約確定になりません'),
    'service/attend' => array('個別', '行程'),
);
foreach ($consultationServices as $path => $phrases) {
    $page = $get_page($path);
    if ($page === null) {
        continue;
    }
    foreach ($phrases as $phrase) {
        $assert(str_contains($page->post_content, $phrase), sprintf('咨询分支缺少边界说明“%s”: %s', $phrase, $path));
    }
    $assert(!str_contains($page->post_content, '標準サービスに含まれる内容'), sprintf('咨询分支被误写成标准服务: %s', $path));
}

$locationPages = array(
    'area/haneda-airport',
    'area/narita-airport',
    'area/tokyo-station',
    'area/shinagawa-station',
);
foreach ($locationPages as $path) {
    $page = $get_page($path);
    if ($page === null) {
        continue;
    }
    $assert(get_post_meta($page->ID, 'jat_content_role', true) === 'location', sprintf('地点页面角色错误: %s', $path));
    $assert(get_post_meta($page->ID, 'jat_publish_ready', true) !== '1', sprintf('地点页面在现场核验前不应标记发布就绪: %s', $path));
    $assert(get_post_meta($page->ID, 'jat_last_verified_on', true) === '', sprintf('地点页面不应伪造最终核验日期: %s', $path));
    $assert(get_post_meta($page->ID, 'jat_verifier_name', true) === '', sprintf('地点页面不应伪造核验负责人: %s', $path));
    $assert(str_contains($page->post_content, '変更される場合があります'), sprintf('地点页缺少动态现场信息提示: %s', $path));
}

$requiredDrafts = array(
    'company',
    'company/contact',
    'privacy-policy',
    'terms',
    'cancellation-policy',
    'legal',
    'cases',
);
foreach ($requiredDrafts as $path) {
    $page = $get_page($path);
    $assert($page !== null, sprintf('应保留的草稿框架不存在: %s', $path));
    if ($page !== null) {
        $assert($page->post_status === 'draft', sprintf('未确认内容被错误公开: %s', $path));
        $assert(get_post_meta($page->ID, 'jat_publish_ready', true) !== '1', sprintf('未确认草稿被错误标记发布就绪: %s', $path));
    }
}

$reservationPage = $get_page('reservation');
if ($reservationPage !== null) {
    $assert(has_shortcode($reservationPage->post_content, 'jat_reservation_form'), '预约页面未接入五步预约短代码。');
}

$pricePage = $get_page('price');
if ($pricePage !== null) {
    $assert(str_contains($pricePage->post_content, 'お見積り'), '价格页未保持个别报价边界。');
    $assert(!preg_match('/(?:¥|￥)\s*[0-9]|[0-9]{1,3}(?:,[0-9]{3})+\s*円/u', wp_strip_all_tags($pricePage->post_content)), '价格页包含未经确认的具体金额。');
}

$faqCount = (int) wp_count_posts('jat_faq')->publish;
$assert($faqCount === 24, sprintf('结构化 FAQ 数量应为 24，实际为 %d。', $faqCount));

$frontPageId = (int) get_option('page_on_front');
$frontPage = $get_page('home');
$assert($frontPage !== null && $frontPageId === (int) $frontPage->ID, '静态首页未指向 home 页面。');
$assert((string) get_option('show_on_front') === 'page', '首页展示方式不是静态页面。');

if ($failures !== array()) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    fwrite(STDERR, sprintf("PHASE6_BUSINESS=FAIL (%d/%d failed)\n", count($failures), $checks));
    exit(1);
}

echo sprintf("PHASE6_BUSINESS=PASS (%d checks)\n", $checks);
