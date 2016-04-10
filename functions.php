<?php
/**
 * Load resources to initialize the html2wp theme functionalities
 *
 * @package html2wp/simple-wp-starter-theme
 */

// The Composer autoload includes
require_once get_template_directory() . '/html2wp/vendor/autoload.php';

// Theme setup
require_once get_stylesheet_directory() . '/html2wp/setup.php';

// The menu walker
require_once get_template_directory() . '/html2wp/html2wp-walker-nav-menu.php';

// Extend wp
require_once get_stylesheet_directory() . '/html2wp/extend.php';

// Custom methods
require_once get_stylesheet_directory() . '/html2wp/methods.php';

// Form methods
require_once get_stylesheet_directory() . '/html2wp/forms.php';

/**
 * Note: It is recommended to not add any custom code here if you plan to convert your theme again.
 * Please consider using a child theme instead: http://codex.wordpress.org/Child_Themes
 */