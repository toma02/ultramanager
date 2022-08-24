<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Pro {
	public function __construct() {
		require_once HAPPYFILES_PATH . 'includes/pro/settings.php';
		new Pro_Settings();

		require_once HAPPYFILES_PATH . 'includes/pro/plugins.php';
		new Pro_Plugins();

		if ( get_option( 'happyfiles_featured_image_quick_edit', false ) ) {
			require_once HAPPYFILES_PATH . 'includes/pro/quick-edit.php';
			new Pro_Quick_Edit();
		}

		add_action( 'init', [$this, 'init_svg_support'] );
		add_action( 'init', [$this, 'init_filesizes'] );

		add_action( 'init', [$this, 'register_taxonomies'] );
		add_action( 'restrict_manage_posts', [$this, 'add_folder_toolbar_filter'] );

		// Bricks element: HappyFiles gallery
		add_action( 'init', function() {
			if ( class_exists( '\Bricks\Elements' ) ) {
				$file_path = HAPPYFILES_PATH . 'includes/integrations/bricks-gallery.php';
				if ( file_exists( $file_path ) ) {
					\Bricks\Elements::register_element( $file_path );
				}
			}
		}, 11 );
	}

	public function init_svg_support() {
		// Init SVG support & sanitization
		require_once HAPPYFILES_PATH . 'includes/pro/svg.php';
		new Pro_Svg();
	}

	public function init_filesizes() {
		// Init filesizes
		if ( Settings::$filesizes ) {
			require_once HAPPYFILES_PATH . 'includes/pro/filesizes.php';
			new Pro_Filesizes();
		}
	}

	/**
	 * Register HappyFiles custom taxonomies for enabled post types
	 */
	public function register_taxonomies() {
		foreach ( Settings::$enabled_post_types as $post_type ) {
			$taxonomy = "hf_cat_$post_type"; // Taxonomy max length: 32 characters

			register_taxonomy(
				$taxonomy,
				[$post_type],
				[
					'labels' => [
						'name'               => esc_html__( 'Folder', 'happyfiles' ),
						'singular_name'      => esc_html__( 'Folder', 'happyfiles' ),
						'add_new_item'       => esc_html__( 'Add new folder', 'happyfiles' ),
						'edit_item'          => esc_html__( 'Edit folder', 'happyfiles' ),
						'new_item'           => esc_html__( 'Add new folder', 'happyfiles' ),
						'search_items'       => esc_html__( 'Search folder', 'happyfiles' ),
						'not_found'          => esc_html__( 'Folder not found', 'happyfiles' ),
						'not_found_in_trash' => esc_html__( 'Folder not found in trash', 'happyfiles' ),
					],
					'public'                => false,
					'publicly_queryable'    => false,
					'hierarchical'          => true,
					'show_ui'               => true,
					'show_in_menu'          => false,
					'show_in_nav_menus'     => false,
					'show_in_quick_edit'    => false,
					'show_admin_column'     => false,
					'rewrite'               => false,
					'update_count_callback' => '_update_generic_term_count', // Update term count for attachments
				]
			);
		}
	}

	/**
	 * Toolbar filter select dropdown for post types
	 */
	public function add_folder_toolbar_filter() {
		global $pagenow, $typenow;

    if ( $pagenow !== 'edit.php' ) {
      return;
		}

		$post_type = get_post_type();

		if ( ! $post_type ) {
			$post_type = $typenow;
		}

		if ( ! in_array( $post_type, Settings::$enabled_post_types ) ) {
			return;
		}

		// Check if taxonomy exists
		wp_dropdown_categories( [
			'class'            => 'happyfiles-filter',
			'show_option_all'  => sprintf( esc_html__( 'All %1$s folders', 'happyfiles' ), ucwords( str_replace( '_', ' ', $post_type ) ) ),
			'show_option_none' => esc_html__( 'Uncategorized', 'happyfiles' ),
			'taxonomy'         => Data::$taxonomy,
			'name'             => Data::$taxonomy,
			'selected'         => Data::$open_id,
			'hierarchical'     => false,
			'hide_empty'       => false,
		] );
	}	
}