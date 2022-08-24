<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Admin {
  public function __construct() {
		add_action( 'admin_enqueue_scripts', [$this, 'admin_enqueue_scripts'] );

		add_action( 'wp_ajax_happyfiles_hide_import_folders_notification', [$this, 'hide_import_folders_notification'] );
	}

	public function admin_enqueue_scripts() {
		wp_enqueue_script( 'happyfiles-admin', HAPPYFILES_ASSETS_URL . '/js/admin.min.js', [], filemtime( HAPPYFILES_ASSETS_PATH .'/js/admin.min.js' ) );

		wp_localize_script( 'happyfiles-admin', 'HappyFilesAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		] );
	}

	public function hide_import_folders_notification() {
		$updated = update_option( 'happyfiles_hide_import_folders_notification', true );
	
		wp_send_json_success( ['updated' => $updated] );
	}
}