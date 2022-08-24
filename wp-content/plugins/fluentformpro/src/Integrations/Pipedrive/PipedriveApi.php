<?php

namespace FluentFormPro\Integrations\Pipedrive;

class PipedriveApi
{
    protected $apiToken = null;
    protected $apiUrl = 'https://api.pipedrive.com/v1/';

    public function __construct($api_Token = null) {
        $this->apiToken = $api_Token;
    }

    private function getApiUrl($resource)
    {
        $parameters = [];

        $parameters['api_token']   = $this->apiToken;

        $paramString = http_build_query($parameters);

        return $this->apiUrl . $resource . '?' . $paramString;
    }

    public function auth_test()
    {
        return  $this->make_request('leads', [], 'GET');
    }

    public function make_request($resource, $data, $method = 'GET')
    {
        $requestApi = $this->getApiUrl($resource);

        $args =  array(
            'headers' => array(
                'Content-Type'  => 'application/json'
            )
        );

        if ($method == 'GET') {
            $response = wp_remote_get($requestApi, $args);
        } else if ($method == 'POST') {
            $args['body'] = json_encode($data);
            $response = wp_remote_post($requestApi, $args);
        } else {
            return (new \WP_Error(423, 'Request method could not be found'));
        }

        /* If WP_Error, die. Otherwise, return decoded JSON. */
        if (is_wp_error($response)) {
            return (new \WP_Error(423, $response->get_error_message()));
        }

        return json_decode($response['body'], true);
    }

    public function getFields($serviceId){
        return $this->make_request($serviceId, [], 'GET');
    }

    public function getUsers(){
        return $this->make_request('users', [], 'GET');
    }
    public function getPerson() {
        return $this->make_request('persons', [], 'GET');
    }
    public function getOrganizations(){
        return $this->make_request('organizations', [], 'GET');
    }
    public function getCurrencies(){
        return $this->make_request('currencies', [], 'GET');
    }

    public function insertServiceData($service_name, $data)
    {
        $response = $this->make_request($service_name, $data, 'POST');

        if ($response['success']) {
            return $response;
        }

        if (is_wp_error($response)) {
            $err_msg = $response->get_error_message();
        }
        if (!$response['success']) {
            $err_msg = $response['error'];
        }
        return new \WP_Error('error', $err_msg);
    }

}