<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Import folders data from other plugins
 * 
 * Filebird (custom database tables)
 * Real Media Library (custom database tables)
 * Folders (custom taxonomy terms)
 */
class Import {
	public static $plugins = [
		// HappyFiles: Import & Export (custom taxonomy terms)
		'happyfiles' => [
			'name'        => 'HappyFiles',
			'taxonomies'  => [
				'any'         => [
					'hf_cat_{post_type}',
				],
			],
			'folders'     => [],
			'items'       => [],
		],

		// STEP: Plugins with custom database tables

		// Attachments only
		'filebird' => [
			'name'        => 'FileBird (v4+)',
			'taxonomies'  => [], // Uses custom database table
			'folders'     => [],
			'items'       => [],
		],

		// Attachments
		'real-media-library' => [
			'name'        => 'Real Media Library (by DevOwl)',
			'taxonomies'  => [], // Uses custom database table
			'folders'     => [],
			'items'       => [],
		],
		
		// STEP: Plugins with custom taxonomy terms

		// Attachments & post types
		'folders' => [
			'name'        => 'Folders (by Premio)',
			'taxonomies'  => [
				'attachment' => 'media_folder',
				'page'       => 'folder',
				'post'       => 'post_folder',
			],
			'folders'     => [],
			'items'       => [],
		],

		// Attachments
		'enhanced-media-library' => [
			'name'        => 'Enhanced Media Library',
			'taxonomies'  => [
				'attachment' => 'media_category',
			],
			'folders'     => [],
			'items'       => [],
		],

		// Attachments
		'wp-media-folder' => [
			'name'        => 'WP Media Folder (by JoomUnited)',
			'taxonomies'  => [
				'attachment' => 'wpmf-category'
			],
			'folders'     => [],
			'items'       => [],
		],

		// Attachments & post types
		'wicked-folders' => [
			'name'        => 'Wicked Folders',
			'taxonomies'  => [
				'any' => 'wf_{post_type}_folders',
			],
			'folders'     => [],
			'items'       => [],
		],
	];

  public function __construct() {
		add_action( 'admin_notices', [$this, 'admin_notice_folders_to_import'] );
		add_action( 'admin_init', [$this, 'admin_init'] );

		// Import HappyFiles folder structure from JSON string
		add_action( 'wp_ajax_happyfiles_import_folder_structure', [$this, 'import_folder_structure'] );

		// Plugins that use custom taxonomy terms
		add_action( 'wp_ajax_happyfiles_import_third_party_terms', [$this, 'import_third_party_terms'] );
		add_action( 'wp_ajax_happyfiles_delete_third_party_terms', [$this, 'delete_third_party_terms'] );

		// Plugins that use custom database tables
		add_action( 'wp_ajax_happyfiles_import_third_pary_tables', [$this, 'import_third_pary_tables'] );
		add_action( 'wp_ajax_happyfiles_delete_third_pary_tables', [$this, 'delete_third_pary_tables'] );
	}

	/**
	 * HappyFiles settings page: Populate $plugins data to show folder import/delete button & counts
	 */
	public function admin_init() {
		$is_settings_page = ! empty( $_GET['page'] ) ? $_GET['page'] === 'happyfiles_settings' : false;

		if ( $is_settings_page ) {
			self::get_plugins_data();
		}
	}

	/**
	 * Admin notice: Third-party plugin media folders found to import
	 */
	public function admin_notice_folders_to_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
      return;
		}

		// Return: Notification dismissed
    if ( get_option( 'happyfiles_hide_import_folders_notification', false ) ) {
      return;
    }

		// Return: Not on settings page nor HappyFiles enabled page
		$is_settings_page = ! empty( $_GET['page'] ) ? $_GET['page'] === 'happyfiles_settings' : false;

		if ( ! $is_settings_page && ! Data::$load_plugin ) {
			return;
		}

		$classes = [
			'happyfiles-notice',
      'notice',
      'notice-info',
      'is-dismissible',
		];

		// Check: At least one third-party plugin found with folders to import
		$plugins = self::get_plugins_data();
		$plugin_names = [];

		foreach ( $plugins as $slug => $plugin ) {
			if ( count( $plugin['folders'] ) ) {
				$plugin_names[] = $plugin['name'];
			}
		}

		if ( ! count( $plugin_names ) ) {
			return;
		}

		$message  = esc_html__( 'Folders from another media plugin detected to import', 'happyfiles' ) . ': ';
    $message .= '<a href="' . admin_url( 'options-general.php?page=happyfiles_settings#tab-import' ) . '">' . esc_html__( 'Import/delete folder data', 'happyfiles' ) . '</a>';
		$message .= ' [' .implode( ', ' , $plugin_names ) . ']';

    printf(
      '<div id="happyfiles-notification-import-folders" class="%s"><p>%s</p></div>',
      trim( implode( ' ', $classes ) ),
      $message
    );
	}

	/**
	 * Get folders and items data from third-party plugins to import/delete their data
	 */
	public static function get_plugins_data() {
		foreach ( self::$plugins as $slug => $plugin ) {
			$taxonomies = $plugin['taxonomies'];
			$all_folders = [];
			$all_items = [];

			// Plugins with custom database tables (FileBird, Real Media Library)
			if ( ! count( $taxonomies ) ) {
				$taxonomy = 'attachment';
				$folders = self::get_folders( $taxonomy, $slug );

				if ( is_array( $folders ) && count( $folders ) ) {
					$all_folders = self::map_folders( $folders );

					$items = self::get_items( $slug, $all_folders );
					$all_items = self::map_items( $slug, $items );
				}
			}

			// Plugins with custom taxonomy terms
			else {
				/**
				 * Wicked Folders (can have folders for any post type: wf_{post_type}_folders)
				 * 
				 * Create taxonomies array for all HappyFiles enabled post types
				 */
				if ( $slug === 'wicked-folders' ) {
					$taxonomies = [];
					
					foreach ( Settings::$enabled_post_types as $post_type ) {
						// Plugin folder import not possible (plugin IDs are custom)
						if ( $post_type === 'plugins' ) {
							continue;
						}

						$taxonomies[$post_type] = 'wf_' . $post_type . '_folders';
					}

					self::$plugins[$slug]['taxonomies'] = $taxonomies;
				}

				// Get folders & items (folderized attachments, posts, pages, etc.)
				foreach ( $taxonomies as $post_type => $taxonomy ) {
					// Skip plugin fodlers && post types not enabled for HappyFiles
					if ( $post_type === 'plugins' || ! in_array( $post_type, Settings::$enabled_post_types ) ) {
						continue;
					}

					$folders = self::get_folders( $taxonomy, $slug );

					if ( is_array( $folders ) && count( $folders ) ) {
						$all_items = array_merge( $all_items, self::get_items( $taxonomy, $folders ) );
						$all_folders = array_merge( $all_folders, $folders );
					}
				}
			}

			self::$plugins[$slug]['folders'] = $all_folders;
			self::$plugins[$slug]['items'] = $all_items;
		}

		return self::$plugins;
	}

	/**
	 * Get folders from third-party plugins
	 */
	public static function get_folders( $taxonomy, $slug ) {
		global $wpdb;

		$folders = [];

		switch ( $slug ) {
			// FileBird: Custom database table
			case 'filebird':
				$filebird_folders_table = $wpdb->prefix . 'fbv';

				// Get FileBird folders (order by 'parent' to create parent categories first)
				if ( $wpdb->get_var( "SHOW TABLES LIKE '$filebird_folders_table'") == $filebird_folders_table ) {
					$folders = $wpdb->get_results( "SELECT * FROM $filebird_folders_table ORDER BY parent ASC" );
				}
			break;

			// Real Media Library: Custom database table
			case 'real-media-library':
				$rml_folders_table = $wpdb->prefix . 'realmedialibrary';

				// Get FileBird folders (order by 'parent' to create parent categories first)
				if ( $wpdb->get_var( "SHOW TABLES LIKE '$rml_folders_table'") == $rml_folders_table ) {
					$folders = $wpdb->get_results( "SELECT * FROM $rml_folders_table ORDER BY parent ASC" );
				}
			break;

			// Default: Custom taxonomy terms (order by parent to create root folders first)
			default:
				$folders = $wpdb->get_results(
					"SELECT * FROM " . $wpdb->term_taxonomy . "
					LEFT JOIN  " . $wpdb->terms . "
					ON  " . $wpdb->term_taxonomy . ".term_id =  " . $wpdb->terms . ".term_id
					WHERE " . $wpdb->term_taxonomy . ".taxonomy = '" . $taxonomy . "'
					ORDER BY parent ASC"
				);

				// WP Media Folder (JoomUnited): Remove root folder from import
				if ( $slug === 'wp-media-folder' ) {
					foreach ( $folders as $index => $folder ) {
						if ( $folder->slug === 'wp-media-folder-root' ) {
							unset( $folders[$index] );
						}
					}
				}
			break;
		}

		return is_array( $folders ) ? array_values( $folders ) : [];
	}

	/**
	 * Get categorized items from third-party plugins
	 */
	public static function get_items( $taxonomy, $folders ) {
		global $wpdb;

		// FileBird has its own custom database table
		if ( $taxonomy === 'filebird' ) {
			$filebird_attachments_table = $wpdb->prefix . 'fbv_attachment_folder';

			// Get FileBird attachments (order by 'folder_id')
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$filebird_attachments_table'") == $filebird_attachments_table ) {
				return $wpdb->get_results( "SELECT * FROM $filebird_attachments_table ORDER BY folder_id ASC" );
			}
		}

		// Real Media Library has its own custom database table
		else if ( $taxonomy === 'real-media-library' ) {
			$rml_attachments_table = $wpdb->prefix . 'realmedialibrary_posts';

			// Get FileBird folders (order by 'parent' to create parent categories first)
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$rml_attachments_table'") == $rml_attachments_table ) {
				return $wpdb->get_results( "SELECT * FROM $rml_attachments_table ORDER BY fid ASC" );
			}
		}

		// Default: Plugins with custom taxonomy terms
		else {
			if ( is_array( $folders ) && count( $folders ) ) {
				return $wpdb->get_results(
					"SELECT  " . $wpdb->term_relationships . ".object_id,
					" . $wpdb->term_relationships . ".term_taxonomy_id
					FROM " . $wpdb->term_relationships . "
					WHERE " . $wpdb->term_relationships . ".term_taxonomy_id IN (" . implode( ',', array_column( $folders, 'term_id' ) ) . ")"
				);
			}
		}
	}

	/**
	 * Map folders to prepare for import from plugins that use their own database table: FileBird, Real Media Library
	 */
	public static function map_folders( $folders ) {
		$mapped_folders = [];

		foreach ( $folders as $folder ) {
			$folder_object = new \stdClass();

			$folder_object->name = $folder->name;
			$folder_object->id = intval( $folder->id );
			$folder_object->parent = intval( $folder->parent );
			$folder_object->position = intval( $folder->ord );

			$mapped_folders[] = $folder_object;
		}

		return $mapped_folders;
	}

	/**
	 * Map items to import items from plugins that use their own db table: FileBird, Real Media Library
	 */
	public static function map_items( $slug, $items ) {
		$mapped_items = [];

		foreach ( $items as $folder ) {
			// FileBird
			if ( $slug === 'filebird' ) {
				$folder_object = new \stdClass();

				$folder_object->folder_id = intval( $folder->folder_id );
				$folder_object->attachment_id = intval( $folder->attachment_id );

				$mapped_items[] = $folder_object;
			}

			// Real Media Library
			if ( $slug === 'real-media-library' ) {
				$folder_object = new \stdClass();

				$folder_object->folder_id = intval( $folder->fid );
				$folder_object->attachment_id = intval( $folder->attachment );

				$mapped_items[] = $folder_object;
			}
		}

		return $mapped_items;
	}

	/**
	 * Import HappyFiles folder structure from JSON string
	 * 
	 * @since 1.7
	 */
	public function import_folder_structure() {
		// Parse JSON string
		$folders = stripslashes( $_POST['folders'] );
		$folders = json_decode( $folders, true );

		// Return: No folders to import
		if ( ! is_array( $folders ) || ! count( $folders ) ) {
			wp_send_json_error( ['message' => esc_html__( 'Nothing to import', 'happyfiles' )] );
		}

		$folders_old_id_new_id = []; // key = old folder ID; value = new folder ID (to set parent from newly created folder)
		$imported_folders = [];

		foreach ( $folders as $folder ) {
			$taxonomy = ! empty( $folder['taxonomy'] ) ? $folder['taxonomy'] : false;

			// Skip folder if taxonomy does not exist on this installation
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$folder_id = $folder['term_id'];
			$folder_name = $folder['name'];
			$parent_id = $folder['parent'];

			// Get new folder parent ID
			if ( $parent_id && ! empty( $folders_old_id_new_id[$parent_id] ) ) {
				$parent_id = $folders_old_id_new_id[$parent_id];

				// Check if new parent ID exists
				if ( ! term_exists( $parent_id, $taxonomy ) ) {
					$parent_id = 0;
				}
			}

			// Add new folder term to database
			$new_folder = wp_insert_term( $folder_name, $taxonomy, ['parent' => $parent_id] );

			// Skip position as folder creation failed
			if ( is_wp_error( $new_folder ) ) {
				$imported_folders[] = [
					'error'       => $new_folder,
					'folder'      => $folder,
					'folder_name' => $folder_name,
					'parent'      => $parent_id,
					'taxonomy'    => $taxonomy,
				];

				continue;
			}

			$folders_old_id_new_id[$folder_id] = $new_folder['term_id'];

			$imported_folders[] = [
				'folder'      => $folder,
				'folder_name' => $folder_name,
				'new_folder'  => $new_folder,
				'parent'      => $parent_id,
				'taxonomy'    => $taxonomy,
			];

			// Set folder position
			if ( ! empty( $folder['position'] ) ) {
				$position_updated = update_term_meta( $new_folder['term_id'], HAPPYFILES_POSITION, $folder['position'] );
			}
		}

		wp_send_json_success( [
			'folders'               => $folders,
			'imported_folders'      => $imported_folders,
			'folders_old_id_new_id' => $folders_old_id_new_id,
			'message'               => sprintf( esc_html__( '%s folders successfully imported.', 'happyfiles' ), count( $folders_old_id_new_id ) ),
		] );
	}

	/**
	 * Import custom taxonomy terms from third-party plugins
	 */
	public function import_third_party_terms() {
		$plugins = self::get_plugins_data();
		$plugin_name = ! empty( $_POST['plugin'] ) ? $_POST['plugin'] : false;
		$plugin = $plugins[$plugin_name];
		$folders = $plugin['folders'];
		$items = $plugin['items'];

		$new_folder_by_id = [];
		$folders_imported = [];
		$items_imported = [];

		// STEP: Create HappyFiles folders
		foreach ( $folders as $folder ) {
			$folder_post_type = array_search( $folder->taxonomy, $plugin['taxonomies'] );
			$folder_taxonomy = $folder->taxonomy;
			$folder_id = $folder->term_id;
			$parent_id = intval( $folder->parent );

			$happyfiles_taxonomy = $folder_post_type === 'attachment' ? HAPPYFILES_TAXONOMY : "hf_cat_$folder_post_type";

			if ( $parent_id && isset( $new_folder_by_id[$parent_id]['term_id'] ) ) {
				$parent_id = intval( $new_folder_by_id[$parent_id]['term_id'] );
			}

			// Create new HappyFiles folder
			$new_happyfiles_folder = wp_insert_term( $folder->name, $happyfiles_taxonomy, ['parent' => $parent_id] );

			// Skip: Folder creation failed
			if ( is_wp_error( $new_happyfiles_folder ) ) {
				continue;
			}

			// Save HappyFiles position as term meta (Plugins: Folders)
			if ( in_array( $plugin_name, ['folders'] ) ) {
				$position_meta_key = false;

				switch ( $plugin_name ) {
					case 'folders':
						$position_meta_key = 'wcp_custom_order';
					break;
				}

				$position = get_term_meta( $folder_id, $position_meta_key, true );

				if ( $position_meta_key && $position !== '' ) {
					update_term_meta( $new_happyfiles_folder['term_id'], HAPPYFILES_POSITION, $position );
				}
			}

			$folders_imported[] = $new_happyfiles_folder;

			$new_folder_by_id[$folder_id] = [
				'term_id' => $new_happyfiles_folder['term_id'],
				'parent'  => $parent_id,
				'name'    => $folder->name,
			];
		}

		// STEP: Assign folders to items
		foreach ( $items as $item ) {
			$happyfiles_folder_id = isset( $new_folder_by_id[$item->term_taxonomy_id]['term_id'] ) ? intval( $new_folder_by_id[$item->term_taxonomy_id]['term_id'] ) : 0;
			$item_id = isset( $item->object_id ) ? intval( $item->object_id ) : 0;

			if ( ! $happyfiles_folder_id || ! $item_id ) {
				continue;
			}

			// Get attachment taxonomy by post type
			$post_type = get_post_type( $item_id );
			$taxonomy = $post_type === 'attachment' ? HAPPYFILES_TAXONOMY : "hf_cat_$post_type";

			$folder_ids = wp_get_object_terms( $item_id, $taxonomy, ['fields' => 'ids'] );
			$folder_ids[] = $happyfiles_folder_id;

			$term_set = wp_set_object_terms( $item_id, $folder_ids, $taxonomy );

			if ( is_wp_error( $term_set ) ) {
				continue;
			}

			wp_update_term_count_now( $folder_ids, $taxonomy );

			$items_imported[] = [
				'cat_id'   => $happyfiles_folder_id,
				'term_ids' => $folder_ids,
				'set'      => $term_set,
			];
		}

		wp_send_json_success( [
			'message' => sprintf(
				'<span>' . esc_html__( '%s folders imported and %s items categorized.', 'happyfiles' ) . '</span>',
				count( $folders_imported ) . '/' . count( $folders ),
				count( $items_imported ) . '/' . count( $items )
			) . '<a href="' . admin_url( 'upload.php' )  . '" target="_blank" class="button button-large">' . esc_html__( 'View media library', 'happyfiles' ) . '</a>',
		] );
	}

	/**
	 * Delete custom taxonomy terms from third-party plugins
	 */
	public function delete_third_party_terms() {
		$plugins = self::get_plugins_data();
		$plugin_name = ! empty( $_POST['plugin'] ) ? $_POST['plugin'] : false;
		$plugin = $plugins[$plugin_name];
		$folders = $plugin['folders'];
		$items = $plugin['items'];
		$deleted = [];

		global $wpdb;

		foreach ( $folders as $folder ) {
			$folder_id = intval( $folder->term_id );

			if ( $folder_id ) {
				$deleted[$folder_id]['term_taxonomy'] = $wpdb->delete( $wpdb->prefix . 'term_taxonomy', ['term_id' => $folder_id] );
				$deleted[$folder_id]['terms'] = $wpdb->delete( $wpdb->prefix . 'terms', ['term_id' => $folder_id] );
				$deleted[$folder_id]['termmeta'] = $wpdb->delete( $wpdb->prefix . 'termmeta', ['term_id' => $folder_id] );

				// Folder position
				if ( $plugin_name === 'folders' ) {
					$deleted[$folder_id]['term_relationships'] = $wpdb->delete( $wpdb->prefix . 'term_relationships', ['term_taxonomy_id' => $folder_id] );
				}
			}
		}

		wp_send_json_success( [
			'deleted' => $deleted,
			'post'    => $_POST,
		] );
	}

	/**
	 * Import data from third-party plugins with custom db table
	 * 
	 * - FileBird
	 * - Real Media Library
	 * 
	 * - Folders (attachments, post types)
	 */
	public static function import_third_pary_tables() {
		$plugins = self::get_plugins_data();
		$plugin_name = ! empty( $_POST['plugin'] ) ? $_POST['plugin'] : false;
		$plugin = $plugins[$plugin_name];
		$folders = $plugin['folders'];
		$items = $plugin['items'];

		$folders_imported = [];
		$items_imported = [];
		$new_folder_by_id = [];

		// STEP: Create HappyFiles folders from plugin folders
		foreach ( $folders as $folder ) {
			$parent_id = intval( $folder->parent );
			$happyfiles_parent = 0;

			if ( $parent_id && isset( $new_folder_by_id[$parent_id]['term_id'] ) ) {
				$happyfiles_parent = $new_folder_by_id[$parent_id]['term_id'];
			}

			// Create new HappyFiles folder from plugin
			$new_happyfiles_folder = wp_insert_term( $folder->name, HAPPYFILES_TAXONOMY, ['parent' => $happyfiles_parent] );

			// Skip if folder couldn't be created
			if ( is_wp_error( $new_happyfiles_folder ) ) {
				continue;
			}

			// Save folder position (termmeta)
			update_term_meta( $new_happyfiles_folder['term_id'], HAPPYFILES_POSITION, intval( $folder->position ) );

			$folders_imported[] = $new_happyfiles_folder;

			$new_folder_by_id[$folder->id] = [
				'name'    => $folder->name,
				'parent'  => $parent_id,
				'term_id' => $new_happyfiles_folder['term_id'],
			];
		}

		// STEP: Assign plugin folders to HappyFiles folders
		foreach ( $items as $item ) {
			$happyfiles_folder_id = isset( $new_folder_by_id[$item->folder_id]['term_id'] ) ? intval( $new_folder_by_id[$item->folder_id]['term_id'] ) : 0;
			$item_id = isset( $item->attachment_id ) ? intval( $item->attachment_id ) : 0;

			if ( ! $happyfiles_folder_id || ! $item_id ) {
				continue;
			}

			$folder_ids = wp_get_object_terms( $item_id, HAPPYFILES_TAXONOMY, ['fields' => 'ids'] );
			$folder_ids[] = $happyfiles_folder_id;

			$term_set = wp_set_object_terms( $item_id, $folder_ids, HAPPYFILES_TAXONOMY );

			if ( is_wp_error( $term_set ) ) {
				continue;
			}

			$items_imported[] = [
				'cat_id'   => $happyfiles_folder_id,
				'term_ids' => $folder_ids,
				'set'      => $term_set,
			];
		}

		wp_send_json_success( [
			'message' => sprintf(
				'<span>' . esc_html__( '%s folders imported and %s items categorized.', 'happyfiles' ) . '</span>',
				count( $folders_imported ) . '/' . count( $folders ),
				count( $items_imported ) . '/' . count( $items )
			) . '<a href="' . admin_url( 'upload.php' )  . '" target="_blank" class="button button-large">' . esc_html__( 'View media library', 'happyfiles' ) . '</a>',
		] );
	}

	/**
	 * Delete data from third-party plugins with custom db table
	 * 
	 * - FileBird
	 * - Real Media Library
	 */
	public static function delete_third_pary_tables() {
		$plugins = self::get_plugins_data();
		$plugin_name = ! empty( $_POST['plugin'] ) ? $_POST['plugin'] : false;
		$plugin = $plugins[$plugin_name];
		$folders = $plugin['folders'];
		$items = $plugin['items'];

		$folders_deleted = false;
		$items_deleted = false;
		
		global $wpdb;

		// Delete custom folders table(s) from database
		if ( count( $folders ) ) {
			switch ( $plugin_name ) {
				case 'filebird':
					$folders_deleted = $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fbv" );
				break;

				case 'real-media-library':
					$folders_deleted = $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}realmedialibrary" );
				break;
			}
		}

		// Delete custom table(s) from database
		if ( count( $items ) ) {
			switch ( $plugin_name ) {
				case 'filebird':
					$items_deleted = $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fbv_attachment_folder" );
				break;

				case 'real-media-library':
					$items_deleted = $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}realmedialibrary_meta" );
					$items_deleted = $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}realmedialibrary_posts" );
					$items_deleted = $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}realmedialibrary_tmp" );
				break;
			}
		}

		wp_send_json_success( [
			'plugin_name'     => $plugin_name,
			'folders_deleted' => $folders_deleted,
			'items_deleted'   => $items_deleted,
		] );
	}
}
