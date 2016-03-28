<?php
//Domain - html2wp-theme

define( 'THEME_DOMAIN', 'html2wp-theme');
define( 'THEME_DIR', get_template_directory() );
define( 'CONTACT_PAGE_GF_FORM_CREATED', '_html2wp_theme_gf_form_id' );

/**
 * Creates a gravity for programatically from the form json
 *
 * @return formid
 */
function setup_gravity_contact_form() {
    //Get the form data from form config json
    //in order to create a new GV Form
    $form_data = json_decode(file_get_contents(THEME_DIR . '/form_config.json'), true);
    //Iterate through multiple forms
    foreach ($form_data as $this_form_data) {
        //get the name of the form
        $gf_form_name = $this_form_data["gfname"];

        //Get all available GV Forms
        $forms = GFAPI::get_forms();

        //Form flag
        $form_created = 0;

        //Iterate through all GV Forms and look if the form
        //corresponding to the Form ID in the Form-config JSON has already been created
        foreach ($forms as $form) {
            if ($gf_form_name == $form["title"]) {
                //Form has already been created
                //may be from a bad previous install?
                $form_created = 1;
            } else {

            }
        }

        //Form has not been created previously, create one now
        if ($form_created == 0) {

            $form = array();
            $form['title'] = $gf_form_name;
            $form['id'] = 1; //can be any value, GV overrides this anyways

            foreach ($this_form_data["data"] as $key=>$elem) {
                echo $key;
                //this switch is needed as GF Forms treats 'type'
                //as the html element tag
                switch($elem["tag_name"]) {
                    case "input":
                        $form['fields'][$key]->type = $elem["type"];
                        //TODO: Handle for radio and checkbox
                        break;
                    case "select":
                        $form['fields'][$key]->type = $elem["tag_name"];
                        $choices = array();
                        //build the choices array
                        foreach ($elem["options"] as $ekey=>$option) {
                            $choices[$ekey]["text"] = $option["text"];
                            $choices[$ekey]["value"] = $option["value"];
                            $choices[$ekey]["isSelected"] = $option["selected"];
                        }
                        $form['fields'][$key]->choices = $choices;
                        break;
                    case "textarea":
                        $form['fields'][$key]->type = $elem["tag_name"];
                        break;
                    default:
                        break;
                }
                $form['fields'][$key]->name = $elem["name"];
                $form['fields'][$key]->inputName = $elem["name"];
                $form['fields'][$key]->label = $elem["placeholder"];
                $form['fields'][$key]->isRequired = $elem["required"];
                //GF Forms needs the id set to generate correct
                //name attributes
                $form['fields'][$key]->id = $key;
                //TODO: Set Honeypot property for spam
                $unique_id = time();
                //Notifications needs a 13 character unique ID in GF Forms
                if ($unique_id <= 13) {
                    $unique_id = $unique_id . 10*(12 - strlen($unique_id));
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

                //setup confirmations for the form,
                //message to be displayed after form submit.
                //TODO: Remove this section, after discussing. GF Forms already Creates
                //one default confirmation. So should we let users know via a WP
                //Dashboard Notification about the options for configuring this?
                //also should we support multiple confirmations
                // $form['confirmations'] = array(
                //     $unique_id => array(
                //         'isDefault'          => true,
                //         'id'                => $unique_id,
                //         'name'              => 'Default Confirmation',
                //         'type'             => 'message', //values: message, page, redirect
                //         'message'           => 'Thanks for your submission!' //Keys: message, pageid, url+queryString
                //     )
                // );

            }

            //create a form
            $formid = GFAPI::add_form( $form );
            //Store the form ID in theme options, so that
            //we know gravity forms was run once already
            //and we need not run the gravity forms setup
            //methods again with the activated_plugin hook
            if (get_option(CONTACT_PAGE_GF_FORM_CREATED, -1) != -1) {
                update_option( CONTACT_PAGE_GF_FORM_CREATED, $formid );
            }
        }
    }
}

/**
 * Sets up a Contact Us page in wordpress
 */
function setup_wp_contact_page() {
    //TODO: check for the template file presence?
    $template_file_name = "contact-page-template.php";
    $page_found = 0;

    //get all pages and check if a page
    //by the slug we set, already exists?
    $all_pages = get_pages();
    $contact_page= array(
                    'slug' => 'contact-us', ////get this from theme options?
                    'title' =>'Contact Us'	//get this from theme options?
                );

    //loop through all pages to check if page already exists
    foreach ($all_pages as $page) {
    	switch ( $page->post_name ){
            case $contact_page['slug'] :
                $page_found= $page->ID; //store the postID in case it is found
                break;
    		default:
                break;
    	}
    }

	if($page_found == 0){
        //create a new contact us page
		$page_id = wp_insert_post(array(
			'post_title' => $contact_page['title'],
			'post_type' =>'page',
			'post_name' => $contact_page['slug'],
			'post_status' => 'publish',
			'post_excerpt' => 'Contact Us Page'
		));
	    add_post_meta( $page_id, '_wp_page_template', $template_file_name );
        //TODO: Uppdate theme_option as well that the page has been created
	} else {
        //update the existing page
        update_post_meta( $page_found, '_wp_page_template', $template_file_name );
    }
}

//handle the form submit api endpoint
add_action( 'parse_request', 'form_submit_api_endpoint', 0 );

/**
 * Handle form submission, this function is called
 * when wordpress detects the api endpoint called
 */
function form_submit_api_endpoint() {
    global $wp;
    if ( $wp->query_vars['pagename'] == 'html2wp_api' ) {

        //post vars
        if ( isset( $_POST['gfformname'] ) ) {

            $entry = array(); //Entry is the data object that we save to GF Forms
            $input_id = 0;
            $gf_form = array();

            //TODO: Sanitise this?
            $gf_form_name = $_POST['gfformname'];
            $gf_form_id = 0;

            //unset the form name and form ID fields
            unset($_POST['gfformname']);
            unset($_POST['gfformid']);

            //Now get the form ID to which we have to savethe data
            //using the form name that was passed ast the data request

            //Get all available GV Forms
            $forms = GFAPI::get_forms();

            //Iterate through all GV Forms and look if the form
            //corresponding to the Form ID in the Form-config JSON has already been created
            $form_fields = array();
            foreach ($forms as $form) {
                if ($gf_form_name == $form["title"]) {
                    $gf_form_id = $form["id"];
                    $gf_form = $form;
                    //let us get the list of the data field in this form_object
                    //we will use this list to weed out any redundant data from
                    //the $_POST array
                    foreach ($form["fields"] as $k => $v) {
                        $form_fields[] = $v->name;
                    }
                    break;
                }
            }

            // sanitize form input values
            foreach ($_POST as $key => $value) {
                //weed out redundant keys in $_POST
                if (in_array($key, $form_fields)) {
                    $entry["{$input_id}"] = sanitize_text_field($value);
                    $input_id++;
                }
            }

            //Submit form to GV
            if ($gf_form_id != 0) {
                $entry['date_created'] = date('Y-m-d G:i');
                $entry['form_id'] = $gf_form_id;

                $entry_id = GFAPI::add_entry( $entry );

                if ( is_wp_error( $entry ) ) {
                   $error_string = $entry->get_error_message();
                   $response = array(-1, $error_string);
                }

                if ($entry_id) {
                    $response = array();

                    //entry succesful, send notifications
                    GFAPI::send_notifications( $gf_form, $entry );

                    foreach ($gf_form["confirmations"] as $confirmation) {
                        //As of now we support configuring only the 'default confirmation'
                        //so let us get what to do with the confirmation

                        if ("Default Confirmation" == $confirmation["name"] &&
                            1 == $confirmation["isDefault"]) {
                                if ("message" == $confirmation["type"]) {
                                    $response = array(1, $confirmation["message"]);

                                } else if ("page" == $confirmation["type"]) {
                                    $uri = home_url() . "?p=" . $confirmation["pageId"];
                                    if (!empty($confirmation["queryString"])) {
                                        $uri .= "?" . $confirmation["queryString"];
                                    }
                                    wp_redirect( $uri );
                                    exit;

                                } else if ("redirect" == $confirmation["type"]) {
                                    $uri = $confirmation["url"];
                                    if (!empty($confirmation["queryString"])) {
                                        $uri .= "?" . $confirmation["queryString"];
                                    }
                                    wp_redirect( $uri );
                                    exit;
                                }

                                //exit the loop
                                break;
                            }
                    }

                    //Set the default form submission success message
                    //if the response was not set from the Default Confirmations above
                    //or if the default confirmation message was was empty.
                    //$response[1] is the message
                    if (empty($response) || empty($response[1])) {
                        $response = array(1, "Thanks for your submission!");
                    }
                }

                //send email
                //TODO: Setup notifications
            } else {
                $response = array(0, "Error");
            }
        } else {
            $response = array(-2, "Bad Input");
        }


        header('content-type: application/json; charset=utf-8');
        echo json_encode($response)."\n";
        exit;

    }
}

//Perform setup after this theme is activated
add_action('after_switch_theme', 'setup_theme_components');

/**
 * Checks for gravity forms plugin and then builds the first gravity form
 * after the theme is activated.
 */
function setup_theme_components () {

    //check if the Gravity forms plugin is active
    if( class_exists('GFForms') ) {
    	//Gravity forms is active
        //Process the setup methods
        //these should occur each time a theme is activated,
        //as it could a totally different theme.
        //TODO: Check if Gravity forms is activated (License Key Input)
        setup_gravity_contact_form();
        setup_wp_contact_page();
    }

}

//Perform theme setup after Gravity forms is installed
add_action( 'activated_plugin', 'detect_plugin_activation', 10, 2 );

/**
 * Peforms contact form setup after Gravity forms plugin is activated
 *
 */
function detect_plugin_activation(  $plugin, $network_activation ) {
    //this will take place in the event user does not have gravity
    //forms already installed and imo this will be the most common case
    $gf_plugin_name = "Gravity Forms";

    //get the details of the plugin which was just activated
    $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
    //if it was the Gravity Forms plugin
    if ($gf_plugin_name == $plugin_data['Name']) {
        //check if a GF contact form has already been created
        //if yes then deactivating or reactivating should not
        //process the setup methods. These methods should be
        //processed only if a GF contact form was not already
        //created by the theme activation hook.
        if (get_option(CONTACT_PAGE_GF_FORM_CREATED, -1) == -1) {
            setup_gravity_contact_form();
            setup_wp_contact_page();
        }
    }
}

//If gravity form plugin is not present then asks the user to install it
if( !class_exists('GFForms') ) {
  add_action( 'admin_notices', 'gravity_forms_notice' );
}

/**
 * Notice for installing gravity forms
 */
function gravity_forms_notice() {
  ?>
  <div class="error notice">
      <p><?php _e( 'Please install <a href="http://www.gravityforms.com" target="_blank">Gravity Forms</a> in order to activate the Contact Form feature of this theme!', THEME_DOMAIN ); ?></p>
  </div>
  <?php
}