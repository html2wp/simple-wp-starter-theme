<?php

/**
 * Setup the theme on activation based on settings
 *
 * @package html2wp/simple-wp-starter-theme
 */


// This hook is triggered on the request immediately following a theme switch.
add_filter('after_switch_theme', 'html2wp_theme_activation');

// This hook is called during each page load, after the theme is initialized.
// It is generally used to perform basic setup, registration, and init actions for a theme.
add_action( 'after_setup_theme', 'html2wp_register_content' );
add_action( 'after_setup_theme', 'html2wp_setup_theme_support' );

// Register the required plugins for this theme
add_action( 'tgmpa_register', 'html2wp_register_required_plugins' );

// Perform setup after this theme is activated
add_action( 'after_switch_theme', 'html2wp_setup_theme_components' );

// Perform theme setup after Gravity forms is installed
add_action( 'activated_plugin', 'html2wp_detect_plugin_activation' );


/**
 * Holds the theme configurations, which are read from json
 * @var array
 */
$html2wp_settings = json_decode( file_get_contents( get_stylesheet_directory() . '/html2wp/json/settings.json' ), true );

/**
 * Set up the theme on activation
 */
function html2wp_theme_activation() {

	/**
	 * Gets us the settings from global scope
	 */
	global $html2wp_settings;

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
	 * Gets us the settings from global scope
	 */
	global $html2wp_settings;

	/**
	 * Register widgets
	 */
	foreach ( $html2wp_settings['widgets'] as $widget ) {
		register_sidebar( $widget );
	}

	/**
	 * Register menus
	 */
	foreach ( $html2wp_settings['menus'] as $menu ) {
		register_nav_menus( $menu );
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
	 * Gets us the settings from global scope
	 */
	global $html2wp_settings;	

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