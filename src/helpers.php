<?php

function wmcf_get_setting($key = null)
{
    $defaults = [
        'default_variant' => 'public',
        'account_id' => '',
        'api_token' => '',
    ];

    $settings = get_option('wmcf_settings', $defaults);

    if (!$key) {
        return $settings;
    }

    if (isset($settings[$key])) {
        return $settings[$key];
    }

    return null;
}