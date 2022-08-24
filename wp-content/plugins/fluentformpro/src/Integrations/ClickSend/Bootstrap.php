<?php

namespace FluentFormPro\Integrations\ClickSend;

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
            'ClickSend',
            'clicksend_sms_notification',
            '_fluentform_clicksend_sms_notification_settings',
            'clicksend_sms_notification_feed',
            25
        );

        $this->logo = $this->app->url('public/img/integrations/clicksend.png');

        $this->description = 'Send SMS in real time when a form is submitted with ClickSend.';


        $this->registerAdminHooks();
        add_filter('fluentform_save_integration_value_' . $this->integrationKey, [$this, 'validate'], 10, 3);
        add_action('wp_ajax_fluentform_clicksend_sms_config', array($this, 'getClickSendConfigOptions'));

//        add_filter('fluentform_notifying_async_clicksend_sms_notification', '__return_false');

        add_filter(
            'fluentform_get_integration_values_clicksend_sms_notification', 
            [$this, 'resolveIntegrationSettings'],
            100, 
            3
        );
    }

    public function getClickSendConfigOptions()
    {

    }

    public function getGlobalFields($fields)
    {
        return [
            'logo' => $this->logo,
            'menu_title' => __('SMS Provider Settings (ClickSend)', 'fluentformpro'),
            'menu_description' => __('Please Provide your ClickSend Settings here', 'fluentformpro'),
            'valid_message' => __('Your ClickSend API Key is valid', 'fluentformpro'),
            'invalid_message' => __('Your ClickSend API Key is not valid', 'fluentformpro'),
            'save_button_text' => __('Save Settings', 'fluentformpro'),
            'config_instruction' => $this->getConfigInstructions(),
            'fields' => [
                'senderNumber' => [
                    'type' => 'text',
                    'placeholder' => 'ClickSend Sender Number',
                    'label_tips' => __("Enter your clicksend sender number", 'fluentformpro'),
                    'label' => __('Sender Number', 'fluentformpro'),
                ],
                'username' => [
                    'type' => 'text',
                    'placeholder' => 'ClickSend Username',
                    'label_tips' => __("Enter ClickSend Username. This can be found from ClickSend", 'fluentformpro'),
                    'label' => __('Username', 'fluentformpro'),
                ],
                'authToken' => [
                    'type' => 'password',
                    'placeholder' => 'API Key',
                    'label_tips' => __("Enter ClickSend API Key. This can be found from ClickSend", 'fluentformpro'),
                    'label' => __('API Key', 'fluentformpro'),
                ]
            ],
            'hide_on_valid' => true,
            'discard_settings' => [
                'section_description' => 'Your ClickSend API integration is up and running',
                'button_text' => 'Disconnect ClickSend',
                'data' => [
                    'authToken' => ''
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
            'senderNumber' => '',
            'username' => '',
            'authToken' => '',
            'provider' => 'ClickSend'
        ];

        return wp_parse_args($globalSettings, $defaults);
    }

    public function saveGlobalSettings($settings)
    {

        try {
            if (empty($settings['authToken'])) {
                //prevent saving integration without the sender number
                throw new \Exception(
                    __('API Key is required', 'fluentformpro')
                );

            }

            if (empty($settings['senderNumber'])) {
                //prevent saving integration without the sender number
                throw new \Exception(
                    __('Sender number is required', 'fluentformpro')
                );

            }
            if (empty($settings['username'])) {
                //prevent saving integration without the sender number
                throw new \Exception(
                    __('Username number is required', 'fluentformpro')
                );
            }

            $integrationSettings = [
                'senderNumber' => sanitize_textarea_field($settings['senderNumber']),
                'username' => sanitize_text_field($settings['username']),
                'authToken' => sanitize_text_field($settings['authToken']),
                'provider' => 'ClickSend',
                'status' => false
            ];
            update_option($this->optionKey, $integrationSettings, 'no');

            $api = new ClickSend($settings['authToken'], $settings['username']);
            $result = $api->auth_test();

            if (!empty($result['error'])) {
                throw new \Exception($result['message']);
            }
        } catch (\Exception $exception) {
            $integrationSettings = [
                'senderNumber' => '',
                'username' => '',
                'authToken' => '',
                'provider' => 'ClickSend',
                'status' => false
            ];
            update_option($this->optionKey, $integrationSettings, 'no');
            wp_send_json_error([
                'message' => $exception->getMessage()
            ], 400);
        }

        // Integration key is verified now, Proceed now

        $integrationSettings = [
            'senderNumber' => sanitize_textarea_field($settings['senderNumber']),
            'username' => sanitize_text_field($settings['username']),
            'authToken' => sanitize_text_field($settings['authToken']),
            'provider' => 'ClickSend',
            'status' => true
        ];

        // Update the reCaptcha details with siteKey & secretKey.
        update_option($this->optionKey, $integrationSettings, 'no');

        wp_send_json_success([
            'message' => __('Your ClickSend API Key has been verified and successfully set', 'fluentformpro'),
            'status' => true
        ], 200);
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title' => __('ClickSend SMS Notification', 'fluentformpro'),
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => __('Configuration required!', 'fluentformpro'),
            'global_configure_url' => admin_url('admin.php?page=fluent_forms_settings#general-clicksend_sms_notification-settings'),
            'configure_message' => __('ClickSend SMS Notification is not configured yet! Please configure your ClickSend SMS api first', 'fluentformpro'),
            'configure_button_text' => __('Set ClickSend SMS Notification API', 'fluentformpro')
        ];
        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        $name = $this->app->request->get('serviceName', '');
        $listId = $this->app->request->get('serviceId', '');

        return [
            'name' => $name,
            'receiver_number' => '',
            'list_id' => $listId,
            'email'  => '',
            'message_body' => 'message-input',
            'message' => '',
            'phone_number' => '',
            'fields' => (object)[],
            'other_add_contact_fields' => [
                [
                    'item_value' => '',
                    'label' => ''
                ]
            ],
            'contact_list_name' => '',
            'campaign_name' => '',
            'email_campaign_subject' => '',
            'campaign_list_id' => '',
            'schedule' => '',
            'template_id' => '',
            'email_template_id' => '',
            'email_address_id' => '',
            'email_form_name' => '',
            'enabled' => true,
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
        ];
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
                    'options' => [
                        'single-sms' => 'Single SMS',
                        'sms-campaign' => 'SMS Campaign',
                        'create-new-contact' => 'Create Subscriber Contact',
                        'add-contact-list' => 'Add Contact List',
                        'email-campaign' => 'Add Email Campaign',
                    ]
                ]
            ],
            'button_require_list' => false,
            'integration_title' => $this->title
        ];

        $listId = $this->app->request->get(
            'serviceId', 
            ArrayHelper::get($settings, 'list_id')
        );
        
        if ($listId) {
            $fields = $this->getFieldsByService($listId);

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

    public function getFieldsByService($listId)
    {
        $api = $this->getRemoteClient();

        switch ($listId){
            case 'single-sms':
                $template_options = array();
                $templates = $api->getTemplates('sms/templates');
                if (!is_wp_error($templates)) {
                    foreach ($templates['data']['data'] as $template) {
                        $template_options[$template['template_id']] = $template['template_name'];
                    }
                }

                $fields = [
                    [
                        'key' => 'message_body',
                        'label' => 'Message Body',
                        'tips' => 'Select your message body type e.g. Input Message or Template Message.',
                        'placeholder' => 'Chose Message Body',
                        'required' => true,
                        'component' => 'select',
                        'options' => [
                            'message-input' => 'Input Message',
                            'template-message' => 'Template Message',
                        ]
                    ],
                    [
                        'key' => 'receiver_number',
                        'label' => 'To',
                        'required' => true,
                        'tips' => 'Enter a receiver number or select input field shortcode.',
                        'placeholder' => 'Type the receiver number',
                        'component' => 'value_text'
                    ],
                    [
                        'key' => 'message',
                        'label' => 'Message',
                        'tips' => 'Enter your message. If you chose Template Message as Message Body, you can ignore this settings.',
                        'required' => false,
                        'placeholder' => 'Message Body',
                        'component' => 'value_textarea'
                    ],
                    [
                        'key' => 'template_id',
                        'label' => 'SMS Template',
                        'placeholder' => 'Choose Template',
                        'tips' => 'Choose a template for SMS body. This settings won\'t take effect if you chose Input Message as Message Body.',
                        'required' => false,
                        'component' => 'select',
                        'options' => $template_options
                    ],
                    [
                        'key' => 'schedule',
                        'label' => 'SMS Schedule',
                        'placeholder' => 'SMS schedule date and time ',
                        'tips' => 'Optional. Choose a datetime for sending SMS. If empty, SMS will be sent immediately.',
                        'required' => false,
                        'component' => 'datetime',
                    ]
                ];
                break;
            case 'sms-campaign':
                $template_options = array();
                $templates = $api->getTemplates('sms/templates');
                if (!is_wp_error($templates)) {
                    foreach ($templates['data']['data'] as $template) {
                        $template_options[$template['template_id']] = $template['template_name'];
                    }
                }

                $list_options = array();
                $lists = $api->getLists();
                if (!is_wp_error($lists)) {
                    foreach ($lists['data']['data'] as $list) {
                        $list_options[$list['list_id']] = $list['list_name'];
                    }
                }

                $fields = [
                    [
                        'key' => 'message_body',
                        'label' => 'Message Body',
                        'tips' => 'Select your message body type e.g. Input Message or Template Message.',
                        'placeholder' => 'Chose Message Body',
                        'required' => true,
                        'component' => 'select',
                        'options' => [
                            'message-input' => 'Input Message',
                            'template-message' => 'Template Message',
                        ]
                    ],
                    [
                        'key' => 'campaign_name',
                        'label' => 'Campaign Name',
                        'tips' => 'Enter your campaign name or select input shortcode field for campaign name.',
                        'required' => true,
                        'placeholder' => 'Campaign Name',
                        'component' => 'value_text'
                    ],
                    [
                        'key' => 'campaign_list_id',
                        'label' => 'Campaign List',
                        'placeholder' => 'Choose list',
                        'tips' => 'Choose a list for sending SMS to all of its contact.',
                        'required' => true,
                        'component' => 'select',
                        'options' => $list_options
                    ],
                    [
                        'key' => 'message',
                        'label' => 'Message',
                        'tips' => 'Enter your message. If you chose Template Message as Message Body, you can ignore this settings.',
                        'required' => false,
                        'placeholder' => 'Message Body',
                        'component' => 'value_textarea'
                    ],
                    [
                        'key' => 'template_id',
                        'label' => 'SMS Template',
                        'placeholder' => 'Choose Template',
                        'tips' => 'Choose a template for SMS body. This settings won\'t take effect if you chose Input Message as Message Body.',
                        'required' => false,
                        'component' => 'select',
                        'options' => $template_options
                    ],
                    [
                        'key' => 'schedule',
                        'label' => 'Campaign Schedule',
                        'placeholder' => 'Campaign schedule date and time ',
                        'tips' => 'Choose a datetime for your SMS campaign.',
                        'required' => false,
                        'component' => 'datetime',
                    ]
                ];
                break;
            case 'create-new-contact':
                $list_options = array();
                $lists = $api->getLists();
                if (!is_wp_error($lists)) {
                    foreach ($lists['data']['data'] as $list){
                        $list_options[$list['list_id']] = $list['list_name'];
                    }
                }
                $fields = [
                    [
                        'key'           => 'campaign_list_id',
                        'label'         => 'Campaign List',
                        'placeholder'   => 'Choose list',
                        'tips'          => 'Choose the list to which the contact should be added.',
                        'required'      => true,
                        'component'     => 'select',
                        'options'       => $list_options
                    ],
                    [
                        'key'           => 'phone_number',
                        'label'         => 'Phone Number',
                        'placeholder'   => 'Subscriber Number',
                        'tips'          => 'Enter subscriber or select input shortcode field to add contact in the list.',
                        'required'      => true,
                        'component'     => 'value_text',
                    ],
                    [
                        'key'           => 'email',
                        'label'         => 'Email',
                        'placeholder'   => 'Subscriber Email',
                        'tips'          => 'Enter subscriber email or select input shortcode field to add contact in the list.',
                        'required'      => true,
                        'component'     => 'value_text',
                    ],
                    [
                        'key' => 'other_add_contact_fields',
                        'required' => false,
                        'label' => 'Other Fields',
                        'tips' => 'Other contact fields to add more information about the contact. These fields are optional.',
                        'component' => 'dropdown_many_fields',
                        'options' => [
                            'first_name'         => 'First Name',
                            'last_name'          => 'Last Name',
                            'organization_name'  => 'Company',
                            'fax_number'         => 'Fax Number',
                            'address_line_1'     => 'Address Line 1',
                            'address_line_2'     => 'Address Line 2',
                            'address_city'       => 'City',
                            'address_state'      => 'State',
                            'address_postal_code'=> 'Postal Code',
                            'address_country'    => 'Country',
                            'custom_1'           => 'Custom Field 1',
                            'custom_2'           => 'Custom Field 2',
                            'custom_3'           => 'Custom Field 3',
                            'custom_4'           => 'Custom Field 4',
                        ]
                    ],
                ];
                break;
            case 'email-campaign':
                $list_options = array();
                $lists = $api->getLists();
                if (!is_wp_error($lists)) {
                    foreach ($lists['data']['data'] as $list) {
                        $list_options[$list['list_id']] = $list['list_name'];
                    }
                }

                $template_options = array();
                $templates = $api->getTemplates('email/templates');
                if (!is_wp_error($templates)) {
                    foreach ($templates['data']['data'] as $template) {
                        $template_options[$template['template_id']] = $template['template_name'];
                    }
                }

                $email_address_options = array();
                $email_address = $api->getEmailAddress('email/addresses');
                if (!is_wp_error($email_address)) {
                    foreach ($email_address['data']['data'] as $email_info) {
                        $email_address_options[$email_info['email_address_id']] = $email_info['email_address'];
                    }
                }

                $from_name_options = array();
                $account_info = $api->get('account');
                if (!is_wp_error($account_info)) {
                    $first_name = $account_info['data']['user_first_name'];
                    $last_name = $account_info['data']['user_last_name'];
                    $from_name_options[$first_name . ' ' . $last_name] = $first_name . ' ' . $last_name;
                    $from_name_options[$first_name] = $first_name;
                    $from_name_options[$last_name] = $last_name;
                    $from_name_options[$account_info['data']['username']] = $account_info['data']['username'];
                    $from_name_options[$account_info['data']['account_name']] = $account_info['data']['account_name'];
                }

                $fields = [
                    [
                        'key' => 'campaign_name',
                        'label' => 'Campaign Name',
                        'tips' => 'Enter your campaign name or select input shortcode field for campaign name.',
                        'required' => true,
                        'placeholder' => 'Campaign Name',
                        'component' => 'value_text'
                    ],
                    [
                        'key' => 'email_campaign_subject',
                        'label' => 'Campaign Subject',
                        'tips' => 'Enter your campaign subject or select input shortcode field for campaign subject.',
                        'required' => true,
                        'placeholder' => 'Campaign Subject',
                        'component' => 'value_text'
                    ],
                    [
                        'key' => 'campaign_list_id',
                        'label' => 'Campaign List',
                        'placeholder' => 'Choose list',
                        'tips' => 'Choose a list for sending email to all of its contact.',
                        'required' => true,
                        'component' => 'select',
                        'options' => $list_options
                    ],

                    [
                        'key' => 'message',
                        'label' => 'Message',
                        'tips' => 'Enter your message.',
                        'required' => true,
                        'placeholder' => 'Message Body',
                        'component' => 'value_textarea'
                    ],

                    [
                        'key' => 'email_template_id',
                        'label' => 'Email Template',
                        'placeholder' => 'Choose Template',
                        'tips' => 'Choose a template for SMS body.',
                        'required' => false,
                        'component' => 'select',
                        'options' => $template_options
                    ],
                    [
                        'key' => 'email_address_id',
                        'label' => 'From Email Address',
                        'placeholder' => 'Enter From Email',
                        'tips' => 'Enter an email address for Form Email.',
                        'required' => true,
                        'component' => 'select',
                        'options' => $email_address_options
                    ],
                    [
                        'key' => 'email_form_name',
                        'label' => 'Email Form Name',
                        'placeholder' => 'Enter Form Name',
                        'tips' => 'Enter a name for sending email Form Name.',
                        'required' => true,
                        'component' => 'select',
                        'options' => $from_name_options
                    ],
                    [
                        'key' => 'schedule',
                        'label' => 'Campaign Schedule',
                        'placeholder' => 'Campaign schedule date and time ',
                        'tips' => 'Choose a datetime for your sms campaign.',
                        'required' => false,
                        'component' => 'datetime',
                    ]
                ];
                break;
            case 'add-contact-list':
                $fields = [
                    [
                        'key'           => 'contact_list_name',
                        'label'         => 'List Name',
                        'placeholder'   => 'Contact List Name',
                        'tips'          => 'Enter name or select input shortcode field for contact list.',
                        'required'      => true,
                        'component'     => 'value_text',
                    ],
                ];
                break;
            default:
                return [];
        }

        return $fields;
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

        foreach ($this->getFieldsByService($settings['list_id']) as $field){
            if ($field['required'] && empty($settings[$field['key']])) {
                $error = true;

                $errors[$field['key']] = [__($field['label'] .' is required', 'fluentformpro')];
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
    /*
     * Form Submission Hooks Here
     */
    public function notify($feed, $formData, $entry, $form)
    {
        $feedData = $feed['processedValues'];

        if (empty($feedData['list_id'])) {
            do_action('ff_integration_action_result', $feed, 'failed', 'no valid service found');
            return;
        }

        $apiSettings = $this->getGlobalSettings([]);
        $api = $this->getRemoteClient();

//        sms body
        $smsBody = '';
        if (!empty($feedData['message_body'])) {
            if ($feedData['message_body'] == 'template-message' && !empty($feedData['template_id'])) {
                $templates = $api->getTemplates('sms/templates');
                if (!is_wp_error($templates)) {
                    foreach ($templates['data']['data'] as $template) {

                        if ($template['template_id'] == $feedData['template_id']) {
                            $smsBody = $template['body'];
                        }
                    }
                }
            }
            if ($feedData['message_body'] == 'message-input' && !empty($feedData['message'])) {
                $smsBody = $feedData['message'];
            }
        }

//        sms time schedule
        $schedule = 0;
        if (!empty($feedData['schedule'])) {
            $schedule = strtotime($feedData['schedule']);
        }

//        switch services and do operation
        switch ($feedData['list_id']){
            case 'single-sms':
                if (
                    empty($feedData['message_body']) ||
                    empty($feedData['receiver_number']) ||
                    ($feedData['message_body'] == 'template-message' && empty($feedData['template_id'])) ||
                    ($feedData['message_body'] == 'message-input' && empty($feedData['message']))
                ) {
                    do_action('ff_integration_action_result', $feed, 'failed', 'no fulfill required field');
                    return;
                }

                $action = 'single-sms';
                $smsData = [
                    "messages" => array([
                        'body' => $smsBody,
                        'schedule' => $schedule,
                        'from' => $apiSettings['senderNumber'],
                        'to' => $feedData['receiver_number'],
                    ])
                ];
                $this->handleSMSResponse($action, $smsData, $feed, $entry);
                break;
            case 'sms-campaign':
                if (empty($feedData['message_body']) ||
                    empty($feedData['campaign_list_id']) ||
                    ($feedData['message_body'] == 'template-message' && empty($feedData['template_id'])) ||
                    ($feedData['message_body'] == 'message-input' && empty($feedData['message']))
                ) {
                    do_action('ff_integration_action_result', $feed, 'failed', 'no fulfill required field');
                    return;
                }
                $action = 'sms-campaign';
                $smsData = [
                    'body' => $smsBody,
                    'schedule' => $schedule,
                    'from' => $apiSettings['senderNumber'],
                    'name' => $feedData['campaign_name'],
                    'list_id' => $feedData['campaign_list_id']
                ];
                $this->handleSMSResponse($action, $smsData, $feed, $entry);
                break;
            case 'create-new-contact':
                if (empty($feedData['phone_number']) || empty($feedData['campaign_list_id']) || empty($feedData['email'])) {
                    do_action('ff_integration_action_result', $feed, 'failed', 'no fulfill required field');
                    return;
                }

                $data = array();
                $data['phone_number'] = $feedData['phone_number'];
                $data['email'] = sanitize_email($feedData['email']);

                if ($feedData['other_add_contact_fields']) {
                    foreach ($feedData['other_add_contact_fields'] as $field) {
                        $data[$field['label']] = $field['item_value'];
                    }
                }

                $response = $api->addSubscriberContact($feedData['campaign_list_id'], $data);

                if (is_wp_error($response)) {
                    $this->handleFailed($feed, $response->get_error_message());
                } else {
                    $this->handleSuccess($feed,'ClickSend SMS feed has been successfully initialed and add subscriber in contact list' );
                }
                break;
            case 'add-contact-list':
                if (empty($feedData['contact_list_name'])) {
                    do_action('ff_integration_action_result', $feed, 'failed', 'no fulfill required field');
                    return;
                }

                $data = array();
                $data['list_name'] = $feedData['contact_list_name'];

                $response = $api->addContactList($data);

                if (is_wp_error($response)) {
                    $this->handleFailed( $feed, $response->get_error_message());
                } else {
                    $this->handleSuccess($feed,'ClickSend SMS feed has been successfully initialed and add contact list' );
                }
            break;
            case 'email-campaign':
                if (
                    empty($feedData['email_address_id']) ||
                    empty($feedData['campaign_list_id']) ||
                    empty($feedData['email_campaign_subject']) ||
                    empty($feedData['campaign_name']) ||
                    empty($feedData['email_form_name']) ||
                    empty($feedData['message'])
                ) {
                    do_action('ff_integration_action_result', $feed, 'failed', 'no fulfill required field');
                    return;
                }
                $data = array();

                $data['list_id'] = $feedData['campaign_list_id'];
                $data['subject'] = $feedData['email_campaign_subject'];
                $data['name'] = $feedData['campaign_name'];
                $data['body'] = $feedData['message'];
                $data['from_name'] = $feedData['email_form_name'];
                $data['schedule'] = $schedule;
                $data['from_email_address_id'] = $feedData['email_address_id'];
                if ($feedData['email_template_id']) {
                    $data['template_id'] = $feedData['email_template_id'];
                }
                $response = $api->addEmailCampaign($data);

                if (is_wp_error($response)) {
                    $this->handleFailed($feed, $response->get_error_message());
                } else {
                    $this->handleSuccess($feed,'ClickSend SMS feed has been successfully initialed and pushed email campaign data' );
                }
                break;
        }
    }


    public function handleSMSResponse($action, $smsData, $feed, $entry)
    {

        $smsData = apply_filters('fluentform_integration_data_' . $this->integrationKey, $smsData, $feed, $entry);
        $api = $this->getRemoteClient();
        $response = $api->sendSMS($action, $smsData);

        if (is_wp_error($response)) {
            $this->handleFailed( $feed, $response->get_error_message());
        } else {
            $this->handleSuccess($feed );
        }
    }

    public function handleFailed( $feed, $err_msg = 'ClickSend Integration Data insert failed.')
    {
        do_action('ff_integration_action_result', $feed, 'failed',  $err_msg);
    }

    public function handleSuccess( $feed, $success_msg = 'ClickSend feed has been successfully initialed and pushed data')
    {
        // It's success
        do_action('ff_integration_action_result', $feed, 'success', $success_msg);
    }
    protected function getConfigInstructions()
    {
        ob_start();
        ?>
        <div><h4>To Authenticate clicksend you have to clicksend account first. <a href="https://dashboard.clicksend.com/#/signup/" target="_blank">Sign UP</a></h4>
            <ol>
                <li>Go to Your ClickSend account <a href="https://dashboard.clicksend.com/#/dashboard/home" target="_blank">Dashboard</a>, Click on the profile icon and also click on account settings or browse
                    <a href="https://dashboard.clicksend.com/account/subaccounts" target="_blank">Account link</a>
                </li>
                <li>
                    You'll need an API key and clicksend number. You can access your
                    <a href="https://dashboard.clicksend.com/#/account/subaccount" target="_blank"> API key </a> and
                    <a href="https://dashboard.clicksend.com/#/numbers/sms" target="_blank">purchase a number</a>.
                </li>
                <li>Copy your ClickSend purchase number, username and API key. And paste bellow input. Then click
                    save settings.
                </li>
            </ol>
        </div>
        <?php
        return ob_get_clean();
    }

    public function getRemoteClient()
    {
        $settings = $this->getGlobalSettings([]);
        return new ClickSend($settings['authToken'], $settings['username']);
    }

}
