<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Data {
	public static $post_type = 'attachment';
	public static $taxonomy = 'happyfiles_category';
	public static $folders = [];
	public static $default_id = '';
	public static $open_id = -2;
	public static $load_plugin = false;

  public function __construct() {
		add_action( 'init', [$this, 'get_post_type'], 11 );
		add_action( 'init', [$this, 'get_taxonomy'], 12 );
		add_action( 'init', [$this, 'get_folders'], 13 );
		add_action( 'init', [$this, 'get_open_id'], 14 );

		// Run after 'Settings::get_settings()'
		add_action( 'init', [$this, 'load_plugin_check'], 21 );

		add_action( 'wp_ajax_happyfiles_get_open_id', [$this, 'ajax_get_open_id'] );
	}

	/**
	 * Load HappyFiles?
	 */
	public static function load_plugin_check() {
		// Logged-in user has no permission to use HappyFiles
		if ( User::$folder_access === 'none' ) {
			self::$load_plugin = false;
			
			return;
		}

		// Enabled post types found
		self::$load_plugin = in_array( self::$post_type, Settings::$enabled_post_types );
	}

	/**
	 * Load HappyFiles assets for page builder editing
	 *
	 */
	public static function is_page_builder() {
		// Bricks
		if ( isset( $_GET['bricks'] ) ) {
			return true;
		}

		// Elementor
		else if ( isset( $_GET['action'] ) && $_GET['action'] === 'elementor' ) {
			return true;
		}

		// Beaver
		else if ( isset( $_GET['fl_builder'] ) ) {
			return true;
		}

		// Brizy
		else if ( isset( $_GET['brizy-edit-iframe'] ) ) {
			return true;
		}

		// Oxygen
		else if ( isset( $_GET['ct_builder'] ) && ! isset( $_GET['oxygen_iframe'] ) ) {
			return true;
		}

		// Visual Composer (already triggered in 'post.php' check)
		// TODO: HF filter 'happyfiles_category' not set in filter_grid_view
		// else if ( isset( $_GET['vcv-action'] ) ) {
		// 	return false;
		// }

		// Divi
		else if ( isset( $_GET['et_fb'] ) ) {
			return true;
		}
	}

	/**
	 * Only return post type for screen HappyFiles is supposed to be loaded
	 * 
	 * No post type: No HappyFiles :)
	 */
	public static function get_post_type() {
		global $pagenow;

		$post_type = false;

		// Admin area
		if ( is_admin() ) {
			switch ( $pagenow ) {
				
				// Media library
				case 'upload.php':
					$post_type = 'attachment';
				break;

				// Add new {post_type}
				case 'post-new.php':
					$post_type = 'attachment';
				break;

				// Admin: Post type screen
				case 'edit.php':
					$post_type = empty( $_GET['post_type'] ) ? 'post' : $_GET['post_type'];
				break;

				// Admin: Edit single post
				case 'post.php':
					$post_type = 'attachment';
				break;

				// Admin: Plugins screen (@since 1.6)
				case 'plugins.php':
					// Folders for 'Plugins' are an enabled post type
					if ( in_array( 'plugins', Settings::$enabled_post_types ) ) {
						$post_type = 'plugins';
					}
				break;

			}
		}
		
		else if ( self::is_page_builder() ) {
			$post_type = 'attachment';
		}

		self::$post_type = $post_type;

		return $post_type;
	}

	/**
	 * Taxonomy for media library, edit single post: happyfiles_category
	 * Taxonomy for post type edit screen: 'hf_cat_{post_type}'
	 */
	public static function get_taxonomy( $post_type ) {
		$post_type = $post_type ? $post_type : self::$post_type;
		self::$taxonomy = 'happyfiles_category'; 
		
		if ( $post_type && $post_type !== 'attachment' ) {
			self::$taxonomy = "hf_cat_{$post_type}";
		}

		return self::$taxonomy;
	}

	/**
	 * Get all folders (custom taxonomy terms)
	 * 
	 * = Flat list of default (all, uncategorized) & custom folders
	 * 
	 * Default terms (0 not allowed):
	 * All:           -2 (term_id)
	 * Uncategorized: -1 (term_id)
	 */
	public static function get_folders( $taxonomy = '', $post_type = '' ) {
		$taxonomy = $taxonomy ? $taxonomy : self::$taxonomy;
		$post_type = $post_type ? $post_type : self::$post_type;

		self::$folders = [];
		
		// All items
		$folder_all = new \stdClass();

		$folder_all->slug      = 'all';
		$folder_all->term_id   = -2;
		$folder_all->id        = -2;
		$folder_all->position  = -2;
		$folder_all->value     = -2;
		$folder_all->level     = 0;
		$folder_all->parent    = 0;
		$folder_all->post_type = $post_type;

		if ( $post_type === 'plugins' ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			$installed_plugins = get_plugins();

			$folder_all->name     = esc_html__( 'All Plugins', 'happyfiles' );
			$folder_all->count    = count( $installed_plugins );
			$folder_all->item_ids = $installed_plugins;
		}

		else {
			$post_type_object = get_post_type_object( $post_type );
			$folder_all->name = $post_type === 'attachment' || ! is_object( $post_type_object ) ? esc_html__( 'All Files', 'happyfiles' ) : sprintf( esc_html__( 'All %s', 'happyfiles' ), $post_type_object->label );
			$folder_all->post_type_label = $post_type !== 'attachment' && is_object( $post_type_object ) ? $post_type_object->label : esc_html__( 'Files', 'happyfiles' );

			$all_folders = new \WP_Query( [
				'post_type'     => $post_type,
				'post_status'   => 'any',
				'post_per_page' => -1,
				'fields'        => 'ids',
			] );

			$folder_all->count     = $all_folders->found_posts;
			$folder_all->item_ids  = $all_folders->posts;
		}

		self::$folders[] = $folder_all;

		// Uncategorized items
		$folder_uncategorized = new \stdClass();

		$folder_uncategorized->slug     = 'uncategorized';
		$folder_uncategorized->term_id  = -1;
		$folder_uncategorized->id       = -1;
		$folder_uncategorized->position = -1;
		$folder_uncategorized->value    = -1;
		$folder_uncategorized->level    = 0;
		$folder_uncategorized->parent   = 0;
		$folder_uncategorized->name     = esc_html__( 'Uncategorized', 'happyfiles' );

		if ( $post_type === 'plugins' ) {
			$plugin_ids_from_db = get_option( Pro_Plugins::$plugin_ids_db_key, [] );
			$uncategorized_plugin_ids = [];

			foreach ( $installed_plugins as $filename => $plugin ) {
				$plugin_id = ! empty( $plugin_ids_from_db[$filename] ) ? $plugin_ids_from_db[$filename] : false;

				if ( ! $plugin_id ) {
					continue;
				}

				$plugin_terms = wp_get_object_terms( $plugin_id, self::$taxonomy );

				if ( is_array( $plugin_terms ) && ! count( $plugin_terms ) ) {
					$uncategorized_plugin_ids[] = $plugin_id;
				}
			}

			$folder_uncategorized->count    = count( $uncategorized_plugin_ids );
			$folder_uncategorized->item_ids = $uncategorized_plugin_ids;
		}

		else {
			$uncategorized_folders = new \WP_Query( [
				'post_type'     => $post_type,
				'post_status'   => 'any',
				'post_per_page' => -1,
				'fields'        => 'ids',
				'tax_query'     => [
					[
						'taxonomy' => $taxonomy,
						'operator' => 'NOT EXISTS',
					],
				],
			] );

			$folder_uncategorized->count    = $uncategorized_folders->found_posts;
			$folder_uncategorized->item_ids = $uncategorized_folders->posts;
		}

		self::$folders[] = $folder_uncategorized;

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return self::$folders;
		}

		// Attachment terms
    $folders = get_terms( [
      'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		] );

		// Running position index for folders without custom position (ensure that they are listed last)
		$folders_index = count( $folders );

		// Add folder position (termmeta)
		foreach ( $folders as $index => $folder ) {
			$folder_position = get_term_meta( $folder->term_id, HAPPYFILES_POSITION, true );
			
			// To use 'id' prop in Vue
			$folder->id = $folder->term_id;

			// Unescape term name (e.g. &amp;)
			$folder->name = wp_specialchars_decode( $folder->name );

			if ( is_numeric( $folder_position ) ) {
				$folders[$index]->position = intval( $folder_position );
			} else {
				$folders_index = $folders_index + 1;
				$folders[$index]->position = $folders_index;
			}

			// Set 'level' to indicate nesting with leading '-' in Gutenberg "HappyFiles - Gallery" block categories/folders select control
			$folders[$index]->level = count( get_ancestors( $folder->term_id, $taxonomy ) );
		}

		self::$folders = array_merge( self::$folders, $folders );

		return self::$folders;
	}

	public static function get_open_id() {
		$user_open_ids = get_user_meta( get_current_user_id(), 'happyfiles_open_ids', true );

		self::$open_id = ! empty( $user_open_ids[self::$taxonomy] ) ? $user_open_ids[self::$taxonomy] : -2;

		$default_id = get_option( 'happyfiles_default_open_folders', '' );

		self::$default_id = $default_id != '' ? $default_id : self::$open_id;

		// Uncategorized
		if ( self::$open_id == -1 ) {
			return self::$open_id;
		}

		// Folder doesn't exist: Fallback to "All folders"
		if ( ! term_exists( self::$open_id, self::$taxonomy ) ) {
			self::$open_id = -2;
		}

		return self::$open_id;
	}

	public function ajax_get_open_id() {
		$open_id = self::get_open_id();

		if ( self::$default_id != '' ) {
			$open_id = self::$default_id;
		}

		wp_send_json_success( ['openId' => $open_id] );
	}
}