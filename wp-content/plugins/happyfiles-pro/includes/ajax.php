<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Ajax {
  public function __construct() {
		add_action( 'wp_ajax_happyfiles_create_folders', [$this, 'create_folders'] );
		add_action( 'wp_ajax_happyfiles_move_item_ids', [$this, 'move_item_ids'] );
		add_action( 'wp_ajax_happyfiles_move_folder', [$this, 'move_folder'] );
		add_action( 'wp_ajax_happyfiles_export_folders', [$this, 'export_folders'] );

		add_action( 'wp_ajax_happyfiles_save_open_id', [$this, 'save_open_id'] );
		add_action( 'wp_ajax_happyfiles_quick_inspect', [$this, 'quick_inspect'] );

		add_action( 'add_attachment', [$this, 'add_attachment'] );
	}

	/**
   * Create HappyFiles folders (custom taxonomy terms)
   *
   * @return array
   */
	public function create_folders() {
		$taxonomy = $_POST['taxonomy'];
		$post_type = $_POST['postType'];
		$folder_names = ! empty( $_POST['folderNames'] ) ? $_POST['folderNames'] : [];
		$parent_id = ! empty( $_POST['parentId'] ) ? intval( $_POST['parentId'] ) : 0;

		// Check if parent term exists
		if ( $parent_id ) {
			$parent_id = term_exists( $parent_id, $taxonomy ) ? $parent_id : 0;
		}

		foreach ( $folder_names as $folder_name ) {
			$new_folder = wp_insert_term( 
				esc_attr( trim( $folder_name ) ), 
				$taxonomy, 
				['parent' => $parent_id] 
			);

			if ( is_wp_error( $new_folder ) ) {
				wp_send_json_error( ['error' => $new_folder->get_error_message() ] );
			}
		}

		$folders = Data::get_folders( $taxonomy, $post_type );

		// Return new folders to add them to toolbar filter dropdown (grid view)
		$new_folders = array_slice( array_reverse( $folders ), 0, count( $folder_names ) );

    wp_send_json_success( [
			'taxonomy'     => $taxonomy,
      'folders'      => $folders,
			'newFolders'   => $new_folders,
			'parentId '    => $parent_id,
    ] );
  }

	/**
   * Move HappyFiles folder (via DnD)
   *
   * @return array
   */
	public function move_item_ids() {
		$taxonomy = $_POST['taxonomy'];
		$post_type = $_POST['postType'];
		$folder_id = ! empty( $_POST['folderId'] ) ? intval( $_POST['folderId'] ) : 0;
		$item_ids = ! empty( $_POST['itemIds'] ) ? $_POST['itemIds'] : [];
		$open_id = ! empty( $_POST['openId'] ) ? intval( $_POST['openId'] ) : 0;

		foreach ( $item_ids as $item_id ) {
			$folder_ids = wp_get_object_terms( $item_id, $taxonomy, ['fields' => 'ids'] );

			// Multiple folders per item
			if ( Settings::$multiple_folders ) {
				// Add to folder
				if ( $folder_id > 0 ) {
					$folder_ids[] = $folder_id;
				}

				// Remove from folder
				else {
					// Remove from all folders
					if ( Settings::$remove_from_all_folders ) {
						$folder_ids = [];
					} 
					
					// Remove from open folder
					else {
						$index = array_search( $open_id, $folder_ids );

						if ( is_int( $index ) ) {
							unset( $folder_ids[$index] );
						}
					}
				}
			} 
			
			// One folder per item (default)
			else {
				$folder_ids = $folder_id > 0 ? [$folder_id] : [];
			}

			// Delete terms
      if ( ! count( $folder_ids ) ) {
        wp_delete_object_term_relationships( $item_id, $taxonomy );
      }

			$reponse = wp_set_object_terms( $item_id, $folder_ids, $taxonomy, false );	

			if ( is_wp_error( $reponse ) ) {
        wp_send_json_error( ['error' => $reponse->get_error_message() ] );
      }
		}

		$folders = Data::get_folders( $taxonomy, $post_type );

		// Update term count
    foreach ( $folders as $folder ) {
      wp_update_term_count_now( [$folder->term_id], $taxonomy );
		}

    wp_send_json_success( [
			'taxonomy' => $taxonomy,
			'itemIds'  => $item_ids,
      'folders'  => $folders,
    ] );
	}

	/** 
	 * Move folder
	 * 
	 * - Update parent
	 * - Update folder positions (of all sibling folders)
	 */
	public function move_folder() {
		$post_type = ! empty( $_POST['postType'] ) ? $_POST['postType'] : false;
		$taxonomy = ! empty( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : false;
		$parent_id = ! empty( $_POST['parentId'] ) ? $_POST['parentId'] : 0;
		$folder_ids = isset( $_POST['folderIds'] ) ? $_POST['folderIds'] : [];

		foreach ( $folder_ids as $index => $folder_id ) {
			$parent_set = wp_update_term( $folder_id, $taxonomy, ['parent' => $parent_id] );
			$position_updated = update_term_meta( $folder_id, HAPPYFILES_POSITION, $index );

			if ( is_wp_error( $position_updated ) ) {
				wp_send_json_error( ['error' => $position_updated->term_meta_updated() ] );
			}
		}

		wp_send_json_success( [
			'folders'    => Data::get_folders( $taxonomy, $post_type ),
			'parent_id'  => $parent_id,
			'folder_ids' => $folder_ids,
			'post_type'  => $post_type,
			'taxonomy'   => $taxonomy,
		] );
	}

	/**
	 * Export HappyFiles folders by passed 'postTypes'
	 * 
	 * @since 1.7
	 */
	public function export_folders() {
		$post_types = $_POST['postTypes'];
		$folders = [];

		foreach ( $post_types as $post_type ) {
			$taxonomy = Data::get_taxonomy( $post_type );
			$folders = array_merge( $folders, Data::get_folders( $taxonomy, $post_type ) );
		}
		
		wp_send_json_success( $folders );
	}

	/**
	 * Save open folder ID as user metadata
	 * 
	 * Key:   Taxonomy slug
	 * Value: Taxonomy term ID
	 */
	public function save_open_id() {
		$user_id = get_current_user_id();

		$user_open_ids = get_user_meta( $user_id, 'happyfiles_open_ids', true );

		if ( ! $user_open_ids ) {
			$user_open_ids = [];
		}

		$taxonomy = ! empty( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : false;
		$folder_id = ! empty( $_POST['folderId'] ) ? intval( $_POST['folderId'] ) : false;

		if ( ! $taxonomy ) {
			wp_send_json_error( ['message' => 'Error: No taxonomy provided (save_open_id)'] );
		}

		if ( ! $folder_id ) {
			wp_send_json_error( ['message' => 'Error: No folder_id provided (save_open_id)'] );
		}

		$user_open_ids[$taxonomy] = $folder_id;

		$updated = update_user_meta( $user_id, 'happyfiles_open_ids', $user_open_ids );

		wp_send_json_success( [
			'update_user_meta' => $updated,
			'user_open_ids'    => $user_open_ids,
		] );
	}

	/**
   * Set folder for uploaded attachment
   *
   * @see Mixins.js: 'BeforeUpload'
   */
  public function add_attachment( $post_id ) {
    $folder_id = isset( $_REQUEST['hfOpenId'] ) ? intval( Helpers::sanitize_data( $_REQUEST['hfOpenId'] ) ) : 0;

    if ( is_numeric( $folder_id ) && $folder_id > 0 ) {
      wp_set_object_terms( $post_id, $folder_id, Data::$taxonomy, false );
    }
  }

	/**
	 * Quick inspect item: Get HappyFiles folders for passed post ID
	 */
	public function quick_inspect() {
		$taxonomy = ! empty( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : HAPPYFILES_TAXONOMY; 
		$post_id = ! empty( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$folders = wp_get_object_terms( $post_id, $taxonomy );

		wp_send_json_success( [
			'taxonomy' => $taxonomy,
			'folders'  => $folders,
		] );
	}
}