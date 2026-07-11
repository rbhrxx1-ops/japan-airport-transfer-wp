<?php
/**
 * JAT Meet Theme functions.
 *
 * @package JAT_Meet_Theme
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/inc/content-models.php';

/**
 * Theme setup.
 */
function jat_meet_theme_setup(): void
{
    load_theme_textdomain('jat-meet-theme', get_template_directory() . '/languages');

    add_theme_support('automatic-feed-links');
    add_theme_support('block-styles');
    add_theme_support('editor-styles');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('title-tag');
    add_theme_support('wp-block-styles');
    add_theme_support(
        'custom-logo',
        array(
            'height'      => 96,
            'width'       => 360,
            'flex-height' => true,
            'flex-width'  => true,
        )
    );

    add_editor_style('style.css');
}
add_action('after_setup_theme', 'jat_meet_theme_setup');

/**
 * Load the public theme assets.
 */
function jat_meet_theme_enqueue_assets(): void
{
    $theme = wp_get_theme();

    wp_enqueue_style(
        'jat-meet-theme',
        get_stylesheet_uri(),
        array(),
        (string) $theme->get('Version')
    );

    wp_enqueue_script(
        'jat-meet-theme',
        get_template_directory_uri() . '/assets/js/theme.js',
        array(),
        (string) $theme->get('Version'),
        array('in_footer' => true)
    );
}
add_action('wp_enqueue_scripts', 'jat_meet_theme_enqueue_assets');

/**
 * Register controlled pattern categories for editors.
 */
function jat_meet_theme_register_pattern_categories(): void
{
    register_block_pattern_category(
        'jat-sections',
        array('label' => __('JAT：ページセクション', 'jat-meet-theme'))
    );

    register_block_pattern_category(
        'jat-calls-to-action',
        array('label' => __('JAT：お申し込み導線', 'jat-meet-theme'))
    );
}
add_action('init', 'jat_meet_theme_register_pattern_categories');

/**
 * Add a stable class for the public site language audit.
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function jat_meet_theme_body_classes(array $classes): array
{
    $classes[] = 'jat-public-site';
    return $classes;
}
add_filter('body_class', 'jat_meet_theme_body_classes');

/**
 * Render an accessible breadcrumb for hierarchical pages.
 */
function jat_meet_theme_breadcrumb(): string
{
    if (! is_page()) {
        return '';
    }

    $post = get_post();
    if (! $post instanceof WP_Post) {
        return '';
    }

    $items = array(
        sprintf(
            '<a href="%s">%s</a>',
            esc_url(home_url('/')),
            esc_html__('ホーム', 'jat-meet-theme')
        ),
    );

    $ancestorIds = array_reverse(get_post_ancestors($post));
    foreach ($ancestorIds as $ancestorId) {
        $items[] = sprintf(
            '<a href="%s">%s</a>',
            esc_url(get_permalink($ancestorId)),
            esc_html(get_the_title($ancestorId))
        );
    }

    $items[] = '<span aria-current="page">' . esc_html(get_the_title($post)) . '</span>';

    return '<nav class="jat-breadcrumb" aria-label="' . esc_attr__('パンくずリスト', 'jat-meet-theme') . '">' . implode('<span aria-hidden="true"> ／ </span>', $items) . '</nav>';
}
add_shortcode('jat_breadcrumb', 'jat_meet_theme_breadcrumb');

/**
 * Prefer Japanese archive title labels on public templates.
 *
 * @param string $title Archive title.
 */
function jat_meet_theme_archive_title(string $title): string
{
    if (is_category()) {
        return single_cat_title('', false);
    }

    if (is_tag()) {
        return single_tag_title('', false);
    }

    if (is_author()) {
        return sprintf(
            /* translators: %s: author display name. */
            __('%s の記事', 'jat-meet-theme'),
            get_the_author()
        );
    }

    return $title;
}
add_filter('get_the_archive_title', 'jat_meet_theme_archive_title');

/**
 * Hide emoji assets to reduce unnecessary requests on the public site.
 */
function jat_meet_theme_disable_emoji_assets(): void
{
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
}
add_action('init', 'jat_meet_theme_disable_emoji_assets');

/**
 * Add an accessible skip link before the site header.
 */
function jat_meet_theme_skip_link(): void
{
    echo '<a class="jat-skip-link" href="#main-content">' . esc_html__('本文へ移動', 'jat-meet-theme') . '</a>';
}
add_action('wp_body_open', 'jat_meet_theme_skip_link');
