<?php
	/*
	Plugin Name: UMatter2Us
	Plugin URI: http://www.umatter2.us
	Description: UMatter2Us converts WordPress into a learning community website 
	Version: 0.1
	Author: Morgan Benton
	Author URI: http://www.umatter2.us/morgan
	License: GPLv3
	*/
/**
 * UMatter2Us
 * @package umatter2us
 * @author Morgan Benton
 * @version 0.1
 */
 
/*
 * Load Qualtrics Class
 */
//if ( ! class_exists( 'QualtricsAPI' ) ) require 'class-qualtrics.php';
if ( ! class_exists( 'Qualtrics' ) ) require 'qualtrics.php';

/**
 * Loads the plugin
 */
$umatter2us = new UMatter2Us;

// include modules
//require 'members.php';
//$members = new UM2UMembers;
require 'meetings.php';
$meetings = new UM2UMeetings;
require 'objectives.php';
$objectives = new UM2UObjectives;
require 'feedback.php';
$feedback = new UM2UFeedback;
require 'members.php';
$feedback = new UM2UMembers;


/**
 * UMatter2Us
 * @since 0.1
 * @package umatter2us
 */
class UMatter2Us {
	
    /**
     * UMatter2Us settings
     */
    private $settings;
    private $members;
	private $objectives;
	private $meetings;
	private $feedback;
	private $qtrx;
	
    /**
     * Constructor
     *
     * PHP 5.x style, calls {@link __construct}
     */
    function UMatter2Us() {
        $this->__construct();
    }
    
    /**
     * Constructor
     *
     * PHP 4.x style for backwards compatibility.  Add main actions for plugin.
     */
    function __construct() {
        // load settings
        $this->settings  = get_option( 'umatter2us_settings' );
        
        // first time only, generate unique ID for this blog
        if ( ! $this->settings[ 'wpid' ] ) {
            $this->settings[ 'wpid' ] = 'WP_' . substr( md5( plugins_url() ), 0, 8 );
            update_option( 'umatter2us_settings', $this->settings );
        }
        //$this->qtrx = new QualtricsAPI( $this->settings[ 'qtrxuser' ], $this->settings[ 'qtrxpass' ], $this->settings[ 'qtrxlib' ] );
        
		add_shortcode( 'github', array( &$this, 'add_github_repo' ) );

        // primary actions
        if ( is_admin() ) {
            add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );
            add_action( 'admin_init',     array( &$this, 'admin_init'     ) );
            add_action( 'admin_menu',     array( &$this, 'admin_menu'     ) );
            add_action( 'admin_head',     array( &$this, 'admin_head'     ) );
            add_action( 'admin_notices',  array( &$this, 'admin_notices'  ) );
            add_action( 'wp_ajax_um2u_generate_panel_csv', array( &$this, 'wp_ajax_um2u_generate_panel_csv' ) );
	        add_action( 'wp_ajax_get_libraries',           array( &$this, 'get_libraries'                   ) );
	        add_action( 'wp_ajax_get_surveys',             array( &$this, 'get_surveys'                     ) );
        }
		add_action( 'wp_ajax_save_github_repo', array( &$this, 'save_github_repo' ) );
    }
    
    /**
     * Registers all styles and scripts
     */
    function plugins_loaded() {
        // register styles
        wp_register_style( 'jquery_ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/themes/base/jquery-ui.css' );
        wp_register_style( 'umatter2us_css', plugins_url() . '/umatter2us/css/style.css' );
        
        // register scripts
        wp_register_script( 'umatter2us_js', plugins_url() . '/umatter2us/js/umatter2us.js', array( 'jquery', ), false, true );
    }
    
    /**
     * Registers all settings
     */
    function admin_init() {
        // register settings
        register_setting( 'umatter2us_settings', 'umatter2us_settings', array( &$this, 'validate_umatter2us_settings' ) );
        
        // umatter2us settings and fields
        add_settings_section( 'umatter2us_settings_section', null, array( &$this, 'umatter2us_settings_section' ), 'umatter2us-menu' );
        add_settings_field( 'qtrxuser',  'Qualtrics Username', array( &$this, 'qtrx_username_field' ), 'umatter2us-menu', 'umatter2us_settings_section' );
        add_settings_field( 'qtrxtoken', 'Qualtrics Token',    array( &$this, 'qtrx_token_field'    ), 'umatter2us-menu', 'umatter2us_settings_section' );
        add_settings_field( 'qtrxlib',   'Qualtrics Library',  array( &$this, 'qtrx_library_field'  ), 'umatter2us-menu', 'umatter2us_settings_section' );
        add_settings_field( 'wklysrvy',  'Weekly Self-Eval',   array( &$this, 'qtrx_wklysrvy_field' ), 'umatter2us-menu', 'umatter2us_settings_section' );

        add_settings_section( 'import_qualtrics_surveys_section', null, array( &$this, 'import_qualtrics_surveys_section'), 'um2u-import-surveys-menu' );
        add_settings_field( 'qtrx_survey_to_import', 'Survey to Import', array( &$this, 'survey_to_import_field' ), 'um2u-import-surveys-menu', 'import_qualtrics_surveys_section' );

        add_settings_section( 'create_qualtrics_panels_section', null, array( &$this, 'create_qualtrics_panels_section'), 'um2u-create-panels-menu' );
        add_settings_field( 'qtrx_panels_survey_to_use', 'Survey to Import', array( &$this, 'survey_to_import_field' ), 'um2u-create-panels-menu', 'create_qualtrics_panels_section' );
        add_settings_field( 'qtrx_panel_to_modify',      'Panel to Modify',  array( &$this, 'panel_to_modify_field' ),  'um2u-create-panels-menu', 'create_qualtrics_panels_section' );

        add_settings_section( 'user_profile_settings_section', null, array( &$this, 'user_profile_settings_section' ), 'um2u-user-profile-menu' );
        add_settings_field(   'um2u_author_page_surveys', 'Surveys', array( &$this, 'author_page_surveys_field' ), 'um2u-user-profile-menu', 'user_profile_settings_section' );
    }
	
    /**
     * Sets up all the menus and submenus.  Adds actions for enqueuing styles and scripts
     */
    function admin_menu() {
        // main menu page
        $menus[] = add_menu_page( 'UMatter2Us', 'UMatter2Us', 'manage_options', 'umatter2us-menu', array( &$this, 'umatter2us_menu' ), plugins_url() . '/umatter2us/images/umatter2us_icon.png', 31 );        
    	$menus[] = add_submenu_page( 'umatter2us-menu', 'Import Survey Results', 'Import Survey Results', 'manage_options', 'um2u-import-surveys-menu', array( &$this, 'import_surveys_menu' ) );
    	$menus[] = add_submenu_page( 'umatter2us-menu', 'Create Survey Panels',  'Create Survey Panels',  'manage_options', 'um2u-create-panels-menu',  array( &$this, 'create_panels_menu'  ) );
    	$menus[] = add_submenu_page( 'umatter2us-menu', 'User Profile Settings', 'User Profile Settings', 'manage_options', 'um2u-user-profile-menu',   array( &$this, 'user_profile_menu'   ) );
    	foreach ( $menus as $menu ) {
        	add_action( 'admin_print_styles-'  . $menu, array( &$this, 'enqueue_umatter2us_styles'  ) );
        	add_action( 'admin_print_scripts-' . $menu, array( &$this, 'enqueue_umatter2us_scripts' ) );
    	}
    	// add import users menu
    	add_users_page( 'Import Users',            'Import Users',            'add_users', 'um2u-import-users',   array( &$this, 'import_users'   ) );
    	add_users_page( 'Send Password Reminders', 'Send Password Reminders', 'add_users', 'um2u-send-passwords', array( &$this, 'send_passwords' ) );
	}
    
    function admin_head() {
    }
    
    /**
     * Display admin notices
     */
    function admin_notices() {
        // warn if Qualtrics is misconfigured
        //if ( $message = $this->qtrx->is_misconfigured() ) {
        //    echo '<div class="updated fade"><p><strong>' . __( $message ) . '</strong></p></div>';
        //}
    }
    /**
     * Enqueue styles
     */
    function enqueue_umatter2us_styles() {
        wp_enqueue_style( 'jquery_ui' );
        wp_enqueue_style( 'umatter2us_css' );
    }
    
    /**
     * Enqueue scripts
     */
    function enqueue_umatter2us_scripts() {
        wp_enqueue_script( 'umatter2us_js' );
    }
    
    /**
     * Outputs main menu for UMatter2Us Settings
     */
    function umatter2us_menu() {
        if ( 'true' == $_GET[ 'settings-updated' ] ) {
            $errors = get_settings_errors( 'um2u_qualtrics_account_info' );
            if ( is_array( $errors ) && count( $errors ) ) {
                foreach ( $errors as $error ) {
                    $messages[] = '<div id="' . $error->code . '" class="error fade"><p><strong>' . $error[ 'message' ] . '</strong></p></div>';
                }
            } else {
                $messages[] = '<div id="message" class="updated fade"><p><strong>' . __( 'Configuration options saved' ) . '.';
            }
        }
        if ( is_array( $messages ) ) foreach ( $messages as $message ) echo $message;
        ?>
            <div class="wrap">
                <h2><?php _e( 'UMatter2Us Settings' ); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'umatter2us_settings' ); ?>
                    <?php do_settings_sections( 'umatter2us-menu' ); ?>
                    <p class="submit">
                        <input name="submit" type="submit" class="button-primary" value="<?php _e( 'Save Settings' ); ?>" />
                    </p>
                </form>
            </div>
        <?php
    }
    
    /**
     * Outputs menu for importing Qualtrics survey data into usermeta table
     */
    function import_surveys_menu() {
		/*
        if ( 'true' == $_GET[ 'settings-updated' ] && isset( $this->settings[ 'qtrx_survey_to_import' ] ) ) {
            // get the survey results
            $data = $this->qtrx->getResponseData( array( 'SurveyID' => $this->settings[ 'qtrx_survey_to_import' ], 'Format' => 'XML' ) );
            // if there are results to process
            if ( is_array( $data ) ) {
                $updated = 0;
                // for each response
                foreach ( $data as $response ) {
                    // get the community member associated with the response
                    $member = get_user_by( 'login', (string) $response->ExternalDataReference );
                    if ( 0 != $member->ID ) {
                        if ( 2 == $member->ID ) {
                            //$this->debug( $response ); exit;
                        }
                        // convert the SimpleXML response into an array
                        //$response = json_decode( json_encode( $response ), true );
                        $response = $this->simplexml_to_array( $response );
                        // add their response data to their user meta
                        $surveys = $new_surveys = get_user_meta( $member->ID, 'surveys', true );
                        $new_surveys[ $this->settings[ 'qtrx_survey_to_import' ] ] = $response;
                        if ( update_user_meta( $member->ID, 'surveys', $new_surveys, $surveys ) ) $updated++;
                    }
                }
            }
            // remove the survey id of the just imported survey
            unset( $this->settings[ 'qtrx_survey_to_import' ] );
            update_option( 'umatter2us_settings', $this->settings );
            
            $messages[] = '<div class="updated fade"><p><strong>' . __( 'Surveys imported for ' ) . $updated . __( ' members' ) . '.</div>';
        }
        if ( is_array( $messages ) ) foreach ( $messages as $message ) echo $message;
        ?>
            <div class="wrap">
                <h2><?php _e( 'Import Qualtrics Survey' ); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'umatter2us_settings' ); ?>
                    <?php do_settings_sections( 'um2u-import-surveys-menu' ); ?>
                    <p class="submit">
                        <input name="submit" type="submit" class="button-primary" value="<?php _e( 'Import Survey' ); ?>" />
                    </p>
                </form>
            </div>
        <?php
    	*/
	}
    
    /**
     * Utility function to convert SimpleXML objects into arrays
     */
    function simplexml_to_array( $obj ) {
        $arr = (array) $obj;
        if ( empty( $arr ) ) {
            $arr = '';
        } else {
            foreach ( $arr as $k => $v ) {
                if ( ! is_scalar( $v ) ) $arr[ $k ] = $this->simplexml_to_array( $v );
            }
        }
        return $arr;
    }
    
    /**
     * Outputs menu for creating Qualtrics panels
     */
    function create_panels_menu() {
        ?>
        <div class="wrap">
            <h2><?php _e( 'Import Qualtrics Survey' ); ?></h2>
            <?php settings_fields( 'umatter2us_settings' ); ?>
            <?php do_settings_sections( 'um2u-create-panels-menu' ); ?>
            <p class="submit">
                <input id="um2u_create_panel_button" name="submit" type="button" class="button-primary" value="<?php _e( 'Create Panel' ); ?>" />
            </p>
        </div>
        <?php
    }
    
    /**
     * Outputs a menu for adding surveys and other settings to the user profile pages
     */
    function user_profile_menu() {
        ?>
        <div class="wrap">
            <h2><?php _e( 'User Profile Settings' ); ?></h2>
            <?php settings_fields( 'umatter2us_settings' ); ?>
            <?php do_settings_sections( 'um2u-user-profile-menu' ); ?>
            <p class="submit">
                <input id="um2u_profile_settings_button" name="submit" type="button" class="button-primary" value="<?php _e( 'Save User Profile Settings' ); ?>" />
            </p>
        </div>
        <?php
    }
    
    /**
     * Ajax call which outputs a CSV file for newly generate panels
     */
    function wp_ajax_um2u_generate_panel_csv() {
        /*
		// TODO: Handle when the post variables are not set
        $panelID   = $_POST[ 'panelID'  ];
        $survey_id = $_POST[ 'surveyID' ];
        $panel = $this->qtrx->getPanel( array( 'PanelID' => $panelID, 'Format' => 'CSV', 'EmbeddedData' => 'eID,sID,Section' ) );
        $panel = explode( "\n", trim( $panel ) );
        $headers = str_getcsv( substr( trim( $panel[ 0 ] ), 0, -1 ) );
        $panelists = array();
        for ( $i = 1; $i < count( $panel ); $i++ ) {
            $panelist = array();
            for ( $j = 0; $j < count( $headers ); $j++ ) {
                $p = str_getcsv( substr( trim( $panel[ $i ] ), 0, -1 ) );
                if ( 'Email' == $headers[ $j ] ) {
                    $panelist[ 'PrimaryEmail' ] = $p[ $j ];
                } else {
                    $panelist[ preg_replace( '/^\xEF\xBB\xBF/', '', $headers[ $j ] ) ] = $p[ $j ];
                }
            }
            $panelists[ $panelist[ 'eID' ] ] = $panelist;
        }
        $users = get_users();
        foreach ( $users as $u ) {
            $user = new WP_User( $u->ID );
            if ( ! in_array( 'administrator', $user->roles ) ) {
                $data = get_user_meta( $u->ID, 'surveys', true );
                $panelists[ $u->user_login ][ 'FirstName' ] = get_user_meta( $u->ID, 'first_name', true ); 
                $panelists[ $u->user_login ][ 'LastName'  ] = get_user_meta( $u->ID, 'last_name',  true ); 
                if ( '' != $data && array_key_exists( $survey_id, $data ) ) {
                    $data = $data[ $survey_id ];
                    $panelists[ $u->user_login ][ 'Goal1' ] = ( ! is_array( $data[ 'Q6_1_TEXT' ] ) ) ? str_replace( "\n" , ' ', str_replace( ';', '--', trim( $data[ 'Q6_1_TEXT' ] ) ) ) : 'not specified';
                    $panelists[ $u->user_login ][ 'Goal2' ] = ( ! is_array( $data[ 'Q6_2_TEXT' ] ) ) ? str_replace( "\n" , ' ', str_replace( ';', '--', trim( $data[ 'Q6_2_TEXT' ] ) ) ) : 'not specified';
                    $panelists[ $u->user_login ][ 'Goal3' ] = ( ! is_array( $data[ 'Q6_3_TEXT' ] ) ) ? str_replace( "\n" , ' ', str_replace( ';', '--', trim( $data[ 'Q6_3_TEXT' ] ) ) ) : 'not specified';
                    $panelists[ $u->user_login ][ 'Goal4' ] = ( ! is_array( $data[ 'Q6_4_TEXT' ] ) ) ? str_replace( "\n" , ' ', str_replace( ';', '--', trim( $data[ 'Q6_4_TEXT' ] ) ) ) : 'not specified';
                    $panelists[ $u->user_login ][ 'Goal5' ] = ( ! is_array( $data[ 'Q6_5_TEXT' ] ) ) ? str_replace( "\n" , ' ', str_replace( ';', '--', trim( $data[ 'Q6_5_TEXT' ] ) ) ) : 'not specified';
                    $panelists[ $u->user_login ][ 'PlannedHours' ] = ( ! is_array( $data[ 'Q7' ] ) ) ? $data[ 'Q7' ] : 'unspecified';
                } else {
                    $panelists[ $u->user_login ][ 'Goal1' ] = 'not specified';
                    $panelists[ $u->user_login ][ 'Goal2' ] = 'not specified';
                    $panelists[ $u->user_login ][ 'Goal3' ] = 'not specified';
                    $panelists[ $u->user_login ][ 'Goal4' ] = 'not specified';
                    $panelists[ $u->user_login ][ 'Goal5' ] = 'not specified';
                    $panelists[ $u->user_login ][ 'PlannedHours' ] = 'unspecified';
                }
            }
        }
        $headers = array_merge( $headers, array( 'Goal1', 'Goal2', 'Goal3', 'Goal4', 'Goal5', 'PlannedHours' ) );
        $new_panel = implode( ';', $headers ) . "\n";
        foreach ( $panelists as $p ) {
            if ( isset( $p[ 'RecipientID' ] ) ) $new_panel .= implode( ';', $p ) . "\n";
        }
        $ud   = wp_upload_dir();
        $npf  = 'new_panel_' . mt_rand() . '.csv';
        $npfh = fopen( $ud[ 'path' ] . '/' . $npf, 'w' );
        fwrite( $npfh, $new_panel );
        fclose( $npfh );
        echo '<a href="' . $ud[ 'url' ] . '/' . $npf . '">' . __( 'Download New Panel CSV file' ) . '</a>';
		*/
        exit;
    }
    
    /**
     * Displays instructions for setting up main UMatter2Us Settings
     */
    function umatter2us_settings_section() {
        echo '<p>' . __( 'Configure UMatter2Us by filling in the values below.' ) . '</p>';
    }
    
    /**
     * Displays instructions for importing qualtircs survey results
     */
    function import_qualtrics_surveys_section() {
        echo 'Select a survey below.  Clicking "Import Survey" will update the user meta with the results of the survey.';
    }

    /**
     * Displays instructions for creating new weekly panels
     */
    function create_qualtrics_panels_section() {
        echo __( 'Select a panel, and a survey from which to populate data in an updated panel.' );
    }
    
    /**
     * Displays instructions for adding surveys to the user profiles
     */
    function user_profile_settings_section() {
        echo __( 'Add surveys whose results should be displayed on the user profile.' );
    }
    
    /**
     * Qualtrics username field
     */
    function qtrx_username_field() {
        echo '<input type="text" id="qualtrics_username" name="umatter2us_settings[qtrxuser]" value="' . $this->settings[ 'qtrxuser' ] . '" />';
    }
    
    /**
     * Qualtrics token field
     */
    function qtrx_token_field() {
        echo '<input type="password" id="qualtrics_token" name="umatter2us_settings[qtrxtoken]" value="' . $this->settings[ 'qtrxtoken' ] . '" />';
    }

    /**
     * Qualtrics library field
     */
    function qtrx_library_field() {
		$qtrx = $this->getQualtrics();
		if ( ! is_wp_error( $qtrx ) ) {
			$qlib = $this->settings[ 'qtrxlib' ];
			$info = $qtrx->getUserInfo();
			$lib_opts = '<option value="">Select a library...</option>';
			foreach ( $info->Libraries as $lib ) {
				$selected = $qlib == $lib->ID ? ' selected' : '';
				$lib_opts .= '<option value="' . $lib->ID . '"' . $selected . '>' . $lib->Name . '</option>';
			}
			echo '<select id="qualtrics_library" name="umatter2us_settings[qtrxlib]">' . $lib_opts . '</select>';
		} else {
			echo '<p id="qualtrics_library"><em>Please enter a valid username and token.</em></p>';
		}
    }

    /**
     * List of Qualtrics surveys from which one can be chosen as the weekly self-eval
     */
    function qtrx_wklysrvy_field() {
		$qtrx = $this->getQualtrics();
		if ( ! is_wp_error( $qtrx ) ) {
			$survey = $this->settings[ 'um2u_weekly_self_eval_survey_id' ];
			$surveys = $qtrx->getSurveys();
	        $survey_opts  = '<select id="self_eval_survey" name="umatter2us_settings[um2u_weekly_self_eval_survey_id]">';
	        $survey_opts .= '<option value="">Select a survey...</option>';
	        foreach ( $surveys->Surveys as $s ) {
				$selected = $survey == $s->SurveyID ? ' selected' : '';
	            $survey_opts .= '<option value="' . $s->SurveyID . '"' . $selected . '>' . htmlspecialchars( $s->SurveyName ) . '</option>';
	        }
	        $survey_opts .= '</select>';
			echo $survey_opts;
		} else {
			echo '<p id="self_eval_survey"><em>Please enter a valid username, token, and library.</em></p>';
		}
    }
    
    /**
     * List of Qualtrics panels from which one can be chosen to modify
     */
    function panel_to_modify_field() {
		/*
        $panels = $this->qtrx->getPanels();
        //$this->debug( $panels );
        echo '<select name="umatter2us_settings[qtrx_panel_to_modify]">';
        echo '<option value="">Select a Panel</option>';
        foreach ( $panels as $panel ) {
            echo '<option value="' . (string)$panel->PanelID . '">' . (string)$panel->Name . '</option>';
        }
        echo '</select>';
		*/
    }
    
    /**
     * Table of Qualtrics surveys that can be added to/removed from a user's profile
     */
    function author_page_surveys_field() {
        /*
		// generate an array with survey names and IDs
        $all_surveys = $added = array();
        foreach ( $this->qtrx->getSurveys() as $s ) $all_surveys[ (string) $s->SurveyID ] = (string) $s->SurveyName;
        // get the surveys that have already been selected and sort them
        $surveys = $this->settings[ 'um2u_author_page_surveys' ];
        if ( is_array( $surveys ) && count( $surveys ) > 0 ) {
            foreach ( $surveys as $key => $s ) {
                $order[ $key ] = $s[ 'order' ];
                $added[] = $s[ 'id' ];
            } 
            array_multisort( $order, SORT_ASC, $surveys );
        }
        ?>
        <table class="form-table">
            <thead>
                <tr>
                    <th scope="col">Survey</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody><?php
                if ( is_array( $surveys ) && count( $surveys ) > 0 ) { foreach ( $surveys as $i => $s ) { ?>
                    <tr>
                        <td>
                            <input type="hidden" name="umatter2us_settings[um2u_author_page_surveys][<?php echo $i;  ?>][id]"    value="<?php echo $s[ 'id'    ];  ?>" />
                            <input type="hidden" name="umatter2us_settings[um2u_author_page_surveys][<?php echo $i;  ?>][name]"  value="<?php echo $s[ 'name'  ];  ?>" />
                            <input type="hidden" name="umatter2us_settings[um2u_author_page_surveys][<?php echo $i;  ?>][order]" value="<?php echo $s[ 'order' ];  ?>" />
                            <?php echo $s[ 'name' ]; ?>
                        </td>
                        <td>
                            <a href="#" class="remove_author_survey" title="<?php echo $s[ 'id' ]; ?>">Remove</a> 
                            <a href="#" class="moveup_author_survey" title="<?php echo $s[ 'id' ]; ?>">Move Up</a> 
                            <a href="#" class="movedn_author_survey" title="<?php echo $s[ 'id' ]; ?>">Move Down</a> 
                        </td>
                    </tr>
                <?php } } ?>
                <tr>
                    <tr>
                        <td>
                            <select id="new_author_survey">
                                <option value="">Select a survey...</option><?php
                                foreach ( $all_surveys as $id => $s ) {
                                    if ( ! in_array( $id, $added ) ) {
                                        echo '<option value"' . $id . '">' . $s . '</option>';
                                    }
                                } ?>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="button" id="new_author_survey_button">Add Survey</button>
                        </td>
                    </tr>
                </tr>
            </tbody>
        </table>
        <?php
    	*/
	}
    
    /**
     * Validates user input in settings pages
     */
    function validate_umatter2us_settings( $input ) {
        if ( isset( $input[ 'qtrx_survey_to_import' ] ) && isset( $input[ 'qtrx_panel_to_modify' ] ) ) {
            $this->settings[ 'qtrx_survey_to_import' ] = $input[ 'qtrx_survey_to_import' ];
            $this->settings[ 'qtrx_panel_to_modify'  ] = $input[ 'qtrx_panel_to_modify'  ];
            return $this->settings;
        }
        if ( isset( $input[ 'qtrx_survey_to_import' ] ) ) {
            $this->settings[ 'qtrx_survey_to_import' ] = $input[ 'qtrx_survey_to_import' ];
            return $this->settings;
        }
        if ( isset( $input[ 'qtrxuser' ] ) && isset( $input[ 'qtrxtoken' ] ) ) {
            $temp_qtrx = new Qualtrics( $input[ 'qtrxuser' ], $input[ 'qtrxtoken' ] );
            if ( $errors = $temp_qtrx->is_misconfigured() ) {
                add_settings_error( 'um2u_qualtrics_account_info', $errors, $errors, 'error' );
            } else {
                $this->settings[ 'qtrxuser' ] = $input[ 'qtrxuser' ];
                $this->settings[ 'qtrxtoken' ] = $input[ 'qtrxtoken' ];
            }
        } else {
            add_settings_error( 'um2u_qualtrics_account_info', 'credentials_missing', __( 'The Qualtrics username and password cannot be blank.' ), 'error' );
        }
        if ( isset( $temp_qtrx ) && isset( $input[ 'qtrxlib' ] ) ) {
            $panels = $temp_qtrx->getPanels( array( 'LibraryID' => $input[ 'qtrxlib' ] ) );
            if ( is_wp_error( $panels ) ) {
                add_settings_error( 'um2u_qualtrics_account_info', 'invalid_library', __( 'The Qualtrics Library ID entered could not be accessed.  Please check that it is correct and try again.' ), 'error' );
            } else {
                $this->settings[ 'qtrxlib' ] = $input[ 'qtrxlib' ];
            }
        }
		if ( isset( $temp_qtrx ) && isset( $input[ 'qtrxlib' ] ) && isset( $input[ 'um2u_weekly_self_eval_survey_id' ] ) ) {
			$this->settings[ 'um2u_weekly_self_eval_survey_id' ] = $input[ 'um2u_weekly_self_eval_survey_id' ];
		}
        return $this->settings;
    }
    
    /**
     * Function to import users and photos from a zip file
     */
    function import_users() {
        // create a variable to hold new users
        $nu = array();
        // debugging variables
        $added = $updated = 0;
        // get the directories for photo upload and max dimension
        $ud = wp_upload_dir();
        $dir = trailingslashit( $ud[ 'basedir' ] ) . 'userphoto';
        if ( ! file_exists( $dir ) ) mkdir( $dir, 0775, true );  // creates the userphoto directory if necessary
        $maxdim = get_option( 'userphoto_maximum_dimension' );
        $thumbdim = get_option( 'userphoto_thumb_dimension' );

        // check to see if our zip file was uploaded
    	if ( is_array( $_FILES[ 'new_users_file' ] ) && $_FILES[ 'new_users_file' ][ 'name' ] ) {
    	    // open the zip archive
    	    $za = new ZipArchive();
    	    if ( $za->open( $_FILES[ 'new_users_file' ][ 'tmp_name' ] ) ) {
    	        // try to open the roster file
    	        if ( $rf = $za->getFromName( 'roster.csv' ) ) {
        	        // extract the contents
        	        $roster = explode( "\n", $rf );
        	        // for each line of the file
        	        foreach ( $roster as $r ) {
        	            // get student info
        	            $si = str_getcsv( trim( $r ) );
        	            // loop through and add the necessary properties to the new users array
        	            $nu[] = array(
        	                'user_login'   => trim( $si[ 2 ] ), 
        	                'user_email'   => trim( $si[ 4 ] ), 
        	                'first_name'   => trim( $si[ 0 ] ), 
        	                'last_name'    => trim( $si[ 1 ] ), 
        	                'user_pass'    => trim( $si[ 3 ] ), 
        	                'display_name' => trim( $si[ 0 ] ) . ' ' . trim( $si[ 1 ] ), 
        	                'nickname'     => trim( $si[ 0 ] ),
        	                'role'         => 'author'
        	            );
        	        }
        	        // loop through the array of new users
        	        foreach ( $nu as $u ) {
        	            if ( $user = get_user_by( 'login', $u[ 'user_login' ] ) ) {
        	                unset( $u[ 'user_pass' ] );
        	                $u[ 'ID' ] = $user->ID;
        	                wp_update_user( $u );
        	                $updated++;
        	            } else {
        	                $id = wp_insert_user( $u );
        	                $user = get_user_by( 'login', $u[ 'user_login' ] );
        	                //wp_new_user_notification( $id, $u[ 'user_pass' ] );
        	                $added++;
        	            }
        	            // set user image filenames
        	            $imagefile = $user->ID . '.' . preg_replace('{^.+?\.(?=\w+$)}', '', strtolower( $u[ 'user_login' ] . '.jpg' ) );
        	            $imagepath = $dir . '/' . $imagefile;
        	            $thumbfile = preg_replace( '/(?=\.\w+$)/', '.thumbnail', $imagefile );
        	            $thumbpath = $dir . '/' . $thumbfile;

        	            // get the image and save it
        	            $if = $za->getFromName( $u[ 'user_login' ] . '.jpg' );
        	            $img = imagecreatefromstring( $if );
        	            imagejpeg( $img, $imagepath );
        	            imagejpeg( $img, $thumbpath );
        	            $image = imagecreatefromjpeg( $imagepath );
        	            $thumb = imagecreatefromjpeg( $thumbpath );

        	            // resize the images
        	            list( $w, $h ) = getimagesize( $imagepath );
        	            if ( $w > $h ) {
        	                $nw = $maxdim;
        	                $ir = $w / $nw;
        	                $nh = $h / $ir;
        	                $tw = $thumbdim;
        	                $tr = $w / $tw;
        	                $th = $h / $tr;
        	            } else {
        	                $nh = $maxdim;
        	                $ir = $h / $nh;
        	                $nw = $w / $ir;
        	                $th = $thumbdim;
        	                $tr = $h / $th;
        	                $tw = $w / $tr;
        	            }

        	            $resized = imagecreatetruecolor( $nw, $nh );
        	            @imagecopyresampled( $resized, $image, 0, 0, 0, 0, $nw, $nh, $w, $h );
        	            imagejpeg( $resized, $imagepath );
        	            $resized = imagecreatetruecolor( $tw, $th );
        	            @imagecopyresampled( $resized, $thumb, 0, 0, 0, 0, $tw, $th, $w, $h );
        	            imagejpeg( $resized, $thumbpath );

        	            // update metadata
        	            update_usermeta( $user->ID, 'userphoto_approvalstatus', 2 );
    					update_usermeta( $user->ID, "userphoto_image_file", $imagefile); //TODO: use userphoto_image
    					update_usermeta( $user->ID, "userphoto_image_width", $nw); //TODO: use userphoto_image_width
    					update_usermeta( $user->ID, "userphoto_image_height", $nh);
    					update_usermeta( $user->ID, "userphoto_thumb_file", $thumbfile);
    					update_usermeta( $user->ID, "userphoto_thumb_width", $tw);
    					update_usermeta( $user->ID, "userphoto_thumb_height", $th);

        	        }
    	        } else {
    	            // couldn't open up roster.csv as a file stream
    	            die( 'couldn\t open roster.csv' );
    	        }
    	    } else {
    	        // couldn't open uploaded file as a zip
    	        die( 'couldn\'t open the zip file' );
    	    }
    		echo '<div id="message" class="updated fade"><p><strong>' . $added . ' added. ' . $updated . ' updated.</strong></p></div>';
    	}
    	?>
    	<div class="wrap">
    		<h2><?php _e('Import Users'); ?></h2>
    		<p>
    			Select a .zip archive with a roster.csv file and an .jpg file for each new user saved with the username as the image name.
    			The .csv file should have the following columns (no headers): first name, last name, username, password, email
    		</p>
    		<form enctype="multipart/form-data" method="post" action="<?php admin_url( 'users.php?page=morphatic-import-users' ); ?>">
    			<?php wp_nonce_field('update-options'); ?>
    			<input type="hidden" name="action" value="update" />
    			<table class="form-table">
    				<tr>
    					<th scope="row">Select a file:</th>
    					<td>
    						<input type="file" name="new_users_file" />
    					</td>
    				</tr>	
    			</table>
    			<p class="submit">
    				<input type="submit" class="button-primary" value="<?php _e('Import Users'); ?>" />
    			</p>
    		</form>
    	</div>
    	<?php    
    }
    
    /**
     * Displays a button that allows an email reminder be sent to all users of their creds
     */
    function send_passwords() {
        if ( isset( $_POST[ 'action' ] ) && 'update' == trim( $_POST[ 'action' ] ) ) {
            // get all the users
            $users = get_users();
            foreach ( $users as $user ) {
                $ud = get_userdata( $user->ID );
                if ( ! in_array( 'administrator', $ud->roles ) ) {
                    echo $user->display_name . '<br />';
                }
            }
        }
    	?>
    	<div class="wrap">
    		<h2><?php _e('Send Password Reminders'); ?></h2>
    		<p>
    			Clicking the button below will send a reminder email to all non-administrator users of this blog with their passwords.
    		</p>
    		<form enctype="multipart/form-data" method="post" action="<?php admin_url( 'users.php?page=morphatic-send-user-passwords' ); ?>">
    			<?php wp_nonce_field('update-options'); ?>
    			<input type="hidden" name="action" value="update" />
    			<p class="submit">
    				<input type="submit" class="button-primary" value="<?php _e('Send Password Reminders'); ?>" />
    			</p>
    		</form>
    	</div>
    	<?php
    }
    
	/**
	 * Adds a shortcode to create a page that allows students to upload 
	 * links to a github repo, and creates a script that can add all of them
	 * to a directory on a local hard drive
	 */
	function add_github_repo( $atts ) {
		extract( shortcode_atts( array(
			'dir' => 'repos'
		), $atts ) );
		
		$nonce = wp_nonce_field( 'save_github_repo', 'um2u_github_nonce', true, false );
		
		$form = <<<GIT
		<!--<p><a href="">Click here to download a script that will get all the repos listed on this page</a></p>-->
		<p><strong>Add a Repo</strong></p>
		<form action="#" method="post" name="um2u_add_github_repo_form" id="um2u_add_github_repo_form">
			$nonce
			<table>
				<tbody>
					<tr>
						<th scope="row"><label for="team_name">Your Team Name:</label></th>
						<td><input type="text" name="team_name" id="team_name"></td>
					</tr>
					<tr>
						<th scope="row"><label for="project_name">Your Project Name:</label></th>
						<td><input type="text" name="project_name" id="project_name"></td>
					</tr>
					<tr>
						<th scope="row"><label for="repo_url">Your Github repo URL:</label></th>
						<td>
							<input type="url" name="repo_url" id="repo_url"><br>
							<em>(e.g. http://github.com/morphatic/d3BarChart.git)</em>
						</td>
					</tr>
					<tr>
						<td colspan="2" style="text-align:right;">
							<input type="submit" class="button" value="Add Your Repo">
						</td>
					</tr>
				</tbody>
			</table>
		</form>
GIT;
		$out  = is_user_logged_in() ? $form : '';
		$out .= '<h3>Repos for this course</h3>';
		$out .= '<div id="um2u_repos">';
		if ( $repos = get_option( 'um2u_repos' ) ) {
			foreach ( $repos as $team => $repo ) {
				$out .= "<h4>$team</h4><ul>";
				foreach ( $repo as $r ) {
					$url = str_replace( '.git', '', $r[ 'url' ] );
					$out .= '<li><a href="' . $url . '">' . $r[ 'name' ] . '</a></li>';
				}
				$out .= '</ul>';
			}
		}
		$out .= '</div>';
		$adminajax = admin_url( 'admin-ajax.php' );
		$out .= <<<GJS
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				$( '#um2u_add_github_repo_form' ).submit( function( e ) {
					e.preventDefault();

					$.post( '$adminajax', { action: 'save_github_repo', data: $( this ).serialize() } )
					.done( function( res ) {
						var html = '', url;
						$.each( res.repos, function( team, reps ) {
							html += '<h4>' + team + '</h4><ul>';
							$.each( reps, function( i, repo ) {
								url = repo.url.replace( '.git', '' );
								html += '<li><a href="' + url + '">' + repo.name + '</a>';
							});
							html += '</ul>';
						});
						$( '#um2u_repos' ).html( html );
					})
					.fail( function( res ) {
						console.log( res );
					});

					return false;
				});
			});
		</script>
GJS;
		return $out;
	}
	
	/**
	 * AJAX request handler that saves the new github repos
	 */
	function save_github_repo() {
		header( 'content-type: application/json' );
		parse_str( $_POST[ 'data' ] );
		$success = 1;
		// exit if nonce doesn't verify
		if ( ! wp_verify_nonce( $um2u_github_nonce, 'save_github_repo' ) ) {
			header( 'HTTP/1.0 403 Forbidden: Invalid nonce', true, 403 );
			exit( json_encode( (object) array( 'success' => 0, 'error' => 'invalid nonce' ) ) );
		}
		
		$repos = get_option( 'um2u_repos' );
		if ( $team_name && $project_name && $repo_url ) {
			$repos[ $team_name ][] = array( 'name' => $project_name, 'url' => $repo_url);
			update_option( 'um2u_repos', $repos );
		}
		
		echo json_encode( (object) array( 'success' => 1, 'repos' => $repos ) );
		exit;
	}

	/**
	 * TODO: AJAX request that creates a bash script to clone all repos from the site
	 */
	function get_github_bash_script() {
		/*
		$repos = get_option( 'um2u_repos' );
		foreach ( $repos as $team => $repo ) {
			
		}
		$sh = "#!/bin/sh\n";
		*/
		exit;
	}

    /**
     * AJAX call to get the list of surveys
     */
    function get_surveys() {
        header( 'content-type: application/json' );
        $user  = $_GET[ 'user'    ];
        $token = $_GET[ 'token'   ];
        $lib   = $_GET[ 'library' ];
        $qtrx = new Qualtrics( $user, $token, $library );
        $survey_opts  = '<select id="self_eval_survey" name="umatter2us_settings[um2u_weekly_self_eval_survey_id]">';
        $survey_opts .= '<option value="">Select a survey...</option>';
        $surveys = $qtrx->getSurveys();
        if ( ! is_wp_error( $surveys ) ) {
            if ( 0 == count( $surveys->Surveys ) ) {
                $return = (object) array( 'success' => 0, 'message' => '<p>There are no surveys associated with this Qualtrics account.  Please log into qualtrics and add at least one survey.</p>' );
                echo json_encode( $return );
            } else {
                foreach ( $surveys->Surveys as $s ) {
                    $survey_opts .= '<option value="' . $s->SurveyID . '">' . htmlspecialchars( $s->SurveyName ) . '</option>';
                }
                $survey_opts .= '</select>';
                $return = (object) array( 'success' => 1, 'opts' => $survey_opts );
                echo json_encode( $return );
            }
        } else {
			header( 'Status: ' . $info->get_error_message(), true, 500 );
            $return = (object) array( 'success' => 0, 'message' => '<p>' . $info->get_error_message() . '</p>' );
            echo json_encode( $return );
        }
        exit;
    }

    /**
     * AJAX call to get the list of libraries
     */
    function get_libraries() {
        header( 'content-type: application/json' );
        $user  = $_GET[ 'user'  ];
        $token = $_GET[ 'token' ];
        $qlib_opts  = '<select name="umatter2us_settings[um2u_weekly_self_eval_survey_id]">';
        $qlib_opts .= '<option value="">Select a Library...</option>';
        $qtrx = new Qualtrics( $user, $token );
        $info = $qtrx->getUserInfo();
        if ( ! is_wp_error( $info ) ) {
            foreach ( $info->Libraries as $lib ) {
                $qlib_opts .= '<option value="' . $lib->ID . '">' . $lib->Name . '</option>';
            }
			$qlib_opts .= '</select>';
            $return = (object) array( 'success' => 1, 'opts' => $qlib_opts );
            echo json_encode( $return );
        } else {
			header( 'Status: ' . $info->get_error_message(), true, 500 );
            $return = (object) array( 'success' => 0, 'message' => '<p>' . $info->get_error_message() . '</p>' );
            echo json_encode( $return );
        }
        exit;
    }

    /**
     * Gets a Qualtrics object
     */
    function getQualtrics() {
        $lib = $this->settings[ 'qtrxlib' ];
        if ( '' == $lib ) {
            return new WP_Error( 'Qualtrics Library not set', __( 'The Qualtrics Library ID has not been set.' ) );
        } else {
            $user  = $this->settings[ 'qtrxuser'  ];
            $token = $this->settings[ 'qtrxtoken' ];
            return new Qualtrics( $user, $token, $lib );
        }
    }

    /**
     * Debug is a utility function for outputting debug messages.
     */
    function debug( $var ) {
        echo '<pre>';
        print_r( $var );
        echo '</pre>';
    }
}

