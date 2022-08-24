<?php

namespace FluentFormPro\Integrations\CleverReach;

use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper;
use WpFluent\Exception;

class Bootstrap extends IntegrationManager
{
    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            'CleverReach',
            'cleverreach',
            '_fluentform_cleverreach_settings',
            'cleverreach_feed',
            36
        );

//        add_filter('fluentform_notifying_async_cleverreach', '__return_false');

        $this->logo = $this->app->url('public/img/integrations/clever_reach.png');

        $this->description = 'Connect Fluent Forms with CleverReach  to grow your CleverReach subscriber list.';

        $this->registerAdminHooks();

        add_action('admin_init', function () {
            if (isset($_REQUEST['ff_cleverreach_auth'])) {
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
                    wp_redirect(admin_url('admin.php?page=fluent_forms_settings#general-cleverreach-settings'));
                    exit();
                } else {
                    $client->redirectToAuthServer();
                }
                die();
            }
        });
    }

    public function getRemoteClient()
    {
        $settings = $this->getGlobalSettings([]);
        return new API(
            $settings
        );
    }

    public function getGlobalSettings($settings = [])
    {
        $globalSettings = get_option($this->optionKey);

        if (!$globalSettings) {
            $globalSettings = [];
        }

        $defaults = [
            'client_id'     => '',
            'client_secret' => '',
            'status'        => false,
            'access_token'  => '',
            'refresh_token' => '',
            'expire_at'     => false
        ];

        return wp_parse_args($globalSettings, $defaults);
    }

    public function saveGlobalSettings($settings)
    {
        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            $integrationSettings = [
                'client_id'     => '',
                'client_secret' => '',
                'status'        => false,
                'access_token'  => ''
            ];
            // Update the details with siteKey & secretKey.
            update_option($this->optionKey, $integrationSettings, 'no');

            wp_send_json_success([
                'message' => __('Your settings has been updated', 'fluentformpro'),
                'status'  => false
            ], 200);
        }

        // Verify API key now
        try {
            $oldSettings = $this->getGlobalSettings([]);
            $oldSettings['client_id'] = sanitize_text_field($settings['client_id']);
            $oldSettings['client_secret'] = sanitize_text_field($settings['client_secret']);
            $oldSettings['status'] = false;
            update_option($this->optionKey, $oldSettings, 'no');

            $client = $this->getRemoteClient();
            $check = $client->checkForClientId();
            if (is_wp_error($check)) {
                $integrationSettings = [
                    'client_id'     => '',
                    'client_secret' => '',
                    'status'        => false,
                    'access_token'  => ''
                ];
                update_option($this->optionKey, $integrationSettings, 'no');

                wp_send_json_error([
                    'message' => __($check->errors['invalid_client'][0], 'fluentformpro'),
                    'status'  => false
                ], 400);
            } else {
                wp_send_json_success([
                    'message'      => __('You are redirect to authenticate', 'fluentformpro'),
                    'redirect_url' => admin_url('?ff_cleverreach_auth')
                ], 200);
            }
        } catch (\Exception $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage()
            ], 400);
        }
    }

    public function getGlobalFields($fields)
    {
        return [
            'logo'               => $this->logo,
            'menu_title'         => __('CleverReach Settings', 'fluentformpro'),
            'menu_description'   => $this->description,
            'valid_message'      => __('Your CleverReach API Key is valid', 'fluentformpro'),
            'invalid_message'    => __('Your CleverReach API Key is not valid', 'fluentformpro'),
            'save_button_text'   => __('Save Settings', 'fluentformpro'),
            'config_instruction' => $this->getConfigInstructions(),
            'fields'             => [
                'client_id'     => [
                    'type'        => 'text',
                    'placeholder' => __('CleverReach Client ID', 'fluentformpro'),
                    'label_tips'  => __('Enter your CleverReach Client ID', 'fluentformpro'),
                    'label'       => __('CleverReach Client ID', 'fluentformpro'),
                ],
                'client_secret' => [
                    'type'        => 'password',
                    'placeholder' => __('CleverReach App Client Secret', 'fluentformpro'),
                    'label_tips'  => __('Enter your CleverReach Client secret', 'fluentformpro'),
                    'label'       => __('CleverReach Client Secret', 'fluentformpro'),
                ],
            ],
            'hide_on_valid'      => true,
            'discard_settings'   => [
                'section_description' => __('Your CleverReach API integration is up and running', 'fluentformpro'),
                'button_text'         => __('Disconnect CleverReach', 'fluentformpro'),
                'data'                => [
                    'client_id'     => '',
                    'client_secret' => '',
                    'access_token'  => '',
                ],
                'show_verify'         => true
            ]
        ];
    }

    protected function getConfigInstructions()
    {
        ob_start();
        ?>
        <div>
            <h4>To Authenticate CleverReach you have to enable your API first</h4>
            <ol>
                <li>Go to Your CleverReach account dashboard, Click on the profile icon on the top right
                    corner. Click on My Account >> Extras >> REST Api then click on Create an OAuth App now button.
                </li>
                <li>Then give your oauth app a name >> choose REST API Version 3 >> Select the Forms scope >> Redirect
                    URL should be '*' and save it.<br/>
                </li>
                <li>Paste your CleverReach account Client Id and Secret Id. Then click save settings.
                </li>
            </ol>
        </div>
        <?php
        return ob_get_clean();
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title'                 => $this->title . ' Integration',
            'logo'                  => $this->logo,
            'is_active'             => $this->isConfigured(),
            'configure_title'       => __('Configuration required!', 'fluentformpro'),
            'global_configure_url'  => admin_url('admin.php?page=fluent_forms_settings#general-cleverreach-settings'),
            'configure_message'     => __('CleverReach is not configured yet! Please configure your CleverReach API first',
                'fluentformpro'),
            'configure_button_text' => __('Set CleverReach API', 'fluentformpro')
        ];
        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name' => '',
            'list_id' => '',
            'fieldEmailAddress' => '',
            'custom_fields'        => (object)[],
            'conditionals'         => [
                'conditions' => [],
                'status'     => false,
                'type'       => 'all'
            ],
            'enabled'              => true
        ];
    }

    public function getSettingsFields($settings, $formId)
    {
        return [
            'fields'            => [
                [
                    'key'         => 'name',
                    'label'       => __('Feed Name', 'fluentformpro'),
                    'required'    => true,
                    'placeholder' => __('Your Feed Name', 'fluentformpro'),
                    'component'   => 'text'
                ],
                [
                    'key'         => 'list_id',
                    'label'       => __('CleverReach List', 'fluentformpro'),
                    'required'    => true,
                    'placeholder' => __('Select CleverReach List', 'fluentformpro'),
                    'tips'        => __('Select the CleverReach list you would like to add your contacts to.',
                        'fluentformpro'),
                    'component'   => 'list_ajax_options',
                    'options'     => $this->getLists(),
                ],
                [
                    'key'                => 'custom_fields',
                    'require_list'       => true,
                    'label'              => __('Map Fields', 'fluentformpro'),
                    'tips'               => __('Associate your CleverReach merge tags to the appropriate Fluent Forms fields by selecting the appropriate form field from the list.',
                        'fluentformpro'),
                    'component'          => 'map_fields',
                    'field_label_remote' => __('CleverReach Field', 'fluentformpro'),
                    'field_label_local'  => __('Form Field', 'fluentformpro'),
                    'primary_fileds'     => [
                        [
                            'key'           => 'email',
                            'label'         => __('Email Address', 'fluentformpro'),
                            'required'      => true,
                            'input_options' => 'emails'
                        ],
                    ]
                ],
                [
                    'require_list' => true,
                    'key'          => 'conditionals',
                    'label'        => __('Conditional Logics', 'fluentformpro'),
                    'tips'         => __('Allow  CleverReach integration conditionally based on your submission values',
                        'fluentformpro'),
                    'component'    => 'conditional_block'
                ],
                [
                    'require_list'   => true,
                    'key'            => 'enabled',
                    'label'          => __('Status', 'fluentformpro'),
                    'component'      => 'checkbox-single',
                    'checkbox_label' => __('Enable This feed','fluentformpro'),
                ]
            ],
            'integration_title' => $this->title
        ];
    }

    protected function getLists()
    {
        $client = $this->getRemoteClient();
        $settings = get_option($this->optionKey);

        try {
            $token = ($settings['access_token']);
            $lists = $client->makeRequest('https://rest.cleverreach.com/groups', null, 'GET',
                ['Authorization' => 'Bearer ' . $token]);

            if (!$lists || is_wp_error($lists)) {
                return [];
            }
        } catch (\Exception $exception) {
            return [];
        }

        $formattedLists = [];
        foreach ($lists as $list) {
            $formattedLists[$list['id']] = $list['name'];
        }

        return $formattedLists;
    }

    public function getMergeFields($list, $listId, $formId)
    {
        $client = $this->getRemoteClient();

        if (!$this->isConfigured()) {
            return false;
        }

        $settings = get_option($this->optionKey);

        try {
            $token = ($settings['access_token']);
            $lists = $client->makeRequest('https://rest.cleverreach.com/groups/' . $listId . '/attributes/', null,
                'GET', ['Authorization' => 'Bearer ' . $token]);

            if (!$lists) {
                return [];
            }
        } catch (\Exception $exception) {
            return false;
        }

        if (is_wp_error($lists)) {
            return [];
        }

        $mergedFields = $lists;
        $fields = [];

        foreach ($mergedFields as $merged_field) {
            $fields[$merged_field['name']] = $merged_field['name'];
        }

        return $fields;
    }

    public function notify($feed, $formData, $entry, $form)
    {
        $feedData = $feed['processedValues'];

        if (!is_email($feedData['email'])) {
            $feedData['email'] = ArrayHelper::get($formData, $feedData['email']);
        }

        if (!is_email($feedData['email'])) {
            do_action('ff_integration_action_result', $feed, 'failed',
                'CleverReach API call has been skipped because no valid email available');
            return;
        }

        $subscriber = [];
        $subscriber['list_id'] = $feedData['list_id'];
        $subscriber['email'] = $feedData['email'];
        $subscriber['attributes'] = ArrayHelper::get($feedData, 'custom_fields');

        $client = $this->getRemoteClient();
        $response = $client->subscribe($subscriber);

        if (is_wp_error($response)) {
            // it's failed
            $error = isset($response->errors['error'][0][0]['message']) ? $response->errors['error'][0][0]['message'] : __('CleverReach API call failed please try again !',
                'fluentformpro');
            do_action('ff_integration_action_result', $feed, 'failed', $error);

        } else {
            // It's success
            do_action('ff_integration_action_result', $feed, 'success', __('CleverReach has been successfully initialed and pushed data','fluentformpro'));

        }
    }
}
