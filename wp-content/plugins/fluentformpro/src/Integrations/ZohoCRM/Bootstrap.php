<?php

namespace FluentFormPro\Integrations\ZohoCRM;

use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper;

class Bootstrap extends IntegrationManager
{

    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            'Zoho CRM',
            'zohocrm',
            '_fluentform_zohocrm_settings',
            'zohocrm_feed',
            16
        );
        $this->logo = $this->app->url('public/img/integrations/zohocrm.png');
        $this->description = 'Zoho CRM is an online Sales CRM software that manages your sales, marketing and support in one CRM platform.';
        $this->registerAdminHooks();
        add_filter('fluentform_save_integration_value_' . $this->integrationKey, [$this, 'validate'], 10, 3);
        add_action('admin_init', function () {
            if (isset($_REQUEST['ff_zohocrm_auth'])) {
                $client = $this->getRemoteClient();
                if (isset($_REQUEST['code'])) {
                    // Get the access token now
                    $code = sanitize_text_field($_REQUEST['code']);
                    $settings = $this->getGlobalSettings([]);
                    $settings = $client->generateAccessToken($code, $settings);

                    if (!is_wp_error($settings)) {
                        $settings['status'] = true;
                        update_option($this->optionKey, $settings, 'no');
                    }

                    wp_redirect(admin_url('admin.php?page=fluent_forms_settings#general-zohocrm-settings'));
                    exit();
                } else {
                    $client->redirectToAuthServer();
                }
                die();
            }

        });
//        add_filter('fluentform_notifying_async_zohocrm', '__return_false');

        add_filter(
            'fluentform_get_integration_values_zohocrm', 
            [$this, 'resolveIntegrationSettings'],
            100, 
            3
        );
    }

    public function getGlobalFields($fields)
    {
        return [
            'logo' => $this->logo,
            'menu_title' => __('Zoho CRM Settings', 'fluentformpro'),
            'menu_description' => $this->description,
            'valid_message' => __('Your Zoho CRM API Key is valid', 'fluentformpro'),
            'invalid_message' => __('Your Zoho CRM API Key is not valid', 'fluentformpro'),
            'save_button_text' => __('Save Settings', 'fluentformpro'),
            'config_instruction' => $this->getConfigInstructions(),
            'fields' => [
                'accountUrl' => [
                    'type' => 'select',
                    'placeholder' => 'Your Zoho CRM Account URL',
                    'label_tips' => __("Please Choose your Zoho CRM Account URL", 'fluentformpro'),
                    'label' => __('Account URL', 'fluentformpro'),
                    'options' => [
                        'https://accounts.zoho.com' => 'US',
                        'https://accounts.zoho.com.au' => 'AU',
                        'https://accounts.zoho.eu' => 'EU',
                        'https://accounts.zoho.in' => 'IN',
                        'https://accounts.zoho.com.cn' => 'CN',
                    ]
                ],
                'client_id' => [
                    'type' => 'text',
                    'placeholder' => 'Zoho CRM Client ID',
                    'label_tips' => __("Enter your Zoho CRM Client ID, if you do not have <br>Please login to your Zoho CRM account and go to <a href='https://api-console.zoho.com/'>Zoho Developer Console</a><br>", 'fluentformpro'),
                    'label' => __('Zoho CRM Client ID', 'fluentformpro'),
                ],
                'client_secret' => [
                    'type' => 'password',
                    'placeholder' => 'Zoho CRM Client Secret',
                    'label_tips' => __("Enter your Zoho CRM  Key, if you do not have <br>Please login to your Zoho CRM account and go to <a href='https://api-console.zoho.com/'>Zoho Developer Console</a>", 'fluentformpro'),
                    'label' => __('Zoho CRM Client Secret', 'fluentformpro'),
                ],
            ],
            'hide_on_valid' => true,
            'discard_settings' => [
                'section_description' => 'Your Zoho CRM integration is up and running',
                'button_text' => 'Disconnect Zoho CRM',
                'data' => [
                    'accountUrl' => '',
                    'client_id' => '',
                    'client_secret' => ''
                ],
                'show_verify' => true
            ]
        ];
    }

    public function getGlobalSettings($settings)
    {
        $globalSettings = get_option($this->optionKey);
        if (!$globalSettings) {
            $globalSettings = [];
        }
        $defaults = [
            'accountUrl' => '',
            'client_id' => '',
            'client_secret' => '',
            'status' => '',
            'access_token' => '',
            'refresh_token' => '',
            'expire_at' => false
        ];

        return wp_parse_args($globalSettings, $defaults);
    }

    public function saveGlobalSettings($settings)
    {
        $integrationSettings = array();
        $err_msg = 'Error: Authorization info missing.';
        $err = false;
        if (empty($settings['client_secret'])) {
            $integrationSettings['client_secret'] = '';
            $err_msg = 'Client Secret is required';
            $err = true;
        }
        if (empty($settings['client_id'])) {
            $integrationSettings['client_id'] = '';
            $err_msg = 'Client Id is required';
            $err = true;
        }
        if (empty($settings['accountUrl'])) {
            $integrationSettings['accountUrl'] = '';
            $err_msg = 'Choose an account Url.';
            $err = true;
        }
        if ($err) {
            $integrationSettings['status'] = false;
            update_option($this->optionKey, $integrationSettings, 'no');
            wp_send_json_error([
                'message' => __($err_msg, 'fluentformpro'),
                'status' => false
            ], 400);
        }


        // Verify API key now
        try {
            $oldSettings = $this->getGlobalSettings([]);
            $oldSettings['accountUrl'] = esc_url_raw($settings['accountUrl']);
            $oldSettings['client_id'] = sanitize_text_field($settings['client_id']);
            $oldSettings['client_secret'] = sanitize_text_field($settings['client_secret']);
            $oldSettings['status'] = false;

            update_option($this->optionKey, $oldSettings, 'no');
            wp_send_json_success([
                'message' => 'You are being redirected to authenticate',
                'redirect_url' => admin_url('?ff_zohocrm_auth=1')
            ], 200);
        } catch (\Exception $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage()
            ], 400);
        }
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        $name = $this->app->request->get('serviceName', '');
        $listId = $this->app->request->get('serviceId', '');

        return [
            'name' => $name,
            'list_id' => $listId,
            'other_fields' => [
                [
                    'item_value' => '',
                    'label' => ''
                ]
            ],
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'enabled' => true
        ];
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title' => $this->title . ' Integration',
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => 'Configuration required!',
            'global_configure_url' => admin_url('admin.php?page=fluent_forms_settings#general-zohocrm-settings'),
            'configure_message' => 'Zoho CRM is not configured yet! Please configure your Zoho CRM api first',
            'configure_button_text' => 'Set Zoho CRM API'
        ];
        return $integrations;
    }

    public function getSettingsFields($settings, $formId)
    {
        $fieldSettings = [
            'fields' => [
                [
                    'key' => 'name',
                    'label' => 'Name',
                    'required' => true,
                    'placeholder' => 'Your Feed Name',
                    'component' => 'text'
                ],

                [
                    'key' => 'list_id',
                    'label' => 'Services',
                    'placeholder' => 'Choose Service',
                    'required' => true,
                    'component' => 'refresh',
                    'options' => $this->getServices()
                ],
            ],
            'button_require_list' => false,
            'integration_title' => $this->title
        ];

        $listId = $this->app->request->get(
            'serviceId',
            ArrayHelper::get($settings, 'list_id')
        );
        
        if ($listId) {
            $fields = $this->getFields($listId);

            $fields = array_merge($fieldSettings['fields'], $fields);

            $fieldSettings['fields'] = $fields;
        }

        $fieldSettings['fields'] = array_merge($fieldSettings['fields'], [
            [
                'require_list' => false,
                'key' => 'conditionals',
                'label' => 'Conditional Logics',
                'tips' => 'Allow this integration conditionally based on your submission values',
                'component' => 'conditional_block'
            ],
            [
                'require_list' => false,
                'key' => 'enabled',
                'label' => 'Status',
                'component' => 'checkbox-single',
                'checkbox_label' => 'Enable This feed'
            ]
        ]);

        return $fieldSettings;
    }

    public function resolveIntegrationSettings($settings, $feed, $formId)
    {
        $serviceName = $this->app->request->get('serviceName', '');
        $serviceId = $this->app->request->get('serviceId', '');

        if ($serviceName) {
            $settings['name'] = $serviceName;
        }

        if ($serviceId) {
            $settings['list_id'] = $serviceId;
        }

        return $settings;
    }

    public function getMergeFields($list, $listId, $formId)
    {
        return false;
    }

    public function validate($settings, $integrationId, $formId)
    {
        $error = false;
        $errors = array();

        foreach ($this->getFields($settings['list_id']) as $field){
            if ($field['required'] && empty($settings[$field['key']])) {
                $error = true;

                $errors[$field['key']] = [__($field['label'].' is required', 'fluentformpro')];
            }
        }

        if ($error){
            wp_send_json_error([
                'message' => __('Validation Failed', 'fluentformpro'),
                'errors'  => $errors
            ], 423);
        }

        return $settings;
    }

    public function notify($feed, $formData, $entry, $form)
    {
        $feedData = $feed['processedValues'];
        $list_id = $feedData['list_id'];
        if (!$list_id) {
            return false;
        }
        $keys = $this->getAllKeys($list_id);
        $postData = array();

        foreach ($keys as $key){
            if($key['required'] && empty($feedData[$key['key']])){
                return false;
            }
            if(!empty($feedData[$key['key']])){
                switch ($key['data_type']){
                    case 'email' :
                         $value = sanitize_email($feedData[$key['key']]);
                        break;
                    case 'date' :
                        $date_str = $feedData[$key['key']];
                        if(strstr($date_str, '/')){
                            $date_str = str_replace('/', '.', $date_str);
                        }
                        $date = new \Datetime($date_str);
                        $value = $date->format('Y-m-d');
                        break;
                    case 'datetime':
                        $datetime_str = $feedData[$key['key']];
                        if(strstr($datetime_str, '/')){
                            $datetime_str = str_replace('/', '.', $datetime_str);
                        }
                        $datetime = new \Datetime($datetime_str);
                        $value = $datetime->format("c");
                        break;
                    default:
                        $value = sanitize_text_field($feedData[$key['key']]);
                        break;
                }
                $postData[$key['key']] = $value;
            }

        }

        if(!empty($feedData['other_fields'])){
            foreach ($feedData['other_fields'] as $other_field){
                if(!empty($other_field['item_value']) && !empty($other_field['label'])){
                    $postData[$other_field['label']] = $other_field['item_value'];
                }
            }
        }

        $client = $this->getRemoteClient();
        $response = $client->insertModuleData($list_id, $postData);

        if (is_wp_error($response)) {
            // it's failed
            do_action('ff_integration_action_result', $feed, 'failed',  'Failed to insert Zoho CRM feed. Details : ' . $response->get_error_message());
        } else {
            // It's success
            do_action('ff_integration_action_result', $feed, 'success', 'Zoho CRM feed has been successfully inserted '. $list_id .' data.');
        }

    }

    protected function getAllKeys($list_id)
    {
        $client = $this->getRemoteClient();
        $response = $client->getAllFields($list_id);
        if (is_wp_error($response)) {
            return false;
        }
        $keys = array();
        if ($response['fields']) {
            $keys_data = [];
            foreach ($response['fields'] as $field) {
                if ($field['system_mandatory'] ||
                    $field['data_type'] == 'picklist' ||
                    $field['data_type'] == 'email'
                ) {
                    $keys_data['key'] = $field['api_name'];
                    $keys_data['required'] = $field['system_mandatory'];
                    $keys_data['data_type'] = $field['data_type'];
                    array_push($keys, $keys_data);
                }
            }
        }
        return $keys;
    }

    protected function getServices()
    {
        $client = $this->getRemoteClient();
        $response = $client->getAllModules();
        $services_options = array();
        if (is_wp_error($response)) {
            return $services_options;
        }
        if ($response['modules']) {
            $services = $response['modules'];

            $availableServices = [
                'Leads', 'Contacts', 'Accounts', 'Deals', 'Tasks', 'Cases', 'Vendors', 'Solutions', 'Campaigns'
            ];

            foreach ($services as $service) {
                $validService = $service['creatable'] &&
                                $service['global_search_supported'] &&
                                in_array($service['api_name'], $availableServices);

                if ($validService) {
                    $services_options[$service['api_name']] = $service['singular_label'];
                }
            }
        }
        return $services_options;
    }

    protected function getFields($module_key)
    {
        $client = $this->getRemoteClient();
        $response = $client->getAllFields($module_key);
        if (is_wp_error($response)) {
            return false;
        }
        $fields = array();
        if ($response['fields']) {

            $others_fields = array();
            foreach ($response['fields'] as $field) {

                if ($field['system_mandatory'] || $field['data_type'] == 'picklist' || $field['data_type'] == 'email') {

                    $data = array(
                        'key' => $field['api_name'],
                        'placeholder' => $field['display_label'],
                        'label' => $field['field_label'],
                        'required' => false,
                        'tips' => 'Enter ' . $field['display_label'] . ' value or choose form input provided by shortcode.',
                        'component' => 'value_text'
                    );

                    if ($field['system_mandatory']) {
                        $data['required'] = true;
                        $data['tips'] = $field['display_label'] . ' is a required field. Enter value or choose form input provided by shortcode.';
                    }
                    if($field['data_type'] == 'datetime'){
                        $data['tips'] = $field['display_label'] . ' is a required field. Enter value or choose form input shortcode. <br> Make sure format is (01/01/2022 00:00 +0:00)';
                    }
                    if ($field['data_type'] == 'picklist' && $field['pick_list_values']) {
                        $data['component'] = 'select';
                        $data['tips'] = "Choose " . $field['display_label'] . " type in select list.";
                        $data_options= array();
                        foreach ($field['pick_list_values'] as $option) {
                            $data_options[$option['actual_value']] = $option['display_value'];
                        }
                        $data['options'] = $data_options;
                    }
                    if ($field['data_type'] == 'textarea') {
                        $data['component'] = 'value_textarea';
                    }

                    array_push($fields, $data);
                } else {
                    $other_supported_fields = ['text', 'textarea', 'integer', 'website', 'phone', 'double'];
                    if (in_array($field['data_type'], $other_supported_fields)) {
                        $others_fields[$field['api_name']] = $field['field_label'];
                    }
                }

            }
            if (!empty($others_fields)) {
                array_push($fields, [
                    'key' => 'other_fields',
                    'require_list' => false,
                    'required' => false,
                    'label' => 'Other Fields',
                    'tips' => 'Select which Fluent Forms fields pair with their respective Zoho crm modules fields. <br /> Field value must be string type.',
                    'component' => 'dropdown_many_fields',
                    'field_label_remote' => 'Others Field',
                    'field_label_local' => 'Others Field',
                    'options' => $others_fields
                ]);
            }
        }

        return $fields;
    }

    protected function getConfigInstructions()
    {
        ob_start();
        ?>
        <div><h4>To Authenticate Zoho CRM First you need to register your application with Zoho CRM.</h4>
            <ol>
                <li> To register,
                    Go to <a href="https://api-console.zoho.com/" target="_blank">Zoho Developer Console</a>.
                </li>
                <li>
                    Choose a client type: <br>
                    <strong>Web Based:</strong> Applications that are running on a dedicated HTTP server. <br>
                    <strong>Note:</strong> No other client type allowed.
                </li>
                <li>
                    Enter the following details: <br>
                    <strong>Client Name:</strong> The name of your application you want to register with Zoho. <br>
                    <strong>Homepage URL:</strong> The URL of your web page. Your site url
                    <b><u><?php echo site_url(); ?></u></b><br>
                    <strong>Authorized Redirect URIs:</strong> Your app redirect url must be
                    <b><u><?php echo admin_url('?ff_zohocrm_auth=1'); ?></u></b>
                </li>

                <li>
                    Click CREATE. You will receive the Client ID and Client Secret. Copy the Client Id and Client
                    Secret.
                </li>
                <li>Then go back to your Zoho CRM setting page and choose your account URL, also paste the Client Id and
                    Secret Id in the input bellow.
                </li>
                <li>
                    Then save settings. You will be redirect to the Zoho CRM authorized page. Click Allow button for
                    authorized.
                </li>
                <strong>Note:</strong> If authorized successful you wil be redirected to the Zoho CRM settings page. If not
                you will see the error message on that page.
            </ol>
        </div>
        <?php
        return ob_get_clean();
    }

    public function getRemoteClient()
    {
        $settings = $this->getGlobalSettings([]);
        return new ZohoCRM(
            $settings['accountUrl'],
            $settings
        );
    }
}