<?php
/*
Plugin Name: WP Cloudflare Image Integration
Plugin URI: https://heave.app/plugins/wp-cloudflare-image-integration
Description: Use cloudflare to serve images from your WordPress site.
Version: 0.0.1
Author: Heave.app
Author URI: https://heave.app/
License: GPLv2 or later
Text Domain: wmcf
*/

require_once __DIR__ . '/vendor/autoload.php';

define('WPCFI_PLUGIN_DIR', __DIR__);
define('WPCFI_PLUGIN_URL', plugins_url('', __FILE__));

new \Heave\WpMediaCloudflare\Settings;
new \Heave\WpMediaCloudflare\Main;
