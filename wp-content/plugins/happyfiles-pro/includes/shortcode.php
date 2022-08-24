<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Shortcode {
	public function __construct() {
		add_action( 'init', [$this, 'register_block'] );
		add_action( 'admin_enqueue_scripts', [$this, 'enqueue_gallery_styles'] );
		add_action( 'wp_enqueue_scripts', [$this, 'enqueue_gallery_styles'] );

		add_filter( 'block_categories_all', [$this, 'block_categories_all'] );

		add_shortcode( 'happyfiles_gallery', [$this, 'render_shortcode'] );
	}

	/**
	 * Load gallery styles alongside HappyFiles (gallery initial render, etc.)
	 */
	public function enqueue_gallery_styles() {
		wp_register_style( 'happyfiles-gallery', HAPPYFILES_ASSETS_URL . '/css/gallery.min.css', [], filemtime( HAPPYFILES_ASSETS_PATH .'/css/gallery.min.css' ) );

		if ( Data::$load_plugin ) {
			wp_enqueue_style( 'happyfiles-gallery' );
		}
	}

	/**
	 * Block category: 'HappyFiles'
	 */
	public function block_categories_all( $categories ) {
		$category_slugs = wp_list_pluck( $categories, 'slug' );

    if ( in_array( 'happyfiles', $category_slugs, true ) ) {
			return $categories;
		}

		return array_merge(
			$categories,
			[
				[
					'slug'  => 'happyfiles',
					'title' => 'HappyFiles',
					'icon'  => null,
				],
			]
    );
	}

	/**
	 * Register block: 'happyfiles/gallery'
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// https://developer.wordpress.org/block-editor/tutorials/javascript/js-build-setup/#dependency-management
		// https://make.wordpress.org/core/2021/06/29/block-based-widgets-editor-in-wordpress-5-8/#comment-41559
		$dependencies = [
			// 'happyfiles', 
			'wp-blocks',
			'wp-block-editor',
			// 'wp-element',
			'wp-server-side-render',
			'wp-components',
		];

		wp_register_script( 'happyfiles-block-gallery', HAPPYFILES_ASSETS_URL . '/js/blocks/gallery.js', $dependencies, filemtime( HAPPYFILES_ASSETS_PATH .'/js/blocks/gallery.js' ) );

		// Inline style to fix Gutenberg select[multiple] default styling issue
		// https://github.com/WordPress/gutenberg/issues/27166
		if ( is_admin() ) {
			wp_add_inline_style( 'wp-block-library', '
				.happyfiles-select-multiple label { font-size: inherit !important; }
				.happyfiles-select-multiple select[multiple] { height: auto !important; padding: 0 !important; }
				.happyfiles-select-multiple select[multiple] option { white-space: normal !important; }
				.happyfiles-select-multiple svg { display: none !important; }
			' );
		}

		$attachment_folders = self::get_folders_ordered( HAPPYFILES_TAXONOMY, 'attachment' );

		wp_localize_script( 'happyfiles-block-gallery', 'HappyFilesGallery', [
			'folders' => $attachment_folders,

			'i18n'  => [
				'block' => [
					'gallery' => [
						'title'              => 'HappyFiles ' . esc_html__( 'Gallery', 'happyfiles' ),
						'description'        => esc_html__( 'Display your images in a beautiful gallery.', 'happyfiles' ),
						'settingsTitle'      => esc_html__( 'Gallery settings', 'happyfiles' ),
						'columns'            => esc_html__( 'Columns', 'happyfiles' ),
						'maxItems'           => esc_html__( 'Max items', 'happyfiles' ),
						'height'             => esc_html__( 'Height', 'happyfiles' ),
						'spacing'            => esc_html__( 'Spacing', 'happyfiles' ),
						'folders'            => esc_html__( 'Folders', 'happyfiles' ),
						'foldersHelp'        => esc_html__( 'Hold down CTRL/CMD to select multiple folders.', 'happyfiles' ),
						'imageSize'          => esc_html__( 'Image size', 'happyfiles' ),
						'linkTo'             => esc_html__( 'Link to', 'happyfiles' ),
						'orderBy'            => esc_html__( 'Order by', 'happyfiles' ),
						'order'              => esc_html__( 'Order', 'happyfiles' ),
						'noCrop'             => esc_html__( 'No crop', 'happyfiles' ) . ' (masonry)',
						'includeSubfolder'   => esc_html__( 'Include subfolder', 'happyfiles' ),
						'caption'            => esc_html__( 'Caption', 'happyfiles' ),
						'lightbox'           => esc_html__( 'Lightbox', 'happyfiles' ),
						'lightboxFullscreen' => esc_html__( 'Lightbox', 'happyfiles' ) . ': ' . esc_html__( 'Fullscreen', 'happyfiles' ),
						'lightboxThumbnails' => esc_html__( 'Lightbox', 'happyfiles' ) . ': ' . esc_html__( 'Thumbnails', 'happyfiles' ),
						'lightboxZoom'       => esc_html__( 'Lightbox', 'happyfiles' ) . ': ' . esc_html__( 'Zoom', 'happyfiles' ),
					],
				],
			],

			// Use localized WordPress string for gallery select options
			'options' => [
				'orderBy' => [
					'none'     => __( 'None' ),
					'ID'       => __( 'ID' ),
					'author'   => __( 'Author' ),
					'title'    => __( 'Title' ),
					'name'     => __( 'Name' ),
					'type'     => __( 'Type' ),
					'date'     => __( 'Date' ),
					'modified' => __( 'Modified' ),
					'parent'   => __( 'Parent' ),
					'rand'     => __( 'Random' ),
				],

				'order' => [
					'ASC'  => __( 'Ascending' ),
					'DESC' => __( 'Descending' ),
				],

				'imageSizes' => [
					'thumbnail' => esc_html__( 'Thumbnail', 'happyfiles' ),
					'medium'    => esc_html__( 'Medium', 'happyfiles' ),
					'large'     => esc_html__( 'Large', 'happyfiles' ),
					'full'      => esc_html__( 'Full size', 'happyfiles' ),
				],
	
				'linkTo' => [
					'attachment' => esc_html__( 'Attachment page', 'happyfiles' ),
					'media'      => esc_html__( 'Media file', 'happyfiles' ),
					'none'       => esc_html__( 'None', 'happyfiles' ),
				],
			]
		] );

		register_block_type( 'happyfiles/gallery', [
			'editor_script'   => 'happyfiles-block-gallery',
			'render_callback' => [$this, 'render_block'],
			'attributes'      => [
				'categories' => [
					'type'    => 'array',
					'default' => [],
				],
				
				'columns' => [
					'type'    => 'number',
					'default' => 3,
				],

				'max' => [
					'type'    => 'number',
				],

				'height' => [
					'type'    => 'string',
				],
				
				'spacing' => [
					'type'    => 'string',
				],

				'orderBy' => [
					'type' => 'string',
					'default' => 'date',
				],

				'order' => [
					'type' => 'string',
					'default' => 'DESC',
				],

				'imageSize' => [
					'type'    => 'string',
					'default' => 'medium',
				],

				'linkTo' => [
					'type'    => 'string',
					'default' => 'none',
				],

				'noCrop' => [
					'type'    => 'boolean',
					'default' => false,
				],

				'includeChildren' => [
					'type'    => 'boolean',
					'default' => false,
				],

				'caption' => [
					'type'    => 'boolean',
					'default' => false,
				],

				'lightbox' => [
					'type'    => 'boolean',
					'default' => false,
				],

				'lightboxFullscreen' => [
					'type'    => 'boolean',
					'default' => false,
				],

				'lightboxThumbnails' => [
					'type'    => 'boolean',
					'default' => false,
				],

				'lightboxZoom' => [
					'type'    => 'boolean',
					'default' => false,
				],
			],
		] );
	}

	/**
	 * Render "HappyFiles Gallery" Gutenberg block
	 *
	 * Also used in editor via ServerSideRender.
	 */
	public static function render_block( $attributes = [], $content = '' ) {
		$categories = false;
		$columns = 3;
		$max = -1;
		$height = false;
		$spacing = false;
		$image_size = 'medium';
		$link_to = 'none';
		$order_by = 'date';
		$order = 'DESC';
		$no_crop = false;
		$include_children = false;
		$show_caption = false;
		$lightbox = false;
		$lightbox_thumbnails = false;
		$lightbox_zoom = false;
		$lightbox_fullscreen = false;

		if ( ! is_array( $attributes ) ) {
			$attributes = [];
		}

		foreach ( $attributes as $key => $value ) {
			if ( ! $key || $value === '' ) {
				continue;
			}

			// NOTE: As shortcode atts are converted to lowercase (e.g.: imageSize > imagesize)
			$key = strtolower( $key );

			switch ( $key ) {
				case 'categories':
				case 'folders':
				case 'ids':
					$categories = $value;
				break;

				case 'columns':
					$columns = intval( $value );
				break;

				case 'max':
					$max = intval( $value );
				break;

				case 'height':
					$height = is_numeric( $value ) ? $value . 'px' : $value;
				break;
				
				case 'spacing':
					$spacing = is_numeric( $value ) ? $value . 'px' : $value;
				break;

				case 'imagesize':
					$image_size = $value;
				break;

				case 'linkto':
					$link_to = $value;
				break;

				case 'orderby':
					$order_by = $value;
				break;

				case 'order':
					$order = $value;
				break;

				case 'nocrop': 
					$no_crop = $value ? true : false;
				break;

				case 'includechildren':
					$include_children = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				break;

				case 'caption':
					$show_caption = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				break;

				case 'lightbox':
					$lightbox = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				break;

				case 'lightboxfullscreen':
					$lightbox_fullscreen = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				break;

				case 'lightboxthumbnails':
					$lightbox_thumbnails = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				break;

				case 'lightboxzoom':
					$lightbox_zoom = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				break;

			}
		}

		if ( ! is_array( $categories ) ) {
			$categories = explode( ',', $categories );
		}

		if ( ! $categories ) {
			return '<div class="components-notice is-error"><div class="components-notice__content"><p>' . esc_html__( 'No folder selected.', 'happyfiles' ) . '</p></div></div>';
		}

		// Get term field type from first passed category
		$field = count( $categories ) && ! is_numeric( $categories[0] ) ? 'slug' : 'term_id';

		$attachment_ids = get_posts( [
			'post_type'      => 'attachment',
			'posts_per_page' => intval( $max ),
			'post_status'    => ['inherit', 'private'],
			'fields'         => 'ids',
			'orderby'        => $order_by,
			'order'          => $order,
			'tax_query'      => [
				[
					'taxonomy'         => HAPPYFILES_TAXONOMY,
					'terms'            => $categories,
					'field'            => $field,
					'include_children' => $include_children,
				],
			],
		] );

		if ( ! count( $attachment_ids ) ) {
			return '<div class="components-notice is-error"><div class="components-notice__content"><p>' . esc_html__( 'The selected folder has no items.', 'happyfiles' ) . '</p></div></div>';
		}

		wp_enqueue_style( 'happyfiles-gallery' );

		$root_classes = ['happyfiles-gallery'];

		if ( ! empty( $attributes['className'] ) ) {
			$root_classes[] = sanitize_html_class( $attributes['className'] );
		}
		
		if ( $lightbox ) {
			$root_classes[] = 'lightbox';
		}

		// Rendered in Gutenberg (wp-admin): Remove 'a' link tag
		$gutenberg_requested = strpos( wp_get_referer(), admin_url() ) === 0;

		if ( $gutenberg_requested ) {
			$link_to = false;
		}

		// Gallery element ID
		$gallery_id = 'happyfiles-gallery-';

		// Generate random element ID for every gallery lightbox
		$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
		$characters_length = strlen( $characters );

		for ( $i = 0; $i < 6; $i++ ) {
			$gallery_id .= $characters[rand( 0, $characters_length - 1 )];
		}

		if ( $lightbox ) {
			// https://github.com/sachinchoolur/lightgallery.js
			wp_enqueue_script( 'happyfiles-gallery-lightbox', HAPPYFILES_ASSETS_URL . '/libs/lightgallery/js/lightgallery.min.js', [], filemtime( HAPPYFILES_ASSETS_PATH .'/libs/lightgallery/js/lightgallery.min.js' ) );

			if ( $lightbox_fullscreen ) {
				wp_enqueue_script( 'happyfiles-gallery-lightbox-fullscreen', HAPPYFILES_ASSETS_URL . '/libs/lightgallery/js/lg-fullscreen.min.js', ['happyfiles-gallery-lightbox'], filemtime( HAPPYFILES_ASSETS_PATH .'/libs/lightgallery/js/lg-fullscreen.min.js' ) );
			}

			if ( $lightbox_thumbnails ) {
				wp_enqueue_script( 'happyfiles-gallery-lightbox-thumbnail', HAPPYFILES_ASSETS_URL . '/libs/lightgallery/js/lg-thumbnail.min.js', ['happyfiles-gallery-lightbox'], filemtime( HAPPYFILES_ASSETS_PATH .'/libs/lightgallery/js/lg-thumbnail.min.js' ) );
			}

			if ( $lightbox_zoom ) {
				wp_enqueue_script( 'happyfiles-gallery-lightbox-zoom', HAPPYFILES_ASSETS_URL . '/libs/lightgallery/js/lg-zoom.min.js', ['happyfiles-gallery-lightbox'], filemtime( HAPPYFILES_ASSETS_PATH .'/libs/lightgallery/js/lg-zoom.min.js' ) );
			}

			wp_add_inline_script( 'happyfiles-gallery-lightbox', "
				lightGallery(document.querySelector('#$gallery_id ul'), {
					counter: false,
					download: false,
					hideBarsDelay: 1000,
					fullScreen: " . var_export( $lightbox_fullscreen, true ) . ",
					thumbnail: " . var_export( $lightbox_thumbnails, true ) . ",
					zoom: " . var_export( $lightbox_zoom, true ) . "
				})
			" );

			wp_enqueue_style( 'happyfiles-gallery-lightbox', HAPPYFILES_ASSETS_URL . '/libs/lightgallery/css/lightgallery.min.css', [], filemtime( HAPPYFILES_ASSETS_PATH .'/libs/lightgallery/css/lightgallery.min.css' ) );
		}

		ob_start();

		if ( $height !== false && ! $no_crop ) {
			echo "<style>#$gallery_id.happyfiles-gallery li.item { height: $height; }</style>";
		}; 

		if ( $spacing !== false ) {
			if ( $no_crop ) {
				echo '<style>';
				echo "#$gallery_id.happyfiles-gallery > ul { column-gap: $spacing; }";
				echo "#$gallery_id.happyfiles-gallery > ul > li { padding-bottom: $spacing; }";
				echo '</style>';
			} else {
				echo "<style>#$gallery_id.happyfiles-gallery > ul { gap: $spacing; }</style>";
			}
		}

		// No crop: Use masonry via column-count (aligned & ordered vertical, though. Not as one whould expect horizontally)
		$list_classes[] = $no_crop ? 'no-crop' : 'crop';
		?>

		<figure id="<?php echo esc_attr( $gallery_id ); ?>" class="<?php echo implode( ' ', $root_classes ); ?>">
			<ul class="<?php echo implode( ' ', $list_classes ); ?>" style="width: 100%;" data-col="<?php echo esc_attr( $columns ); ?>">
				<?php
				$attr = [];

				foreach ( $attachment_ids as $attachment_id ) {
					$image_classes = ['item'];

					$image_categories = get_the_terms( $attachment_id, HAPPYFILES_TAXONOMY );

					// if ( is_array( $image_categories ) && count( $image_categories ) ) {
					// 	$image_categories = wp_list_pluck( $image_categories, 'slug' );
					// 	$image_classes = array_merge( $image_classes, $image_categories );
					// }

					if ( $lightbox ) {
						echo '<li class="' . join( ' ', $image_classes ) . '" data-src="' . wp_get_attachment_url( $attachment_id ) . '">';
					} else {
						echo '<li class="' . join( ' ', $image_classes ) . '">';
					}

					echo '<figure>';

					if ( $link_to && $link_to !== 'none' ) {
						$attachment_url = $link_to === 'attachment' ? get_permalink( $attachment_id ) : wp_get_attachment_url( $attachment_id );

						echo '<a href="' . $attachment_url . '">';
					}

					echo wp_get_attachment_image( $attachment_id, $image_size, false, $attr );

					if ( $link_to && $link_to !== 'none' ) {
						echo '</a>';
					}

					$the_caption = wp_get_attachment_caption( $attachment_id );

					if ( $show_caption && $the_caption ) {
						echo '<figcaption class="figcaption">' . $the_caption . '</figcaption>';
					}

					echo '</figure>';
					echo '</li>';
				}
				?>
			</ul>
		</figure>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render shortcode using the same code we use to render the "HappyFiles Gallery" Gutenberg block
	 */
	public function render_shortcode( $tag, $callback ) {
		return self::render_block( $tag );
	}

	// Gallery block folders select dropdown: Get flat list of folders ordered by position & parent
	public static function get_folders_ordered( $taxonomy, $post_type ) {
		$folders = Data::get_folders( $taxonomy, $post_type );

		// Sort folders by position
		usort( $folders, function( $a, $b ) {
			return $a->position > $b->position ? 1 : 0;
		} );

		$folders_ordered = self::get_folders_by_parent( $folders, 0 );

		return $folders_ordered;
	}

	/**
	 * Recursive helper function get generate ordered flat list (see: get_folders_ordered)
	 */
	public static function get_folders_by_parent( $folders, $parent_id = 0 ) {
		$folders_ordered = [];

		foreach ( $folders as $folder ) {
			if ( $folder->parent === $parent_id ) {
				$folder->name = str_repeat( '-', $folder->level ) . ' ' . $folder->name . ' (' . $folder->count . ')';

				$folders_ordered[] = $folder;
				$folders_ordered = array_merge( $folders_ordered, self::get_folders_by_parent( $folders, $folder->term_id ) );
			}
		}

		return $folders_ordered;
	}
}
