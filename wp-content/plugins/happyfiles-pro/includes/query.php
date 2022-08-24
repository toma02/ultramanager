<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Query {
  public function __construct() {
		add_action( 'pre_get_posts', [$this, 'filter_list_view'] );
		add_filter( 'ajax_query_attachments_args', [$this, 'filter_grid_view'] );
	}

	/**
	 * List View: Set open folder ID on page load
	 */
	public function filter_list_view( $query ) {
		if ( ! Data::$load_plugin ) {
			return;
		}

		if ( ! $query->is_main_query() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		$taxonomy = Helpers::get_taxonomy_by( 'post_type', $post_type );

		if ( ! $taxonomy ) {
			return;
		}

		unset( $query->query_vars[$taxonomy] );

		// Specific folder requested
		if ( ! empty( $_GET[$taxonomy] ) ) {
			$open_id = intval( $_GET[$taxonomy] );
		}
		
		// Fallback: Get open ID from user meta data
		else {
			$user_open_ids = get_user_meta( get_current_user_id(), 'happyfiles_open_ids', true );
			$open_id = ! empty( $user_open_ids[$taxonomy] ) ? intval( $user_open_ids[$taxonomy] ) : false;
		}

		// On page load: Default open folder
		// Determine by 'term_slug' in URL, which HF sends with every folder switch
		if ( ! isset( $_GET[$taxonomy] ) ) {
			$open_id = intval( Data::$default_id );
		}

		// Return: No HappyFiles folder OR 'All items' ID (-2) selected
		if ( ! $open_id || $open_id === -2 ) {
			return;
		}

		// Return: HappyFiles folder ID no longer exists
		if ( $open_id > 0 && ! term_exists( $open_id, $taxonomy ) ) {
			return;
		}

		$tax_query = $query->get( 'tax_query' );

		if ( ! is_array( $tax_query ) ) {
			$tax_query = [];
		}

		// Uncategorized items
		if ( $open_id == -1 ) {
			$tax_query[] = [
				'taxonomy' => $taxonomy,
				'operator' => 'NOT EXISTS',
			];

			$query->set( 'tax_query', $tax_query );
		}

		// HappyFiles taxonomy term
		else {
			$tax_query[] = [
				'taxonomy' 			   => $taxonomy,
				'field' 			     => 'term_id',
				'terms' 			     => $open_id,
				'include_children' => false,
			];

			$query->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * Filter grid view (media library)
	 */
	public function filter_grid_view( $query = [] ) {
		// Runs before 'init' so Data::$load_plugin check doesn't work here
		if ( User::$folder_access === 'none' ) {
			return $query;
		}

		$taxonomy = Data::$taxonomy;
		$open_id = Data::$open_id;

		// On page load: Default open folder
		// Determine by 'ignore' param, which HF sends with every folder switch)
		if ( ! isset( $_REQUEST['query']['ignore'] ) & Data::$default_id !== '' ) {
			$open_id = Data::$default_id;
		}

		$folder_id = ! empty( $query[$taxonomy] ) ? intval( $query[$taxonomy] ) : intval( $open_id );

		// All items
		if ( ! $folder_id || $folder_id < -1 ) {
			return $query;
		}

    // Subsequent grid view AJAX calls
		if ( isset( $query['tax_query'] ) && is_array( $query['tax_query'] ) ) {
			$query['tax_query']['relation'] = 'AND';
		} else {
			$query['tax_query'] = ['relation' => 'AND'];
		}

		// Uncategorized
		if ( $folder_id == -1 ) {
			$query['tax_query'][] = [
				'taxonomy' => $taxonomy,
				'operator' => 'NOT EXISTS',
			];
		}

		// Open ID
		else {
			$query['tax_query'][] = [
				'taxonomy'         => $taxonomy,
				'terms'            => $folder_id,
				'field'            => 'id',
				'include_children' => false,
			];
		}

		// $query['post_mime_type'] = 'image/gif'; // TODO NEXT

		unset( $query[$taxonomy] );

		return $query;
  }
}