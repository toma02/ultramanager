<?php
namespace FluentFormPro\Integrations\Pipedrive;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper;

class Bootstrap extends \FluentForm\App\Services\Integrations\IntegrationManager
{
    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            'Pipedrive',
            'pipedrive',
            '_fluentform_pipedrive_settings',
            'pipedrive_feed',
            26
        );

        $this->logo = $this->app->url('public/img/integrations/pipedrive.png');

        $this->description = 'By connecting Pipedrive with Fluent Forms, you can connect and organize leads and more.';

        $this->registerAdminHooks();

//        add_filter('fluentform_notifying_async_pipedrive', '__return_false');
        add_filter(
            'fluentform_get_integration_values_pipedrive',
            [$this, 'resolveIntegrationSettings'],
            100,
            3
        );
        add_filter('fluentform_save_integration_value_' . $this->integrationKey, [$this, 'validate'], 10, 3);
    }


    public function getGlobalFields($fields)
    {
        return [
            'logo'               => $this->logo,
            'menu_title'         => __('Pipedrive API Settings', 'fluentformpro'),
            'menu_description'   => __('Pipedrive is a deal-driven customer relationship management CRM solution that also works as an account-management tool with the ability to assist with marketing and the entire sales process. If you don\'t have an Pipedrive account, you can <a href="https://www.pipedrive.com/en/register"  target="_blank">sign up for one here.</a>', 'fluentformpro'),
            'valid_message'      => __('Your Pipedrive API token is valid', 'fluentformpro'),
            'invalid_message'    => __('Your Pipedrive API token is invalid', 'fluentformpro'),
            'save_button_text'   => __('Verify Pipedrive API Token', 'fluentformpro'),
            'config_instruction' => $this->getConfigInstructions(),
            'fields'             => [
                'apiToken' => [
                    'type'        => 'text',
                    'placeholder' => 'Enter Your API token',
                    'label_tips'  => __("Enter your Pipedrive API token, if you do not have follow-up upper instructions.", 'fluentformpro'),
                    'label'       => __('Pipedrive API Token', 'fluentformpro'),
                ],
            ],
            'hide_on_valid'      => true,
            'discard_settings'   => [
                'section_description' => 'Your Pipedrive API integration is up and running',
                'button_text'         => 'Disconnect Pipedrive',
                'data'                => [
                    'apiToken' => ''
                ],
                'show_verify'         => true
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
            'apiToken' => '',
            'status'      => ''
        ];

        return wp_parse_args($globalSettings, $defaults);
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        $name = $this->app->request->get('serviceName', '');
        $serviceId = $this->app->request->get('serviceId', '');

        return [
            'name' => $name,
            'list_id' => $serviceId,
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
            'title'                 => $this->title . ' Integration',
            'logo'                  => $this->logo,
            'is_active'             => $this->isConfigured(),
            'configure_title'       => 'Configuration required!',
            'global_configure_url'  => admin_url('admin.php?page=fluent_forms_settings#general-pipedrive-settings'),
            'configure_message'     => 'Pipedrive is not configured yet! Please configure your Pipedrive api token first',
            'configure_button_text' => 'Set Pipedrive API Token'
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

        $serviceId = $this->app->request->get(
            'serviceId',
            ArrayHelper::get($settings, 'list_id')
        );

        if ($serviceId) {
            $fields = $this->getFields($serviceId);
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

    public function saveGlobalSettings($settings){
        if (!$settings['apiToken']) {
            $integrationSettings = [
                'apiToken' => '',
                'status'      => false
            ];
            // Update the  details with access token
            update_option($this->optionKey, $integrationSettings, 'no');
            wp_send_json_error([
                'message'      => __('API token in required.', 'fluentformpro'),
                'status'       => false,
                'require_load' => true
            ], 404);
        }

        try {
            $settings['status'] = false;
            update_option($this->optionKey, $settings, 'no');

            $api = $this->getApi($settings['apiToken']);
            $auth = $api->auth_test();

            if ($auth['success']) {
                $settings['status'] = true;
                update_option($this->optionKey, $settings, 'no');
                wp_send_json_success([
                    'status'  => true,
                    'message' => __('Your settings has been updated!', 'fluentformpro')
                ], 200);
            }
            throw new \Exception(__('Invalid Api Token','fluentformpro'), 401);
        } catch (\Exception $e) {
            wp_send_json_error([
                'status'  => false,
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }

    public function getMergeFields($list, $listId, $formId)
    {
        return [];
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

    protected function getServices() {
        return [
            'persons'       => 'Person',
            'leads'         => 'Leads',
            'organizations' => 'Organization',
            'deals'         => 'Deal',
            'notes'         => 'Note',
            'activities'    => 'Activity',
        ];
    }

    public function getFields($serviceId) {
        if ($serviceId === 'leads') {
            $fieldId = $this->makeFieldEndpoint('deals');
        } else {
            $fieldId = $this->makeFieldEndpoint($serviceId);
        }
        $api = $this->getApi();
        $response = $api->getFields($fieldId);

        // if got error or integration status is false exit with false
        if (is_wp_error($response) || !$response['success']) {
            return false;
        }

        $fields = array();
        if ($response['data']) {
            $others_fields = array();
            foreach ($response['data'] as $field) {
                // if field is not valid skip this field
                if (!$this->isFieldValid($field, $serviceId)) {
                    continue;
                }


                if ($this->filterField($field))  {

                    if (in_array($field['key'], ['name', 'status'])) {
                        $field['key'] = 'field_' . $field['key'];
                    }

                    $data = array(
                        'key' => $field['key'],
                        'placeholder' => $field['name'],
                        'label' => $field['name'],
                        'data_type' => $field['field_type'],
                        'required' => false,
                        'tips' => 'Enter ' . $field['name'] . ' value or choose form input provided by shortcode.',
                        'component' => 'value_text'
                    );

                    if ($this->isRequiredField($field)) {
                        $data['required'] = true;
                        $data['tips'] = $field['name'] . ' is a required field. Enter value or choose form input provided by shortcode.';
                    }

                    if ($field['key'] === 'value') {
                        $data['tips'] = 'Amount value. Currency is pipedrive default currency.';
                    }

                    if ($this->isSelectField($field)) {
                        $data['component'] = 'select';
                        $data['tips'] = "Choose " . $field['name'] . " type in select list.";
                        $data_options = array();
                        if ($field['field_type'] === 'user') {
                            $users = $api->getUsers();
                            if (is_wp_error($users) || !$users['success']) {
                                continue;
                            }
                            if ($users['data']) {
                                $data_options = $this->formatArray($users['data']);
                            }
                        } elseif (in_array($field['field_type'], ['org', 'people'])) {
                            $people_options = $orgs_options = [];
                            $data['tips'] .= "<br> If list empty first create " . $field['name'] ." in you pipedrive dashboard.";
                            $orgs = $api->getOrganizations();
                            if (is_wp_error($orgs) || !$orgs['success']) {
                                continue;
                            }

                            if ($orgs['data']) {
                                $orgs_options = $this->formatArray($orgs['data']);
                            }

                            $people = $api->getPerson();
                            if (is_wp_error($people) || !$people['success']) {
                                continue;
                            }

                            if ($people['data']) {
                                $people_options = $this->formatArray($people['data']);
                            }
                            if ($field['field_type'] === 'people') {
                                if ($orgs_options && empty($people_options)) {
                                    $data['required'] =  false;
                                }
                                $data_options = $people_options;
                            }

                            if ($field['field_type'] === 'org') {
                                if ($people_options && empty($orgs_options)) {
                                    $data['required'] =  false;
                                }
                                if ($serviceId === 'notes' && $people_options && !empty($orgs_options)) {
                                    $data['required'] =  false;
                                }
                                $data_options = $orgs_options;
                            }

                        }
                        else {
                            $data_options = $this->formatArray($field['options'], 'options');
                        }

                        $data['options'] = $data_options;
                    }
                    if ($field['field_type'] == 'text') {
                        $data['component'] = 'value_textarea';
                    }

                    if ($serviceId === 'leads' && $field['key'] === 'value') {
                        $data['required'] = true;
                        $fields[] = $data;
                        $fields = $this->getLeadExtraFields($fields);
                    } elseif ($serviceId === 'activities' && $field['key'] === 'type') {
                        $fields[] = $data;
                        $fields[] = [
                            'key' => 'due_date',
                            'placeholder' => 'Enter Lead Title or choose shortcode',
                            'label' => 'Activity Due Date',
                            'required' => false,
                            'tips' => 'The due date of the activity. In ISO 8601 format: YYYY-MM-DD.',
                            'component' => 'datetime'
                        ];
                    }
                    else {
                        $fields[] = $data;
                    }
                } else {
                    if ($this->isOthersfield($field)) {
                        $others_fields[$field['key']] = $field['name'];
                    }
                }

            }
            if (!empty($others_fields)) {
                $fields[] = [
                    'key' => 'other_fields',
                    'require_list' => false,
                    'required' => false,
                    'label' => 'Other Fields',
                    'tips' => 'Select which Fluent Forms fields pair with their respective pipedrivie modules fields. <br /> Field value must be string type.',
                    'component' => 'dropdown_many_fields',
                    'options' => $others_fields
                ];
            }
        }

        return $fields;

    }

    protected function getLeadExtraFields($fields) {
        $api = $this->getApi();
        $currencies = [];


        $response = $api->getCurrencies();
        if (!is_wp_error($response) && $response['success'] && $response['data']) {
            $currencies = $this->formatArray($response['data'], 'currencies');
        }

        $fields[] = [
            'key' => 'currency',
            'placeholder' => 'Currency',
            'label' => 'Currency Code',
            'required' => true,
            'tips' => 'Choose witch country currency amount value is.',
            'component' => 'select',
            'options' => $currencies
        ];
        $fields[] = [
            'key' => 'expected_close_date',
            'placeholder' => 'Enter Lead Title or choose shortcode',
            'label' => 'Expected Close Date',
            'required' => false,
            'tips' => 'The date of when the deal which will be created from the lead is expected to be closed. In ISO 8601 format: YYYY-MM-DD.',
            'component' => 'datetime'
        ];
        return $fields;
    }

    protected function formatArray($items = [], $from = '') {
        $newArray = [];
        foreach ($items as $item) {
            if ($from === 'currencies') {
                // make country currencies options for dropdown
                $newArray[$item['code']] = $item['name'];
            } elseif ($from === 'lead_keys') {
                // make leads service keys for validation and get data from feed settings
                $data = [
                    'key' => $item['key'],
                    'feed_key' => $item['key'],
                    'label' => $item['label'],
                    'data_type' => '',
                    'required' => $item['required']
                ];
                if (isset($item['data_type'])) {
                    $data['data_type'] = $item['data_type'];
                }
                array_push($newArray, $data);
            }  elseif ($from === 'options') {
                // format other field options
                $newArray[$item['id']] = $item['label'];
            }
            else {
                // make other select field options
                $newArray[$item['id']] = $item['name'];
            }
        }
        return $newArray;
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
            if (ArrayHelper::get($key, 'other_fields') && !empty($feedData['other_fields'])) {
                foreach ($feedData['other_fields'] as $other_field) {
                    if (
                            !empty($other_field['item_value']) &&
                            !empty($other_field['label']) &&
                            $other_field['label'] === $key['feed_key']
                    ) {
                        $postData[$other_field['label']] = $other_field['item_value'];
                    }
                }
                continue;
            }

            if ($key['required'] && empty($feedData[$key['feed_key']])) {
                do_action('ff_integration_action_result', $feed, 'failed',  'Failed to insert Pipedrive feed. Details : ' . $key['label'] . ' empty');
                return false;
            }

            if (!empty($feedData[$key['feed_key']])) {
                $postData[$key['key']] = $feedData[$key['feed_key']];
            }
        }

        if ($list_id === 'leads') {
            $postData['value'] = [
                'amount' => intval(ArrayHelper::get($postData, 'value')),
                'currency' => ArrayHelper::get($postData,'currency')
            ];
            ArrayHelper::forget($postData, ['currency']);

            if ($orgId = ArrayHelper::get($postData, 'org_id')) {
                $postData['organization_id'] = intval($orgId);
                ArrayHelper::forget($postData, ['org_id']);
            }
            if ($person = ArrayHelper::get($postData, 'person_id')) {
                $postData['person_id'] = intval($person);
            }
            if ($owner = ArrayHelper::get($postData, 'user_id')) {
                $postData['owner_id'] = intval($owner);
                ArrayHelper::forget($postData, ['user_id']);
            }

            if ($date = ArrayHelper::get($postData, 'expected_close_date')) {
                $postData['expected_close_date'] = date('Y-m-d', strtotime($date));
            }
        }

        if ($list_id === 'activities') {
            if ($date = ArrayHelper::get($postData,'due_date')) {
                $postData['due_date'] = date('Y-m-d', strtotime($date));
            }
        }

        $api = $this->getApi();
        $response = $api->insertServiceData($feedData['list_id'], $postData);

        if (is_wp_error($response)) {
            // it's failed
            do_action('ff_integration_action_result', $feed, 'failed',  'Failed to insert Pipedrive feed. Details : ' . $response->get_error_message());
        } else {
            // It's success
            do_action('ff_integration_action_result', $feed, 'success', 'Pipedrive feed has been successfully inserted '. $list_id .' data.');
        }
    }

    public function validate($settings, $integrationId, $formId)
    {
        $error = false;
        $errors = array();
        $fields = $this->getAllKeys($settings['list_id']);
        if ($fields) {
            foreach ($fields as $field){
                if ($field['required'] && empty($settings[$field['feed_key']])) {
                    $error = true;
                    $msg = __($field['label'].' is required.', 'fluentformpro');
                    if ($field['data_type'] === 'org') {
                        $msg .= __(' First Create Organization In your Integration.');
                    }
                    $errors[$field['feed_key']] = [$msg];
                }
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

    protected function getAllKeys($serviceId)
    {
        $fields = $this->getFields($serviceId);
        $keys = [];
        foreach ($fields as $field) {

            $data = [
                'key' => $field['key'],
                'feed_key' => $field['key'],
                'label' => $field['label'],
                'data_type' => ArrayHelper::get($field, 'data_type'),
                'required' => $field['required']
            ];
            if (in_array($field['key'], ['field_name', 'field_status'])) {
                $data['key'] = (explode("_",$field['key']))[1];
            }
            if ($field['key'] == 'other_fields' && $options = ArrayHelper::get($field, 'options')) {
                $data['other_fields'] = true;
                foreach ($options as $key => $value) {
                    $data = [
                        'key' => $key,
                        'feed_key' => $key,
                        'label' => $value,
                        'other_fields' => true,
                        'data_type' => '',
                        'required' => false
                    ];
                    $keys[] =$data;
                }
            } else {
                $keys[] = $data;
            }
        }
        return $keys;
    }


    protected function isFieldValid($field, $serviceId)
    {
        // if bulk edit not set skip this field
        if (!isset($field['bulk_edit_allowed'])) {
            return false;
        }

        //skip leads status field
        if ($serviceId === 'leads' && $field['key'] === 'status') {
            return false;
        }

        // if field data type is data/stage/varchar_options/lead/deal skip this field
        if (
            in_array($field['field_type'], ['date', 'stage', 'varchar_options', 'lead', 'deal']) ||
            $field['key'] === 'done'
        ) {
            return  false;
        }

        // if field type is select but not property options skit this field
        if (in_array($field['field_type'], ['enum', 'visible_to']) && !isset($field['options'])) {
            return false;
        }

        return true;
    }
    protected function filterField($field)
    {
        if (
            ($field['mandatory_flag'] && $field['bulk_edit_allowed']) ||
            ($field['bulk_edit_allowed'] &&
                (
                    in_array($field['field_type'], ['enum', 'visible_to', 'text', 'org']) ||
                    $field['key'] === 'value'
                )
            ) ||
            ArrayHelper::get($field, 'important_flag')
        ) {
            return true;
        }
        return false;
    }
    protected function isSelectField($field)
    {
        if (
            (
                in_array($field['field_type'], ['enum', 'visible_to', 'status'])
                && ArrayHelper::get($field, 'options')
            ) ||
            in_array($field['field_type'], ['user', 'people', 'org'])
        ) {
            return true;
        }
        return false;
    }
    protected function isRequiredField($field) {
        if ($field['mandatory_flag'] || ArrayHelper::get($field,'important_flag') || $field['key'] === 'content') {
            return true;
        }
        return false;
    }
    protected function isOthersfield($field) {
        if ($field['field_type'] == 'varchar' || $field['field_type'] == 'phone') {
            return true;
        }
        return false;
    }
    protected function makeFieldEndpoint($serviceId)
    {
        if ($serviceId === 'activities') {
            $fieldId = 'activityFields';
        } else {
            $fieldId = substr($serviceId,0,strlen($serviceId) -1) . 'Fields';
        }
        return $fieldId;
    }



    protected function getApi($apiToken = null)
    {
        if (!$apiToken) {
            $apiToken = $this->getGlobalSettings([])['apiToken'];
        }
        return new PipedriveApi($apiToken);
    }

    protected function getConfigInstructions()
    {
        ob_start();
        ?>
        <div>
            <h4>You can get the API token manually from the Pipedrive web app by going to account name (on the top right) > Company settings > Personal preferences > API or by clicking <a href="https://app.pipedrive.com/settings/api"  target="_blank">here</a>.</h4>
        </div>
        <?php
        return ob_get_clean();
    }
}