<?php
/**
 * Plugin Name: TradeHub Headless CMS
 * Description: Provides TradeHub marketplace administration and headless storefront content.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: tradehub-headless
 */

if (!defined('ABSPATH')) {
    exit;
}

const TRADEHUB_HEADLESS_SETTINGS = [
    'site_name' => 'TradeHub',
    'tagline' => '',
    'hero_title' => '',
    'hero_text' => '',
    'about_text' => '',
    'contact_email' => '',
    'contact_phone' => '',
];

function tradehub_headless_register_announcement_type(): void
{
    register_post_type('th_announcement', [
        'labels' => [
            'name' => 'Announcements',
            'singular_name' => 'Announcement',
            'add_new_item' => 'Add announcement',
            'edit_item' => 'Edit announcement',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_rest' => true,
        'supports' => ['title', 'editor'],
        'menu_icon' => 'dashicons-megaphone',
    ]);
}
add_action('init', 'tradehub_headless_register_announcement_type');

function tradehub_headless_register_settings(): void
{
    foreach (array_keys(TRADEHUB_HEADLESS_SETTINGS) as $key) {
        register_setting('tradehub_headless', 'tradehub_' . $key, [
            'type' => 'string',
            'sanitize_callback' => $key === 'contact_email' ? 'sanitize_email' : 'sanitize_textarea_field',
            'default' => TRADEHUB_HEADLESS_SETTINGS[$key],
        ]);
    }
}
add_action('admin_init', 'tradehub_headless_register_settings');

function tradehub_headless_get_content(): WP_REST_Response
{
    $settings = [];
    foreach (TRADEHUB_HEADLESS_SETTINGS as $key => $default) {
        $settings[$key] = (string) get_option('tradehub_' . $key, $default);
    }

    $posts = get_posts([
        'post_type' => 'th_announcement',
        'post_status' => 'publish',
        'numberposts' => 10,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    $announcements = array_map(static function (WP_Post $post): array {
        return [
            'title' => get_the_title($post),
            'body' => wp_strip_all_tags($post->post_content),
            'created_at' => get_post_time('Y-m-d H:i:s', false, $post),
        ];
    }, $posts);

    return new WP_REST_Response([
        'settings' => $settings,
        'announcements' => $announcements,
    ]);
}

add_action('rest_api_init', static function (): void {
    register_rest_route('tradehub/v1', '/content', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'tradehub_headless_get_content',
        'permission_callback' => '__return_true',
    ]);
});

require_once __DIR__ . '/includes/admin.php';
