<?php

namespace FluentFormPro\Integrations\Discord;

if (!defined('ABSPATH')) {
    exit;
}

class Discord
{
    public static function sendMessage($webhook, $message)
    {
        $data = [
            'payload_json' => json_encode($message)
        ];
        
        $res = wp_remote_post($webhook, [
            'body' => $data,
            'header' => [
                'content-type' => 'multipart/form-data',
            ]
        ]);
        
        if(!is_wp_error($res) && ($res['response']['code'] == 200 || $res['response']['code'] == 201 || $res['response']['code'] == 204)) {
            return true;
        }
        return new \WP_Error($res['response']['code'] ,wp_remote_retrieve_response_message($res));
    
    }
    
}
