<?php

/**
 * Setup the theme on activation based on settings
 *
 * @package html2wp/simple-wp-starter-theme
 */


// This hook is triggered on the request immediately following a theme switch.
add_filter('after_switch_theme', 'html2wp_theme_activation');

// Create images as WordPress attachments when the theme is activated
add_filter('after_switch_theme', 'html2wp_create_image_attachments');

// This hook is called during each page load, after the theme is initialized.
// It is generally used to perform basic setup, registration, and init actions for a theme.
add_action( 'after_setup_theme', 'html2wp_register_content' );
add_action( 'after_setup_theme', 'html2wp_setup_theme_support' );

// Register the required plugins for this theme
add_action( 'tgmpa_register', 'html2wp_register_required_plugins' );

// Perform setup after this theme is activated
add_action( 'after_switch_theme', 'html2wp_setup_theme_components' );
add_action( 'after_switch_theme', 'html2wp_setup_menu_links' );
add_action( 'after_switch_theme', 'html2wp_set_posts_per_page' );

// Perform theme de-activation routines
add_action( 'switch_theme', 'html2wp_reset_posts_per_page' );

// Perform theme setup after Gravity forms is installed
add_action( 'activated_plugin', 'html2wp_detect_plugin_activation' );

// Prevent the default WP widgets init routine
add_action( 'after_setup_theme', 'html2wp_remove_default_widgets_remove_action' );

// Since we prevented the default WP Widgets Init, need to perform regular Widgets Init
// see https://developer.wordpress.org/reference/functions/wp_widgets_init/
add_action( 'init', 'html2wp_remove_default_widgets_do_widgets_init', 1 );

// Start creating the custom post types and taxonomies
add_action( 'init', 'html2wp_setup_custom_post_types_taxonomies' );

// If necessary create theme folder rewrite rule on theme activation (for wp cli)
// and on admin_init if the theme folder name changes. The rewrite rules flush works only in the wp dashboard.
add_action( 'after_switch_theme', 'html2wp_add_rewrite_to_theme_folder' );
add_action( 'admin_init', 'html2wp_update_rewrite_to_theme_folder' );

/**
 * Read the theme configurations from json
 * @return array
 */
function html2wp_get_theme_settings() {
	return json_decode( file_get_contents( get_stylesheet_directory() . '/html2wp/json/settings.json' ), true );
}

/**
 * Set up the theme on activation
 */
function html2wp_theme_activation() {

	/**
	 * Gets us the settings
	 */
	$html2wp_settings = html2wp_get_theme_settings();

	/**
	 * Set up pages
	 */
	foreach ( $html2wp_settings['pages'] as $page_data ) {

		/**
		 * The pages matching the template if any exist
		 * @var array
		 */
		$pages = get_pages( array( 'meta_key' => '_wp_page_template', 'meta_value' => $page_data['template'] ) );

		/**
		 * Get the page id if a page exists and is not in the trash
		 */
		if ( ! empty( $pages ) && isset( $pages[0] ) && 'trash' !== $pages[0]->post_status ) {
			$page_id = $pages[0]->ID;
		}

		/**
		 * Else create the page
		 */
		else {

			// Create post object
			$new_page = array(
				'post_title'    => $page_data['title'],
				'post_name'     => $page_data['slug'],
				'page_template' => $page_data['template'],
				'post_type'     => 'page',
				'post_status'   => 'publish',
			);

			// Insert the post into the database
			$page_id = wp_insert_post( $new_page );
		}

		/**
		 * If we area dealing with home page and everything went smoothly set it as front page
		 */
		if ( 'front-page' === $page_data['slug'] && is_numeric( $page_id ) && $page_id > 0 ) {

			// Use a static front page
			update_option( 'page_on_front', $page_id );
			update_option( 'show_on_front', 'page' );
		}
	}
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 */
function html2wp_register_content() {

	/**
	 * Gets us the settings
	 */
	$html2wp_settings = html2wp_get_theme_settings();

	/**
	 * Register widgets
	 */
	foreach ( $html2wp_settings['widgets'] as $widget ) {
		register_sidebar( $widget );
	}

	/**
	 * Register menus
	 */
	foreach ( $html2wp_settings['menus']['locations'] as $menu_location => $menu_name ) {
		register_nav_menus( array( $menu_location => $menu_name ) );
	}
}

/**
 * Setup theme's supported features
 */
function html2wp_setup_theme_support() {

	// Support for post thumbnails a.k.a featured images
	add_theme_support( 'post-thumbnails' );

}

/**
 * Register the required plugins for this theme
 */
function html2wp_register_required_plugins() {

	/**
	 * Gets us the settings
	 */
	$html2wp_settings = html2wp_get_theme_settings();

	$plugins = array(

		array(
			'name'             => 'Simple Live Editor',
			'slug'             => 'simple-live-editor',
			'source'           => 'https://github.com/html2wp/simple-live-editor/archive/master.zip',
			'required'         => true,
			'force_activation' => true,
		)

	);

	if ( isset( $html2wp_settings['forms'] ) && ! empty( $html2wp_settings['forms'] ) ) {
		$plugins[] = array(
			'name'             => 'Gravity Forms',
			'slug'             => 'gravityforms',
			'source'           => 'https://github.com/wp-premium/gravityforms/archive/master.zip',
			'required'         => true,
			'force_activation' => true,
		);
	}

	tgmpa( $plugins );
}

/**
 * Checks for gravity forms plugin and then builds the first gravity form
 * after the theme is activated.
 */
function html2wp_setup_theme_components() {

	/**
	 * Disable the gravity forms installation wizard
	 * as it conflicts with auto setupof forms
	 */
	update_option( GRAVITY_PENDING_INSTALLATION, -1 );

	//check if the Gravity forms plugin is active
	if ( class_exists( 'GFForms' ) ) {

		/**
		 * Gravity forms is active
		 * Process the setup methods
		 * these should occur each time a theme is activated,
		 * as it could a totally different theme.
		 */
		html2wp_setup_gravity_contact_form();
		delete_option( GRAVITY_PENDING_INSTALLATION );
	}

}

/**
 * If the theme has a menu (or more) and the menu locations
 * were defined during conversion, then we try to automatically
 * create the menu and add it to the corresponding location
 */
function html2wp_setup_menu_links() {

	// The lazyloader needs to be included separately,
	// or otherwise wp 4.5.2 won't find it when theme is activated with wp cli
	$lazyloader_path = ABSPATH . WPINC . '/class-wp-metadata-lazyloader.php';
	if ( file_exists( $lazyloader_path ) ) {
		require_once( $lazyloader_path );
	}

	/**
	 * Gets us the settings
	 */
	$html2wp_settings = html2wp_get_theme_settings();

	/**
	 * Set up menus
	 *
	 * Go through all the menu links and create the menu at the corresponding
	 * Menu location. If a location does not exist, let it go.
	 */
	foreach ( $html2wp_settings['menus']['links'] as $menu_location => $menu_links ) {

		// get the pretty name of the menu
		$menu_name = $html2wp_settings['menus']['locations'][$menu_location];

		// Does the menu exist already?
		$menu_exists = wp_get_nav_menu_object( $menu_name );

		// If it doesn't exist, let's create it.
		if( ! $menu_exists ) {

			$menu_id = wp_create_nav_menu( $menu_name );

			// setup the links and add them to the menu.
			foreach ( $menu_links as $link ) {

				// $link has two indices
				// index 0 = anchor
				// index 1 = submenu, if it exists
				$slug = '';

				// is this slug a hash anchor or an actual link
				$is_hash = true;

				// get the page slug for this menu link
				foreach ( $html2wp_settings['pages'] as $page ) {
					if ( $link[0]['link'] === $page['file_name'] ) {
						$slug = $page['slug'];
						$is_hash = false;
						break;
					}
				}

				if ( $is_hash ) {
					// this is a hash anchor, so set it accordingly
					$slug = $link[0]['link'];
				}

				// Update the menu item
				if ( ! empty( $slug ) ) {

					if ( $is_hash ) {

						$menu_item = array(
							'menu-item-title'     => $link[0]['text'],
							'menu-item-url'       => site_url( '/' . $slug ),
							'menu-item-status'    => 'publish',
							'menu-item-parent-id' => 0,
						);

						$main_menu = wp_update_nav_menu_item( $menu_id, 0, $menu_item );

					} else {

						$menu_item = array(
							'menu-item-title'     => $link[0]['text'],
							'menu-item-object'    => 'page',
							'menu-item-object-id' => get_page_by_path( $slug )->ID,
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
							'menu-item-parent-id' => 0,
						);

						// this is an actual url with a page, so get the slug and set the
						// menu-item-object-id to the slug id
						$main_menu = wp_update_nav_menu_item( $menu_id, 0, $menu_item );
					}

					// check if a submenu exists
					if ( array_key_exists( 1, $link ) ) {

						// now add the sub menus, if any exist
						foreach ( $link[1] as $sub_menu_link ) {

							$slug = '';
							$is_hash = true;

							// get the page slug for this menu link
							foreach ( $html2wp_settings['pages'] as $page ) {
								if ( $sub_menu_link['link'] === $page['file_name'] ) {
									$slug = $page['slug'];
									$is_hash = false;
									break;
								}
							}

							if ( $is_hash ) {
								$slug = $sub_menu_link['link'];
							}

							// Update the sub menu item
							if ( ! empty( $slug ) ) {

								if ( $is_hash ) {

									$menu_item = array(
										'menu-item-title'     => $sub_menu_link['text'],
										'menu-item-url'       => site_url( '/' . $slug ),
										'menu-item-status'    => 'publish',
										'menu-item-parent-id' => $main_menu,
									);

									wp_update_nav_menu_item( $menu_id, 0, $menu_item );

								} else {

									$menu_item = array(
										'menu-item-title'     => $sub_menu_link['text'],
										'menu-item-object'    => 'page',
										'menu-item-object-id' => get_page_by_path( $slug )->ID,
										'menu-item-type'      => 'post_type',
										'menu-item-status'    => 'publish',
										'menu-item-parent-id' => $main_menu,
									);

									// this is an actual url with a page, so get the slug and set the
									// menu-item-object-id to the slug id
									wp_update_nav_menu_item( $menu_id, 0, $menu_item );
								}
							}
						}
					}
				}
			}

			// Grab the theme locations and assign our newly-created menu
			// to the menu location.
			if ( ! has_nav_menu( $menu_location ) ) {
				$locations = get_theme_mod( 'nav_menu_locations' );
				$locations[$menu_location] = $menu_id;
				set_theme_mod( 'nav_menu_locations', $locations );
			}
		}
	}
}

/**
 * Peforms contact form setup after Gravity forms plugin is activated
 * @param  string $plugin The name of the plugin that was activated
 */
function html2wp_detect_plugin_activation( $plugin ) {

	/**
	 * this will take place in the event user does not have gravity
	 * forms already installed and imo this will be the most common case
	 */
	$gf_plugin_name = 'Gravity Forms';

	//get the details of the plugin which was just activated
	$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );

	//if it was the Gravity Forms plugin
	if ( $gf_plugin_name === $plugin_data['Name'] ) {

		/**
		 * Since we disable the GFForms wizard, the required
		 * tables are not created.
		 */
		GFForms::setup_database();

		/**
		 * Disable the gravity forms installation wizard
		 * as it conflicts with auto setupof forms
		 */
		update_option( GRAVITY_PENDING_INSTALLATION, -1 );

		/**
		 * check if a GF contact form has already been created
		 * if yes then deactivating or reactivating should not
		 * process the setup methods. These methods should be
		 * processed only if a GF contact form was not already
		 * created by the theme activation hook.
		 */
		if ( get_option( HTML2WP_FORM_CREATED, -1 ) === -1 ) {
			html2wp_setup_gravity_contact_form();
			delete_option( GRAVITY_PENDING_INSTALLATION );
		}
	}
}

/**
 * Peforms the widgets_init action
 */
function html2wp_remove_default_widgets_do_widgets_init() {
	do_action( 'widgets_init' );
}

/**
 * Removes the wp_widgets_init action
 */
function html2wp_remove_default_widgets_remove_action() {
	remove_action( 'init', 'wp_widgets_init', 1 );
}

/**
 * Update rewrite rule for retrieving hardcoded assets from the theme folder
 */
function html2wp_update_rewrite_to_theme_folder() {

	// Get name of theme folder
	$theme_name = get_template();

	// If the name of the theme folder has changed update rewrite rule
	if ( get_option( 'html2wp_theme_name' ) !== $theme_name ) {

		html2wp_add_rewrite_to_theme_folder();

		// Update rewrite rules
		flush_rewrite_rules();

		// Update the theme name to database
		update_option( 'html2wp_theme_name', $theme_name, true );
	}
}

/**
 * Add rewrite rule for retrieving hardcoded assets from the theme folder
 */
function html2wp_add_rewrite_to_theme_folder() {

	// We need the file.php to get the true home path
	require_once( ABSPATH . 'wp-admin/includes/file.php' );

	// Get relative theme path
	$theme_path = html2wp_replace_first_occurrence( get_template_directory(), get_home_path(), '' );

	// Add the rewrite rule
	add_rewrite_rule( '(?!wp-.*|xmlrpc.php.*|index.php.*)(.*\..*)', $theme_path . '/$1', 'bottom' );
}

/**
 * sets the posts_per_page option to a specified number from the
 * html2wp settings global array
 */
function html2wp_set_posts_per_page() {

	/**
	 * Gets us the settings
	 */
	$html2wp_settings = html2wp_get_theme_settings();

	/**
	 * Check if the posts_per_page setting is available
	 * if yes, then get the current WP posts_per_page option
	 * save it in a different wp_option for use later
	 * and update the WP posts_per_page option to the value
	 * from the setting
	 */

	// the check for 0 exists because of the default value of the global setting array for the same key
	if ( isset( $html2wp_settings['posts_per_page'] ) && ! empty( $html2wp_settings['posts_per_page'] )
			&& is_int( $html2wp_settings['posts_per_page'] ) && ( $html2wp_settings['posts_per_page'] >= 0 ) ) {

		$posts_per_page = get_option( 'posts_per_page' );

		// Update only if the WP posts_per_page option is greater than the html2wp
		// converted theme posts_per_page value: see http://stackoverflow.com/q/4600357/303802
		if ( $posts_per_page > $html2wp_settings['posts_per_page'] ) {

			// Get the current WP posts_per_page option and save it for later use
			update_option( 'html2wp_posts_per_page', $posts_per_page );

			// Update the WP posts_per_page option with the html2wp setting value
			update_option( 'posts_per_page', $html2wp_settings['posts_per_page'] );
		}

	}
}

/**
 * re-sets the posts_per_page option to it's original value as it was before
 * the converted HTML2WP theme was installed
 */
function html2wp_reset_posts_per_page() {

	// Update the WP posts_per_page option with the original value
	$html2wp_posts_per_page = get_option( 'html2wp_posts_per_page' );

	if ( $html2wp_posts_per_page ) {
		update_option( 'posts_per_page', $html2wp_posts_per_page );
	}
}

/**
 * Adds images from the website conversion to the WP theme
 * image attachments gallery
 */
function html2wp_create_image_attachments() {

	$html2wp_settings = html2wp_get_theme_settings();

	if ( !empty( $html2wp_settings['attachments'] ) ) {

		// go through all the attachments and create the media attachments in wordpress
		foreach ( $html2wp_settings['attachments'] as $file ) {

			// Get the path to the upload directory.
			$wp_upload_dir = wp_upload_dir();

			// If wp_upload_dir succeeds
			if ( ! $wp_upload_dir['error'] ) {

				// $new_file should be the path to a file in the upload directory.
				$new_file = trailingslashit( $wp_upload_dir['path'] ) . basename( $file );

				$file = get_template_directory() . '/' . $file;

				// Check that the file exists
				if ( file_exists( $file ) ) {

					// Move the file to uploaddir
					if ( copy( $file, $new_file ) ) {

						// Check the type of file. We'll use this as the 'post_mime_type'.
						$filetype = wp_check_filetype( basename( $new_file ), null );

						// Prepare an array of post data for the attachment.
						$attachment_data = array(
							'guid'           => trailingslashit( $wp_upload_dir['url'] ) . basename( $new_file ),
							'post_mime_type' => $filetype['type'],
							'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $new_file ) ),
							'post_content'   => '',
							'post_status'    => 'inherit'
						);

						// Insert the attachment without any parent
						$attach_id = wp_insert_attachment( $attachment_data, $new_file, 0 );

						// If wp_insert_attachment was successful
						if ( $attach_id ) {

							// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
							require_once( ABSPATH . 'wp-admin/includes/image.php' );

							// Generate the metadata for the attachment
							$attach_data = wp_generate_attachment_metadata( $attach_id, $new_file );

							// Update the database record
							wp_update_attachment_metadata( $attach_id, $attach_data );
						}
					}
				}
			}
		}
	}
}

/**
 * Reads the global settings from html2wp and creates any
 * new custom post types and / or categories if needed
 */
function html2wp_setup_custom_post_types_taxonomies() {

	/**
	 * Gets us the settings
	 */
	$html2wp_settings = html2wp_get_theme_settings();

	if ( isset( $html2wp_settings['taxonomies'] ) && ! empty( $html2wp_settings['taxonomies'] ) ) {

		// Loop through all the custom post type names in settings
		// and create them if they do not exist already
		foreach ( $html2wp_settings['taxonomies'] as $post_type => $taxonomies ) {

			// create a new custom post type if it does not exist
			if ( $post_type !== 'post' ) {

				register_post_type( $post_type,
					array(
						'labels'            => array(
							'name'          => __( ucfirst( $post_type ) ),
							'singular_name' => __( $post_type ),
						),
						'supports'          => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions', 'custom-fields'),
						'show_ui'           => true,
						'show_in_menu'      => true,
						'show_in_nav_menus' => true,
						'show_in_admin_bar' => true,
						'taxonomies'        => array( 'category' ),
						'public'            => true,
						'has_archive'       => true,
						'rewrite'           => array(
							'slug' => strtolower( $post_type ),
						),
					)
				);

				// Register the taxonomies
				register_taxonomy( $post_type . '_categories',
					$post_type,
					array (
						'label' => ucfirst( $post_type ) . ' Categories',
						'hierarchical' => true // This makes the terms for this custom post type categories appear as a checkbox
					)
				);
				register_taxonomy_for_object_type ( $post_type . '_categories', $post_type );

				// Insert the terms in the DB if they do not already exist
				// $taxonomies actually contains the terms
				foreach ( $taxonomies as $term ) {

					// if the term does not exist already for this taxonomy,
					// then insert it into the db
					if ( !term_exists( $term, $post_type . '_categories' )
						&& strtolower( $term ) != 'uncategorized' ) {

						wp_insert_term(
							$term,   // the term
							$post_type . '_categories', // the category for custom post type
							array (
								'slug' => $term
							)
						);
					}

				}

			} else {

				// create categories for the Post type
				foreach ( $taxonomies as $term ) {

					// if the term already does not exist in the database
					if ( !term_exists( $term, 'category' ) ) {

						wp_insert_term(
							$term,
							'category'
						);
					}
				}
			}
		}
	}
}