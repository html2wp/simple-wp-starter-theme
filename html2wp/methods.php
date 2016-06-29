<?php

/**
 * Our custom methods
 *
 * @package html2wp/simple-wp-starter-theme
 */


/**
 * Finds the first matching page for the template and returns it's link
 * @param       string   $template The template to look for
 * @return      string   Returns the link to the irst matching page for the template
 */
function html2wp_get_page_link( $template ) {

	/**
	 * The pages matching the template if any exist
	 * @var array
	 */
	$pages = get_posts( array( 'post_type' => 'page', 'meta_key' => '_wp_page_template', 'meta_value' => $template ) );

	/**
	 * If a page exists and is not in the trash, echo the link
	 */
	if ( ! empty( $pages ) && isset( $pages[0] ) && 'trash' !== $pages[0]->post_status ) {
		return get_permalink( $pages[0]->ID );
	} else {

		/**
		 * We couldn't find any pages matching the template
		 */

		// Get the file name
		$file_name = pathinfo( $template, PATHINFO_FILENAME );

		/**
		 * If dealing with front-page we will run the same function again
		 */
		if ( 'index' === $file_name ) {

			$file_name = 'front-page';

			if ( '.' !== dirname( $template ) ) {
				$dir = dirname( $template ) . '/';
			} else {
				$dir = '';
			}

			return html2wp_get_page_link( $dir . $file_name . '.php' );
		} else {

			// if there exists a custom post type of the name $file_name

			/**
			 * Get the settings
			 */
			$html2wp_settings = html2wp_get_theme_settings();

			$custom_post_type_name = str_replace("archive_", "", $file_name);

			// check if we have a custom post type by this name
			if ( in_array($custom_post_type_name, array_keys( $html2wp_settings['taxonomies'] ) ) ) {

				return site_url( $custom_post_type_name );
			}

			// set the default return to the home page url
			return site_url();
		}
	}
}

/**
 * Finds the first matching page for the template and echoes it's link
 * @param  string $template The template to look for
 */
function html2wp_the_page_link( $template ) {

	echo html2wp_get_page_link( $template );

}

/**
 * Prints a notification message for the website admin to install a widget
 * in a new registered sidebar
 * @param  string $widget_name Name of the widget that has been registered
 */
function html2wp_notify_sidebar_install( $widget_name ) {

    $html  = '<div class="html2wp-widget-install-notice">';
    $html .= '<p>' . $widget_name . ' widget is ready<p>';
    $html .= '<a href="' . admin_url( 'widgets.php' ) . '">Click to install your widget</a>';
    $html .= '</div>';
    echo $html;

}

/**
 * Find the first occurence of a string and replace it
 * Splits $haystack into an array of 2 items by $needle, and then joins the array with $replace_with
 * @see http://stackoverflow.com/a/1252717/3073849
 * @param  string $haystack     The subject of the replacing
 * @param  string $needle       The string to look for
 * @param  string $replace_with The replacement string
 * @return string The modified string
 */
function html2wp_replace_first_occurrence( $haystack, $needle, $replace_with ) {
    return implode( $replace_with, explode( $needle, $haystack, 2 ) );
}