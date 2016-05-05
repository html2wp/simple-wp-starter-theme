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
	$pages = get_pages( array( 'meta_key' => '_wp_page_template', 'meta_value' => $template ) );

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