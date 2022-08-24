<?php

namespace FluentFormPro\Components\Post\Components;

use FluentForm\App\Services\FormBuilder\BaseFieldManager;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Components\Post\PopulatePostForm;
use FluentFormPro\Components\Post\PostFormHandler;

class PostUpdate extends BaseFieldManager
{
    public function __construct() {
        parent::__construct(
            'post_update',
            'Post Update',
            ['update', 'post_update', 'post update'],
            'post'
        );
        add_filter('fluentform_response_render_' . $this->key, function ($value) {
            if (!$value || !is_numeric($value)) {
                return $value;
            }
            $post = get_post($value);
            return (isset($post->post_title)) ? $post->post_title : $value;
        });
      new PopulatePostForm();
    }
    public function getComponent()
    {
        return [
            'index' => 5,
            'element' => $this->key,
            'attributes' => [
                'name' => $this->key,
                'class' => '',
                'value' => '',
                'type' => 'select',
                'placeholder' => '- Select -'
            ],
            'settings' => [
                'container_class' => '',
                'placeholder' => '- Select -',
                'label' => __('Select Post', 'fluentformpro'),
                'label_placement' => '',
                'help_message' => '',
                'admin_field_label' => '',
                'post_extra_query_params' => '',
                'infoBlock' => '',
                'allow_view_posts' => 'all_post',
                'enable_select_2'         => 'no',
                'validation_rules' => [
                    'required' => [
                        'value' => true,
                        'message' => __('This field is required', 'fluentformpro'),
                    ]
                ],
                'conditional_logics' => []
            ],
            'editor_options' => [
                'title' => $this->title,
                'icon_class' => 'ff-edit-text',
                'element'    => 'select',
                'template'   => 'select'
            ],
        ];
    }

    public function getGeneralEditorElements()
    {
        return [
            'label',
            'admin_field_label',
            'placeholder',
            'value',
            'post_extra_query_params',
            'label_placement',
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

        return [
            'allow_view_posts'     => [
                'template'  => 'radio',
                'label'     => 'Filter Posts',
                'help_text' => 'Select Which Post you want to show',
                'options'   => array(
                    [
                        "label" => "All Post",
                        "value" => 'all_post'
                    ],
                    [
                        "label" => "Current User Post",
                        "value" => 'current_user_post'
                    ],
                )
            ],
            'post_extra_query_params' => [
                'template'         => 'inputTextarea',
                'label'            => 'Extra Query Parameter',
                'help_text'        => 'Extra Query Parameter for Update Post',
                'inline_help_text' => 'You can provide post query parameters for further filter. <a target="_blank" href="https://wpmanageninja.com/?p=1520634">Check the doc here</a>'
            ],
            'infoBlock' => [
                'text' => 'Post update field will only work when Post Feeds Submission Type is set to Update Post.',
                'template' => 'infoBlock'
            ]
        ];
    }

    public function render($data, $form)
    {
        if ($form->type != 'post') return;
        $postFormHandler = new PostFormHandler();
        $feeds = $postFormHandler->getFormFeeds($form);

        if (!$feeds) {
            return;
        }
        foreach ($feeds as $feed) {
            $feed->value = json_decode($feed->value, true);
            if (ArrayHelper::get($feed->value, 'post_form_type') == 'update') {
                $data['attributes']['id'] = 'post-selector-' . time();
                $data['attributes']['name'] = '__ff_update_post_id';
                $data['settings']['post_type_selection'] = (new \FluentFormPro\Components\Post\PostFormHandler())->getPostType($form);

                if (ArrayHelper::get($data, 'settings.allow_view_posts') === 'current_user_post') {
                    $data['settings']['post_extra_query_params'] .= '&author=' . get_current_user_id();
                }
                (new PopulatePostForm())->renderPostSelectionField($data, $form);
                return;
            }
        }

    }

    public function pushTags($tags, $form)
    {
        if ($form->type != 'post') {
            return $tags;
        }
        $tags[$this->key] = $this->tags;
        return $tags;
    }

}
