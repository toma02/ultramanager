<?php 
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Pro_Svg {
  public function __construct() {
		// Render SVG thumbnail preview for everyone (no matter SVG support setting)
		add_filter( 'wp_prepare_attachment_for_js', [ $this, 'render_svg_thumbnail_preview_image' ], 10, 3 );

		// Return: User not allowed to upload SVG files
		if ( ! User::$svg_support ) {
			return;
		}

		// Enable SVG support
		add_filter( 'upload_mimes', [$this, 'upload_mimes'] );
		add_filter( 'wp_check_filetype_and_ext', [$this, 'disable_real_mime_check'], 10, 4 );

		// Sanitize SVG file on upload
		if ( ! get_option( 'happyfiles_disable_svg_sanitization', false ) ) {
			add_filter( 'wp_handle_upload_prefilter', [ $this, 'sanitize_svg_file_on_upload' ] );
		}
	}

	/**
	 * Add SVG file support
	 */
	public function upload_mimes( $mimes ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';

		return $mimes;
	}

	/**
	 * Disable real MIME check (introduced in WordPress 4.7.1)
	 *
	 * https://wordpress.stackexchange.com/a/252296/44794
	 */
	public function disable_real_mime_check( $data, $file, $filename, $mimes ) {
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}

		$filetype = wp_check_filetype( $filename, $mimes );

		return [
				'ext'             => $filetype['ext'],
				'type'            => $filetype['type'],
				'proper_filename' => $data['proper_filename']
		];
	}

	/**
	 * Render SVG thumbnail preview image
	 */
	public function render_svg_thumbnail_preview_image( $response, $attachment, $meta ) {
    if ( class_exists( 'SimpleXMLElement' ) && $response['type'] === 'image' && $response['subtype'] === 'svg+xml' ) {
			try {
				$path = get_attached_file( $attachment->ID );
				
				if ( @file_exists( $path ) ) {
					$svg = new \SimpleXMLElement( @file_get_contents( $path ) );
					$src = $response['url'];
					$width = (int) $svg['width'];
					$height = (int) $svg['height'];

					// Media library
					$response['image'] = compact( 'src', 'width', 'height' );
					$response['thumb'] = compact( 'src', 'width', 'height' );

					// Media single image
					$response['sizes']['full'] = [
						'height'      => $height,
						'width'       => $width,
						'url'         => $src,
						'orientation' => $height > $width ? 'portrait' : 'landscape',
					];
				}
			}

			catch ( Exception $e ) {}
    }

    return $response;
	}

	/**
	 * Init SVG sanitzier (Safe SVG)
	 * 
	 * @uses https://github.com/darylldoyle/svg-sanitizer
	 */
	public function init_svg_sanitizer() {
		// Load SVG Sanitizer Library (https://github.com/darylldoyle/svg-sanitizer)
		require_once HAPPYFILES_PATH . 'includes/pro/svg-sanitizer/data/AttributeInterface.php';
		require_once HAPPYFILES_PATH . 'includes/pro/svg-sanitizer/data/TagInterface.php';
		require_once HAPPYFILES_PATH . 'includes/pro/svg-sanitizer/data/AllowedAttributes.php';
		require_once HAPPYFILES_PATH . 'includes/pro/svg-sanitizer/data/AllowedTags.php';
		require_once HAPPYFILES_PATH . 'includes/pro/svg-sanitizer/data/XPath.php';

		require_once HAPPYFILES_PATH . 'includes/pro/svg-sanitizer/ElementReference/Resolver.php';
		require_once HAPPYFILES_PATH . 'includes/pro/svg-sanitizer/ElementReference/Subject.php';
		require_once HAPPYFILES_PATH . 'includes/pro/svg-sanitizer/ElementReference/Usage.php';

		require_once HAPPYFILES_PATH . 'includes/pro/svg-sanitizer/Exceptions/NestingException.php';

		require_once HAPPYFILES_PATH . 'includes/pro/svg-sanitizer/Helper.php';
		require_once HAPPYFILES_PATH . 'includes/pro/svg-sanitizer/Sanitizer.php';
	}

	/**
	 * Sanitize SVG files on file upload
	 */
	public function sanitize_svg_file_on_upload( $file ) {
		if ( $file['type'] !== 'image/svg+xml' ) {
			return $file;
		}

		// Return if Sae SVG plugin & it's sanitizer are already in use
		if ( interface_exists( 'enshrined\svgSanitize\data\AttributeInterface' ) ) {
			return $file;
		}

		self::init_svg_sanitizer();

		$sanitizer = new \enshrined\svgSanitize\Sanitizer();

		$dirty = file_get_contents( $file['tmp_name'] );

		$clean = $sanitizer->sanitize( $dirty );

		// Success: SVG successfully sanitized (save it)
		if ( $clean ) {
			file_put_contents( $file['tmp_name'], $clean );
		} 
		
		// Error: XML parsing failed (return error message)
		else {
			$file['error'] = __( 'This file couldn\'t be sanitized for security reasons and wasn\'t uploaded.', 'happyfiles' );
		}

		return $file;
	}
}