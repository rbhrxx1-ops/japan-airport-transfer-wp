<?php
/**
 * Meet & Link brand publication checks.
 *
 * Run with:
 * wp --path=wordpress eval-file tests/meet-and-link-brand-publication.php
 */

if (! defined('ABSPATH')) {
    fwrite(STDERR, "WordPress must be loaded.\n");
    exit(1);
}

$checks = 0;
$failures = array();

$assert = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (! $condition) {
        $failures[] = $message;
    }
};

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root, $assert): string {
    $path = $root . '/' . $relative;
    $assert(is_file($path), sprintf('品牌文件不存在: %s', $relative));
    if (! is_file($path)) {
        return '';
    }
    $content = file_get_contents($path);
    $assert(is_string($content), sprintf('品牌文件无法读取: %s', $relative));
    return is_string($content) ? $content : '';
};

$brandName = 'Meet & Link';
$brandReading = 'ミート＆リンク';
$subtitle = 'EXECUTIVE & EVENT TRANSFER';
$description = '会議・来賓の送迎と出迎え';

$assert(html_entity_decode((string) get_option('blogname'), ENT_QUOTES | ENT_HTML5, 'UTF-8') === $brandName, 'WordPress 站点名称不是 Meet & Link。');
$assert(get_option('blogdescription') === $description, 'WordPress 日文说明语不正确。');

$header = $read('wp-content/themes/jat-meet-theme/parts/header.html');
$footer = $read('wp-content/themes/jat-meet-theme/parts/footer.html');
$frontPage = $read('wp-content/themes/jat-meet-theme/templates/front-page.html');
$functions = $read('wp-content/themes/jat-meet-theme/functions.php');
$seo = $read('wp-content/themes/jat-meet-theme/inc/seo.php');
$mailer = $read('wp-content/plugins/jat-reservation/includes/class-jat-reservation-mailer.php');
$privacy = $read('wp-content/plugins/jat-reservation/includes/class-jat-reservation-privacy.php');
$plugin = $read('wp-content/plugins/jat-reservation/jat-reservation.php');

$assert(str_contains($header, 'assets/images/brand/meet-and-link-light.webp'), '页头未使用浅色背景版 Logo。');
$assert(str_contains($footer, 'assets/images/brand/meet-and-link-dark.webp'), '页脚未使用深色背景版 Logo。');
$assert(str_contains(html_entity_decode($header, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $brandName . '（' . $brandReading . '）'), '页头 Logo 缺少完整无障碍品牌名称。');
$assert(str_contains($footer, $description), '页脚缺少指定日文说明语。');
$decodedFrontPage = html_entity_decode($frontPage, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$assert(str_contains($decodedFrontPage, $brandName), '首页缺少品牌名称。');
$assert(str_contains($decodedFrontPage, $subtitle), '首页缺少英文副标题。');
$assert(str_contains($frontPage, $description), '首页缺少指定日文说明语。');
$assert((bool) preg_match("/'name'\\s*=>\\s*'Meet & Link'/", $seo), '结构化数据未使用 Meet & Link 品牌名称。');
$assert(str_contains($seo, 'meet-and-link-icon-512.png'), '结构化数据未引用新品牌站点图标。');
$assert(str_contains($mailer, '【Meet & Link】'), '预约邮件主题未使用新品牌名称。');
$assert(str_contains($mailer, 'Meet & Link（ミート＆リンク）をご利用いただき'), '预约邮件正文未使用完整品牌名称。');
$assert(str_contains($privacy, 'Meet & Link お申し込みデータ'), '隐私工具未使用新品牌名称。');
$assert(str_contains($plugin, 'Author: Meet & Link'), '预约插件作者品牌未更新。');

foreach (array(32, 180, 192, 512) as $size) {
    $relative = sprintf('wp-content/themes/jat-meet-theme/assets/images/brand/meet-and-link-icon-%d.png', $size);
    $assert(is_file($root . '/' . $relative), sprintf('缺少 %d×%d 站点图标。', $size, $size));
    $assert(str_contains($functions, basename($relative)), sprintf('主题未注册 %d×%d 站点图标。', $size, $size));
}

$sourceFiles = array(
    'bin/seed-site.php',
    'wp-content/plugins/jat-reservation/jat-reservation.php',
    'wp-content/plugins/jat-reservation/includes/class-jat-reservation-mailer.php',
    'wp-content/plugins/jat-reservation/includes/class-jat-reservation-privacy.php',
    'wp-content/themes/jat-meet-theme/functions.php',
    'wp-content/themes/jat-meet-theme/inc/seo.php',
    'wp-content/themes/jat-meet-theme/parts/header.html',
    'wp-content/themes/jat-meet-theme/parts/footer.html',
    'wp-content/themes/jat-meet-theme/templates/front-page.html',
);
$oldBrandPatterns = array(
    'Japan Airport Transfer',
    'ミート＆センディング',
    '空港・駅 ミート＆センディングサービス',
);
foreach ($sourceFiles as $relative) {
    $content = $read($relative);
    foreach ($oldBrandPatterns as $oldBrand) {
        $assert(! str_contains($content, $oldBrand), sprintf('生产源码仍含旧品牌「%s」: %s', $oldBrand, $relative));
    }
}

$assert(! is_file($root . '/wp-content/themes/jat-meet-theme/assets/images/favicon.svg'), '旧品牌 favicon.svg 不应继续存在。');

if ($failures !== array()) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    fwrite(STDERR, sprintf("MEET_AND_LINK_BRAND=FAIL (%d/%d failed)\n", count($failures), $checks));
    exit(1);
}

echo sprintf("MEET_AND_LINK_BRAND=PASS (%d checks)\n", $checks);
