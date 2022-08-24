<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Pro_Filesizes {
	public static $filesize_column    = 'hf_filesizes';
	public static $filesize_post_meta = '_happyfiles_filesizes';

	public function __construct() {
		// Add admin column: File size
		add_filter( 'manage_upload_columns', [$this, 'filesize_column_header'] );
		add_action( 'manage_media_custom_column', [$this, 'filesize_column_data'], 10, 2 );
		add_filter( 'manage_upload_sortable_columns', [$this, 'filesize_column_sortable'] );

		// Sort by file size
		add_action( 'pre_get_posts', [$this, 'list_view_orderby_filesize'] );
		add_filter( 'ajax_query_attachments_args', [$this, 'grid_view_orderby_filesize'] );

		add_action( 'wp_ajax_happyfiles_regenerate_filesize', [$this, 'regenerate_filesize' ] );
		add_action( 'add_attachment', [$this, 'attachment_uploaded'] );

		add_action( 'happyfiles/delete_plugin_data', [$this, 'delete_filesizes'] );
	}

	public function filesize_column_header( $columns ) {
    $columns[self::$filesize_column] = esc_html__( 'File size', 'happyfiles' );
    
		return $columns;
	}
	
	public function filesize_column_data( $column_name, $media_item ) {
		if ( $column_name !== self::$filesize_column ) {
			return;
		}

		$filesize = filesize( get_attached_file( $media_item ) );
		$filesize = size_format( $filesize, 1 );

		echo $filesize;
	}

	public function filesize_column_sortable( $columns ) {
		$columns[self::$filesize_column] = self::$filesize_column;

		return $columns;	
	}

	public function list_view_orderby_filesize( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( $orderby === self::$filesize_column ) {
			$meta_query = [
				'relation' => 'OR',
				[
					'key' => self::$filesize_post_meta,
				],
				[
					'key' => self::$filesize_post_meta,
					'compare' => 'NOT EXISTS',
				],
			];

			$query->set( 'meta_query', $meta_query );
			$query->set('orderby', 'meta_value_num' );
		}
	}

	public function grid_view_orderby_filesize( $query = [] ) {
		$meta_query = [
			'relation' => 'OR',
			[
				'key'     => self::$filesize_post_meta,
				'compare' => 'EXISTS',
			],
			[
				'key'     => self::$filesize_post_meta,
				'compare' => 'NOT EXISTS',
			],
		];

		if ( empty( $query['meta_query'] ) ) {
			$query['meta_query'] = $meta_query;
		} else {
			$query['meta_query'][] = $meta_query;
		}

		$query['orderby'] = 'meta_value_num';

		// Not sure why, but have to reverse order
		if ( $query['order'] === 'ASC' ) {
			$query['order'] = 'DESC';
		} else if ( $query['order'] === 'DESC' ) {
			$query['order'] = 'ASC';
		}

		return $query;
	}

	public function regenerate_filesize() {
		$attachment_id = ! empty( $_POST['attachmentId'] ) ? intval( $_POST['attachmentId'] ) : false;
		
		$response = self::generate_filesize_for_attachment_id( $attachment_id );

		wp_send_json_success( $response );
	}

	/** 
	 * Generate file size and saev as post meta (for orderby filesize)
	 */ 
	public static function generate_filesize_for_attachment_id( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		$filesize = filesize( $file );
		$generated = update_post_meta( $attachment_id, self::$filesize_post_meta, intval( $filesize ) );

		return [
			'id'          => $attachment_id,
			'file'        => $file,
			'name'        => basename( $file ),
			'filesize'    => size_format( $filesize, 1 ),
			'filesizeraw' => $filesize,
			'generated'   => $generated,
		];
	}

	/**
	 * Attachment uploaded: Generate file size
	 */
	public function attachment_uploaded( $attachment_id ) {
		if ( Settings::$filesizes ) {
			self::generate_filesize_for_attachment_id( $attachment_id );
		}
	}

	/**
	 * Delete post meta filesize (when plugin data is deleted)
	 */
	public function delete_filesizes() {
		$attachment_ids = get_posts( [
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'order'          => 'ASC',
		] );

		foreach ( $attachment_ids as $attachment_id ) {
			delete_post_meta( $attachment_id, self::$filesize_post_meta );
		}
	}
}