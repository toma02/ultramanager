<?php

namespace FluentFormPro\Components\Post;

use FluentForm\App\Modules\Component\Component;
use FluentForm\App\Services\FormBuilder\Components\Select;
use FluentForm\Framework\Helpers\ArrayHelper;

/**
 * Populate Post Form on Post Selection Change
 */
class PopulatePostForm
{
    /**
     * Boot Class if post feed has post form type set to update
     */
    public function __construct()
    {
        add_action('fluentformpro_populate_post_form_values', [$this, 'boot'], 10, 3);
        add_action('wp_enqueue_scripts', function () {
            wp_register_script(
                'fluentformpro_post_update',
                FLUENTFORMPRO_DIR_URL . 'public/js/fluentformproPostUpdate.js',
                ['jquery'],
                FLUENTFORMPRO_VERSION,
                true
            );
        });
    }
    
    public function boot($form, $feed, $postType)
    {
        add_filter('fluentform/form_vars_for_JS', function ($formVars) {
            $formVars['rules']['__ff_update_post_id'] = [
                'required' => [
                    'value'   => true,
                    'message' => __('This field is required', 'fluentformpro')
                ]
            ];
            
            return $formVars;
        });
        
        wp_enqueue_script('fluentformpro_post_update');
        wp_localize_script('fluentformpro_post_update', 'fluentformpro_post_update_vars', array(
            'post_selector' => 'post-selector-' . time(),
            'nonce'         => wp_create_nonce('fluentformpro_post_update_nonce'),
        ));
    }
    

    /**
     * Push Post Selection field in the form
     *
     * @param $form
     * @param $postType
     *
     * @return void
     */
    public function renderPostSelectionField($data, $form)
    {
        $postType = ArrayHelper::get($data, 'settings.post_type_selection');

        $posts = apply_filters('fluentform_post_selection_posts_pre_data', [], $data, $form);

        if (!$posts) {
            $queryParams = [
                'post_type'      => $postType,
                'posts_per_page' => -1
            ];

            $extraParams = ArrayHelper::get($data, 'settings.post_extra_query_params');
            $extraParams = apply_filters('fluentform_post_selection_posts_query_args', $extraParams, $data, $form);
            if ($extraParams) {
                if (strpos($extraParams, '{') !== false) {
                    $extraParams = (new Component(wpFluentForm()))->replaceEditorSmartCodes($extraParams, $form);
                }

                parse_str($extraParams, $get_array);
                $queryParams = wp_parse_args($get_array, $queryParams);
            }

            $posts = query_posts($queryParams);
            wp_reset_query();
        }

        $formattedOptions = [];

        $postValueBy = apply_filters('fluentform_post_selection_value_by', 'ID', $form);
        $labelBy = apply_filters('fluentform_post_selection_label_by', 'post_title', $form);

        foreach ($posts as $post) {
            $formattedOptions[] = [
                'label'      => $post->{$labelBy},
                'value'      => $post->{$postValueBy},
                'calc_value' => ''
            ];
        }

        $data['settings']['advanced_options'] = $formattedOptions;

        (new Select())->compile($data, $form);
    }

    /**
     * Get JSON Post Data
     * @return void
     */
    public function getPostDetails()
    {
        \FluentForm\App\Modules\Acl\Acl::verifyNonce('fluentformpro_post_update_nonce');
        $postId = intval($_REQUEST['post_id']);
        if (!$postId) {
            wp_send_json([
                'message' => __('Please select a Post', 'fluentformpro')
            ], 423);
        }
        $post = get_post($postId, 'ARRAY_A');
        $selectedData = ArrayHelper::only($post,
            array('post_content', 'post_excerpt', 'post_category', 'tags_input', 'post_title', 'post_type'));
        $selectedData['thumbnail'] = get_the_post_thumbnail_url($postId);
    
        $taxonomiesData = [];
        $taxonomies = get_object_taxonomies($post['post_type']);
        foreach ($taxonomies as $taxonomy) {
            $taxonomiesData[$taxonomy] = $this->formattedTerms($postId, $taxonomy);
        }
        wp_send_json_success([
            'post'     => $selectedData,
            'taxonomy' => $taxonomiesData
        ]);
    }
    
    private function formattedTerms($postId, $taxonomy)
    {
        $terms = get_the_terms($postId, $taxonomy);
        $formattedTaxonomies = [];
        if (empty($terms)) {
            return $formattedTaxonomies;
        }
        foreach ($terms as $term) {
            $formattedTaxonomies[] = [
                'value' => $term->term_id,
                'label' => $term->name
            ];
        }
        return $formattedTaxonomies;
    }
}
