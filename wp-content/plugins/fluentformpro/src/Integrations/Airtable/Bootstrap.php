<?php

namespace FluentFormPro\Integrations\Airtable;

use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper;

class Bootstrap extends IntegrationManager
{
    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            'Airtable',
            'airtable',
            '_fluentform_airtable_settings',
            'airtable_feed',
            36
        );

        // add_filter('fluentform_notifying_async_airtable', '__return_false');

        $this->logo = $this->app->url('public/img/integrations/airtable.png');

        $this->description = "Airtable is a low-code platform for building collaborative apps. Customize your workflow, collaborate, and achieve ambitious outcomes.";

        $this->registerAdminHooks();
    }

    public function getRemoteClient()
    {
        $settings = $this->getGlobalSettings([]);
        return new API(
            $settings
        );
    }

    public function getGlobalSettings($settings)
    {
        $globalSettings = get_option($this->optionKey);

        if (!$globalSettings) {
            $globalSettings = [];
        }

        $defaults = [
            'api_key'  => '',
            'base_id'  => '',
            'table_id' => '',
            'status'   => false,
        ];

        return wp_parse_args($globalSettings, $defaults);
    }

    public function getGlobalFields($fields)
    {
        return [
            'logo'               => $this->logo,
            'menu_title'         => __('Airtable Settings', 'fluentformpro'),
            'menu_description'   => $this->description,
            'valid_message'      => __('Your Airtable API Key is valid', 'fluentformpro'),
            'invalid_message'    => __('Your Airtable API Key is not valid', 'fluentformpro'),
            'save_button_text'   => __('Save Settings', 'fluentformpro'),
            'config_instruction' => $this->getConfigInstructions(),
            'fields'             => [
                'api_key' => [
                    'type'        => 'password',
                    'placeholder' => __('Airtable API Key', 'fluentformpro'),
                    'label_tips'  => __('Enter your Airtable API Key', 'fluentformpro'),
                    'label'       => __('Airtable API Key', 'fluentformpro'),
                ],
                'base_id' => [
                    'type'        => 'text',
                    'placeholder' => __('Airtable Base ID', 'fluentformpro'),
                    'label_tips'  => __('Enter your Airtable Base ID', 'fluentformpro'),
                    'label'       => __('Airtable Base ID', 'fluentformpro'),
                ],
                'table_id' => [
                    'type'        => 'text',
                    'placeholder' => __('Airtable Table ID', 'fluentformpro'),
                    'label_tips'  => __('Enter your Airtable Table ID', 'fluentformpro'),
                    'label'       => __('Airtable Table ID', 'fluentformpro'),
                ],
            ],
            'hide_on_valid'    => true,
            'discard_settings' => [
                'section_description' => __('Your Airtable API integration is up and running', 'fluentformpro'),
                'button_text'         => __('Disconnect Airtable', 'fluentformpro'),
                'data'                => [
                    'api_key'     => '',
                ],
                'show_verify' => true
            ]
        ];
    }

    protected function getConfigInstructions()
    {
        ob_start(); ?>
        <div>
            <ol>
                <li>Go <a href="https://airtable.com/account" target="_blank">Here</a> and copy your API key and paste it.</li>
                <li>Go <a href="https://airtable.com/api" target="_blank">Here</a> and select your desired Airtable base and then copy the ID of this base and paste it as Base ID. Then scroll to Table section and select your desired Airtable table under selected base and then copy the ID of this table and paste it as Table ID.</li>
                <li>You have to fill all of fields of any single row in Airtable to integrate with Fluent Form. If there is any blank column in the row, blank columns will be skipped. You must ensure that there is at least one row in the table that contains no blank column.</li>
            </ol>
        </div>
        <?php
        return ob_get_clean();
    }

    public function saveGlobalSettings($settings)
    {
        if (empty($settings['api_key']) || empty($settings['base_id']) || empty($settings['table_id'])) {
            $integrationSettings = [
                'api_key' => '',
                'base_id' => '',
                'table_id' => '',
                'status'  => false
            ];

            update_option($this->optionKey, $integrationSettings, 'no');

            wp_send_json_error([
                'message' => __('Please provide all fields to integrate', 'fluentformpro'),
                'status'  => false
            ], 423);
        }

        try {
            $client = new API($settings);
            $result = $client->checkAuth();

            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message(), $result->get_error_code());
            }

        } catch (\Exception $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage(),
                'status' => false
            ], $exception->getCode());
        }

        $integrationSettings = [
            'api_key'  => $settings['api_key'],
            'base_id'  => $settings['base_id'],
            'table_id' => $settings['table_id'],
            'status'   => true
        ];

        update_option($this->optionKey, $integrationSettings, 'no');

        wp_send_json_success([
            'message' => __('Your Airtable API key has been verified and successfully set', 'fluentformpro'),
            'status'  => true
        ], 200);
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title'                 => $this->title . ' Integration',
            'logo'                  => $this->logo,
            'is_active'             => $this->isConfigured(),
            'configure_title'       => __('Configration required!', 'fluentformpro'),
            'global_configure_url'  => admin_url('admin.php?page=fluent_forms_settings#general-airtable-settings'),
            'configure_message'     => __('Airtable is not configured yet! Please configure your Airtable api first', 'fluentformpro'),
            'configure_button_text' => __('Set Airtable API', 'fluentformpro')
        ];

        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name' => '',
            'fields' => (object)[],
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'enabled' => true
        ];
    }

    public function getSettingsFields($settings, $formId)
    {
        $fields = [
            'fields' => [
                [
                    'key'         => 'name',
                    'label'       => __('Feed Name', 'fluentformpro'),
                    'required'    => true,
                    'placeholder' => __('Your Feed Name', 'fluentformpro'),
                    'component'   => 'text'
                ],
            ],
            'integration_title' => $this->title
        ];

        $allFieldSettings = $this->getFields($fields);

        $allFieldSettings['fields'] = array_merge($allFieldSettings['fields'], [
            [
                'require_list' => false,
                'key'          => 'conditionals',
                'label'        => __('Conditional Logics', 'fluentformpro'),
                'tips'         => __('Allow this integration conditionally based on your submission values', 'fluentformpro'),
                'component'    => 'conditional_block'
            ],
            [
                'require_list'   => false,
                'key'            => 'enabled',
                'label'          => __('Status', 'fluentformpro'),
                'component'      => 'checkbox-single',
                'checkbox_label' => __('Enable this feed', 'fluentformpro')
            ]
        ]);

        return $allFieldSettings;
    }

    public function getMergeFields($list, $listId, $formId) {
        return false;
    }

    public function getFields($fields)
    {
        $client = $this->getRemoteClient();

        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $lists = $client->makeRequest('https://api.airtable.com/v0/'. $client->baseId .'/' . $client->tableId, null);

            if (!$lists) {
                return [];
            }
        } catch (\Exception $exception) {
            return false;
        }

        if (is_wp_error($lists)) {
            return [];
        }

        $customList = [];
        $maxKeyCount = 0;
        $desiredKey = '';

        foreach ($lists['records'] as $fieldKey => $fieldValues) {
            if ($maxKeyCount < count($fieldValues['fields'])) {
                $maxKeyCount = count($fieldValues['fields']);
                $desiredKey = $fieldKey;
            }
        }

        if (empty($lists['records'])) {
            wp_send_json_error([
                'message' => __('Your base table is empty. You must ensure that there is at least one row in the table that contains no blank column.', 'fluentformpro'),
                'status'  => false
            ], 500);
        }

        foreach ($lists['records'][$desiredKey]['fields'] as $fieldKey => $fieldValues) {
            if(is_array($fieldValues)) {
                if(array_key_exists('name', $fieldValues) && array_key_exists('email', $fieldValues)) {
                    $customList['key'] = 'airtable_collab_' . $fieldKey;
                    $customList['label'] = __('Enter ' . $fieldKey, 'fluentformpro');
                    $customList['required'] = false;
                    $customList['tips'] = __('Enter ' . $fieldKey . ' value or choose form input provided by shortcode.',                                       'fluentform');
                    $customList['component'] = 'value_text';
                }
                else {
                    foreach ($fieldValues as $value) {
                        if(!empty(ArrayHelper::get($value, 'url'))) {
                            $customList['key'] = 'airtable_url_' . $fieldKey;
                        }
                        else {
                            $customList['key'] = 'airtable_array_' . $fieldKey;
                        }
                        $customList['label'] = __('Enter ' . $fieldKey, 'fluentformpro');
                        $customList['required'] = false;
                        $customList['tips'] = __('Enter ' . $fieldKey . ' value or choose form input provided by                                                    shortcode.', 'fluentform');
                        $customList['component'] = 'value_text';
                    }
                }
            }
            else {
                if ($fieldValues == 'true' || $fieldValues == 'false') {
                    $customList['key'] = 'airtable_boolean_' . $fieldKey;
                }
                else {
                    $customList['key'] = 'airtable_normal_' . $fieldKey;
                }
                $customList['label'] = __('Enter ' . $fieldKey, 'fluentformpro');
                $customList['required'] = false;
                $customList['tips'] = __('Enter ' . $fieldKey . ' value or choose form input provided by shortcode.',                                       'fluentform');
                $customList['component'] = 'value_text';
            }

            array_push($fields['fields'], $customList);
        }

        return $fields;
    }

    public function notify($feed, $formData, $entry, $form)
    {
        $feedData = $feed['processedValues'];
        $subscriber = [];
        $records['records'] = [];

        foreach ($feedData as $key => $value) {
            if (strpos($key, 'airtable') !== false && !empty($value)) {
                $fieldArray = explode('_', $key);
                $fieldType = $fieldArray[1];
                $fieldName = $fieldArray[2];

                if ($fieldType == 'normal') {
                    $subscriber[$fieldName] = $value;
                }

                elseif ($fieldType == 'boolean') {
                    if ($value == 'true' || $value == 'yes') {
                        $value = true;
                    }

                    if ($value == 'false' || $value == 'no') {
                        $value = false;
                    }

                    $subscriber[$fieldName] = $value;
                }

                elseif ($fieldType == 'url') {
                    $arrayValues = array_map('trim', explode(',', $value));
                    $subscriber[$fieldName] = [];

                    foreach ($arrayValues as $urlValue) {
                        array_push($subscriber[$fieldName], ['url' => $urlValue]);
                    }
                }

                elseif ($fieldType == 'array') {
                    $arrayValues = array_map('trim', explode(',', $value));
                    $subscriber[$fieldName] = $arrayValues;
                }

                elseif ($fieldType == 'collab') {
                    if (is_email($value)) {
                        $subscriber[$fieldName] = ['email' => $value];
                    }
                }
            }
        }
        array_push($records['records'], ['fields' => $subscriber]);

        $client = $this->getRemoteClient();
        $response = $client->subscribe($records);

        if (!is_wp_error($response)) {
            do_action('ff_integration_action_result', $feed, 'success', 'Airtable feed has been successfully initialed and pushed data');
        } else {
            $error = $response->get_error_message();
            do_action('ff_integration_action_result', $feed, 'failed', $error);
        }
    }
}
