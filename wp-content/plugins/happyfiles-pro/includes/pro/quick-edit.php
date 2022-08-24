<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Pro_Quick_Edit {
	// Add CSS class .hide by default to hide featured image preview column
	public static $column_name = 'hf_featured_image hide';

	public function __construct() {
		add_action( 'admin_enqueue_scripts', [$this, 'admin_enqueue_scripts'] );

		// Enable "Featured Image" admin column for all post types with 'thumbnail' support
		if ( get_option( 'happyfiles_featured_image_column', false ) ) {
			self::$column_name = 'hf_featured_image';
		}

		$registered_post_types = get_post_types( [] );
		$post_types_with_thumbnail_support = [];
		
		foreach ( $registered_post_types as $post_type ) {
			if ( post_type_supports( $post_type, 'thumbnail' ) ) {
				$post_types_with_thumbnail_support[] = $post_type;

				add_filter( "manage_{$post_type}_posts_columns", [$this, 'manage_posts_columns'], 999 );
				add_action( "manage_{$post_type}_posts_custom_column", [$this, 'manage_posts_custom_column'], 999, 2 );
			}
		}

		add_action( 'quick_edit_custom_box',  [$this, 'quick_edit_featured_image'], 10, 2 );
		add_action( 'admin_footer', [$this, 'quick_edit_set_featured_image'] );
	}

  public function admin_enqueue_scripts() {
		global $current_screen;

		if ( $current_screen->base !== 'edit' ) {
			return;
		}

    // Enqueue media uploader to quick edit post thumbnail
    if ( ! did_action( 'wp_enqueue_media' ) ) {
      wp_enqueue_media();
    }
  }

	/**
   * Posts columns: Add thumbnail to table head
   */
  public function manage_posts_columns( $columns ) {
    $columns[self::$column_name] = esc_html__( 'Featured Image' );
    
    return $columns;
  }

	/**
   * Posts columns: Add thumbnail to table body
   */
  public function manage_posts_custom_column( $column, $id ) { 
    if ( $column === self::$column_name ) {
      $post_thumbnail_id = get_post_thumbnail_id( $id );

      echo get_the_post_thumbnail( $id, [40, 40], ['data-id' => $post_thumbnail_id] );
    }
  }

	/**
	 * Print inputs for custom columns (hf_featured_image) when quick editing
	 */
	public function quick_edit_featured_image( $column_name, $post_type ) {
		if ( ! post_type_supports( $post_type, 'thumbnail' ) ) {
			return;
		}

		if ( $column_name !== self::$column_name ) {
      return;
    }
		
    // Add #hf_featured_image to use it in JavaScript in CSS
    echo 
    '<fieldset id="hf_featured_image" class="inline-edit-col-left">
      <div class="inline-edit-col">
        <div class="hf_featured_image_wrapper">
					<div class="hf_featured_image"></div>

					<div class="hf_featured_image_actions">
						<a href="#" class="button button-small hf_select_featured_image">' . esc_html__( 'Select featured image', 'happyfiles' ) . '</a>
						<a href="#" class="button button-small hf_remove_featured_image">' . esc_html__( 'Remove featured image', 'happyfiles' ) . '</a>
						<input type="hidden" name="_thumbnail_id" value="" />
					</div>
        </div>
      </div>
    </fieldset>';
  }

	public function quick_edit_set_featured_image() {
    global $current_screen;
  
    if ( $current_screen->base !== 'edit' ) {
      return;
    } 
		?>

		<style>
			.column-hf_featured_image.hide {
				display: none !important;
			}
			
			.column-hf_featured_image {
				width: 10%;
			}

			.hf_featured_image_wrapper {
				display: flex;
				margin: 10px 0 0;
			}

			.hf_featured_image_wrapper .hf_featured_image img {
				display: block;
				max-height: 60px;
				width: auto;
				margin-right: 10px;
			}

			.hf_featured_image_actions {
				display: flex;
				flex-direction: column;
				align-items: flex-start;
				justify-content: space-between;
			}
		</style>
    
    <script>
    jQuery(function($) {
      $('body').on('click', '.hf_select_featured_image', function(e) {
        e.preventDefault()
        var custom_uploader = wp.media({
          title: 'Set featured image',
          library : { type : 'image' },
          button: { text: 'Set featured image' }
        }).on('select', function() {
          var attachment = custom_uploader.state().get('selection').first().toJSON()
					var featuredImageWrapper = e.target.closest('.hf_featured_image_wrapper')
					var featuredImage = featuredImageWrapper.querySelector('img')

					// Update featured image previde in quick edit
					if (featuredImage) {
						featuredImage.setAttribute('src', attachment.url)
					} 
					
					// Add feature image HTML to quick edit
					else {
						featuredImage = document.createElement('img')
						
						featuredImage.setAttribute('src', attachment.url)
						featuredImage.setAttribute('width', 150)
						featuredImage.setAttribute('height', 150)

						featuredImageWrapper.querySelector('.hf_featured_image').appendChild(featuredImage)
					}

					// Set featured image ID in input
					e.target.parentNode.querySelector('input').value = attachment.id

					// Show Remove button
					e.target.parentNode.querySelector('.hf_remove_featured_image').removeAttribute('style')
        }).open()
      })

			// Click: Remove button
      $('body').on('click', '.hf_remove_featured_image', function(e) {
				e.preventDefault()

				// Remove featured image preview from quick edit
				e.target.closest('.hf_featured_image_wrapper').querySelector('img').remove()

				// Hide button
        e.target.style.display = 'none'

				// Set featured image ID to -1 in input
				e.target.nextSibling.nextSibling.value = -1
        
				return false
      })

      var $wp_inline_edit = inlineEditPost.edit
      
      inlineEditPost.edit = function(id) {
        $wp_inline_edit.apply(this, arguments)
        var $post_id = 0

        if (typeof( id ) == 'object') { 
          $post_id = parseInt(this.getId(id))
        }

        if ($post_id > 0) {
          var $edit_row = $('#edit-' + $post_id)
          var $post_row = $('#post-' + $post_id)
          var $featured_image = $( '.column-hf_featured_image', $post_row ).html()
          var $featured_image_id = $( '.column-hf_featured_image', $post_row ).find('img').attr('data-id')

          if ($featured_image_id == undefined) {
						$( '.hf_remove_featured_image', $edit_row ).hide() // Remove button
          } else {
						$( ':input[name="_thumbnail_id"]', $edit_row ).val( $featured_image_id ) // ID
						$( '.hf_featured_image', $edit_row ).html( $featured_image ) // Image HTML
						$( '.hf_remove_featured_image', $edit_row ).show() // Remove button
					}
        }
      }
    })
    </script>
  <?php
  }
}