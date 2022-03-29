<?php

function wmcf_get_setting($key = null, $default = null)
{
    static $settings;

    $defaults = [
        'default_variant'   => 'public',
        'account_id'        => '',
        'api_token'         => '',
        'auto_upload'       => true
    ];

    $settings = get_option('wmcf_settings', $defaults);

    if (!$key) {
        return $settings;
    }

    if (isset($settings[$key])) {
        return $settings[$key];
    }

    return $default;
}