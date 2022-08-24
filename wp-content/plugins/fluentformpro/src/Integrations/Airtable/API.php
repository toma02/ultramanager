<?php

namespace FluentFormPro\Integrations\Airtable;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class API
{
    public $apiKey = null;
    public $baseId = null;
    public $tableId = null;

    public function __construct($settings)
    {
        $this->apiKey = $settings['api_key'];
        $this->baseId = $settings['base_id'];
        $this->tableId = $settings['table_id'];
    }

    public function checkAuth()
    {
        return $this->makeRequest('https://api.airtable.com/v0/'. $this->baseId .'/' . $this->tableId);
    }

    public function getApiKey()
    {
        return [
            'api_key' => 'Bearer ' . $this->apiKey,
        ];
    }

    public function makeRequest($url, $bodyArgs = [], $type = 'GET')
    {
        $apiKey = $this->getApiKey();

        $request = [];
        if ($type == 'GET') {
            $request = wp_remote_get($url, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => $apiKey['api_key'],
                ]
            ]);
        }

        if ($type == 'POST') {
            $request = wp_remote_post($url, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => $apiKey['api_key'],
                ],
                'body' => $bodyArgs
            ]);
        }

        if (is_wp_error($request)) {
            $code = $request->get_error_code();
            $message = $request->get_error_message();
            return new \WP_Error($code, $message);
        }

        $body = wp_remote_retrieve_body($request);
        $body = \json_decode($body, true);
        $code = wp_remote_retrieve_response_code($request);

        if ($code == 200 || $code == 201) {
            return $body;
        }
        else {
            if (is_string($body['error'])) {
                return new \WP_Error($code, __('Error: Please provide valid Airtable Base ID.', 'fluentformpro'));
            }

            return new \WP_Error($code, $body['error']['type'] .': '. $body['error']['message']);
        }
    }

    public function subscribe($subscriber)
    {
        $url = 'https://api.airtable.com/v0/'. $this->baseId .'/' . $this->tableId;
        $post = \json_encode($subscriber, JSON_NUMERIC_CHECK);
        return $this->makeRequest($url, $post, 'POST');
    }
}
