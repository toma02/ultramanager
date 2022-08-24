<?php namespace HappyFiles; ?>
<div class="wrap">
	<h1 class="wp-heading-inline" style="margin-bottom: 15px">HappyFiles <?php esc_html_e( 'Settings', 'happyfiles' ); ?></h1>

	<ul id="happyfiles-settings-tabs" class="nav-tab-wrapper">
		<li><a href="#tab-general" class="nav-tab nav-tab-active" data-tab-id="tab-general"><?php esc_html_e( 'General', 'happyfiles' ); ?></a></li>
		<li><a href="#tab-permissions" class="nav-tab" data-tab-id="tab-permissions"><?php esc_html_e( 'Permissions', 'happyfiles' ); ?></a></li>
		<li><a href="#tab-import" class="nav-tab" data-tab-id="tab-import"><?php echo esc_html__( 'Import', 'happyfiles' ) . ' / ' . esc_html__( 'Export', 'happyfiles' ); ?></a></li>
	</ul>

	<div id="happyfiles-settings-content">
		<form id="happyfiles-settings" action="options.php" method="post">
			<?php settings_fields( HAPPYFILES_SETTINGS_GROUP ); ?>

			<table id="tab-general" class="form-table active" role="presentation">
				<tbody>
					<?php do_action( 'happyfiles/settings/general/top' ); ?>

					<tr>
						<?php
						$default_open_folder = get_option( 'happyfiles_default_open_folders', '' );
						?>
						<th><?php esc_html_e( 'Default open folder', 'happyfiles' ); ?></th>
						<td>
							<fieldset>
									<select name="happyfiles_default_open_folders" id="happyfiles_default_open_folders">
										<option value="-2" <?php selected( $default_open_folder, '-2' ); ?>><?php esc_html_e( 'All Files', 'happyfiles' ); ?></option>
										<option value="-1" <?php selected( $default_open_folder, '-1' ); ?>><?php esc_html_e( 'Uncategorized', 'happyfiles' ); ?></option>
										<option value="" <?php selected( $default_open_folder, '' ); ?>><?php esc_html_e( 'Last opened folder', 'happyfiles' ); ?></option>
									</select>
								<br>
								<p class="description"><?php esc_html_e( 'Set folder you want to open on page load.', 'happyfiles' ); ?></p>
							</fieldset>
						</td>
					</tr>
					
					<tr>
						<th><?php esc_html_e( 'Assign multiple folders', 'happyfiles' ); ?></th>
						<td>
							<fieldset>
								<label for="happyfiles_multiple_folders">
									<input type="checkbox" name="happyfiles_multiple_folders" id="happyfiles_multiple_folders" value="1" <?php checked( Settings::$multiple_folders, true, true ); ?>>
									<?php esc_html_e( 'Assign multiple folders', 'happyfiles' ); ?>
								</label>
								<br>
								<p class="description"><?php esc_html_e( 'When enabled you can assign multiple folders to one item. If disabled an item can only be assigned to one folder.', 'happyfiles' ); ?></p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'Remove from all folders', 'happyfiles' ); ?></th>
						<td>
							<fieldset>
								<label for="happyfiles_remove_from_all_folders">
									<input type="checkbox" name="happyfiles_remove_from_all_folders" id="happyfiles_remove_from_all_folders" value="1" <?php checked( Settings::$remove_from_all_folders, true, true ); ?>>
									<?php esc_html_e( 'Remove from all folders', 'happyfiles' ); ?>
								</label>
								<br>
								<p class="description"><?php esc_html_e( 'When enabled removing an item from a folder removes it from all other folders. If disabled the item will only be removed from the open folder.', 'happyfiles' ); ?></p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'Infinite scrolling', 'happyfiles' ); ?></th>
						<td>
							<fieldset>
								<label for="happyfiles_media_library_infinite_scrolling">
									<input type="checkbox" name="happyfiles_media_library_infinite_scrolling" id="happyfiles_media_library_infinite_scrolling" value="1" <?php checked( Settings::$media_library_infinite_scrolling, true, true ); ?>>
									<?php esc_html_e( 'Infinite scrolling', 'happyfiles' ); ?>
								</label>
								<br>
								<p class="description"><?php esc_html_e( 'When enabled the media library uses infinite scrolling instead of the new "Load more" button (that was introduces in WordPress 5.8).', 'happyfiles' ); ?></p>
							</fieldset>
						</td>
					</tr>

					<?php do_action( 'happyfiles/settings/general/bottom' ); ?>
				</tbody>
			</table>
		
			<table id="tab-permissions" class="form-table" role="presentation">
				<tbody>
					<?php do_action( 'happyfiles/settings/permissions/top' ); ?>

					<tr>
						<th>
							<?php esc_html_e( 'Folder access', 'happyfiles' ); ?>
							<p class="description"><?php esc_html_e( 'Set folder access level per user role. Set folder access for individual user on user profile page.', 'happyfiles' ); ?></p>
						</th>

						<td>
							<?php
							global $wp_roles;
							$user_roles = $wp_roles->role_names;
							$folder_access_per_role = get_option( 'happyfiles_folder_access', [] );

							foreach ( $user_roles as $name => $label ) {
								$folder_access = ! empty( $folder_access_per_role[$name] ) ? $folder_access_per_role[$name] : '';

								if ( $name === 'administrator' ) {
									$folder_access = 'full';
								}

								echo '<section data-user-role="' . $name . '">';
								echo '<label for="happyfiles_folder_access_' . $name . '">' . $label . '</label>';
								echo '<select id="happyfiles_folder_access_' . $name . '" name="happyfiles_folder_access[' . $name . ']" ' . ( $name === 'administrator' ? 'disabled' : '' ) . '>';
								echo '<option value="full" ' . selected( $folder_access === 'full' ) . '>' . esc_html__( 'Full access', 'happyfiles' ) . '</option>';
								echo '<option value="" ' . selected( $folder_access === '' ) . '>' . esc_html__( 'View folders', 'happyfiles' ) . '</option>';
								echo '<option value="upload" ' . selected( $folder_access === 'upload' ) . '>' . esc_html__( 'Upload to folder', 'happyfiles' ) . '</option>';
								echo '<option value="none" ' . selected( $folder_access === 'none' ) . '>' . esc_html__( 'No access', 'happyfiles' ) . '</option>';
								echo '</select>';
								echo '</section>';
							}
							?>
						</td>
					</tr>

					<?php do_action( 'happyfiles/settings/permissions/bottom' ); ?>
				</tbody>
			</table>

			<table id="tab-import" class="form-table" role="presentation">
				<tbody>
					<?php 
					foreach ( Import::$plugins as $slug => $plugin ) { ?>
					<tr id="<?php echo $slug; ?>">
						<th>
							<div><?php echo $plugin['name']; ?></div>
						</th>

						<td>
							<?php
							$plugin_folders = Import::$plugins[$slug]['folders'];
							$plugin_items = Import::$plugins[$slug]['items'];

							// HappyFiles: Import / Export
							if ( $slug === 'happyfiles' ) {
								$registered_post_types = get_post_types( ['show_ui' => true], 'objects' );

								echo '<button id="happyfiles-import" class="button button-primary" data-plugin="' . $slug . '">' . esc_html__( 'Import', 'happyfiles' ) . '<span class="spinner"></span></button>';

								echo '<button id="happyfiles-export" class="button button-primary" data-plugin="' . $slug . '">' . esc_html__( 'Export', 'happyfiles' ) . '<span class="spinner"></span></button>';

								echo '<br><br>';

								foreach ( $registered_post_types as $post_type ) {
									$post_type_name = $post_type->name;
									$post_type_label = $post_type->label;

									if ( $post_type_name === 'wp_block' ) {
										continue;
									}

									echo '<fieldset>';
									echo '<label for="happyfiles_export_' . $post_type_name . '">';
									echo '<input id="happyfiles_export_' . $post_type_name . '" name="happyfiles_export_' . $post_type_name . '" type="checkbox" value="' . $post_type_name . '"/>';
									echo $post_type_label;
									echo '</label>';
									echo '</fieldset>';
								}

								echo '<div class="description">' . esc_html__( 'Export your HappyFiles folder structure to import & use it in another installation.', 'happyfiles' ) . '</div>';
							}

							// Third-party media folder plugins: Import
							else {
								if ( count( $plugin_folders ) ) {
									echo '<p class="message">';
									echo sprintf(
										esc_html__( '%s folders with %s associated items found to import.', 'happyfiles' ),
										count( $plugin_folders ),
										count( $plugin_items )
									);
									echo '</p><br>';
	
									echo '<button class="button button-primary happyfiles-import" data-plugin="' . $slug . '">' . esc_html__( 'Import', 'happyfiles' ) . '<span class="spinner"></span></button>';
									echo '<button class="button button-secondary happyfiles-delete" data-plugin="' . $slug . '">' . esc_html__( 'Delete plugin data', 'happyfiles' ) . '<span class="spinner"></span></button>';
								} else {
									esc_html_e( 'No folders found to import.', 'happyfiles' );
								}
							}
							?>
						</td>
					</tr>
					<?php } ?>
				</tbody>
			</table>

			<table id="save-delete" class="form-table" role="presentation">
			<tfoot>
					<tr>
						<td class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save changes', 'happyfiles' ); ?>"></td>
						<td class="delete"><button id="happyfiles-delete-plugin-data" class="button button-primary"><?php esc_html_e( 'Delete plugin data', 'happyfiles' ); ?></button></td>
					</tr>
				</tfoot>
			</table>
		</form>
	</div>

</div>
