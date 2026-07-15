<?php
/**
 * Public price publication checks.
 *
 * Run with:
 * wp --path=wordpress eval-file tests/public-price-publication.php
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

$price = get_page_by_path('price', OBJECT, 'page');
$assert($price instanceof WP_Post, '料金页面不存在。');

if ($price instanceof WP_Post) {
    $content = $price->post_content;
    $plain = wp_strip_all_tags($content);

    $assert($price->post_status === 'publish', '料金页面不是公开状态。');
    $assert(str_contains($content, '料金はすべて<strong>消費税込み</strong>です'), '料金页面缺少全部含税说明。');

    $expectedRows = array(
        '<tr><td>空港内の送迎サポート</td><td>7,000円</td><td>12,000円</td><td>2時間以内</td></tr>',
        '<tr><td>ターミナル間の乗継サポート</td><td>12,000円</td><td>17,000円</td><td>3時間以内</td></tr>',
        '<tr><td>空港と東京都内の駅・ホテル間の同行</td><td>15,000円</td><td>20,000円</td><td>3時間以内</td></tr>',
        '<tr><td>駅構内の送迎サポート</td><td>7,000円</td><td>12,000円</td><td>1時間30分以内</td></tr>',
        '<tr><td>駅と近隣ホテル間の同行</td><td>13,000円</td><td>18,000円</td><td>3時間以内</td></tr>',
    );
    foreach ($expectedRows as $row) {
        $assert(str_contains($content, $row), sprintf('料金页面缺少已授权价格行: %s', wp_strip_all_tags($row)));
    }
    $assert(substr_count($content, $expectedRows[3]) === 2, '东京站与品川站的站内送迎价格行数量不正确。');
    $assert(substr_count($content, $expectedRows[4]) === 2, '东京站与品川站的酒店同行价格行数量不正确。');

    foreach (array('羽田空港（HND）', '東京駅（Tokyo Station）', '品川駅（Shinagawa Station）', '成田空港（NRT）') as $location) {
        $assert(str_contains($content, $location), sprintf('料金页面缺少地点: %s', $location));
    }

    $naritaStart = strpos($content, '<h3>成田空港（NRT）</h3>');
    $commonStart = strpos($content, '<h3>共通条件</h3>');
    $assert($naritaStart !== false && $commonStart !== false && $naritaStart < $commonStart, '无法识别成田机场准备中区块。');
    if ($naritaStart !== false && $commonStart !== false && $naritaStart < $commonStart) {
        $naritaBlock = substr($content, $naritaStart, $commonStart - $naritaStart);
        $assert(str_contains($naritaBlock, '<strong>準備中</strong>'), '成田机场未标记为准备中。');
        $assert(!preg_match('/[0-9]{1,3}(?:,[0-9]{3})*円/u', $naritaBlock), '成田机场准备中区块不应发布金额。');
    }

    preg_match_all('/([0-9]{1,3}(?:,[0-9]{3})*)円/u', $plain, $matches);
    $actualAmounts = array_values(array_unique($matches[1] ?? array()));
    sort($actualAmounts, SORT_NATURAL);
    $approvedAmounts = array('3,000', '7,000', '12,000', '13,000', '15,000', '17,000', '18,000', '20,000');
    sort($approvedAmounts, SORT_NATURAL);
    $assert($actualAmounts === $approvedAmounts, sprintf('料金页面金额集合不等于授权白名单: %s', implode(', ', $actualAmounts)));
    $assert(!preg_match('/(?:¥|￥)\s*[0-9]/u', $plain), '料金页面出现未授权的货币符号金额格式。');

    foreach (array('7:00〜20:59', '21:00〜翌6:59', '30分ごとに <code>3,000円（税込）</code>', '交通費等の実費は、別途申し受けます') as $condition) {
        $assert(str_contains($content, $condition), sprintf('料金页面缺少共同条件: %s', wp_strip_all_tags($condition)));
    }
    $assert(str_contains($content, '当社からメール等の書面で予約確定をお知らせするまでは、サービスの成立や料金の請求は確定しません'), '料金页面缺少书面确认才算预约确定的业务边界。');
}

if ($failures !== array()) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    fwrite(STDERR, sprintf("PUBLIC_PRICE_PUBLICATION=FAIL (%d/%d failed)\n", count($failures), $checks));
    exit(1);
}

echo sprintf("PUBLIC_PRICE_PUBLICATION=PASS (%d checks)\n", $checks);
