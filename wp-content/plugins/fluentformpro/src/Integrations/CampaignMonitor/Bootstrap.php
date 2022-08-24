<?php

namespace FluentFormPro\Integrations\CampaignMonitor;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper;

class Bootstrap extends IntegrationManager
{
    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            'Campaign Monitor',
            'campaign_monitor',
            '_fluentform_campaignmonitor_settings',
            'campaign_monitor_feeds',
            15
        );
        $this->logo = $this->app->url('public/img/integrations/campaignmonitor.png');

        $this->description = 'Create Campaign Monitor newsletter signup forms in WordPress, and streamline your email marketing.';

        $this->registerAdminHooks();

//        add_filter('fluentform_notifying_async_campaign_monitor', '__return_false');

    }

    public function getGlobalFields($fields)
    {
        $clients = $this->getClients();
        return [
            'logo' => $this->logo,
            'menu_title' => __('Campaign Monitor API Settings', 'fluentformpro'),
            'menu_description' => __('Campaign Monitor drives results with email marketing. It shares how to gain loyal customers with personalized email campaigns and automated customer journeys. Use Fluent Forms to collect customer information and automatically add it to your Campaign Monitor list. If you don\'t have an Campaign Monitor account, you can <a href="https://www.campaignmonitor.com/" target="_blank">sign up for one here.</a>', 'fluentformpro'),
            'valid_message' => __('Your Campaign Monitor configuration is valid', 'fluentformpro'),
            'invalid_message' => __('Your Campaign Monitor configuration is invalid', 'fluentformpro'),
            'save_button_text' => __('Save Settings', 'fluentformpro'),
            'fields' => [
                'apiKey' => [
                    'type' => 'password',
                    'placeholder' => 'API Key',
                    'label_tips' => __("Enter your Campaign Monitor API Key, if you do not have <br>Please login to your Campaign Monitor account and find the api key", 'fluentformpro'),
                    'label' => __('Campaign Monitor API Key', 'fluentformpro'),
                ],
                'clientId' => [
                    'type' => 'select',
                    'hide_on_empty' => true,
                    'placeholder' => 'Select Client',
                    'label_tips' => __("Select the Campaign Monitor client that you want to connect this site", 'fluentformpro'),
                    'label' => __('Campaign Monitor Client', 'fluentformpro'),
                    'options' => $clients
                ]
            ],
            'hide_on_valid' => true,
            'discard_settings' => [
                'section_description' => 'Your Campaign Monitor API integration is up and running',
                'button_text' => 'Disconnect Campaign Monitor',
                'data' => [
                    'apiKey' => ''
                ],
                'show_verify' => true
            ],
            'reload_on_save' => true
        ];
    }


    public function getGlobalSettings($settings)
    {
        $globalSettings = get_option($this->optionKey);
        if (!$globalSettings) {
            $globalSettings = [];
        }
        $defaults = [
            'apiKey' => '',
            'status' => '',
            'clientId' => ''
        ];


        return wp_parse_args($globalSettings, $defaults);
    }

    public function saveGlobalSettings($settings)
    {
        if (!$settings['apiKey']) {
            $integrationSettings = [
                'apiKey' => '',
                'clientId' => '',
                'status' => false
            ];
            // Update the reCaptcha details with siteKey & secretKey.
            update_option($this->optionKey, $integrationSettings, 'no');
            wp_send_json_success([
                'message' => __('Your settings has been updated and discarded', 'fluentformpro'),
                'status' => false
            ], 200);
        }

        try {
            $settings['status'] = false;
            $settings['apiKey'] = sanitize_text_field($settings['apiKey']);
            update_option($this->optionKey, $settings, 'no');
            (new CampaignMonitorApi($settings['apiKey']))->auth_test();
            $settings['status'] = true;
            $message = __('Your settings has been updated', 'fluentformpro');
            if (!$settings['clientId']) {
                $settings['status'] = false;
                $message = __('Please Select Client Now', 'fluentformpro');
            }
            update_option($this->optionKey, $settings, 'no');
            wp_send_json_success([
                'message' => $message,
                'status' => $settings['status']
            ], 200);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'status' => false
            ], 400);
        }
    }


    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title' => $this->title . ' Integration',
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => 'Configuration required!',
            'global_configure_url' => admin_url('admin.php?page=fluent_forms_settings#general-campaign_monitor-settings'),
            'configure_message' => 'Campaign Monitor is not configured yet! Please configure your Campaign Monitor api first',
            'configure_button_text' => 'Set Campaign Monitor API'
        ];
        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name' => '',
            'list_id' => '',
            'fieldEmailAddress' => '',
            'fullName' => '',
            'custom_fields' => (object)[],
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'resubscribe' => false,
            'enabled' => true
        ];
    }

    public function getSettingsFields($settings, $formId)
    {
        return [
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
                    'label' => 'Campaign Monitor List',
                    'placeholder' => 'Select Campaign Monitor Mailing List',
                    'tips' => 'Select the Campaign Monitor Mailing List you would like to add your contacts to.',
                    'component' => 'list_ajax_options',
                    'options' => $this->getLists(),
                ],
                [
                    'key' => 'custom_fields',
                    'require_list' => true,
                    'label' => 'Map Fields',
                    'tips' => 'Select which Fluent Forms fields pair with their<br /> respective Campaign Monitor fields.',
                    'component' => 'map_fields',
                    'field_label_remote' => 'Campaign Monitor Field',
                    'field_label_local' => 'Form Field',
                    'primary_fileds' => [
                        [
                            'key' => 'fieldEmailAddress',
                            'label' => 'Email Address',
                            'required' => true,
                            'input_options' => 'emails'
                        ],
                        [
                            'key' => 'fullName',
                            'label' => 'Full Name'
                        ]
                    ]
                ],
                [
                    'key' => 'resubscribe',
                    'require_list' => true,
                    'label' => 'ReSubscribe',
                    'tips' => 'When this option is enabled, if the subscriber is in an inactive state or<br />has previously been unsubscribed, they will be re-added to the active list.<br />Therefore, this option should be used with caution and only when appropriate.',
                    'component' => 'checkbox-single',
                    'checkbox_label' => 'Enable ReSubscription'
                ],
                [
                    'require_list' => true,
                    'key' => 'conditionals',
                    'label' => 'Conditional Logics',
                    'tips' => 'Allow Campaign Monitor integration conditionally based on your submission values',
                    'component' => 'conditional_block'
                ],
                [
                    'require_list' => true,
                    'key' => 'enabled',
                    'label' => 'Status',
                    'component' => 'checkbox-single',
                    'checkbox_label' => 'Enable This feed'
                ]
            ],
            'button_require_list' => true,
            'integration_title' => $this->title
        ];
    }

    public function getMergeFields($list, $listId, $formId)
    {
        $api = $this->getApiClient();
        if (!$api) {
            return [];
        }
        $fields = $api->get_custom_fields($listId);


        $formattedFields = [];

        foreach ($fields as $field) {
            $field_key = str_replace(array('[', ']'), '', $field['Key']);
            $formattedFields[$field_key] = $field['FieldName'];
        }


        return $formattedFields;
    }

    protected function getLists()
    {
        $api = $this->getApiClient();
        if (!$api) {
            return [];
        }
        $lists = $api->get_lists();
        $formattedLists = [];
        foreach ($lists as $list) {
            $formattedLists[$list['ListID']] = $list['Name'];
        }
        return $formattedLists;
    }

    protected function getClients()
    {
        $api = $this->getApiClient();
        if (!$api) {
            return [];
        }
        try {
            $clients = $api->get_clients();
            $formattedClints = [];
            foreach ($clients as $client) {
                $formattedClints[$client['ClientID']] = $client['Name'];
            }
            return $formattedClints;
        } catch (\Exception $e) {
            return [];
        }
    }

    /*
     * Form Submission Hooks Here
     */
    public function notify($feed, $formData, $entry, $form)
    {
        $feedData = $feed['processedValues'];

        if (!is_email($feedData['fieldEmailAddress'])) {
            $feedData['fieldEmailAddress'] = ArrayHelper::get($formData, $feedData['fieldEmailAddress']);
        }

        if (!is_email($feedData['fieldEmailAddress'])) {
            do_action('ff_integration_action_result', $feed, 'failed', 'Campaign Monitor API call has been skipped because no valid email available');
            return;
        }

        $subscriber = [
            'EmailAddress' => $feedData['fieldEmailAddress'],
            'Name' => ArrayHelper::get($feedData, 'fullName'),
            'Resubscribe' => ArrayHelper::isTrue($feedData, 'resubscribe'),
        ];


        $customFiels = [];

        foreach (ArrayHelper::get($feedData, 'custom_fields', []) as $key => $value) {
            if (!$value) {
                continue;
            }
            $customFiels[] = [
                'Key' => $key,
                'Value' => $value
            ];
        }

        if ($customFiels) {
            $subscriber['CustomFields'] = $customFiels;
        }

        $subscriber = array_filter($subscriber);

        $subscriber = apply_filters('fluentform_integration_data_'.$this->integrationKey, $subscriber, $feed, $entry);

        try {
            // Now let's prepare the data and push to hubspot
            $api = $this->getApiClient();
            if (!$api) {
                return;
            }
            $api->add_subscriber($subscriber, $feedData['list_id']);
            do_action('ff_integration_action_result', $feed, 'success', 'Campaign Monitor feed has been successfully initialed and pushed data');
        } catch (\Exception $exception) {
            do_action('ff_integration_action_result', $feed, 'failed', $exception->getMessage());
        }
    }


    protected function getApiClient()
    {
        try {
            $settings = $this->getGlobalSettings([]);
            if ($settings['clientId']) {
                return new CampaignMonitorApi($settings['apiKey'], $settings['clientId']);
            }
            $api = new CampaignMonitorApi($settings['apiKey']);
            $clients = $api->get_clients();
            if (!isset($clients[0])) {
                throw new \Exception('Client is not configured', 400);
            }
            $settings['clientId'] = $clients[0]['ClientID'];
            update_option($this->optionKey, $settings, 'no');
            $api->set_client_id($clients[0]['ClientID']);
            return $api;
        } catch (\Exception $e) {
            return false;
        }
    }
}
