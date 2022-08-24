<?php

namespace FluentFormPro\Integrations\Salesforce;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class API
{
    protected $clientId = null;

    protected $clientSecret = null;

    protected $instance_url = null;

    protected $accessToken = null;

    protected $callBackUrl = null;

    protected $settings = [];

    public function __construct($settings)
    {
        $this->clientId = $settings['client_id'];
        $this->clientSecret = $settings['client_secret'];
        $this->instance_url = rtrim($settings['instance_url'], '/');
        $this->accessToken = $settings['access_token'];
        $this->callBackUrl = admin_url('?ff_salesforce_auth=true');
        $this->settings = $settings;
    }

    public function getRredirectServerURL()
    {
        return $this->instance_url . 
            '/services/oauth2/authorize?response_type=code&client_id=' . 
            $this->clientId . 
            '&redirect_uri=' . 
            $this->callBackUrl;
    }

    public function generateAccessToken($code, $settings)
    {
        $response = wp_remote_post('https://login.salesforce.com/services/oauth2/token', [
            'body' => [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $this->callBackUrl,
                'code'          => $code
            ]
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $body = \json_decode($body, true);

        if (isset($body['error_description'])) {
            return new \WP_Error('invalid_client', $body['error_description']);
        }

        $settings['access_token'] = $body['access_token'];
        $settings['refresh_token'] = $body['refresh_token'];

        return $settings;
    }

    protected function getApiSettings()
    {
        $this->maybeRefreshToken();

        if(!$this->settings['status']) {
            return new \WP_Error('invalid', 'API key is invalid');
        }

        return [
            'client_id'         => $this->clientId,
            'client_secret'     => $this->clientSecret,
            'callback'          => $this->callBackUrl,
            'access_token'      => $this->settings['access_token'],
            'refresh_token'     => $this->settings['refresh_token']
        ];
    }

    protected function maybeRefreshToken()
    {
        $response = wp_remote_post('https://login.salesforce.com/services/oauth2/token', [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->settings['refresh_token']
            ]
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('error', $response[0]['errorCode'] . ': ' .$response[0]['message']);
        }

        $body = wp_remote_retrieve_body($response);
        $body = \json_decode($body, true);

        if (isset($body['error_description'])) {
            return new \WP_Error('invalid_client', $body['error_description']);
        }

        $this->settings['access_token'] = $body['access_token'];
    }

    public function makeRequest($url, $bodyArgs, $type = 'GET')
    {
        $apiSettings = $this->getApiSettings();
        $this->accessToken = $apiSettings['access_token'];

        $request = [];
        if ($type == 'GET') {
            $request = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => " Bearer ". $this->accessToken,
                ]
            ]);
        }

        if ($type == 'POST') {
            $request = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => " Bearer ". $this->accessToken,
                    'Content-Type' => 'application/json'
                ],
                'body' => $bodyArgs
            ]);
        }

        if (is_wp_error($request)) {
            $message = $request->get_error_message();
            return new \WP_Error($request->get_error_code(), $message);
        } elseif ($request['response']['code'] >= 200 && $request['response']['code'] <= 299) {
            return json_decode($request['body'], true);
        }

        $body = wp_remote_retrieve_body($request);
        $body = \json_decode($body, true)[0];

        $error = 'Unknown Error';

        if (!empty($body['errorCode'])) {
            if (isset($body['message'])) {
                $error = $body['message'];
            }
        }

        return new \WP_Error($request['response']['code'], $error);
    }

    public function subscribe($subscriber)
    {
        $url = $this->instance_url . '/services/data/v53.0/sobjects/' . $subscriber['list_id'];
        $post = \json_encode($subscriber['attributes'], JSON_NUMERIC_CHECK);

        $response = $this->makeRequest($url, $post, 'POST');

        return $response;
    }
}
