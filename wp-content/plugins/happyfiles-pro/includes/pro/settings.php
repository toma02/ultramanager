<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Pro_Settings {
	public static $license_key = '';
	public static $license_status = '';
	public static $disable_for_files = '';

	public function __construct() {
		self::$license_key = get_option( 'happyfiles_license_key', '' );
		self::$license_status = get_option( 'happyfiles_license_status', [] );
		self::$disable_for_files = get_option( 'happyfiles_disable_for_files', false );

		add_action( 'admin_init', [$this, 'register_pro_settings'] );
		add_filter( 'happyfiles/post_types', [$this, 'enabled_post_types'], 10, 1 );

		add_action( 'happyfiles/settings/general/top', [$this, 'render_setting_licence_key' ] );
		add_action( 'happyfiles/settings/general/top', [$this, 'render_setting_post_types' ] );
		add_action( 'happyfiles/settings/general/top', [$this, 'render_setting_disable_for_files' ] );

		add_action( 'happyfiles/settings/general/bottom', [$this, 'render_filesizes' ] );
		add_action( 'happyfiles/settings/general/bottom', [$this, 'render_featured_image_quick_edit' ] );

		add_action( 'happyfiles/settings/permissions/bottom', [$this, 'render_setting_svg_support' ] );

		// License activation/deactivation
		add_action( 'wp_ajax_happyfiles_deactivate_license', [$this, 'deactivate_license'] );

		add_action( 'add_option_happyfiles_license_key', [$this, 'license_key_added'] );
		add_action( 'delete_option_happyfiles_license_key', [$this, 'license_key_removed'] );
		add_filter( 'plugins_api', [$this, 'plugin_info'], 20, 3 );

		add_filter( 'pre_set_site_transient_update_plugins', [$this, 'check_update_available'] );
	}

	/**
	 * Disable HappyFiles for files (post type: attachment)
	 */
	public function enabled_post_types( $post_types ) {
		if ( self::$disable_for_files ) {
			$attachment_index = array_search( 'attachment', $post_types );

			if ( $attachment_index !== false ) {
				unset( $post_types[$attachment_index] );
			}
		}

		return $post_types;
	}

	/**
	 * Deactivate license
	 */
	public function deactivate_license() {
		$license_key_deleted = delete_option( 'happyfiles_license_key' );
		$license_status_deleted = delete_option( 'happyfiles_license_status' );

		if ( $license_key_deleted ) {
			wp_send_json_success( ['message' => esc_html__( 'License key has been removed.', 'happyfiles' )] );
		} else {
			wp_send_json_error( ['message' => esc_html__( 'License key deletion failed.', 'happyfiles' )] );
		}
	}

	/**
	 * License key added: Add site to HappyFiles account & set license status
	 */
	public function license_key_added( $option_name ) {
		$response = wp_remote_post( 'https://happyfiles.io/api/happyfiles/activate_license', [
			'sslverify' => false,
			'body'      => [
				'license_key' => get_option( $option_name ),
				'site'        => get_site_url(),
			],
		] );

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Set license status 
		if ( ! empty( $response_body['message'] ) ) {
			update_option( 'happyfiles_license_status', $response_body );
		}
	}

	/**
	 * License key removed: Send event to happyfiles.io to remove active site
	 */
	public function license_key_removed( $option_name ) {
		$response = wp_remote_post( 'https://happyfiles.io/api/happyfiles/deactivate_license', [
			'sslverify' => false,
			'body'      => [
				'license_key' => self::$license_key,
				'url'         => get_site_url(),
			],
		] );

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Register PRO settings
	 */
	public function register_pro_settings() {
		// Tab: General
		register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_post_types' );
		register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_disable_for_files' );
		register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_filesizes' );
		register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_featured_image_quick_edit' );
		register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_featured_image_column' );

		// Tab: Permissions
		register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_svg_support' );
		register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_disable_svg_sanitization' );

		if ( ! self::$license_key ) {
			register_setting( HAPPYFILES_SETTINGS_GROUP, 'happyfiles_license_key' );
		}
	}

	/**
	 * PRO setting: License key (for one-click updates)
	 */
	public function render_setting_licence_key() {
		// Encrypt license key before rendering on HappyFiles settings page
		$license_key = self::$license_key ? substr_replace( self::$license_key, 'XXXXXXXXXXXXXXXXXXXXXXXX', 4, strlen( self::$license_key ) - 8 ) : '';
		$license_status = self::$license_status;
		?>
		<tr>
			<th><?php esc_html_e( 'One click updates', 'happyfiles' ); ?></th>
			<td id="happyfiles-license-key-field">
				<input 
					name="<?php echo $license_key ? '' : 'happyfiles_license_key'; ?>" 
					id="<?php echo $license_key ? '' : 'happyfiles_license_key'; ?>" 
					type="password" 
					placeholder="<?php echo esc_attr( $license_key ); ?>" 
					spellcheck="false"
					<?php echo $license_key ? ' readonly' : ''; ?>>
				
				<?php
				if ( $license_key ) {
					echo '<input type="submit" name="happyfiles-deactivate-license" id="happyfiles-deactivate-license" class="button button-secondary" value="' . esc_html__( 'Deactivate license', 'happyfiles' ) . '">';
				}
				
				else {
					echo '<p class="description">' . sprintf( esc_html__( 'Paste your license key from your %s in here to enable one-click plugin updates and notifications.', 'happyfiles' ), '<a href="https://happyfiles.io/account/" target="_blank">' . esc_html__( 'HappyFiles account', 'happyfiles' ) .'</a>' ) . '</p>';
				}

				if ( $license_key && ! empty( $license_status['message'] ) ) {
					echo '<div class="message happyfiles-' . ( ! empty( $license_status['type'] ) ? $license_status['type'] : 'info' ) . '">' . $license_status['message'] . '</div>';
				}
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * PRO setting: Post Types
	 */
	public function render_setting_post_types() { ?>
		<tr>
			<th><?php esc_html_e( 'Post Types', 'happyfiles' ); ?></th>
			<td>
				<fieldset>
					<?php
					$registered_post_types = get_post_types( ['show_ui' => true], 'objects' );
					$enabled_post_types = Settings::$enabled_post_types;

					// Folders for plugins (@since 1.6)
					$plugins_object = new \stdClass();
					$plugins_object->name = 'plugins';
					$plugins_object->label = esc_html__( 'Plugins', 'happyfiles' );
					$registered_post_types[] = $plugins_object;

					foreach ( $registered_post_types as $post_type ) {
						$post_type_name = $post_type->name;
						$post_type_label = $post_type->label;

						if ( in_array( $post_type_name, ['attachment', 'wp_block'] ) ) {
							continue;
						}
						?>
						<label for="<?php echo esc_attr( "happyfiles_post_types_$post_type_name" ); ?>">
						<input 
							name="happyfiles_post_types[]" 
							id="<?php echo esc_attr( "happyfiles_post_types_$post_type_name" ); ?>" 
							type="checkbox" 
							value="<?php echo esc_attr( $post_type_name ); ?>"
							<?php checked( in_array( $post_type_name, $enabled_post_types ) ); ?>>
						<?php echo $post_type->label; ?>
						</label>
						<br>
					<?php } ?>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * PRO setting: Disable HappyFiles for files
	 */
	public function render_setting_disable_for_files() { ?>
		<tr>
			<th><?php esc_html_e( 'Disable in media library', 'happyfiles' ); ?></th>
			<td>
				<fieldset>
						<label for="happyfiles_disable_for_files">
						<input 
							name="happyfiles_disable_for_files" 
							id="happyfiles_disable_for_files" 
							type="checkbox" 
							value="1"
							<?php checked( self::$disable_for_files ); ?>>
							<?php esc_html_e( 'Disable in media library', 'happyfiles' ); ?>
						</label>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * PRO setting: Sort attachments by filesize
	 */
	public function render_filesizes() { ?>
		<tr>
			<th><?php esc_html_e( 'Sort by file size', 'happyfiles' ); ?></th>
			<td>
				<fieldset>
				<?php $filesize_enabled = Settings::$filesizes; ?>
				<label for="happyfiles_filesizes">
					<input type="checkbox" name="happyfiles_filesizes" id="happyfiles_filesizes" value="1" <?php checked( $filesize_enabled, true, true ); ?>>
					<?php esc_html_e( 'Sort by file size', 'happyfiles' ); ?>
				</label>
				<br>
				<p class="description"><?php esc_html_e( 'When enabled you can sort your attachments by file size.', 'happyfiles' ); ?></p>
				</fieldset>

				<?php
				if ( $filesize_enabled ) {
					$attachment_ids = get_posts( [
						'post_type'      => 'attachment',
						'posts_per_page' => -1,
						'post_status'    => 'any',
						'fields'         => 'ids',
						'order'          => 'ASC',
					] );
					
					echo '<button id="happyfiles-regenerate-filesizes" class="button button-secondary" data-attachment-ids="' . implode( ',', $attachment_ids ) . '">' . esc_html__( 'Regenerate file sizes', 'happyfiles' ) . '</button>';

					echo '<ul id="happyfiles-regenerate-filesizes-results"></ul>';
				}
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * PRO setting: Featured Image (via "Quick Edit", Preview column)
	 */
	public function render_featured_image_quick_edit() { ?>
		<tr>
			<th><?php esc_html_e( 'Featured image', 'happyfiles' ); ?></th>
			<td>
				<fieldset>
					<?php $featured_image_quick_edit = get_option( 'happyfiles_featured_image_quick_edit', false ); ?>
					<label for="happyfiles_featured_image_quick_edit">
						<input type="checkbox" name="happyfiles_featured_image_quick_edit" id="happyfiles_featured_image_quick_edit" value="1" <?php checked( $featured_image_quick_edit, true, true ); ?>>
						<?php esc_html_e( 'Set featured image via "Quick edit"', 'happyfiles' ); ?>
					</label>
				</fieldset>

				</fieldset>
					<?php $featured_image_column = get_option( 'happyfiles_featured_image_column', false ); ?>
					<label for="happyfiles_featured_image_column">
						<input type="checkbox" name="happyfiles_featured_image_column" id="happyfiles_featured_image_column" value="1" <?php checked( $featured_image_column, true, true ); ?>>
						<?php esc_html_e( 'Show featured image preview column', 'happyfiles' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * PRO setting: SVG support & sanitization
	 */
	public function render_setting_svg_support() {
		?>
		<tr>
			<th>
				<?php esc_html_e( 'SVG support', 'happyfiles' ); ?>
				<p class="description">
					<?php esc_html_e( 'Enable/disable SVG upload, preview & sanitization per user role. Set SVG support for individual user on user profile page.', 'happyfiles' ); ?><br><br>
					<?php esc_html_e( 'Upload SVG files only from trusted sources as they can contain and execute malicious XML code.', 'happyfiles' ); ?>
				</p>
			</th>
			
			<td>
				<?php
				global $wp_roles;
				$user_roles = $wp_roles->role_names;
				$svg_support_per_role = get_option( 'happyfiles_svg_support', [] );

				foreach ( $user_roles as $name => $label ) {
					$svg_support = ! empty( $svg_support_per_role[$name] ) ? $svg_support_per_role[$name] : '';

					if ( $name === 'administrator' ) {
						$svg_support = '1';
					}

					echo '<section data-user-role="' . $name . '">';
					echo '<label for="happyfiles_svg_support_' . $name . '">' . $label . '</label>';
					echo '<select id="happyfiles_svg_support_' . $name . '" name="happyfiles_svg_support[' . $name . ']" ' . ( $name === 'administrator' ? 'disabled' : '' ) . '>';
					echo '<option value="" ' . selected( $svg_support === '' ) . '>' . esc_html__( 'Disabled', 'happyfiles' ) . '</option>';
					echo '<option value="1" ' . selected( $svg_support === '1' ) . '>' . esc_html__( 'Enabled', 'happyfiles' ) . '</option>';
					echo '</select>';
					echo '</section>';
				}
				?>
			</td>
		</tr>

		<tr>
			<th>
				<?php esc_html_e( 'Disable SVG sanitization', 'happyfiles' ); ?>
			</th>

			<td>
				<?php 
				$disable_svg_sanitization = get_option( 'happyfiles_disable_svg_sanitization', false );
				?>
				
				<fieldset>
					<label for="happyfiles_disable_svg_sanitization">
						<input type="checkbox" name="happyfiles_disable_svg_sanitization" id="happyfiles_disable_svg_sanitization" value="1" <?php checked( $disable_svg_sanitization, true, true ); ?>>
						<?php esc_html_e( 'Disable SVG sanitization', 'happyfiles' ); ?>
					</label>
					<br>
					<p class="description">
						<?php esc_html_e( 'Disable built-in SVG sanitization if you experience issues when uploading SVG files.', 'happyfiles' ); ?><br><br>
					</p>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * Check remotely if a newer version of HappyFiles PRO is available
	 *
	 * @param $transient Transient for WordPress plugin updates.
	 * 
	 * @return void
	 */
	public static function check_update_available( $transient ) {
		// 'checked' is an array with all installed plugins and their version numbers
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$license_key = self::$license_key;

		if ( ! $license_key ) {
			return $transient;
		}

		$update_data_url = add_query_arg(
			[
				'license_key' => $license_key,
				'domain'      => get_site_url(),
				'time'        => time(), // Don't cache response
			],
			'https://happyfiles.io/api/happyfiles/get_update_data/'
		);

		$response = wp_remote_get( $update_data_url, [
			'sslverify' => false,
			'timeout'   => 15,
			'headers'   => [
				'Accept' => 'application/json',
			],
		] );

		// Check if remote GET request has been successful (response code works better than is_wp_error)
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return $transient;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ), true );

		$license_status = isset( $response['license_status'] ) ? $response['license_status'] : false;

		update_option( 'happyfiles_license_status', $license_status );

		// STEP: Check for remote whitelist/blacklist/max_sites
		if ( isset( $license_status['type'] ) && $license_status['type'] === 'error' ) {
			return $transient;
		}

		if ( isset( $response['error'] ) ) {
			return $transient;
		}

		$installed_version = HAPPYFILES_VERSION;

		// Check remotely if newer version is available
		$latest_version = isset( $response['new_version'] ) ? $response['new_version'] : $installed_version;
		$newer_version_available = version_compare( $latest_version, $installed_version, '>' );

		if ( ! $newer_version_available ) {
			return $transient;
		}

		if ( isset( $response['license_status'] ) ) {
			unset( $response['license_status'] );
		}

		$response['icons'] = [
			'1x' => 'https://ps.w.org/happyfiles/assets/icon-256x256.png?rev=2361994',
		];

		$response['banners'] = [
			'1x' => 'https://ps.w.org/happyfiles/assets/banner-772x250.jpg?rev=2248756',
		];

		$response['slug'] = 'happyfiles-pro';
		$response['plugin'] = 'happyfiles-pro/happyfiles-pro.php';
		$response['tested'] = get_bloginfo( 'version' );

		// Save HappyFiles Pro update data in transient
		$transient->response['happyfiles-pro/happyfiles-pro.php'] = (object) $response;

		return $transient;
	}

	/**
	 * Show HappyFiles PRO plugin data in update popup
	 */
	public function plugin_info( $res, $action, $args ) {
		// Return: Action is not about getting plugin information
		if ( $action !== 'plugin_information' ) {
			return $res;
		}

		$plugin_slug = 'happyfiles-pro';

		if ( $plugin_slug !== $args->slug ) {
			return $res;
		}

		$plugin_data = wp_remote_get( 'https://happyfiles.io/api/happyfiles/get_pro_plugin_data?time=' . time(), [
			'sslverify'=> false,
			'timeout'  => 15,
			'headers'  => [
				'Accept' => 'application/json',
			]
		] );

		if ( is_wp_error( $plugin_data ) ) {
			return;
		}

		$plugin_data = json_decode( wp_remote_retrieve_body( $plugin_data ), true );

		if ( ! is_array( $plugin_data ) || ! count( $plugin_data ) ) {
			return;
		}

		$res = new \stdClass();

		$res->slug         = $plugin_slug;
		$res->name         = $plugin_data['name'];
		$res->version      = $plugin_data['version'];
		$res->tested       = $plugin_data['tested'];
		$res->requires     = $plugin_data['requires'];
		$res->author       = $plugin_data['author'];
		$res->requires_php = $plugin_data['requires_php'];
		$res->last_updated = $plugin_data['last_updated'];
		$res->banners      = $plugin_data['banners'];
		$res->sections     = [
			'description'  => $plugin_data['sections']['description'],
			'installation' => $plugin_data['sections']['installation'],
			'releases'     => $plugin_data['sections']['releases'],
			'screenshots'  => $plugin_data['sections']['screenshots'],
		];

		return $res;
	}
}