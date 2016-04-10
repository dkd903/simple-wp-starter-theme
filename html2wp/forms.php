<?php
/**
 * Setup the form on activation based on settings
 *
 * @package html2wp/simple-wp-starter-theme
 */

define( 'THEME_DOMAIN', 'html2wp_theme');
define( 'THEME_DIR', get_template_directory() );
define( 'HTML2WP_FORM_CREATED', 'html2wp_gf_form_created_once_555' );
define( 'GRAVITY_VERSION', '1.9.17.5' );
define( 'GRAVITY_PENDING_INSTALLATION', 'gform_pending_installation' );
define( 'GRAVITY_RG_VERSION_KEY', 'rg_form_version' );

/**
 * Returns the current version of gravity forms from wp-premium
 *
 * @return gf version
 */
function html2wp_get_gf_version() {
    $changelog = trim(file_get_contents("https://raw.githubusercontent.com/wp-premium/gravityforms/master/change_log.txt"));
    $verinfo = explode("Version ", $changelog);
    $verinfo = explode("-", $verinfo[1]);
    return trim($verinfo[0]);
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

    global $html2wp_settings;

    if ( isset($html2wp_settings["forms"]) && !empty($html2wp_settings["forms"]) ) {

        /**
         * Disable the gravity forms installation wizard
         * as it conflicts with auto setupof forms
         */
        update_option(GRAVITY_PENDING_INSTALLATION, -1);     
        //update_option(GRAVITY_RG_VERSION_KEY, GRAVITY_VERSION);      
        update_option(GRAVITY_RG_VERSION_KEY, html2wp_get_gf_version());
        
        //Iterate through multiple forms
        foreach ($html2wp_settings["forms"] as $this_form_data) {
        
            //get the name of the form
            $gf_form_name = $this_form_data["gfname"];
            //get the custom ID of the form
            //this is the ID we use to detect the form
            //Gravity Form ID is different and set by gravity forms
            $gf_form_id = $this_form_data["gfid"];

            //Get all available GV Forms
            $forms = GFAPI::get_forms();

            /**
             * Iterate through all GV Forms and look if the form
             * corresponding to the Form ID in the Form-config JSON has already been created
             */
            $form_to_create = array_filter($forms, function($form) use($gf_form_id) {
                return $gf_form_id == $form["gfid"];
            });         

            //Form has not been created previously, create one now
            if (empty($form_to_create)) {

                $form = array();
                $form['title'] = $gf_form_name;
                $form['gfid'] = $gf_form_id; //custom id for identifying the form

                foreach ($this_form_data["data"] as $key=>$elem) {

                    $form['fields'][$key] = new stdClass();
                    /**
                     * this switch is needed as GF Forms treats 'type'
                     * as the html element tag
                     */
                    switch($elem["tag_name"]) {
                        case "input":
                            $form['fields'][$key]->type = $elem["type"];

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
                    
                    /**
                     * GF Forms needs the id set to generate correct
                     * name attributes
                     */
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

                    /**
                     * Supporting only the default confirmation for now
                     * 
                    */

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
                if (get_option(HTML2WP_FORM_CREATED, -1) == -1) {
                    update_option( HTML2WP_FORM_CREATED, $formid );
                }
            } else {
            }
        }

    }
}

//handle the form submit api endpoint
if( ! is_admin() ) add_action( 'init', 'html2wp_form_submit_api_endpoint' );

/**
 * Handle form submission, this function is called
 * when wordpress detects the api endpoint called
 */
function html2wp_form_submit_api_endpoint() {

    if (strpos($_SERVER['REQUEST_URI'], 'action=html2wp_api') !== false) {  

        //check post vars, check wp_nonce is valid or not
        if ( isset( $_POST['gfformid'] ) && isset( $_POST['gfnonce'] ) && wp_verify_nonce( $_POST['gfnonce'], 'html2wp_key_gfnonce') ) {

            $entry = array(); //Entry is the data object that we save to GF Forms
            $input_id = 0;
            $gf_form = array();

            //TODO: Sanitise this?
            $gf_form_name = $_POST['gfformname'];
            $gf_form_id = $_POST['gfformid']; //this is our custom gfid param passed thru forms as an identifier
            $actual_gf_form_id = 0; //this is the actual Gravity Forms ID of that form

            //unset the form name and form ID fields
            unset($_POST['gfformname']);
            unset($_POST['gfformid']);

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
            foreach ($forms as $form) {
                if ($gf_form_id == $form["gfid"]) {
                    //if a form that matches the custom id gfid has been
                    //found then replace tthe value of gf_form_id with the actual id 
                    $actual_gf_form_id = $form["id"];
                    $gf_form = $form;

                    /**
                     * let us get the list of the data field in this form_object
                     * we will use this list to weed out any redundant data from
                     * the $_POST array
                     */
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
            if ($actual_gf_form_id != 0) {
                $entry['date_created'] = date('Y-m-d G:i');
                $entry['form_id'] = $actual_gf_form_id;

                $entry_id = GFAPI::add_entry( $entry );

                if ( is_wp_error( $entry ) ) {
                   $error_string = $entry->get_error_message();
                   $response = array($error_string);
                }

                if ($entry_id) {
                    $response = array();

                    //entry succesful, send notifications
                    GFAPI::send_notifications( $gf_form, $entry );

                    foreach ($gf_form["confirmations"] as $confirmation) {
                        
                        /**
                         * As of now we support configuring only the 'default confirmation'
                         * so let us get what to do with the confirmation
                         */
                        if ("Default Confirmation" == $confirmation["name"] &&
                            1 == $confirmation["isDefault"]) {
                                if ("message" == $confirmation["type"]) {
                                    $response = array($confirmation["message"]);

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

                    /**
                     * Set the default form submission success message
                     * if the response was not set from the Default Confirmations above
                     * or if the default confirmation message was was empty.
                     * $response[1] is the message
                     */
                    if (empty($response) || empty($response[1])) {
                        $response = array("Thanks for your submission!");
                    }
                }
            } else {
                //A form was not found corresponding to the 
                //GFForm that the user is trying to submit to
                $response = array("Form Error");
            }
        } else {
            //Nonce check failed
            //OR gfformid is not set
            //OR gfnonce is not set
            $response = array("Bad Input");
        }

        //Show this if request is AJAX form submit
        if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') { 
            header('content-type: application/json; charset=utf-8');
            echo json_encode($response)."\n";
            exit;
        }

        //this is shown only if it is a regular form submit
        
        //Header
        get_header();

        //print the response message
        echo "<h3>" . $response[0] . "</h3>";
        
        //Footer
        get_footer();

    }
}

//Perform setup after this theme is activated
add_action('after_switch_theme', 'html2wp_setup_theme_components');

/**
 * Checks for gravity forms plugin and then builds the first gravity form
 * after the theme is activated.
 */
function html2wp_setup_theme_components () {

    /**
     * Disable the gravity forms installation wizard
     * as it conflicts with auto setupof forms
     */
    update_option(GRAVITY_PENDING_INSTALLATION, -1);     
    //update_option(GRAVITY_RG_VERSION_KEY, GRAVITY_VERSION);

    //check if the Gravity forms plugin is active
    if( class_exists('GFForms') ) {

        /**
         * Gravity forms is active
         * Process the setup methods
         * these should occur each time a theme is activated,
         * as it could a totally different theme.
         */
        html2wp_setup_gravity_contact_form();
        delete_option(GRAVITY_PENDING_INSTALLATION);
    }

}

//Perform theme setup after Gravity forms is installed
add_action( 'activated_plugin', 'html2wp_detect_plugin_activation', 10, 2 );

/**
 * Peforms contact form setup after Gravity forms plugin is activated
 *
 */
function html2wp_detect_plugin_activation(  $plugin, $network_activation ) {
    
    /**
     * this will take place in the event user does not have gravity
     * forms already installed and imo this will be the most common case
     */
    $gf_plugin_name = "Gravity Forms";

    //get the details of the plugin which was just activated
    $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
    
    //if it was the Gravity Forms plugin
    if ($gf_plugin_name == $plugin_data['Name']) {

        /**
         * Since we disable the GFForms wizard, the required
         * tables are not created.
         */
        GFForms::setup_database();

        /**
         * Disable the gravity forms installation wizard
         * as it conflicts with auto setupof forms
         */
        update_option(GRAVITY_PENDING_INSTALLATION, -1);     
        //update_option(GRAVITY_RG_VERSION_KEY, GRAVITY_VERSION);    
        
        /**
         * check if a GF contact form has already been created
         * if yes then deactivating or reactivating should not
         * process the setup methods. These methods should be
         * processed only if a GF contact form was not already
         * created by the theme activation hook.
         */
        if (get_option(HTML2WP_FORM_CREATED, -1) == -1) {
            html2wp_setup_gravity_contact_form();
            delete_option(GRAVITY_PENDING_INSTALLATION);  
        }
    }
}