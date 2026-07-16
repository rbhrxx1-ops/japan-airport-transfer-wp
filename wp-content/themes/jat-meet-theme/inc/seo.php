<?php
/**
 * Lightweight SEO, index control, and structured data.
 *
 * @package JAT_Meet_Theme
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Detect a dedicated SEO plugin so the theme does not emit duplicate tags.
 */
function jat_meet_theme_has_seo_plugin(): bool
{
    return defined('WPSEO_VERSION')
        || defined('RANK_MATH_VERSION')
        || defined('AIOSEO_VERSION')
        || defined('SEOPRESS_VERSION');
}

/**
 * Read the saved AIOSEO value for WordPress's static posts page.
 *
 * AIOSEO treats the page assigned to page_for_posts as an archive on the
 * public request, so its regular per-page title and description are skipped.
 * This compatibility layer keeps AIOSEO as the sole tag owner while restoring
 * the values that were saved for that page in AIOSEO's own Post model.
 */
function jat_meet_theme_aioseo_posts_page_value(string $field, string $current): string
{
    if (
        ! defined('AIOSEO_VERSION') ||
        (! is_home() && ! is_post_type_archive('jat_notice'))
    ) {
        return $current;
    }

    $postsPageId = (int) get_option('page_for_posts');
    if ($postsPageId <= 0) {
        return $current;
    }

    if (! class_exists('AIOSEO\\Plugin\\Common\\Models\\Post')) {
        return $current;
    }

    $aioseoPost = AIOSEO\Plugin\Common\Models\Post::getPost($postsPageId);
    if (! $aioseoPost) {
        return $current;
    }

    $value = 'title' === $field
        ? (string) $aioseoPost->title
        : (string) $aioseoPost->description;

    return '' !== trim($value) ? $value : $current;
}

/**
 * Restore the saved AIOSEO title on the static posts page.
 */
function jat_meet_theme_aioseo_posts_page_title(string $title): string
{
    return jat_meet_theme_aioseo_posts_page_value('title', $title);
}
add_filter('aioseo_title', 'jat_meet_theme_aioseo_posts_page_title');

/**
 * Restore the saved AIOSEO description on the static posts page.
 */
function jat_meet_theme_aioseo_posts_page_description(string $description): string
{
    return jat_meet_theme_aioseo_posts_page_value('description', $description);
}
add_filter('aioseo_description', 'jat_meet_theme_aioseo_posts_page_description');

/**
 * Override WordPress's document title for the static posts page.
 */
function jat_meet_theme_aioseo_posts_page_document_title(string $title): string
{
    return jat_meet_theme_aioseo_posts_page_value('title', $title);
}
add_filter('pre_get_document_title', 'jat_meet_theme_aioseo_posts_page_document_title', 99);

/**
 * Emit the saved posts-page description only when AIOSEO emits no description.
 */
function jat_meet_theme_aioseo_posts_page_meta_description(): void
{
    if (
        ! defined('AIOSEO_VERSION') ||
        (! is_home() && ! is_post_type_archive('jat_notice')) ||
        ! function_exists('aioseo')
    ) {
        return;
    }

    $nativeDescription = (string) aioseo()->meta->description->getDescription();
    if ('' !== trim($nativeDescription)) {
        return;
    }

    $description = jat_meet_theme_aioseo_posts_page_value('description', '');
    if ('' !== trim($description)) {
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }
}
add_action('wp_head', 'jat_meet_theme_aioseo_posts_page_meta_description', 2);

/**
 * Replace Open Graph values through AIOSEO's supported tag-array filter.
 *
 * @param array<string, mixed> $tags Existing Open Graph tags.
 * @return array<string, mixed>
 */
function jat_meet_theme_aioseo_posts_page_facebook_tags(array $tags): array
{
    $tags['og:title'] = jat_meet_theme_aioseo_posts_page_value(
        'title',
        isset($tags['og:title']) ? (string) $tags['og:title'] : ''
    );
    $tags['og:description'] = jat_meet_theme_aioseo_posts_page_value(
        'description',
        isset($tags['og:description']) ? (string) $tags['og:description'] : ''
    );

    return $tags;
}
add_filter('aioseo_facebook_tags', 'jat_meet_theme_aioseo_posts_page_facebook_tags');

/**
 * Replace Twitter values through AIOSEO's supported tag-array filter.
 *
 * @param array<string, mixed> $tags Existing Twitter tags.
 * @return array<string, mixed>
 */
function jat_meet_theme_aioseo_posts_page_twitter_tags(array $tags): array
{
    $tags['twitter:title'] = jat_meet_theme_aioseo_posts_page_value(
        'title',
        isset($tags['twitter:title']) ? (string) $tags['twitter:title'] : ''
    );
    $tags['twitter:description'] = jat_meet_theme_aioseo_posts_page_value(
        'description',
        isset($tags['twitter:description']) ? (string) $tags['twitter:description'] : ''
    );

    return $tags;
}
add_filter('aioseo_twitter_tags', 'jat_meet_theme_aioseo_posts_page_twitter_tags');

/**
 * Build a concise plain-text description for the current request.
 */
function jat_meet_theme_meta_description(): string
{
    if (is_front_page()) {
        return 'Meet & Link（ミート＆リンク）は、会議・来賓の送迎と出迎え、サインボード対応、空港・駅からの移動をご案内します。';
    }

    if (is_singular()) {
        $post = get_queried_object();
        if (! $post instanceof WP_Post) {
            return '';
        }

        $source = has_excerpt($post) ? get_the_excerpt($post) : $post->post_content;
        $source = strip_shortcodes((string) $source);
        $source = wp_strip_all_tags($source, true);
        $source = preg_replace('/\s+/u', ' ', $source) ?? '';
        $source = trim($source);

        if ('' !== $source) {
            return wp_html_excerpt($source, 150, '…');
        }

        return get_the_title($post) . 'について、サービス内容とご利用方法をご案内します。';
    }

    if (is_post_type_archive()) {
        return wp_strip_all_tags((string) get_the_archive_description(), true);
    }

    return '';
}

/**
 * Resolve one canonical URL for public, indexable views.
 */
function jat_meet_theme_canonical_url(): string
{
    if (is_front_page()) {
        return home_url('/');
    }

    if (is_singular()) {
        $canonical = wp_get_canonical_url(get_queried_object_id());
        return is_string($canonical) ? $canonical : '';
    }

    if (is_post_type_archive()) {
        $postType = get_query_var('post_type');
        $postType = is_array($postType) ? reset($postType) : $postType;
        $url = $postType ? get_post_type_archive_link((string) $postType) : false;
        return is_string($url) ? $url : '';
    }

    if (is_category() || is_tag() || is_tax()) {
        $url = get_term_link(get_queried_object());
        return is_wp_error($url) ? '' : (string) $url;
    }

    return '';
}

/**
 * Output description, canonical, and JSON-LD only when no SEO plugin owns them.
 */
function jat_meet_theme_render_seo_head(): void
{
    if (jat_meet_theme_has_seo_plugin()) {
        return;
    }

    $description = jat_meet_theme_meta_description();
    if ('' !== $description) {
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }

    $canonical = jat_meet_theme_canonical_url();
    if ('' !== $canonical) {
        echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
    }

    $graph = jat_meet_theme_schema_graph();
    if (array() !== $graph) {
        echo '<script type="application/ld+json">' . wp_json_encode(
            array(
                '@context' => 'https://schema.org',
                '@graph'   => $graph,
            ),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . '</script>' . "\n";
    }
}
add_action('wp_head', 'jat_meet_theme_render_seo_head', 2);

/**
 * Remove WordPress core's singular canonical because the module emits one tag.
 */
function jat_meet_theme_manage_core_canonical(): void
{
    if (! jat_meet_theme_has_seo_plugin()) {
        remove_action('wp_head', 'rel_canonical');
    }
}
add_action('wp', 'jat_meet_theme_manage_core_canonical');

/**
 * Keep utility and unverified views out of search indexes.
 *
 * @param array<string, bool> $robots Existing directives.
 * @return array<string, bool>
 */
function jat_meet_theme_robots(array $robots): array
{
    $mustNoindex = is_search() || is_404() || is_attachment() || is_author();

    if (is_singular()) {
        $postId = get_queried_object_id();
        $role   = (string) get_post_meta($postId, 'jat_content_role', true);
        if (in_array($role, array('location', 'company', 'legal', 'price'), true)
            && ! (bool) get_post_meta($postId, 'jat_publish_ready', true)) {
            $mustNoindex = true;
        }
    }

    if ($mustNoindex) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
        unset($robots['index']);
    }

    return $robots;
}
add_filter('wp_robots', 'jat_meet_theme_robots');

/**
 * Build schema from content that is rendered on the current page.
 *
 * @return array<int, array<string, mixed>>
 */
function jat_meet_theme_schema_graph(): array
{
    if (is_search() || is_404() || is_attachment()) {
        return array();
    }

    $canonical = jat_meet_theme_canonical_url();
    if ('' === $canonical) {
        return array();
    }

    $graph = array(
        array(
            '@type'         => 'Organization',
            '@id'           => home_url('/#organization'),
            'name'          => 'Meet & Link',
            'alternateName' => 'ミート＆リンク',
            'url'           => home_url('/'),
            'description'   => '会議・来賓の送迎と出迎え',
            'logo'          => array(
                '@type'  => 'ImageObject',
                'url'    => get_template_directory_uri() . '/assets/images/brand/meet-and-link-icon-512.png',
                'width'  => 512,
                'height' => 512,
            ),
        ),
        array(
            '@type'       => 'WebSite',
            '@id'         => home_url('/#website'),
            'url'         => home_url('/'),
            'name'        => get_bloginfo('name'),
            'publisher'   => array('@id' => home_url('/#organization')),
            'inLanguage'  => 'ja-JP',
        ),
        array(
            '@type'      => 'WebPage',
            '@id'        => $canonical . '#webpage',
            'url'        => $canonical,
            'name'       => html_entity_decode(wp_get_document_title(), ENT_QUOTES | ENT_HTML5, get_bloginfo('charset')),
            'description'=> jat_meet_theme_meta_description(),
            'isPartOf'   => array('@id' => home_url('/#website')),
            'about'      => array('@id' => home_url('/#organization')),
            'inLanguage' => 'ja-JP',
        ),
    );

    if (is_page() && ! is_front_page()) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            $items = array(
                array(
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => 'ホーム',
                    'item'     => home_url('/'),
                ),
            );
            $position = 2;
            foreach (array_reverse(get_post_ancestors($post)) as $ancestorId) {
                $items[] = array(
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'name'     => get_the_title($ancestorId),
                    'item'     => get_permalink($ancestorId),
                );
            }
            $items[] = array(
                '@type'    => 'ListItem',
                'position' => $position,
                'name'     => get_the_title($post),
                'item'     => get_permalink($post),
            );
            $graph[] = array(
                '@type'           => 'BreadcrumbList',
                '@id'             => $canonical . '#breadcrumb',
                'itemListElement' => $items,
            );
        }
    }

    if (is_page('faq') && function_exists('jat_meet_theme_get_visible_faq_posts')) {
        $entities = array();
        foreach (jat_meet_theme_get_visible_faq_posts() as $faqPost) {
            $answer = trim(wp_strip_all_tags(apply_filters('the_content', $faqPost->post_content), true));
            if ('' === $answer) {
                continue;
            }
            $entities[] = array(
                '@type'          => 'Question',
                'name'           => get_the_title($faqPost),
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $answer,
                ),
            );
        }
        if (array() !== $entities) {
            $graph[] = array(
                '@type'      => 'FAQPage',
                '@id'        => $canonical . '#faq',
                'mainEntity' => $entities,
            );
        }
    }

    return $graph;
}
