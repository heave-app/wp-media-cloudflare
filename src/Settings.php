<?php

namespace Heave\WpMediaCloudflare;

class Settings
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function admin_menu()
    {
        add_options_page(
            'WP Cloudflare Image',
            'WP Cloudflare Image',
            'manage_options',
            'wp-media-cloudflare',
            [$this, 'admin_page']
        );
    }

    public function admin_page()
    {
        return require_once(__DIR__ . '/views/settings.php');
    }

    public function assets()
    {
        wp_enqueue_script('wp-media-cloudflare-admin', WPCFI_PLUGIN_URL . '/assets/wp-media-cloudflare.js', ['jquery', 'media-upload', 'media'], '0.0.1', true);
        wp_enqueue_style('wp-media-cloudflare-admin', WPCFI_PLUGIN_URL . '/assets/wp-media-cloudflare.css', [], '0.0.1');
    }

    public function admin_init()
    {
        if (!isset($_POST['wmcf'])) {
            return;
        }

        if (!check_admin_referer('update-wmcf-settings') || !current_user_can('manage_options')) {
            die(__('You are not allowed to perform this action.', 'wmcf'));
        }

        $settings = $_POST['wmcf'];

        update_option('wmcf_settings', $settings);
        wp_safe_redirect(admin_url('options-general.php?page=wp-media-cloudflare&status=success'));
        exit;
    }
}
