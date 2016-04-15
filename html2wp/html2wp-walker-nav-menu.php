<?php

/**
 * The menu walker
 *
 * @package html2wp/simple-wp-starter-theme
 */


/**
 * Create HTML list of nav menu items.
 *
 * @since 0.0.1
 * @uses \Walker_Nav_Menu
 */
class Html2wp_walker_nav_menu extends Walker_Nav_Menu {

	/**
	 * Starts the list before the elements are added.
	 *
	 * @see Walker::start_lvl()
	 *
	 * @since 0.3.5
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int    $depth  Depth of menu item. Used for padding.
	 * @param array  $args   An array of arguments. @see wp_nav_menu()
	 */
	public function start_lvl( &$output, $depth = 0, $args = array() ) {

		if ( isset( $args->walker_sub_menu_wrapper_type ) ) {
			$attributes = '';

			if ( isset( $args->walker_sub_menu_wrapper_attributes ) ) {

				foreach ( $args->walker_sub_menu_wrapper_attributes as $attr => $value ) {
					if ( ! empty( $value ) ) {
						$value = ( $link_attribute === $attr ) ? esc_url( $value ) : esc_attr( $value );
						$attributes .= ' ' . $attr . '="' . $value . '"';
					}
				}
			}

			$indent = str_repeat("\t", $depth);
			$output .= "\n$indent<" . $args->walker_sub_menu_wrapper_type . $attributes . ">";
		}

		$indent = str_repeat("\t", $depth);
		$output .= "\n$indent<ul class=\"sub-menu\">\n";
	}

	/**
	 * Ends the list of after the elements are added.
	 *
	 * @see Walker::end_lvl()
	 *
	 * @since 0.3.5
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int    $depth  Depth of menu item. Used for padding.
	 * @param array  $args   An array of arguments. @see wp_nav_menu()
	 */
	public function end_lvl( &$output, $depth = 0, $args = array() ) {

		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul>\n";

		if ( isset( $args->walker_sub_menu_wrapper_type ) ) {
			$output .= "$indent</" . $args->walker_sub_menu_wrapper_type . ">\n";
		}

	}

	/**
	 * Start the element output.
	 *
	 * @see Walker::start_el()
	 *
	 * @since 0.0.1
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $item   Menu item data object.
	 * @param int    $depth  Depth of menu item. Used for padding.
	 * @param array  $args   An array of arguments. @see wp_nav_menu()
	 * @param int    $id     Current item ID.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {

		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

		$classes = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'menu-item-' . $item->ID;

		/**
		 * Filter the CSS class(es) applied to a menu item's list item element.
		 *
		 * @since 0.0.1
		 *
		 * @param array  $classes The CSS classes that are applied to the menu item's `<li>` element.
		 * @param object $item    The current menu item.
		 * @param array  $args    An array of {@see wp_nav_menu()} arguments.
		 * @param int    $depth   Depth of menu item. Used for padding.
		 */

		$wp_class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) );

		/**
		 * Filter the ID applied to a menu item's list item element.
		 *
		 * @since 0.0.1
		 *
		 * @param string $menu_id The ID that is applied to the menu item's `<li>` element.
		 * @param object $item    The current menu item.
		 * @param array  $args    An array of {@see wp_nav_menu()} arguments.
		 * @param int    $depth   Depth of menu item. Used for padding.
		 */
		$wp_id = apply_filters( 'nav_menu_item_id', 'menu-item-'. $item->ID, $item, $args, $depth );

		// Apply the indent
		$output .= $indent;

		// If we want a list
		if ( $args->walker_links_in_list ) {

			$class_names = $wp_class_names ? ' class="' . esc_attr( $wp_class_names ) . '"' : '';

			//get the walker_list_item_attributes from the $args parameter;
			$walker_list_item_attributes = $args->walker_list_item_attributes;

			// If no ID set, use the ID possibly provided by WP
			if ( empty( $args->walker_list_item_attributes['id'] ) && ! empty( $wp_id ) ) {
				$walker_list_item_attributes['id'] = $wp_id;
			}

			// If WP class names are available append them to the original list
			if ( ! empty( $wp_class_names ) ) {

				// There is original class names, append the wp class names
				if ( ! empty( $args->walker_list_item_attributes['class'] ) ) {
					$walker_list_item_attributes['class'] = $args->walker_list_item_attributes['class'] . ' ' . $wp_class_names;
				}

				// There is no original class names, just use the wp ones
				else {
					$walker_list_item_attributes['class'] = $wp_class_names;
				}
			}

			$output .= '<li';

			foreach ( $walker_list_item_attributes as $key => $value ) {
				$output .= ' ' . $key . '="' . esc_attr( $value ) . '"';
			}

			$output .= '>';
			
		}

		// Decide the link attribute we want
		if ( $args->walker_link_type === 'a' ) {
			$link_attribute = 'href';
		}

		// If we are not dealing with a native link we use the js way
		else {
			$link_attribute = 'data-href';
		}

		// Get the original link attributes
		$atts = $args->walker_link_item_attributes;

		// Override with wp attributes
		$atts['title']  			= ! empty( $item->attr_title ) ? $item->attr_title : '';
		$atts['target'] 			= ! empty( $item->target )     ? $item->target     : '';
		$atts['rel']    			= ! empty( $item->xfn )        ? $item->xfn        : '';
		$atts[ $link_attribute ]   	= ! empty( $item->url )        ? $item->url        : '';

		// If we have a list we have already outputted some of the info for the li element, otherwise ouput the info now
		if ( !$args->walker_links_in_list ) {

			// If no ID set, use the ID possibly provided by WP
			if ( empty( $atts['id'] ) && ! empty( $wp_id ) ) {
				$atts['id'] = $wp_id;
			}

			// If WP class names are available append them to the original list
			if ( ! empty( $wp_class_names ) ) {

				// There is original class names, append the wp class names
				if ( ! empty( $atts['class'] ) ) {
					$atts['class'] .= ' ' .  $wp_class_names;
				}

				// There is no original class names, just use the wp ones
				else {
					$atts['class'] = $wp_class_names;
				}
			}
		}

		/**
		 * Filter the HTML attributes applied to a menu item's link element.
		 *
		 * @since 0.0.1
		 *
		 * @param array $atts {
		 *     The HTML attributes applied to the menu item's link element, empty strings are ignored.
		 *
		 *     @type string $title  Title attribute.
		 *     @type string $href   The href attribute.
		 * }
		 * @param object $item  The current menu item.
		 * @param array  $args  An array of {@see wp_nav_menu()} arguments.
		 * @param int    $depth Depth of menu item. Used for padding.
		 */
		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) ) {
				$value = ( $link_attribute === $attr ) ? esc_url( $value ) : esc_attr( $value );
				$attributes .= ' ' . $attr . '="' . $value . '"';
			}
		}

		$item_output = $args->before;
		$item_output .= '<' . $args->walker_link_type . $attributes .'>';

		/** This filter is documented in wp-includes/post-template.php */
		$item_output .= $args->link_before . apply_filters( 'the_title', $item->title, $item->ID ) . $args->link_after;
		$item_output .= '</' . $args->walker_link_type . '>';
		$item_output .= $args->after;
		/**
		 * Filter a menu item's starting output.
		 *
		 * @since 0.0.1
		 *
		 * @param string $item_output The menu item's starting HTML output.
		 * @param object $item        Menu item data object.
		 * @param int    $depth       Depth of menu item. Used for padding.
		 * @param array  $args        An array of {@see wp_nav_menu()} arguments.
		 */
		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}
	/**
	 * Ends the element output, if needed.
	 *
	 * @see Walker::end_el()
	 *
	 * @since 0.0.1
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $item   Page data object. Not used.
	 * @param int    $depth  Depth of page. Not Used.
	 * @param array  $args   An array of arguments. @see wp_nav_menu()
	 */
	public function end_el( &$output, $item, $depth = 0, $args = array() ) {

		// If we want a list
		if ( $args->walker_links_in_list ) {
			$output .= "</li>\n";
		}
	}
} // Walker_Nav_Menu