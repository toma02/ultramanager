<?php

namespace FluentFormPro\Components\Post;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\Framework\Helpers\ArrayHelper;

class MetaboxHelper
{
    public static function getFields($postType, $withRaw = false)
    {
        if(!defined('RWMB_VER')) {
            return [
                'general' => [],
                'advanced' => []
            ];
        }

        $meta_box_registry = rwmb_get_registry( 'meta_box' );
        $args = [
            'object_type' => 'post'
        ];
        $meta_boxes = $meta_box_registry->get_by( $args );

        $validBoxes = [];

        foreach ($meta_boxes as $key => $meta_box) {
            if(empty($meta_box->meta_box)) {
                continue;
            }
            $postTypes = ArrayHelper::get($meta_box->meta_box, 'post_types',  []);
            if($postTypes && in_array($postType, $postTypes)) {
                $validBoxes[$key] = $meta_box;
            }
        }

        return self::classifyFields($meta_boxes, $withRaw);
    }

    public static function classifyFields($metaBoxes, $withRaw = false)
    {
        $generalAcfFields = self::getGeneralFields();
        $advancedAcfFields = self::getAdvancedFields();

        $generalFields = [];
        $advancedFields = [];

        foreach ($metaBoxes as $field_group) {
            $fields = $field_group->meta_box['fields'];
            foreach ($fields as $field) {
                if (in_array($field['type'], $generalAcfFields)) {
                    $generalFields[$field['field_name']] = [
                        'type' => $field['type'],
                        'label' => $field['name'],
                        'name' => $field['field_name'],
                        'key' => $field['id']
                    ];
                    if($withRaw) {
                        $generalFields[$field['field_name']]['raw'] = $field;
                    }
                } else if (isset($advancedAcfFields[$field['type']])) {
                    $settings = $advancedAcfFields[$field['type']];
                    $advancedFields[$field['field_name']] = [
                        'type' => $field['type'],
                        'label' => $field['name'],
                        'name' => $field['field_name'],
                        'key' => $field['id'],
                        'acceptable_fields' => $settings['acceptable_fields'],
                        'help_message' => $settings['help']
                    ];

                    if($withRaw) {
                        $advancedFields[$field['field_name']]['raw'] = $field;
                    }
                }
            }
        }

        return [
            'general' => $generalFields,
            'advanced' => $advancedFields
        ];
    }

    private static function getGeneralFields()
    {
        return apply_filters('fluent_post_metabox_accepted_general_fields', [
            'hidden',
            'password',
            'text',
            'textarea',
            'url',
            'wysiwyg',
            'time',
            'slider',
            'color',
            'email',
            'number',
            'range',
            'tel'
        ]);
    }

    public static function getAdvancedFields()
    {
        return apply_filters('fluent_post_metabox_accepted_advanced_fields', [
            'select' => [
                'acceptable_fields' => ['select'],
                'help' => __('Select select field for this mapping', 'fluentformpro')
            ],
            'select_advanced' => [
                'acceptable_fields' => ['select'],
                'help' => __('Select appropriate field for this mapping', 'fluentformpro')
            ],
            'checkbox' => [
                'acceptable_fields' => ['gdpr_agreement', 'terms_and_condition'],
                'help' => __('Select checkbox field for this mapping', 'fluentformpro')
            ],
            'checkbox_list' => [
                'acceptable_fields' => ['input_checkbox'],
                'help' => __('Select checkbox field for this mapping', 'fluentformpro')
            ],
            'radio' => [
                'acceptable_fields' => ['input_radio'],
                'help' => __('Select radio field for this mapping', 'fluentformpro')
            ],
            'button_group' => [
                'acceptable_fields' => ['input_radio'],
                'help' => __('Select radio field for this mapping', 'fluentformpro')
            ],
            'image_select' => [
                'acceptable_fields' => ['input_radio', 'input_checkbox', 'select'],
                'help' => __('Select appropriate field for this mapping', 'fluentformpro')
            ],
            'datetime-local' => [
                'acceptable_fields' => ['input_date'],
                'help' => __('Select Date field for this mapping', 'fluentformpro')
            ],
            'date' => [
                'acceptable_fields' => ['input_date'],
                'help' => __('Select Date field for this mapping', 'fluentformpro')
            ],
            'datetime' => [
                'acceptable_fields' => ['input_date'],
                'help' => __('Select Date field for this mapping', 'fluentformpro')
            ],
            'switch' => [
                'acceptable_fields' => ['gdpr_agreement', 'terms_and_condition'],
                'help' => __('Select Date field for this mapping', 'fluentformpro')
            ],
            'file' => [
                'acceptable_fields' => ['input_file'],
                'help' => __('Select File Upload field for this mapping', 'fluentformpro')
            ],
            'file_advanced' => [
                'acceptable_fields' => ['input_file'],
                'help' => __('Select Date field for this mapping', 'fluentformpro')
            ],
            'file_upload' => [
                'acceptable_fields' => ['input_file'],
                'help' => __('Select Date field for this mapping', 'fluentformpro')
            ],
            'file_input' => [
                'acceptable_fields' => ['input_file', 'input_image'],
                'help' => __('Select Date field for this mapping', 'fluentformpro')
            ],
            'image' => [
                'acceptable_fields' => ['input_image'],
                'help' => __('Select Date field for this mapping', 'fluentformpro')
            ],
            'image_advanced' => [
                'acceptable_fields' => ['input_image'],
                'help' => __('Select Date field for this mapping', 'fluentformpro')
            ],
            'image_upload' => [
                'acceptable_fields' => ['input_image'],
                'help' => __('Select Date field for this mapping', 'fluentformpro')
            ],
            'single_image' => [
                'acceptable_fields' => ['input_image'],
                'help' => __('Select Date field for this mapping', 'fluentformpro')
            ],
        ]);
    }

    public static function prepareFieldsData($fields, $postType, $formData)
    {

        $fieldGroups = self::getFields($postType, true);

        $metaValues = [];
        $generalFields = $fieldGroups['general'];
        foreach ($fields['general'] as $field) {
            $fieldValue = ArrayHelper::get($field, 'field_value');
            $fieldKey = ArrayHelper::get($field, 'field_key');

            if (!$fieldKey || !$fieldValue || !isset($generalFields[$fieldKey])) {
                continue;
            }

            $fieldConfig = $generalFields[$fieldKey];
            $mataName = $fieldConfig['name'];
            $metaValues[$mataName] = $fieldValue;
        }

        $advancedFields = $fieldGroups['advanced'];

        foreach ($fields['advanced'] as $field) {
            $fieldValue = ArrayHelper::get($formData, $field['field_value']);
            $fieldKey = ArrayHelper::get($field, 'field_key');

            if (!$fieldKey || !$fieldValue || !isset($advancedFields[$fieldKey])) {
                continue;
            }

            $fieldConfig = $advancedFields[$fieldKey];
            $fieldKey = str_replace('[]', '', $fieldKey);
            $type = $fieldConfig['type'];
            $rawTypes = ['checkbox_list', 'button_group', 'select', 'select_advanced', 'radio', 'datetime-local', 'image_select', 'date', 'datetime'];
            if(in_array($type, $rawTypes)) {
                $metaValues[$fieldKey] = $fieldValue;
                continue;
            }

            $fileIdsTypes = ['file', 'file_advanced', 'file_upload', 'image', 'image_advanced', 'image_upload', 'single_image'];

            if(in_array($type, $fileIdsTypes)) {
                $fileIds = self::getFileIdsFromUrls($fieldValue);
                if($fileIds) {
                    if($type == 'single_image') {
                        $metaValues[$fieldKey] = $fileIds[0];
                    } else {
                        $metaValues[$fieldKey] = $fileIds;
                    }
                }
                continue;
            }
            if($type == 'file_input') {
                if(is_array($fieldValue)) {
                    $metaValues[$fieldKey] = $fieldValue[0];
                }
                continue;
            }

            $booleanTypes = ['switch', 'checkbox'];
            if(in_array($type, $booleanTypes)) {
                $metaValues[$fieldKey] = 1;
            }
        }


        return $metaValues;

    }

    private static function getFileIdsFromUrls($fieldValue)
    {
        if (!array($fieldValue)) {
            return [];
        }

        $attachmentIds = [];
        foreach ($fieldValue as $item) {
            $attachmentId = (new PostFormHandler())->getAttachmentToImageUrl($item);
            if ($attachmentId) {
                $attachmentIds[] = $attachmentId;
            }
        }

       return $attachmentIds;
    }

}