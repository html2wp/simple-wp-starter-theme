<?php

/**
 * Extend wp
 *
 * @package html2wp/simple-wp-starter-theme
 */


// Extend the default WP title function
add_filter( 'wp_title', 'html2wp_extend_wp_title' );

// Extend the default WP menu function
add_filter( 'wp_nav_menu_args', 'html2wp_extend_wp_nav_menu_args' );

// Add an additional active class to the currently selected menu item
add_filter( 'nav_menu_css_class', 'html2wp_add_active_class_to_menu_item' );


/**
 * Extend the wp_title function to fix empty title on homepage an to include site description
 * @see         https://codex.wordpress.org/Function_Reference/wp_title#Covering_Homepage
 * @param       string   $title The default wp_title
 * @return      string   Returns the extended wp_title
 */
function html2wp_extend_wp_title( $title ) {

	// Fix empty homepage title
	if ( empty( $title ) && ( is_home() || is_front_page() ) ) {
		$title = get_bloginfo( 'name' ) . ' | ';
	}

	// Append site description to title
	return $title . get_bloginfo( 'description' );
}

/**
 * Extend the wp_nav_menu functions default arguments to better suit our needs
 * @param      array   The default wp_nav_menu arguments
 * @return     array   Returns the extended wp_nav_menu arguments
 */
function html2wp_extend_wp_nav_menu_args( $args ) {

	// Don't wrap with div
	$args['container'] = false;

	// Don't fallback to default wp-menu that messes up everything
	$args['fallback_cb'] = false;

	// Don't wrap with ul
	$args['items_wrap'] = '%3$s';

	// Create the links
	$args['walker'] = new Html2wp_walker_nav_menu;

	return $args;
}

/**
 * Add an additional active class to menu elements that already have the current-menu-item class.
 * @param      array   all classes currently attached to the menu item
 * @return     array   all existing classes along with the new active class
 */
function html2wp_add_active_class_to_menu_item( $classes ) {

	if ( in_array( 'current-menu-item', $classes ) ) {
		$classes[] = 'active current ';
	}

	return $classes;
}