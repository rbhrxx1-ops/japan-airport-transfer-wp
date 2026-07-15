<?php
/**
 * Structured content models and controlled editorial fields.
 *
 * @package JAT_Meet_Theme
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Register editorial post types and topic taxonomy.
 */
function jat_meet_theme_register_content_models(): void
{
    register_post_type(
        'jat_faq',
        array(
            'labels' => array(
                'name'          => __('よくあるご質問', 'jat-meet-theme'),
                'singular_name' => __('質問', 'jat-meet-theme'),
                'add_new_item'  => __('質問を追加', 'jat-meet-theme'),
                'edit_item'     => __('質問を編集', 'jat-meet-theme'),
            ),
            'public'             => false,
            'show_ui'            => true,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-editor-help',
            'supports'           => array('title', 'editor', 'page-attributes'),
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        )
    );

    register_post_type(
        'jat_case',
        array(
            'labels' => array(
                'name'          => __('事例', 'jat-meet-theme'),
                'singular_name' => __('事例', 'jat-meet-theme'),
                'add_new_item'  => __('事例を追加', 'jat-meet-theme'),
                'edit_item'     => __('事例を編集', 'jat-meet-theme'),
            ),
            'public'         => true,
            'show_in_rest'   => true,
            'menu_icon'      => 'dashicons-portfolio',
            'has_archive'    => 'case',
            'rewrite'        => array('slug' => 'case', 'with_front' => false),
            'supports'       => array('title', 'editor', 'excerpt', 'thumbnail'),
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        )
    );

    register_post_type(
        'jat_notice',
        array(
            'labels' => array(
                'name'          => __('お知らせ', 'jat-meet-theme'),
                'singular_name' => __('お知らせ', 'jat-meet-theme'),
                'add_new_item'  => __('お知らせを追加', 'jat-meet-theme'),
                'edit_item'     => __('お知らせを編集', 'jat-meet-theme'),
            ),
            'public'         => true,
            'show_in_rest'   => true,
            'menu_icon'      => 'dashicons-megaphone',
            'has_archive'    => 'news',
            'rewrite'        => array('slug' => 'news', 'with_front' => false),
            'supports'       => array('title', 'editor', 'excerpt'),
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        )
    );

    register_taxonomy(
        'jat_topic',
        array('page', 'jat_faq', 'jat_case', 'jat_notice'),
        array(
            'labels' => array(
                'name'          => __('JAT分類', 'jat-meet-theme'),
                'singular_name' => __('JAT分類', 'jat-meet-theme'),
            ),
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
        )
    );

    $sharedMeta = array(
        'jat_last_verified_on' => array(
            'type'              => 'string',
            'sanitize_callback' => 'jat_meet_theme_sanitize_date',
        ),
        'jat_verifier_name' => array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ),
        'jat_internal_note' => array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
        ),
        'jat_publish_ready' => array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
        ),
        'jat_sort_order' => array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ),
    );

    foreach (array('page', 'jat_faq', 'jat_case', 'jat_notice') as $postType) {
        foreach ($sharedMeta as $metaKey => $args) {
            register_post_meta(
                $postType,
                $metaKey,
                array_merge(
                    $args,
                    array(
                        'single'        => true,
                        'show_in_rest'  => true,
                        'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
                    )
                )
            );
        }
    }

    register_post_meta(
        'page',
        'jat_content_role',
        array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'jat_meet_theme_sanitize_content_role',
            'auth_callback'     => static fn (): bool => current_user_can('edit_pages'),
        )
    );

    register_post_meta(
        'jat_case',
        'jat_consent_reference',
        array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => static fn (): bool => current_user_can('edit_posts'),
        )
    );

    register_post_meta(
        'jat_case',
        'jat_consent_confirmed',
        array(
            'type'              => 'boolean',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
            'auth_callback'     => static fn (): bool => current_user_can('edit_posts'),
        )
    );

    register_post_meta(
        'jat_notice',
        'jat_notice_priority',
        array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'jat_meet_theme_sanitize_notice_priority',
            'auth_callback'     => static fn (): bool => current_user_can('edit_posts'),
        )
    );
}
add_action('init', 'jat_meet_theme_register_content_models');

/**
 * Return FAQ entries in the exact order used by both HTML and JSON-LD.
 *
 * @return WP_Post[]
 */
function jat_meet_theme_get_visible_faq_posts(): array
{
    return get_posts(
        array(
            'post_type'      => 'jat_faq',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'meta_key'       => 'jat_sort_order',
            'orderby'        => array(
                'meta_value_num' => 'ASC',
                'menu_order'    => 'ASC',
                'title'         => 'ASC',
            ),
            'order'          => 'ASC',
        )
    );
}

/**
 * Render structured FAQ entries grouped by their editorial topic.
 */
function jat_meet_theme_render_faq_list(): string
{
    $faqPosts = jat_meet_theme_get_visible_faq_posts();

    if (array() === $faqPosts) {
        return '<p>' . esc_html__('現在、よくあるご質問を準備しています。', 'jat-meet-theme') . '</p>';
    }

    $grouped = array();
    foreach ($faqPosts as $faqPost) {
        $terms = wp_get_post_terms($faqPost->ID, 'jat_topic');
        $topic = ! is_wp_error($terms) && isset($terms[0]) ? $terms[0]->name : __('その他', 'jat-meet-theme');
        $grouped[$topic][] = $faqPost;
    }

    ob_start();
    foreach ($grouped as $topic => $posts) {
        echo '<h2 class="wp-block-heading">' . esc_html($topic) . '</h2>';
        echo '<div class="jat-faq">';
        foreach ($posts as $faqPost) {
            echo '<details><summary>' . esc_html(get_the_title($faqPost)) . '</summary><div>';
            echo wp_kses_post(apply_filters('the_content', $faqPost->post_content));
            echo '</div></details>';
        }
        echo '</div>';
    }

    return (string) ob_get_clean();
}
add_shortcode('jat_faq_list', 'jat_meet_theme_render_faq_list');

/**
 * Sanitize an ISO date used for operational verification.
 */
function jat_meet_theme_sanitize_date(mixed $value): string
{
    $value = sanitize_text_field((string) $value);
    if ('' === $value) {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
}

/**
 * Restrict page roles to the approved editorial vocabulary.
 */
function jat_meet_theme_sanitize_content_role(mixed $value): string
{
    $value   = sanitize_key((string) $value);
    $allowed = array('service', 'location', 'flow', 'price', 'company', 'corporate', 'contact', 'legal', 'reservation', 'recruit', 'general');

    return in_array($value, $allowed, true) ? $value : 'general';
}

/**
 * Restrict notice priority to deterministic values.
 */
function jat_meet_theme_sanitize_notice_priority(mixed $value): string
{
    $value = sanitize_key((string) $value);
    return in_array($value, array('info', 'important', 'maintenance'), true) ? $value : 'info';
}

/**
 * Add one controlled editorial panel instead of exposing arbitrary custom fields.
 */
function jat_meet_theme_register_editorial_meta_box(): void
{
    foreach (array('page', 'jat_faq', 'jat_case', 'jat_notice') as $postType) {
        add_meta_box(
            'jat-editorial-verification',
            __('JAT 公開確認', 'jat-meet-theme'),
            'jat_meet_theme_render_editorial_meta_box',
            $postType,
            'side',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'jat_meet_theme_register_editorial_meta_box');

/**
 * Render controlled editorial fields.
 */
function jat_meet_theme_render_editorial_meta_box(WP_Post $post): void
{
    wp_nonce_field('jat_editorial_meta_' . $post->ID, 'jat_editorial_meta_nonce');

    $verifiedOn = (string) get_post_meta($post->ID, 'jat_last_verified_on', true);
    $verifier   = (string) get_post_meta($post->ID, 'jat_verifier_name', true);
    $note       = (string) get_post_meta($post->ID, 'jat_internal_note', true);
    $isReady    = (bool) get_post_meta($post->ID, 'jat_publish_ready', true);
    $sortOrder  = (int) get_post_meta($post->ID, 'jat_sort_order', true);
    ?>
    <?php if ('page' === $post->post_type) :
        $contentRole = (string) get_post_meta($post->ID, 'jat_content_role', true);
        $roles = array(
            'general' => __('一般', 'jat-meet-theme'),
            'service' => __('サービス', 'jat-meet-theme'),
            'location' => __('対応エリア', 'jat-meet-theme'),
            'flow' => __('ご利用の流れ', 'jat-meet-theme'),
            'price' => __('料金', 'jat-meet-theme'),
            'company' => __('会社案内', 'jat-meet-theme'),
            'corporate' => __('法人', 'jat-meet-theme'),
            'contact' => __('お問い合わせ', 'jat-meet-theme'),
            'legal' => __('法務', 'jat-meet-theme'),
            'reservation' => __('予約', 'jat-meet-theme'),
            'recruit' => __('採用情報', 'jat-meet-theme'),
        );
        ?>
        <p>
            <label for="jat-content-role"><strong><?php esc_html_e('ページ区分', 'jat-meet-theme'); ?></strong></label><br>
            <select class="widefat" id="jat-content-role" name="jat_content_role">
                <?php foreach ($roles as $roleValue => $roleLabel) : ?>
                    <option value="<?php echo esc_attr($roleValue); ?>" <?php selected($contentRole ?: 'general', $roleValue); ?>><?php echo esc_html($roleLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
    <?php endif; ?>
    <p>
        <label for="jat-sort-order"><strong><?php esc_html_e('表示順', 'jat-meet-theme'); ?></strong></label><br>
        <input class="small-text" id="jat-sort-order" name="jat_sort_order" type="number" min="0" max="9999" value="<?php echo esc_attr((string) $sortOrder); ?>">
    </p>
    <p>
        <label for="jat-last-verified-on"><strong><?php esc_html_e('最終確認日', 'jat-meet-theme'); ?></strong></label><br>
        <input class="widefat" id="jat-last-verified-on" name="jat_last_verified_on" type="date" value="<?php echo esc_attr($verifiedOn); ?>">
    </p>
    <p>
        <label for="jat-verifier-name"><strong><?php esc_html_e('確認担当者', 'jat-meet-theme'); ?></strong></label><br>
        <input class="widefat" id="jat-verifier-name" name="jat_verifier_name" type="text" value="<?php echo esc_attr($verifier); ?>" maxlength="120">
    </p>
    <p>
        <label for="jat-internal-note"><strong><?php esc_html_e('内部メモ（公開されません）', 'jat-meet-theme'); ?></strong></label><br>
        <textarea class="widefat" id="jat-internal-note" name="jat_internal_note" rows="4" maxlength="2000"><?php echo esc_textarea($note); ?></textarea>
    </p>
    <p>
        <label><input name="jat_publish_ready" type="checkbox" value="1" <?php checked($isReady); ?>> <?php esc_html_e('事実・権利・法務の確認が完了', 'jat-meet-theme'); ?></label>
    </p>
    <?php if ('jat_case' === $post->post_type) :
        $consentReference = (string) get_post_meta($post->ID, 'jat_consent_reference', true);
        $consentConfirmed = (bool) get_post_meta($post->ID, 'jat_consent_confirmed', true);
        ?>
        <hr>
        <p>
            <label for="jat-consent-reference"><strong><?php esc_html_e('掲載同意の管理番号', 'jat-meet-theme'); ?></strong></label><br>
            <input class="widefat" id="jat-consent-reference" name="jat_consent_reference" type="text" value="<?php echo esc_attr($consentReference); ?>" maxlength="120">
        </p>
        <p><label><input name="jat_consent_confirmed" type="checkbox" value="1" <?php checked($consentConfirmed); ?>> <?php esc_html_e('掲載同意を確認済み', 'jat-meet-theme'); ?></label></p>
    <?php endif; ?>
    <?php if ('jat_notice' === $post->post_type) :
        $priority = (string) get_post_meta($post->ID, 'jat_notice_priority', true);
        ?>
        <hr>
        <p>
            <label for="jat-notice-priority"><strong><?php esc_html_e('表示区分', 'jat-meet-theme'); ?></strong></label><br>
            <select class="widefat" id="jat-notice-priority" name="jat_notice_priority">
                <option value="info" <?php selected($priority, 'info'); ?>><?php esc_html_e('通常', 'jat-meet-theme'); ?></option>
                <option value="important" <?php selected($priority, 'important'); ?>><?php esc_html_e('重要', 'jat-meet-theme'); ?></option>
                <option value="maintenance" <?php selected($priority, 'maintenance'); ?>><?php esc_html_e('メンテナンス', 'jat-meet-theme'); ?></option>
            </select>
        </p>
    <?php endif;
}

/**
 * Persist editorial fields with capability and nonce checks.
 */
function jat_meet_theme_save_editorial_meta(int $postId): void
{
    if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
        return;
    }

    if (! current_user_can('edit_post', $postId)) {
        return;
    }

    $nonce = isset($_POST['jat_editorial_meta_nonce']) ? sanitize_text_field(wp_unslash($_POST['jat_editorial_meta_nonce'])) : '';
    if (! wp_verify_nonce($nonce, 'jat_editorial_meta_' . $postId)) {
        return;
    }

    $textFields = array(
        'jat_last_verified_on' => 'jat_meet_theme_sanitize_date',
        'jat_verifier_name' => 'sanitize_text_field',
        'jat_internal_note' => 'sanitize_textarea_field',
        'jat_consent_reference' => 'sanitize_text_field',
        'jat_notice_priority' => 'jat_meet_theme_sanitize_notice_priority',
        'jat_content_role' => 'jat_meet_theme_sanitize_content_role',
    );

    foreach ($textFields as $metaKey => $sanitizer) {
        if (isset($_POST[$metaKey])) {
            update_post_meta($postId, $metaKey, call_user_func($sanitizer, wp_unslash($_POST[$metaKey])));
        }
    }

    $sortOrder = isset($_POST['jat_sort_order']) ? absint(wp_unslash($_POST['jat_sort_order'])) : 0;
    update_post_meta($postId, 'jat_sort_order', min($sortOrder, 9999));
    update_post_meta($postId, 'jat_publish_ready', isset($_POST['jat_publish_ready']));
    update_post_meta($postId, 'jat_consent_confirmed', isset($_POST['jat_consent_confirmed']));
}
add_action('save_post', 'jat_meet_theme_save_editorial_meta');
