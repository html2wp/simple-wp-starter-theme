<?php

/**
 * Setup the form on activation based on settings
 *
 * @package html2wp/simple-wp-starter-theme
 */


/**
 * This is a wp_option key which is set when a gravity form is created 
 * for the first time this helps us run the gravity forms setup methods
 * only the first time when the gravity forms plugin is activated
 */
define( 'HTML2WP_FORM_CREATED', 'html2wp_gf_form_created_once_555' );

/**
 * These are gravity forms specific constants needed for the Gravity
 * forms setup and the wizard. Do not modify these.
 */
define( 'GRAVITY_PENDING_INSTALLATION', 'gform_pending_installation' );
define( 'GRAVITY_RG_VERSION_KEY', 'rg_form_version' );

//Add the required actions for using WP Ajax
add_action( 'wp_ajax_nopriv_html2wp_form_submit', 'html2wp_form_submit' );
add_action( 'wp_ajax_html2wp_form_submit', 'html2wp_form_submit' );


/**
 * Returns the current version of gravity forms from wp-premium
 *
 * @return gf version
 */
function html2wp_get_gf_version() {
	$changelog = trim( file_get_contents( 'https://raw.githubusercontent.com/wp-premium/gravityforms/master/change_log.txt' ) );
	$verinfo = explode( 'Version ', $changelog );
	$verinfo = explode( '-', $verinfo[1] );
	return trim( $verinfo[0] );
}

/**
 * Creates a gravity for programatically from the form json
 *
 * @return formid
 */
function html2wp_setup_gravity_contact_form() {

	/**
	 * Get the form data from form config json
	 * in order to create a new GV Form
	 */
	$html2wp_settings = html2wp_get_theme_settings();

	if ( isset( $html2wp_settings['forms'] ) && ! empty( $html2wp_settings['forms'] ) ) {

		/**
		 * Disable the gravity forms installation wizard
		 * as it conflicts with auto setupof forms
		 */
		update_option( GRAVITY_PENDING_INSTALLATION, -1 );     
		update_option( GRAVITY_RG_VERSION_KEY, html2wp_get_gf_version() );

		//Iterate through multiple forms
		foreach ( $html2wp_settings['forms'] as $this_form_data ) {

			//get the name of the form
			$gf_form_name = $this_form_data['gfname'];

			//get the custom ID of the form
			//this is the ID we use to detect the form
			//Gravity Form ID is different and set by gravity forms
			$gf_form_id = $this_form_data['gfid'];

			//Get all available GV Forms
			$forms = GFAPI::get_forms();

			/**
			 * Iterate through all GV Forms and look if the form
			 * corresponding to the Form ID in the Form-config JSON has already been created
			 */
			$form_to_create = array_filter( $forms, function( $form ) use( $gf_form_id ) {
				return $gf_form_id == $form['gfid'];
			} );

			//Form has not been created previously, create one now
			if ( empty( $form_to_create ) ) {

				$form = array();
				$form['title'] = $gf_form_name;
				$form['gfid'] = $gf_form_id; //custom id for identifying the form

				/**
				 * Now we go through the form data and based on the type of the element
				 * input or select or textarea, we set the requisite values for the forms
				 * array needed by Gravity Forms creation routine (GFAPI::add_form)
				 */
				foreach ( $this_form_data['data'] as $key => $elem ) {

					$form['fields'][$key] = new stdClass();

					/**
					 * this switch is needed as GF Forms treats 'type'
					 * as the html element tag
					 */
					switch( $elem['tag_name'] ) {
						case 'input':
							$form['fields'][$key]->type = $elem['type'];
							break;
						case 'select':
							$form['fields'][$key]->type = $elem['tag_name'];
							$choices = array();

							//build the choices array
							foreach ($elem['options'] as $ekey=>$option) {
								$choices[$ekey]['text'] = $option['text'];
								$choices[$ekey]['value'] = $option['value'];
								$choices[$ekey]['isSelected'] = $option['selected'];
							}
							$form['fields'][$key]->choices = $choices;
							break;
						case 'textarea':
							$form['fields'][$key]->type = $elem['tag_name'];
							break;
						default:
							break;
					}

					$form['fields'][$key]->name = $elem['name'];
					$form['fields'][$key]->inputName = $elem['name'];
					$form['fields'][$key]->label = $elem['placeholder'];
					$form['fields'][$key]->isRequired = $elem['required'];

					/**
					 * GF Forms needs the id set to generate correct
					 * name attributes
					 */
					$form['fields'][$key]->id = $key;

					//TODO: Set Honeypot property for spam
					$unique_id = time();

					//Notifications needs a 13 character unique ID in GF Forms
					if ( $unique_id <= 13 ) {
						$unique_id = $unique_id . 10 * ( 12 - strlen( $unique_id ) );
					}

					$form['notifications'] = array(
						$unique_id => array(
							'isActive'          => true,
							'id'                => $unique_id,
							'name'              => 'Admin Notification',
							'event'             => 'form_submission',
							'to'                => '{admin_email}',
							'toType'            => 'email',
							'subject'           => 'New submission from {form_title}',
							'message'           => '{all_fields}',
							'from'              => '{admin_email}',
							'disableAutoformat' => false,
						)
					);
				}

				//create a form
				$formid = GFAPI::add_form( $form );
				//TODO: check for WP_Error and also possibly implement logging

				/**
				 * Store the form ID in theme options, so that
				 * we know gravity forms was run once already
				 * and we need not run the gravity forms setup
				 * methods again with the activated_plugin hook
				 */
				if ( get_option( HTML2WP_FORM_CREATED, -1 ) === -1 ) {
					update_option( HTML2WP_FORM_CREATED, $formid );
				}
			}
		}
	}
}

/**
 * Handle form submission, this function is called
 * when wordpress detects the api endpoint called
 */
function html2wp_form_submit() {

	$success = false;
	
	//check post vars, check wp_nonce is valid or not
	if ( isset( $_POST['gfformid'] ) && isset( $_POST['gfnonce'] ) && wp_verify_nonce( $_POST['gfnonce'], 'html2wp_key_gfnonce') ) {

		$entry = array(); //Entry is the data object that we save to GF Forms
		$input_id = 0;
		$gf_form = array();

		//TODO: Sanitize this?
		$gf_form_name = $_POST['gfformname'];
		$gf_form_id = (double) $_POST['gfformid']; //this is our custom gfid param passed thru forms as an identifier
		$actual_gf_form_id = 0; //this is the actual Gravity Forms ID of that form

		//unset the form name and form ID fields
		unset( $_POST['gfformname'] );
		unset( $_POST['gfformid'] );

		/**
		 * Now get the form ID to which we have to savethe data
		 * using the form name that was passed ast the data request
		 */

		//Get all available GV Forms
		$forms = GFAPI::get_forms();

		/**
		 * Iterate through all GV Forms and look if the form
		 * corresponding to the Form ID in the Form-config JSON has already been created
		 */
		$form_fields = array();

		foreach ( $forms as $form ) {

			if ( $gf_form_id === (double) $form['gfid'] ) {

				//if a form that matches the custom id gfid has been
				//found then replace tthe value of gf_form_id with the actual id 
				$actual_gf_form_id = $form['id'];
				$gf_form = $form;

				/**
				 * let us get the list of the data field in this form_object
				 * we will use this list to weed out any redundant data from
				 * the $_POST array
				 */
				foreach ( $form['fields'] as $value ) {
					$form_fields[] = $value->name;
				}

				break;
			}
		}

		// sanitize form input values
		foreach ( $_POST as $key => $value ) {

			//weed out redundant keys in $_POST
			if ( in_array( $key, $form_fields ) ) {
				$entry["{$input_id}"] = sanitize_text_field( $value );
				$input_id++;
			}
		}

		//Submit form to GV
		if ( $actual_gf_form_id !== 0 ) {

			$entry['date_created'] = date('Y-m-d G:i');
			$entry['form_id'] = $actual_gf_form_id;
			$entry_id = GFAPI::add_entry( $entry );

			if ( is_wp_error( $entry ) ) {
				$error_string = $entry->get_error_message();
				$response = array( $error_string );
			}

			if ( $entry_id ) {

				$response = array();

				//entry succesful, send notifications
				GFAPI::send_notifications( $gf_form, $entry );

				foreach ( $gf_form['confirmations'] as $confirmation ) {
					
					/**
					 * As of now we support configuring only the 'default confirmation'
					 * so let us get what to do with the confirmation
					 */
					if ( 'Default Confirmation' === $confirmation['name'] &&
						true === $confirmation['isDefault'] ) {
						
						if ( 'message' === $confirmation['type'] ) {
							$success = true;
							$response = array( $confirmation['message'] );

						} else if ( 'page' === $confirmation['type'] ) {
							$uri = home_url() . '?p=' . $confirmation['pageId'];
							if ( ! empty( $confirmation['queryString'] ) ) {
								$uri .= '?' . $confirmation['queryString'];
							}
							wp_redirect( $uri );
							exit;

						} else if ( 'redirect' === $confirmation['type'] ) {
							$uri = $confirmation['url'];
							if ( ! empty($confirmation['queryString'] ) ) {
								$uri .= '?' . $confirmation['queryString'];
							}
							wp_redirect( $uri );
							exit;
						}

						//exit the loop
						break;
					}
				}

				/**
				 * Set the default form submission success message
				 * if the response was not set from the Default Confirmations above
				 * or if the default confirmation message was was empty.
				 * $response[1] is the message
				 */
				if ( empty( $response ) || empty( $response['message']) ) {
					$success = true;
					$response = array( 'Thank you for your submission!' );
				}
			}
		} else {
			//A form was not found corresponding to the 
			//GFForm that the user is trying to submit to
			$success = false;
			$response = array( 'Form Not Found' );
		}
	} else {
		//Nonce check failed
		//OR gfformid is not set
		//OR gfnonce is not set
		$success = false;
		$response = array( 'Bad Input' );
	}



	//Show this if request is AJAX form submit
	if ( isset($_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest' ) { 
		header( 'content-type: application/json; charset=utf-8' );
		echo json_encode( $response );
		exit;
	}

	//this is shown only if it is a regular form submit
	include ( get_stylesheet_directory() . '/html2wp/templates/form-submit.php' );
	exit;
}