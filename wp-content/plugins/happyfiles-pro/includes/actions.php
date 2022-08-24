<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Actions {
  public function __construct() {
		add_action( 'wp_ajax_happyfiles_sort_folders', [$this, 'sort_folders'] );
		add_action( 'wp_ajax_happyfiles_rename_folder', [$this, 'rename_folder'] );
		add_action( 'wp_ajax_happyfiles_delete_folder', [$this, 'delete_folder'] );
		add_action( 'wp_ajax_happyfiles_get_folders_to_delete', [$this, 'get_folders_to_delete'] );
	}

	/**
	 * Sort folders (ASC/DESC)
	 * 
	 * Update HappyFiles folder positions (termmeta)
	 */
	public function sort_folders() {
		$post_type = ! empty( $_POST['postType'] ) ? $_POST['postType'] : false;
		$taxonomy = ! empty( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : false;
		$order = ! empty( $_POST['order'] ) ? $_POST['order'] : false;
		$folders = Data::get_folders( $taxonomy, $post_type );

		// Remove default folders
		unset( $folders[0] ); // All
		unset( $folders[1] ); // Uncategorized

		if ( $order === 'ASC' ) {
			array_multisort( array_column( $folders, 'name' ), SORT_ASC, $folders );
		}

		else if ( $order === 'DESC' ) {
			array_multisort( array_column( $folders, 'name' ), SORT_DESC, $folders );
		}

		$folder_index_by_parent_id = [];

		// Update folder position (termmeta)
		foreach ( $folders as $folder ) {
			$folder_id = $folder->term_id;
			$parent = $folder->parent;
			
			$folder_index = isset( $folder_index_by_parent_id[$parent] ) ? intval( $folder_index_by_parent_id[$parent] ) + 1 : 0;
			$folder_index_by_parent_id[$parent] = $folder_index;

			update_term_meta( $folder_id, HAPPYFILES_POSITION, $folder_index );
		}

		wp_send_json_success( [
			'post_type' => $post_type,
			'taxonomy'  => $taxonomy,
			'folders'   => Data::get_folders( $taxonomy, $post_type ),
		] );
	}

	/**
	 * Rename folder
	 */
	public function rename_folder() {
		$taxonomy = ! empty( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : Data::$taxonomy;
    $name = isset( $_POST['name'] ) ? esc_attr( Helpers::sanitize_data( $_POST['name'] ) ) : false;
		$folder_id = isset( $_POST['folderId'] ) ? intval( Helpers::sanitize_data( $_POST['folderId'] ) ) : false;

    if ( ! $name || ! $folder_id ) {
      wp_send_json_error( [ 
				'error' => esc_html__( 'No folder name or term ID provided.', 'happyfiles' ),
				'POST'  => $_POST,
			] );
    }

    $slug = sanitize_title( $name );

    $rename_response = wp_update_term( $folder_id, $taxonomy, [
      'name' => $name,
      'slug' => $slug,
    ] );

    if ( is_wp_error( $rename_response ) ) {
      wp_send_json_error( [ 'error' => $rename_response->get_error_message() ] );
    }

    wp_send_json_success( [
      'name'    => $name,
      'slug'    => $slug,
    ] );
  }

	/**
	 * AJAX: Get all folders to delete folder
	 */
	public static function get_folders_to_delete() {
		$post_type = ! empty( $_POST['postType'] ) ? $_POST['postType'] : false;
		$taxonomy = ! empty( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : false;
		$folder_ids = ! empty( $_POST['folderIds'] ) ? $_POST['folderIds'] : [];
		$delete_subfolders = ! empty( $_POST['deleteSubFolders'] ) && $_POST['deleteSubFolders'] == 'true';
		$folder_ids_to_delete = [];

		foreach ( $folder_ids as $folder_id ) {
			$folder_ids_to_delete[] = intval( $folder_id );

			// Get all subfolders of folder ID (deep)
			if ( $delete_subfolders ) {
				$subfolders = self::get_subfolder_ids_deep( $folder_id, $taxonomy );
				$folder_ids_to_delete = array_merge( $folder_ids_to_delete, $subfolders );
			}
		}

		wp_send_json_success( [
			'deleteSubfolders'=> $delete_subfolders,
			'folderIds'       => $folder_ids_to_delete,
		] );
	}

	public static function get_subfolder_ids_deep( $folder_id, $taxonomy ) {
		$all_subfolder_ids = [];

		$subfolder_ids = get_term_children( $folder_id, $taxonomy );

		foreach ( $subfolder_ids as $subfolder_id ) {
			$all_subfolder_ids[] = $subfolder_id;
			
			$all_subfolder_ids = array_merge( $all_subfolder_ids, self::get_subfolder_ids_deep( $subfolder_id, $taxonomy ) );
		}

		return array_unique( $all_subfolder_ids );
	}

	/**
	 * Delete folder
	 */
	public static function delete_folder() {
		$post_type = ! empty( $_POST['postType'] ) ? $_POST['postType'] : false;
		$taxonomy = ! empty( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : false;
		$folder_id = ! empty( $_POST['folderId'] ) ? intval( $_POST['folderId'] ) : false;

		// Remove folder term ID from associated items (attachment, page, post, etc.) before deleting the folder
		$item_ids = get_posts( [
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => [
				[
					'taxonomy'         => $taxonomy,
					'terms'            => $folder_id,
					'field'            => 'term_id',
					'include_children' => false,
				],
			],
		] );

		foreach ( $item_ids as $item_id ) {
			wp_delete_object_term_relationships( $item_id, $taxonomy );
		}

		// Delete folder position (= term meta)
		$folder_position_deleted = delete_term_meta( 'term', $folder_id, HAPPYFILES_POSITION );

		// Delete folder (custom taxonomy term)
		$folder_deleted = wp_delete_term( $folder_id, $taxonomy );

		// Return: Request comes form subfolder delete
		if ( ! wp_doing_ajax() ) {
			return $folder_deleted;
		}

    if ( is_wp_error( $folder_deleted ) ) {
      wp_send_json_error( ['error' => $folder_deleted->get_error_message()] );
		}
		
		wp_send_json_success( [
			'post_type' => $post_type,
			'taxonomy'  => $taxonomy,
			'folderId'  => $folder_id,
			'folders'   => Data::get_folders( $taxonomy, $post_type ),
		] );
	}
}