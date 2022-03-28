<?php

namespace Heave\WpMediaCloudflare;

class Cloudflare
{
    protected $settings;

    public function __construct()
    {
        //
    }

    public function verifyToken()
    {
        $api_token = wmcf_get_setting('api_token');

        $response = wp_remote_get('https://api.cloudflare.com/client/v4/user/tokens/verify', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type' => 'application/json',
            ],
        ]);

        $body = json_decode(wp_remote_retrieve_body($response));

        if (isset($body->success) && $body->success) {
            return true;
        }

        return false;
    }

    public function getRequiredParams()
    {
        $api_token = wmcf_get_setting('api_token');
        $account_id = wmcf_get_setting('account_id');

        if (!$api_token || !$account_id) {
            return false;
        }

        return compact('api_token', 'account_id');
    }

    public function upload($attachment)
    {
        $cloudflareId = get_post_meta($attachment->ID, 'cloudflare_id', true);

        if ($cloudflareId) {
            throw new \Exception('Already uploaded to Cloudflare');
        }

        $attachedFile = get_attached_file($attachment->ID);

        $params = $this->getRequiredParams();
        $api_token = $params['api_token'];
        $account_id = $params['account_id'];
        $boundary = wp_generate_uuid4();

        $url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/images/v1";

        $payload = '';

        $payload .= '--' . $boundary;
        $payload .= "\r\n";
        $payload .= 'Content-Disposition: form-data; name="' . 'file' .
            '"; filename="' . basename( $attachedFile ) . '"' . "\r\n";
        $payload .= "\r\n";
        $payload .= file_get_contents( $attachedFile );
        $payload .= "\r\n";
        
        $payload .= '--' . $boundary . '--';

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $payload,
        ]);

        $data = wp_remote_retrieve_body($response);
        $data = json_decode($data, true);
        
        if (isset($data['success']) && $data['success']) {
            $id = $data['result']['id'];
            $variants = $data['result']['variants'];

            // Replace data in wp_posts table
            update_post_meta($attachment->ID, 'cloudflare_url', $this->getVariant($variants));
            update_post_meta($attachment->ID, 'cloudflare_image_id', $id);
            update_post_meta($attachment->ID, 'cloudflare_variants', $variants);
            update_post_meta($attachment->ID, 'wcf_local_url', $attachment->guid);
            update_post_meta($attachment->ID, '_wp_attached_file', $this->getVariant($variants));

            $attachment->guid = $this->getVariant($variants);
            $attachmentDetails = wp_get_attachment_metadata($attachment->ID);

            // backup the original metadata
            update_post_meta($attachment->ID, '_wcf_old_attachment_metadata', $attachmentDetails);
            
            foreach ($attachmentDetails['sizes'] as $size => $details) {
                $attachmentDetails['sizes'][$size]['file'] = $size;
            }

            wp_update_attachment_metadata($attachment->ID, $attachmentDetails);
            wp_update_post($attachment);

            return [
                'id'        => $id,
                'variants'  => $variants,
                'url'       => $attachment->guid,
            ];
        }

        return false;
    }

    public function delete($image_id)
    {
        $params         = $this->getRequiredParams();
        $api_token      = $params['api_token'];
        $account_id     = $params['account_id'];

        $url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/images/v1/{$image_id}";

        return wp_remote_request($url, [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function getVariant($variants, $size = null)
    {
        if (!$size) {
            $size = wmcf_get_setting('default_variant');
        }

        foreach ($variants as $variant) {
            if (str_contains($variant, $size)) {
                return $variant;
            }
        }

        return null;
    }

    public function deleteVariant($variant_name)
    {
        $params = $this->getRequiredParams();
        $api_token = $params['api_token'];
        $account_id = $params['account_id'];

        $url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/images/v1/variants/{$variant_name}";

        return wp_remote_request($url, [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type' => 'application/json',
            ],
        ]);
    }
}