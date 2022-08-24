<?php

namespace FluentFormPro\classes;

use FluentForm\Framework\Helpers\ArrayHelper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class AddressAutoComplete
{
    private $optionKey = 'ff_google_maps_autocomplete';
    private $integrationKey = 'google_maps_autocomplete';

    public function init()
    {
        add_filter('fluentform_global_settings_components', array($this, 'addGlobalMenu'), 1);
        add_filter('fluentform_global_integration_settings_' . $this->optionKey, array($this, 'getSettings'), 10);
        add_filter('fluentform_global_integration_fields_' . $this->optionKey, array($this, 'getSettingsFields'), 10, 1);
        add_action('fluentform_save_global_integration_settings_' . $this->optionKey, array($this, 'saveSettings'), 10, 1);

        add_filter('fluentform_editor_vars', function ($vars) {
            if ($this->isEnabled()) {
                $vars['has_address_gmap_api'] = true;
            }
            return $vars;
        });

        add_action('fluentform_address_map_autocomplete', array($this, 'addGmapJs'), 10, 2);

        add_filter('fluentform_editor_init_element_address', function ($element) {
            if (!isset($element['settings']['enable_g_autocomplete'])) {
                $element['settings']['enable_g_autocomplete'] = '';
            }
            if (!isset($element['settings']['enable_g_map'])) {
                $element['settings']['enable_g_map'] = '';
            }
            if (!isset($element['settings']['enable_auto_locate'])) {
                $element['settings']['enable_auto_locate'] = 'no';
            }
            return $element;
        });
    
        add_filter('fluentform_rendering_field_data_address' , function ($data, $form){
            if ($this->ifShowLocateButton($data)){
                $inputName =  ArrayHelper::get(current($data['fields']),'attributes.name');
                $locateIcon = $this->getLocateIcon();
                ArrayHelper::set($data, 'fields.'.$inputName.'.settings.suffix_label',$locateIcon);
            }
            return $data;
        },10,2);

    }

    public function addGlobalMenu($setting)
    {
        $setting['google_maps_autocomplete'] = [
            'hash'         => $this->integrationKey,
            'component'    => 'general-integration-settings',
            'settings_key' => $this->optionKey,
            'title'        => 'Google Maps Integration'
        ];
        return $setting;
    }

    public function getSettings($settings)
    {
        $globalSettings = get_option($this->optionKey);
        if (!$globalSettings) {
            $globalSettings = [];
        }

        $defaults = [
            'api_key' => '',
            'status'  => ''
        ];

        return wp_parse_args($globalSettings, $defaults);
    }

    public function getSettingsFields($fields)
    {
        return [
            'logo'             => FLUENTFORMPRO_DIR_URL . 'public/images/google_map.png',
            'menu_title'       => __('Google Map Integration Settings', 'fluentformpro'),
            'menu_description' => __('For address autocomplete feature you may setup google map API details', 'fluentformpro'),
            'valid_message'    => __('Google Map API is set.', 'fluentformpro'),
            'invalid_message'  => __('Google Map API key is not valid', 'fluentformpro'),
            'save_button_text' => __('Save Settings', 'fluentformpro'),
            'fields'           => [
                'api_key' => [
                    'type'        => 'password',
                    'placeholder' => '',
                    'label_tips'  => __("Enter your Google Map API Key", 'fluentformpro'),
                    'label'       => __('Google Map API Key', 'fluentformpro'),
                ],
            ],
            'hide_on_valid'    => true,
            'discard_settings' => [
                'section_description' => 'Google Map API is set. You can now enable google map autocomplete feature for Address Field',
                'button_text'         => 'Disconnect Google Map API',
                'data'                => [
                    'api_key' => ''
                ]
            ]
        ];
    }

    public function saveSettings($settings)
    {
        $key = $settings['api_key'];

        if (!$key) {
            $defaults = [
                'api_key' => '',
                'status'  => ''
            ];
            update_option($this->optionKey, $defaults, 'no');
            wp_send_json_success([
                'message' => __('Your settings has been updated and discarded', 'fluentformpro'),
                'status'  => false
            ], 200);
        }

        update_option($this->optionKey, [
            'api_key' => sanitize_text_field($settings['api_key']),
            'status'  => true
        ], 'no');

        wp_send_json_success([
            'message' => __('Google Map Api key has been saved', 'fluentformpro'),
            'status'  => true
        ], 200);

    }

    public function isEnabled()
    {
        $settings = $this->getSettings([]);

        return !empty($settings['api_key']) && $settings['status'];
    }

    public function addGmapJs($data, $form)
    {
        $settings = $this->getSettings([]);

        if (empty($settings['api_key'])) {
            return;
        }

        $apiKey = $settings['api_key'];
        wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . $apiKey . '&libraries=places', [], FLUENTFORM_VERSION, true);
        wp_enqueue_script('ff_gmap', FLUENTFORMPRO_DIR_URL.'public/js/ff_gmap.js', ['jquery'], FLUENTFORM_VERSION, true);
    }

    /**
     * @param $data
     * @return bool
     */
    private function ifShowLocateButton($data)
    {
        if ($this->isEnabled() && ArrayHelper::get($data, 'settings.enable_g_autocomplete') == 'yes' &&
            (ArrayHelper::get($data, 'settings.enable_auto_locate') == 'on_load' ||
                ArrayHelper::get($data, 'settings.enable_auto_locate') == 'on_click')
        ) {
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    private function getLocateIcon()
    {
        return '<svg  style="cursor:pointer;" xmlns="http://www.w3.org/2000/svg" width="20" viewBox="0 0 30 30"><path d="M 14.984375 0.98632812 A 1.0001 1.0001 0 0 0 14 2 L 14 3.0507812 C 8.1822448 3.5345683 3.5345683 8.1822448 3.0507812 14 L 2 14 A 1.0001 1.0001 0 1 0 2 16 L 3.0507812 16 C 3.5345683 21.817755 8.1822448 26.465432 14 26.949219 L 14 28 A 1.0001 1.0001 0 1 0 16 28 L 16 26.949219 C 21.817755 26.465432 26.465432 21.817755 26.949219 16 L 28 16 A 1.0001 1.0001 0 1 0 28 14 L 26.949219 14 C 26.465432 8.1822448 21.817755 3.5345683 16 3.0507812 L 16 2 A 1.0001 1.0001 0 0 0 14.984375 0.98632812 z M 14 5.0488281 L 14 6 A 1.0001 1.0001 0 1 0 16 6 L 16 5.0488281 C 20.732953 5.5164646 24.483535 9.2670468 24.951172 14 L 24 14 A 1.0001 1.0001 0 1 0 24 16 L 24.951172 16 C 24.483535 20.732953 20.732953 24.483535 16 24.951172 L 16 24 A 1.0001 1.0001 0 0 0 14.984375 22.986328 A 1.0001 1.0001 0 0 0 14 24 L 14 24.951172 C 9.2670468 24.483535 5.5164646 20.732953 5.0488281 16 L 6 16 A 1.0001 1.0001 0 1 0 6 14 L 5.0488281 14 C 5.5164646 9.2670468 9.2670468 5.5164646 14 5.0488281 z"></path></svg>';
    }
}
