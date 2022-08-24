<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class User {
	public static $folder_access = '';
	public static $svg_support   = false;

	public function __construct() {
		// Permissions (per user role)
		add_action( 'init', [$this, 'get_folder_access'] );
		add_action( 'init', [$this, 'get_svg_support'] );

		// User profile settings: Folder access & SVG support
		add_action( 'edit_user_profile', [ $this, 'user_profile' ], 11, 1 );
		add_action( 'edit_user_profile_update', [ $this, 'user_profile_update' ] );
	}

	/**
	 * Get folder access level of logged-in user
	 * 
	 * full: Can view & edit folders
	 * none: no folder access (Disable HappyFiles sidebar)
	 * '': Can view folders (default)
	 */
	public static function get_folder_access() {
    if ( current_user_can( 'administrator' ) ) {
      self::$folder_access = 'full';

      return;
    }

		// STEP: Get folder access from user settings
		self::$folder_access = get_user_meta( get_current_user_id(), 'happyfiles_folder_access', true );

		if ( self::$folder_access ) {
			return;
		}

		// STEP: Loop through logged in user roles to get access level according to HappyFiles settings
    $folder_access_settings = get_option( 'happyfiles_folder_access', [] );

		if ( is_array( $folder_access_settings ) ) {
			$current_user_roles = wp_get_current_user()->roles;

			foreach ( $current_user_roles as $role ) {
				if ( ! empty( $folder_access_settings[$role] ) ) {
					self::$folder_access = $folder_access_settings[$role];
				}
			}
		}
	}

	/**
	 * Get SVG support of logged-in user
	 */
	public static function get_svg_support() {
    if ( current_user_can( 'administrator' ) ) {
      self::$svg_support = '1';

      return;
    }

		// STEP: Get SVG support from user settings
		self::$svg_support = get_user_meta( get_current_user_id(), 'happyfiles_svg_support', true );

		if ( self::$svg_support ) {
			self::$svg_support = self::$svg_support !== 'disabled';

			return;
		}

		// STEP: Loop through logged in user roles to get access level according to HappyFiles settings
    $svg_support_per_role = get_option( 'happyfiles_svg_support', [] );

		if ( is_array( $svg_support_per_role ) ) {
			$current_user_roles = wp_get_current_user()->roles;

			foreach ( $current_user_roles as $role ) {
				if ( ! empty( $svg_support_per_role[$role] ) ) {
					self::$svg_support = $svg_support_per_role[$role];
				}
			}
		}
	}

	/**
	 * User profile HappyFiles settings: Folder access & SVG support for individual user
	 */
	public function user_profile( $user ) {
		$current_user_roles = $user->roles;

		// STEP: Folder access
		$folder_access_user = get_user_meta( $user->ID, 'happyfiles_folder_access', true );
		$folder_access_settings = get_option( 'happyfiles_folder_access', [] );
		$folder_access_setting = '';

		// Get folder access by user's role
		if ( ! $folder_access_user && is_array( $folder_access_settings ) ) {
			foreach ( $current_user_roles as $role ) {
				if ( ! empty( $folder_access_settings[$role] ) ) {
					$folder_access_setting = $folder_access_settings[$role];
				}
			}
		}

		switch ( $folder_access_setting ) {
			case 'upload':
				$folder_access_inherit = esc_html__( 'Upload to folder', 'happyfiles' );
			break;

			case 'full':
				$folder_access_inherit = esc_html__( 'Full access', 'happyfiles' );
			break;

			case 'none':
				$folder_access_inherit = esc_html__( 'No access', 'happyfiles' );
			break;

			default:
				$folder_access_inherit = esc_html__( 'View folders', 'happyfiles' );
		}

		// STEP: SVG support
		$svg_support_user = get_user_meta( $user->ID, 'happyfiles_svg_support', true );
		$svg_support_settings = get_option( 'happyfiles_svg_support', [] );
		$svg_support_setting = '';

		// Get folder access by user's role
		if ( ! $svg_support_user && is_array( $svg_support_settings ) ) {
			foreach ( $current_user_roles as $role ) {
				if ( ! empty( $svg_support_settings[$role] ) ) {
					$svg_support_setting = $svg_support_settings[$role];
				}
			}
		}

		$svg_support_inherit = $svg_support_setting ? esc_html__( 'Enabled', 'happyfiles' ) : esc_html__( 'Disabled', 'happyfiles' );
		?>
		<h2>HappyFiles</h2>

		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="happyfiles_folder_access"><?php esc_html_e( 'Folder access', 'happyfiles' ); ?></label></th>
					<td>
						<select name="happyfiles_folder_access" id="happyfiles_folder_access">
							<option value=""><?php echo esc_html__( 'Inherit', 'happyfiles' ) . " ($folder_access_inherit)"; ?></option>
							<option value="view" <?php $folder_access_user ? selected( $folder_access_user, 'view', true ) : false; ?>><?php esc_html_e( 'View folders', 'happyfiles' ); ?></option>
							<option value="upload" <?php $folder_access_user ? selected( $folder_access_user, 'upload', true ) : false; ?>><?php esc_html_e( 'Upload to folder', 'happyfiles' ); ?></option>
							<option value="full" <?php $folder_access_user ? selected( $folder_access_user, 'full', true ) : false; ?>><?php esc_html_e( 'Full access', 'happyfiles' ); ?></option>
							<option value="none" <?php $folder_access_user ? selected( $folder_access_user, 'none', true ) : false; ?>><?php esc_html_e( 'No access', 'happyfiles' ); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th><label for="happyfiles_svg_support"><?php esc_html_e( 'SVG support', 'happyfiles' ); ?></label></th>
					<td>
						<select name="happyfiles_svg_support" id="happyfiles_svg_support">
							<option value=""><?php echo esc_html__( 'Inherit', 'happyfiles' ) . " ($svg_support_inherit)"; ?></option>
							<option value="enabled" <?php selected( $svg_support_user === 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'happyfiles' ); ?></option>
							<option value="disabled" <?php selected( $svg_support_user === 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'happyfiles' ); ?></option>
						</select>
						<br>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	public function user_profile_update( $user_id ) {
		// Update user folder access level: '', 'full', 'none'
		if ( ! empty( $_POST['happyfiles_folder_access'] ) ) {
			update_user_meta( $user_id, 'happyfiles_folder_access', $_POST['happyfiles_folder_access'] );
		} else {
			delete_user_meta( $user_id, 'happyfiles_folder_access' );
		}

		// Update user SVG support
		if ( ! empty( $_POST['happyfiles_svg_support'] ) ) {
			update_user_meta( $user_id, 'happyfiles_svg_support', $_POST['happyfiles_svg_support'] );
		} else {
			delete_user_meta( $user_id, 'happyfiles_svg_support' );
		}
	}
}