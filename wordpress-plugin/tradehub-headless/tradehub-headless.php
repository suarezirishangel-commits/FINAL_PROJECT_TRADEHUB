<?php
/**
 * Plugin Name: TradeHub Headless CMS
 * Description: Manages TradeHub storefront copy and announcements for the TradeHub REST API.
 * Version: 1.0.0
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
        'show_in_menu' => true,
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
add_action('admin_menu', static function (): void {
    add_options_page(
        'TradeHub Content',
        'TradeHub Content',
        'manage_options',
        'tradehub-content',
        'tradehub_headless_render_settings'
    );
});

function tradehub_headless_render_settings(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
      <h1>TradeHub Content</h1>
      <p>Edit the public storefront content here.</p>
      <p><a class="button button-secondary" href="<?= esc_url(admin_url('edit.php?post_type=th_announcement')) ?>">Manage Announcements</a></p>
      <form method="post" action="options.php">
        <?php settings_fields('tradehub_headless'); ?>
        <table class="form-table" role="presentation">
          <?php foreach (TRADEHUB_HEADLESS_SETTINGS as $key => $default): ?>
            <tr>
              <th scope="row"><label for="tradehub_<?= esc_attr($key) ?>"><?= esc_html(ucwords(str_replace('_', ' ', $key))) ?></label></th>
              <td>
                <?php if (in_array($key, ['hero_text', 'about_text'], true)): ?>
                  <textarea class="large-text" rows="4" id="tradehub_<?= esc_attr($key) ?>" name="tradehub_<?= esc_attr($key) ?>"><?= esc_textarea(get_option('tradehub_' . $key, $default)) ?></textarea>
                <?php else: ?>
                  <input class="regular-text" type="<?= $key === 'contact_email' ? 'email' : 'text' ?>" id="tradehub_<?= esc_attr($key) ?>" name="tradehub_<?= esc_attr($key) ?>" value="<?= esc_attr(get_option('tradehub_' . $key, $default)) ?>">
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
        <?php submit_button('Save TradeHub content'); ?>
      </form>
    </div>
    <?php
}

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
