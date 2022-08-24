<?php

namespace FluentFormPro\Components;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Services\FormBuilder\BaseFieldManager;
use FluentFormPro\Components\Post\PopulatePostForm;

class PostSelectionField extends BaseFieldManager
{
    public function __construct()
    {
        parent::__construct(
            'cpt_selection',
            'Post/CPT Selection',
            ['post', 'cpt', 'custom post type'],
            'advanced'
        );

        add_filter('fluentform_response_render_' . $this->key, function ($value) {
            if (!$value || !is_numeric($value)) {
                return $value;
            }
            $post = get_post($value);
            return (isset($post->post_title)) ? $post->post_title : $value;
        });
    }

    function getComponent()
    {
        return [
            'index'          => 29,
            'element'        => $this->key,
            'attributes'     => [
                'name'  => $this->key,
                'value' => '',
                'id'    => '',
                'class' => '',
            ],
            'settings'       => array(
                'dynamic_default_value'   => '',
                'label'                   => __('Post Selection', 'fluentformpro'),
                'admin_field_label'       => '',
                'help_message'            => '',
                'container_class'         => '',
                'label_placement'         => '',
                'placeholder'             => '- Select -',
                'post_type_selection'     => 'post',
                'post_extra_query_params' => '',
                'enable_select_2'         => 'no',
                'validation_rules'        => array(
                    'required' => array(
                        'value'   => false,
                        'message' => __('This field is required', 'fluentformpro'),
                    ),
                ),
                'conditional_logics'      => array(),
            ),
            'editor_options' => array(
                'title'      => __('Post/CPT Selection', 'fluentformpro'),
                'icon_class' => 'ff-edit-dropdown',
                'element'    => 'select',
                'template'   => 'select'
            )
        ];
    }

    public function getGeneralEditorElements()
    {
        return [
            'label',
            'post_type_selection',
            'post_extra_query_params',
            'admin_field_label',
            'placeholder',
            'label_placement',
            'validation_rules'
        ];
    }

    public function getAdvancedEditorElements()
    {
        return [
            'name',
            'dynamic_default_value',
            'help_message',
            'container_class',
            'class',
            'conditional_logics',
            'enable_select_2'
        ];
    }

    public function generalEditorElement()
    {
        $postTypes = get_post_types(apply_filters('fluentform_post_type_selection_types_args', [
            'public' => true
        ]));

        $formattedTypes = [];
        foreach ($postTypes as $typeName => $label) {
            $formattedTypes[] = [
                'label' => ucfirst($label),
                'value' => $typeName
            ];
        }

        $formattedTypes = apply_filters('fluentform_post_selection_types', $formattedTypes);

        return [
            'post_type_selection'     => [
                'template'  => 'select',
                'label'     => 'Select Post Type',
                'help_text' => 'Select Post Type that you want to show',
                'options'   => $formattedTypes
            ],
            'post_extra_query_params' => [
                'template'         => 'inputTextarea',
                'label'            => 'Extra Query Parameter',
                'help_text'        => 'Extra Query Parameter for CPT Query',
                'inline_help_text' => 'You can provide post query parameters for further filter. <a target="_blank" href="https://wpmanageninja.com/?p=1520634">Check the doc here</a>'
            ]
        ];
    }

    public function render($data, $form)
    {
        (new PopulatePostForm())->renderPostSelectionField($data, $form);
    }
}