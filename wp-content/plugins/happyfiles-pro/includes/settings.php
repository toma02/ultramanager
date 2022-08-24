<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Settings {
	public static $multiple_folders = false;
	public static $remove_from_all_folders = false;
	public static $media_library_infinite_scrolling = false;
	public static $filesizes = false;

	public static $enabled_post_types = [];
	public static $folder_access = false;
	public static $svg_support = false;

	public function __construct() {
		add_filter( 'plugin_action_links_' . HAPPYFILES_BASENAME, [$this, 'plugin_settings_link'] );

		add_action( 'admin_init', [$this, 'register_settings'] );
		add_action( 'init', [$this, 'get_settings'], 10 );

		add_action( 'admin_menu', [$this, 'settings_menu'] );
		add_action( 'admin_enqueue_scripts', [$this, 'enqueue_settings_assets'] );
		add_action( 'wp_ajax_happyfiles_delete_plugin_data', [$this, 'delete_plugin_data'] );
	}
	
	public function plugin_settings_link( $actions ) {
		$actions[] = '<a href="' . admin_url( 'options-general.php?page=happyfiles_settings' ) . '">' . esc_html__( 'Settings', 'happyfiles' ) . '</a>';
		
		return $actions;
	}

	public function enqueue_settings_assets() {
		if ( get_current_screen() && get_current_screen()->base === 'settings_page_happyfiles_settings' ) {
			wp_enqueue_style( 'happyfiles-settings', HAPPYFILES_ASSETS_URL . '/css/settings.min.css', [], filemtime( HAPPYFILES_ASSETS_PATH .'/css/settings.min.css' ) );
			wp_enqueue_script( 'happyfiles-settings', HAPPYFILES_ASSETS_URL . '/js/settings.min.js', [], filemtime( HAPPYFILES_ASSETS_PATH .'/js/settings.min.js' ) );
			
			// HappyFiles - Settings 
			wp_localize_script( 'happyfiles-settings', 'HappyFiles', [
				'debug'   => isset( $_GET['debug'] ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => [
					'deletePluginData' => esc_html__( 'Are you sure you want to delete all your HappyFiles folders, associations, and settings?', 'happyfiles' ),
				]
			] );
		}
	}

	public function register_settings() {
		// General
		register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_default_open_folders' );
		register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_multiple_folders' );
		register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_remove_from_all_folders' );
		register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_media_library_infinite_scrolling' );
		

		// Permissions (per user role)
		register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_folder_access' );
  }

	/**
	 * Run in 'init' to use 'apply_filters'
	 */
	public function get_settings() {
		// General
		self::$enabled_post_types = ['attachment'];
		$enabled_post_types = get_option( 'happyfiles_post_types', [] );

		if ( is_array( $enabled_post_types ) ) {
			self::$enabled_post_types = array_merge( self::$enabled_post_types, $enabled_post_types );
		}

		self::$enabled_post_types = apply_filters( 'happyfiles/post_types', self::$enabled_post_types );

		self::$multiple_folders = get_option( 'happyfiles_multiple_folders', false );
		self::$remove_from_all_folders = get_option( 'happyfiles_remove_from_all_folders', false );
		self::$media_library_infinite_scrolling = get_option( 'happyfiles_media_library_infinite_scrolling', false );
		self::$filesizes = get_option( 'happyfiles_filesizes', false );

		if ( self::$media_library_infinite_scrolling ) {
			add_filter( 'media_library_infinite_scrolling', '__return_true' );
		}
	}

	/**
	 * Delete all HappyFiles plugin data (folders, options entries, etc.)
	 */
	public function delete_plugin_data() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$folders = get_terms( HAPPYFILES_TAXONOMY, ['hide_empty' => false] );

		// PRO: Get HappyFiles folders of all post types (term prefix: 'hf_cat_')
		global $wpdb;

		$happyfiles_folders = $wpdb->get_results(
			"SELECT * FROM " . $wpdb->term_taxonomy . "
			LEFT JOIN  " . $wpdb->terms . "
			ON  " . $wpdb->term_taxonomy . ".term_id =  " . $wpdb->terms . ".term_id
			WHERE " . $wpdb->term_taxonomy . ".taxonomy LIKE 'hf_cat_%'
			ORDER BY parent ASC"
		);

		$folders = array_merge( $folders, $happyfiles_folders );

		$folders_deleted = [];

		foreach ( $folders as $folder ) {
			$folder_id = intval( $folder->term_id );

			// Delete position (termmeta)
			delete_term_meta( 'term', $folder_id, HAPPYFILES_POSITION );

			// Delete taxonomy term (attachment)
			$folder_deleted = wp_delete_term( $folder_id, $folder->taxonomy );

			$folders_deleted[$folder_id] = $folder_deleted;
		}

		// Delete db options entries (starting with 'happyfiles_')
		$all_options = wp_load_alloptions();
		$happyfiles_options = [];
		$options_deleted = [];

		foreach ( array_keys( $all_options ) as $option_key ) {
			if ( substr( $option_key, 0, strlen( 'happyfiles_' ) ) === 'happyfiles_' ) {
				$happyfiles_options[] = $option_key;
				$option_deleted = delete_option( $option_key );

				if ( $option_deleted ) {
					$options_deleted[] = $option_key;
				}
			}
		}

		$message = '';

		if ( count( $folders_deleted ) || count( $options_deleted ) ) {
			$message = esc_html__( 'HappyFiles data has been deleted.', 'happyfiles' );
		}

		else if ( ! is_array( $folders ) || ! count( $folders ) ) {
			$message = esc_html__( 'No HappyFiles data to delete.', 'happyfiles' );
		}

		// STEP: Delete PRO data
		do_action( 'happyfiles/delete_plugin_data' );

		wp_send_json_success( [
			'folders'         => $folders,
			'foldersDeleted'  => $folders_deleted,
			'options'         => $happyfiles_options,
			'optionsDeleted'  => $options_deleted,
			'message'         => $message,
		] );
	}

	/**
	 * HappyFiles settings
	 */
	public function settings_menu() {
    add_options_page(
      'HappyFiles ' . esc_html__( 'Settings', 'happyfiles' ),
      'HappyFiles',
      'manage_options',
      HAPPYFILES_SETTINGS_GROUP,
      function() {
				require_once HAPPYFILES_PATH . 'includes/admin/settings.php';
			}
    );
  }
}