<?php

namespace FluentFormPro\Integrations\Discord;

use FluentForm\Framework\Foundation\Application;
use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentForm\App\Modules\Form\FormDataParser;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentFormPro\Integrations\Discord\Discord;

class Bootstrap extends IntegrationManager
{
    public $disableGlobalSettings = 'yes';
    
    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            'Discord',
            'discord',
            '_fluentform_discord_settings',
            'discord_feed',
            99
        );
        
        $this->logo = $this->app->url('public/img/integrations/discord.png');
        
        $this->description = __(
            'Send notification with form data to your Discord channel when a form is submitted.',
            'fluentformpro');

        add_filter('fluentform_save_integration_value_' . $this->integrationKey, [$this, 'validate'], 10, 3);

        $this->registerAdminHooks();
//        add_filter('fluentform_notifying_async_' . $this->integrationKey, '__return_false');
    }
    
    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'category'                => 'crm',
            'disable_global_settings' => 'yes',
            'logo'                    => $this->logo,
            'title'                   => $this->title . ' Integration',
            'is_active'               => $this->isConfigured()
        ];
        return $integrations;
    }
    
    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name'         => '',
            'description'  => '',
            'footer'       => '',
            'webhook'      => '',
            'fields'       => [],
            'conditionals' => [
                'conditions' => [],
                'status'     => false,
                'type'       => 'all'
            ],
            'enabled'      => true
        ];
    }
    
    public function getSettingsFields($settings, $formId)
    {
        return [
            'fields' => [
                [
                    'key' => 'name',
                    'label' => __('Feed Name', 'fluentformpro'),
                    'required' => true,
                    'placeholder' => __('Your Feed Name', 'fluentformpro'),
                    'component' => 'value_text'
                ],
                [
                    'key' => 'webhook',
                    'label' => __('Webhook Url', 'fluentformpro'),
                    'tips' => __(
                        'Paste your webhook url here. Click "Edit Channel" cog and create your channel webhook from integrations.',
                        'fluentformpro'
                    ),
                    'component' => 'text',
                    'placeholder' => __('Webhook', 'fluentformpro'),
                    'required' => true,
                ],
                [
                    'key' => 'description',
                    'label' => __('Description', 'fluentformpro'),
                    'placeholder' => __('Discord Message Description', 'fluentformpro'),
                    'component' => 'value_textarea'
                ],
                [
                    'key' => 'fields',
                    'label' => __('Input Fields', 'fluentformpro'),
                    'component' => 'checkbox-multiple-text',
                    'options' => $this->getInputFields($formId),
                ],
                [
                    'key' => 'footer',
                    'label' => __('Footer', 'fluentformpro'),
                    'placeholder' => __('Discord Message Footer', 'fluentformpro'),
                    'component' => 'value_text'
                ],
                [
                    'key' => 'conditionals',
                    'label' => __('Conditional Logics', 'fluentformpro'),
                    'tips' => __(
                        'Allow Discord integration conditionally based on your submission values',
                        'fluentformpro'
                    ),
                    'component' => 'conditional_block'
                ],
                [
                    'key' => 'enabled',
                    'label' => __('Status', 'fluentformpro'),
                    'component' => 'checkbox-single',
                    'checkbox_label' => __('Enable This feed', 'fluentformpro'),
                ],
            ],
            'integration_title' => $this->title
        ];
    }
    
    public function getMergeFields($list = false, $listId = false, $formId = false)
    {
        return [];
    }
    
    public function addGlobalMenu($setting)
    {
        return $setting;
    }
    
    public function isConfigured()
    {
        return true;
    }
    
    public function getInputFields($formId)
    {
        $formApi = fluentFormApi()->form($formId);
        return $formApi->labels();
    }

    public function validate($settings, $integrationId, $formId)
    {
        if (!$settings['webhook']) {
            wp_send_json_error([
                'message' => __('Validation Failed', 'fluentformpro'),
                'errors'  => [
                    'webhook' => ['Webhook is required']
                ]
            ], 423);
        }
        return $settings;
    }
    
    public function notify($feed, $formData, $entry, $form)
    {
        $settings = $feed['processedValues'];
        $inputs = FormFieldsParser::getEntryInputs($form);
        $labels = FormFieldsParser::getAdminLabels($form, $inputs);
        $messageTitle = ArrayHelper::get($settings, 'name');
        $formData = FormDataParser::parseData((object)$formData, $inputs, $form->id);
        
        $fields = [];
        $selectedFields = ArrayHelper::get($settings,'fields');
        
        foreach ($selectedFields as $label => $key) {
            if (empty($value = ArrayHelper::get($formData, $key))) {
                continue;
            }
            $fields[] = [
                'name' => ArrayHelper::get($labels,$key),
                'value' => str_replace('<br />', "\n", $value),
                'inline' => true
            ];
        }


        $webhook = ArrayHelper::get($settings, 'webhook');
        $description = ArrayHelper::get($settings, 'description');
        $footer = ArrayHelper::get($settings, 'footer');
        
        $entryLink = admin_url(
            'admin.php?page=fluent_forms&form_id='
            . $form->id
            . '&route=entries#/entries/'
            . $entry->id
        );
        $message = apply_filters('ff_integration_discord_message', [
            'embeds' => [
                0 => [
                    'fields' => $fields,
                    'title' => esc_html($messageTitle),
                    'url' => esc_url_raw($entryLink),
                    'description' => sanitize_text_field($description),
                    'color' => hexdec('3F9EFF'),
                    'footer' => [
                        'text' => sanitize_text_field($footer)
                    ]
                ],
            ],
            'content' => '*New submission on '. $form->title.' (#' . $entry->id . ')*'
        ], $feed);
        
        $response = Discord::sendMessage($webhook, $message);
        if (is_wp_error($response)) {
            do_action('ff_integration_action_result', $feed, 'failed', $response->get_error_message());
        } else {
            do_action(
                'ff_integration_action_result',
                $feed,
                'success',
                __('Discord feed has been successfully initialed and pushed data','fluentformpro')
            );
        }
    }
    
}
