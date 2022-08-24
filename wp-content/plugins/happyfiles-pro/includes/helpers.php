<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Helpers {
	/**
   * Sanitize all $_GET, $_POST, $_REQUEST, $_FILE input data before processing
   *
   * @param $data (array|string)
	 * 
   * @return mixed
   */
  public static function sanitize_data( $data ) {
		// Sanitize string
		if ( is_string( $data ) ) {
			$data = sanitize_text_field( $data );
		}

		// Sanitize each element individually
		else if ( is_array( $data ) ) {
			foreach ( $data as $key => &$value ) {
				if ( is_array( $value ) ) {
					$value = sanitize_data( $value );
				} 
				
				else {
					$value = sanitize_text_field( $value );
				}
			}
		}

		return $data;
	}

	public static function get_taxonomy_by( $type, $data ) {
		// Return taxonomy name for files if not in wp-admin (e.g. page builder frontend editing)
		if ( ! is_admin() || ! $data ) {
			return HAPPYFILES_TAXONOMY;
		}

		switch ( $type ) {
			case 'ID':
				$taxonomy_obj = get_term( $data );

				return is_object( $taxonomy_obj ) ? $taxonomy_obj->taxonomy : HAPPYFILES_TAXONOMY;
			break;

			case 'post_type':
				return $data === 'attachment' ? HAPPYFILES_TAXONOMY : 'hf_cat_' . $data; // Max. tax length of 32 characters
			break;

			default:
				return HAPPYFILES_TAXONOMY;
			break;
		}
	}
}