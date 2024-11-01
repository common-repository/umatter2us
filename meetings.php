<?php
/*
 * Title: UM2U Meetings 
 * File: meetings.php
 * Author: Morgan Benton
 * Description: This class will set up a meetings menu where you can
 *              view/add/edit meetings 
 */
/**
 * UMatter2Us Meetings Class
 * 
 * This class instantiates the methods for accessing the Meetings API.
 * 
 * @package    UMatter2Us
 * @version    1.0
 * @author    Morgan Benton <bentonmc@jmu.edu>
 * @author    Matthew Hurd
 */
 class UM2UMeetings {
     /*
      *Constructors
      */
    function UM2UMeetings() {
         $this->__construct();
     }
     
    function __construct() {
        // retrieve our settings
        $this->meetings = get_option( 'um2umeetings_settings');

        add_action( 'init', array( &$this, 'add_meetings_post_type' ) );
        add_action( 'save_post', array( &$this, 'save_meta_data' ) );
 
        // setup a custom template to be used with our custom post type
        add_filter( 'the_content', array( &$this, 'the_content' ) );
        
        // primary actions
        if ( is_admin() ) {
            add_action( 'plugins_loaded',     array( &$this, 'plugins_loaded' ) );
            add_action( 'admin_init',         array( &$this, 'admin_init'     ) );
            add_action( 'admin_menu',         array( &$this, 'admin_menu'     ) );
            add_action( 'admin_head',         array( &$this, 'admin_head'     ) );
            add_action( 'admin_print_styles', array( &$this, 'enqueue_um2umeetings_styles' ) );            
            add_action( 'wp_ajax_um2u_generate_exceptions', array( &$this, 'wp_ajax_um2u_generate_exceptions' ) );
        }
    }
     
    function plugins_loaded() {
        // register styles
        wp_register_style( 'jquery_ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/themes/base/jquery-ui.css' );
        wp_register_style( 'um2umeetings_css', plugins_url() . '/umatter2us/css/um2umeetings.css' );
        
        // register scripts
        wp_register_script( 'jquery_timepicker', plugins_url() . '/umatter2us/js/jquery_timepicker.js', array( 'jquery', 'jquery-ui-slider', 'jquery-ui-datepicker' ), '1.0', true );
        wp_register_script( 'um2umeetings_js',   plugins_url() . '/umatter2us/js/um2umeetings.js', 'jquery_timepicker', '1.0', true );
        wp_localize_script( 
            'um2umeetings_js', 
            'UM2UMeetings', 
            array( 
                'ajaxurl' => admin_url( 'admin-ajax.php' ), 
                'um2u_exceptions_nonce' => wp_create_nonce( 'um2u_exceptions_nonce' )
            ) 
        );
    }
 
    function admin_init() {
        // register settings
        register_setting( 'um2umeetings_settings', 'um2umeetings_settings', array( &$this, 'validate_um2umeetings_settings' ) );

        add_filter( 'manage_um2u_meeting_posts_columns',          array( &$this, 'change_columns'                        ) );
        add_action( 'manage_um2u_meeting_posts_custom_column',    array( &$this, 'custom_columns' ), 10, 2 );
        add_filter( 'manage_edit-um2u_meeting_sortable_columns',  array( &$this, 'sortable_columns'                      ) );
        add_filter( 'request',                                    array( &$this, 'sortable_columns_orderby'              ) );
        add_action( 'restrict_manage_posts',                      array( &$this, 'taxonomy_filter_restrict_manage_posts' ) );
        add_filter( 'parse_query',                                array( &$this, 'taxonomy_filter_post_type_request'     ) );        
        add_filter( 'request',                                    array( &$this, 'add_cpt_to_feed'                       ) );
    }
   
    function admin_menu() {
    }

    /**
     * Load tinymce
     */
    function admin_head() {
        //$this->enqueue_um2umeetings_styles();
        $this->enqueue_um2umeetings_scripts();
    }

    /**
     * Enqueue styles
     */
    function enqueue_um2umeetings_styles() {
        global $post;
        if ( 'um2u_meeting' == $post->post_type ) {
            wp_enqueue_style( 'jquery_ui' );
            wp_enqueue_style( 'um2umeetings_css' );            
        }
    }
    
    /**
     * Enqueue scripts
     */
    function enqueue_um2umeetings_scripts() {
        wp_enqueue_script( 'jquery_timepicker' );
        wp_enqueue_script( 'um2umeetings_js' );
    }

    /**
     * Adds meta boxes
     */
    function add_custom_metabox() {
         add_meta_box( 'time-meta',       __( 'Time' ),       array( &$this, 'time_meta_options' ),       'um2u_meeting', 'side',   'high' );
         add_meta_box( 'location-meta',   __( 'Location' ),   array( &$this, 'location_meta_options' ),   'um2u_meeting', 'side',   'high' );
         add_meta_box( 'importance-meta', __( 'Importance' ), array( &$this, 'importance_meta_options' ), 'um2u_meeting', 'side',   'high' );
         add_meta_box( 'repetition',      __( 'Repetition' ), array( &$this, 'repetition_meta_options' ), 'um2u_meeting', 'normal', 'high' );
         add_meta_box( 'invitees-meta',   __( 'Invitees' ),   array( &$this, 'invitees_meta_options' ),   'um2u_meeting', 'normal', 'high' );
     }

    /**
     * Adds time meta box
     */
    function time_meta_options(){
        global $post;
        $start = get_post_meta( $post->ID, '_start_time', true );
        $end   = get_post_meta( $post->ID, '_end_time',   true );
        echo '<p>' . __( 'Please select the meeting start and end times:' ) . '</p>';
        echo '<p>' . __( 'Start:' ) . ' <input type="text" id="_um2u_meeting_start_time" name="_start_time" value="' . $start . '" /><br />';
        echo '<p>' . __( 'End:' )   . ' <input type="text" id="_um2u_meeting_end_time"   name="_end_time"   value="' . $end   . '" />';
    }

    /**
     * Adds location text box 
     */
    function location_meta_options(){
        global $post;
        $location = get_post_meta( $post->ID, '_location', true );
        echo '<p>' . __( 'Please enter the location' ) . '</p>';
        echo '<p>' . '<form><input type="text" name="_location" value="' . $location . '" /></p>';
    }

    /**
     * Adds importance text box 
     */
    function importance_meta_options(){
        global $post;
        $importance = get_post_meta( $post->ID, '_importance', true );
        $importance_values = array( 'Mandatory', 'Recommended', 'Optional' );
        echo '<p>' . __( 'Please select the importance level:' ) . '</p><p>';
        for ( $i = 0; $i < 3; $i++ ) {
            $selected = ( $importance_values[ $i ] == $importance ) ? ' checked="checked"' : '';
            echo '<p><label><input type="radio" name="_importance" value="Mandatory"' . $selected . ' /> ' . $importance_values[ $i ] . '</label><br />';
        }
        echo '</p>';
    }

    /**
     * Adds attendees text box
     */
    function invitees_meta_options(){
        global $post, $wpdb;
        $groups = false;
        $invitees = get_post_meta( $post->ID, '_invitees', true );
        echo '<p>' . __( 'Select people to invite to the meeting.' ) . '<p>';
        // use User Access Manager to get groups if possible
        // check to see if User Access Manager is installed and active
        $ap = get_option( 'active_plugins' );
        if ( is_array( $ap ) && in_array( 'user-access-manager/user-access-manager.php', $ap ) ) {
            // UAM is installed and active, get groups
            $uam_groups = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}uam_accessgroups" );
            // if there are groups
            if ( $wpdb->num_rows ) {
                foreach( $uam_groups as $ug ) {
                    $group = array( 'id' => $ug->ID, 'name' => $ug->groupname, 'members' => array() );
                    // get the members of the group
                    $uam_members = $wpdb->get_results( "SELECT object_id as member FROM {$wpdb->prefix}uam_accessgroup_to_object WHERE object_type = 'user' AND group_id = {$ug->ID}" );
                    // if there are any members of this group
                    if ( $wpdb->num_rows ) {
                        // add them to our array
                        foreach ( $uam_members as $um ) {
                            $group[ 'members' ][] = $um->member;
                        }
                    }
                    // finally add this group to the array of groups
                    $groups[] = $group;
                }
            }
        }
        // do we have groups?
        if ( $groups ) {
            // output a section that allows invitees to be chosen by group, e.g. Section 1
            echo '<h3>' . __( 'Groups' ) . '</h3>';
            echo '<ul id="um2u_group_list">';
            foreach ( $groups as $g ) {
                $checked = ( is_array( $invitees[ 'groups' ] ) && in_array( $g[ 'id' ], $invitees[ 'groups' ] ) ) ? ' checked="checked"' : '';
                echo '<li><label><input type="checkbox" class="um2u_invitee_group" name="_invitees[groups][]" value="' . implode( ',', $g[ 'members'] ) . '"' . $checked . ' /> ' . $g[ 'name' ] . '</label></li>';
            }
            echo '</ul><div style="clear: both;"></div>';
        }
        // now output all of the members of the community individually
        // get all of the members
        $members = get_users();
        echo '<h3>' . __( 'Community Members' ) . '</h3>';
        echo '<ul id="um2u_invitee_list">';
        foreach ( $members as $m ) {
            $checked = ( is_array( $invitees[ 'members' ] ) && in_array( $m->ID, $invitees[ 'members' ] ) ) ? ' checked="checked"' : '';
            echo '<li><label><input type="checkbox" class="um2u_invitee" name="_invitees[members][]" value="' . $m->ID . '"' . $checked . ' /> ' . $m->display_name . '</label></li>';
        }
        echo '<div style="clear:both"></div>';
    }

    /**
     * Adds repetition meta box
     */
    function repetition_meta_options() {
        global $post;
        $rep = get_post_meta( $post->ID, '_repetition', true );
        $rep = ( '' === $rep ) ? array() : $rep;
        $dow = array( 'S', 'M', 'T', 'W', 'T', 'F', 'S' );
        echo '<table id="um2u_meeting_repetition"><tbody>';
        $checked = ( $rep[ 'repeats' ] ) ? ' checked="checked"' : '';
        echo '<tr><th>' . __( 'Repeats' ) . ':</th><td><input type="checkbox" name="_repetition[repeats]" value="1"' . $checked . ' /></td></tr>';
        echo '<tr><th>' . __( 'Frequency' ) . ':</th><td><select name="_repetition[frequency]" class="um2u_repetition" id="um2u_meeting_frequency">';
        foreach ( array( 'Daily', 'Weekly', 'Monthly' ) as $freq ) {
            $sel = ( $freq == $rep[ 'frequency' ] ) ? ' selected="selected"' : '';
            echo "<option $sel>$freq</option>";
        }
        echo '</select></td></tr>';
        echo '<tr><th>' . __( 'Repeat every' ) . ':</th><td><select name="_repetition[every]" class="um2u_repetition">';
        for ( $i = 1; $i <= 30; $i++ ) {
            $sel = ( $i == $rep[ 'every' ] ) ? ' selected="selected"' : '';
            echo "<option $sel>$i</option>";
        }
        switch( $rep[ 'frequency' ] ) { 
            case 'Daily'  : $timespan = __( 'days' );   break;
            case 'Weekly' : $timespan = __( 'weeks' );  break;
            case 'Monthly': $timespan = __( 'months' ); break;
            default: $timespan = __( 'days' );
        }
        echo "</select> <span id=\"um2u_repetition_timespan\">$timespan</span></td></tr>";
        $viz = ( 'Weekly' == $rep[ 'frequency' ] ) ? ' style="display: table-row;"' : ' style="display: none;"';
        echo '<tr id="um2u_repeat_on"' . $viz . '><th>' . __( 'Repeat on' ) . ':</th><td>';
        foreach ( $dow as $k => $d ) {
            $checked = ( is_array( $rep[ 'repeat_on' ] ) && in_array( $k, $rep[ 'repeat_on' ] ) ) ? ' checked="checked"' : '';
            echo '<label><input type="checkbox" name="_repetition[repeat_on][]" class="um2u_repetition" value="' . $k . '"' . $checked . ' /> ' . $d . '</label> ';
        }
        echo '</td></tr>';
        $viz = ( 'Monthly' == $rep[ 'frequency' ] ) ? ' style="display: table-row;"' : ' style="display: none;"';
        echo '<tr id="um2u_repeat_by"' . $viz . '><th>' . __( 'Repeat by' ) . ':</th><td>';
        $checked = ( 'dom' == $rep[ 'repeat_by' ] ) ? ' checked="checked"' : '';
        echo ' <label><input type="radio" name="_repetition[repeat_by]" value="dom" class="um2u_repetition"' . $checked . ' /> ' . __( 'day of the month' ) . '</label>'; 
        $checked = ( 'dow' == $rep[ 'repeat_by' ] ) ? ' checked="checked"' : '';
        echo ' <label><input type="radio" name="_repetition[repeat_by]" value="dow" class="um2u_repetition"' . $checked . ' /> ' . __( 'day of the week' ) . '</label>'; 
        echo '</td></tr>';
        echo '<tr><th>' . __( 'Starts on' ) . ':</th><td><input type="text" id="um2u_repetition_starts_on" name="_repetition[starts_on]" class="um2u_repetition" value="' . $rep[ 'starts_on' ] . '" /></td></tr>';
        echo '<tr><th>' . __( 'Ends' ) . ':</th><td>';
        $checked = ( ! isset( $rep[ 'ends' ][ 'type' ] ) || 'after' == $rep[ 'ends' ][ 'type' ] ) ? ' checked="checked"' : '';
        echo ' <input type="radio" name="_repetition[ends][type]" class="um2u_repetition" value="after"' . $checked . ' /> ';
        $occurrences = ( isset( $rep[ 'ends' ][ 'occurrences' ] ) ) ? $rep[ 'ends' ][ 'occurrences' ] : 1;
        echo __( 'after' ) . ' <input type="text" name="_repetition[ends][occurrences]" id="um2u_meeting_repetition_occurrences" class="um2u_repetition" value="' . $occurrences . '" /> ' . __( 'occurrences' ) . '<br />';
        $checked = ( 'on' == $rep[ 'ends' ][ 'type' ] ) ? ' checked="checked"' : '';
        echo ' <input type="radio" name="_repetition[ends][type]" class="um2u_repetition" value="on"' . $checked . ' /> ';
        echo __( 'on' ) . ' <input type="text" name="_repetition[ends][on]" id="um2u_meeting_repetition_ends_on" class="um2u_repetition" value="' . $rep[ 'ends' ][ 'on' ] . '" /></td></tr>';
        echo '<tr><th>' . __( 'Exceptions' ) . ':</th><td>' . __( 'Meetings will NOT be created for dates checked below' ) . ':<div id="um2u_exception_calendars">';
        // output checkboxes for exceptions
        if ( $rep[ 'starts_on' ] ) {
            echo $this->generate_exception_calendars( $rep );
        }
        echo '</div></td></tr>';
        echo '</tbody></table>';
    }
    
    /**
     * Handle ajax calls when exceptions need to be updated
     */
    function wp_ajax_um2u_generate_exceptions() {
        if ( ! wp_verify_nonce( $_POST[ 'um2u_exceptions_nonce' ], 'um2u_exceptions_nonce' ) ) die( 'Unauthorized' );
        $response[ 'exceptions' ] = $this->generate_exception_calendars( $_POST[ 'repetition' ] );
        $response[ 'nonce' ] = wp_create_nonce( 'um2u_exceptions_nonce' );
        header( 'content-type: application/json' );
        echo json_encode( $response );
        exit; 
    }
    
    /**
     * Generate exception calendars
     */
    function generate_exception_calendars( $rep ) {
        $out = '';
        $dow = array( 'S', 'M', 'T', 'W', 'T', 'F', 'S' );
        $start = new DateTime( $rep[ 'starts_on' ] );
        $number = ( isset( $rep[ 'every' ] ) ) ? $rep[ 'every' ] : 1;
        switch ( $rep[ 'frequency' ] ) {
            case 'Weekly':  $intervals = $this->get_weekly_intervals( $rep, $number, $start ); break;
            case 'Monthly': $intervals = $this->get_monthly_intervals( $rep, $number, $start ); break;
            default:        $intervals = $this->get_daily_intervals( $rep, $number, $start );
        }
        foreach ( $intervals as $interval ) {
            $date = new DateTime( '@' . $start->format( 'U' ) );
            $date->add( $interval );
            if ( isset( $current_month ) && $current_month == $date->format( 'F' ) ) {
                while ( $current_day < $date->format( 'j' ) ) {
                    $cd = new DateTime( "$current_day $current_month $current_year" );
                    if ( 0 == $cd->format( 'w' ) ) $out .= '</tr><tr>';
                    $out .= "<td>$current_day</td>";
                    $current_day++;
                }
                $cd = new DateTime( "$current_day $current_month $current_year" );
                if ( 0 == $cd->format( 'w' ) ) $out .= '</tr><tr>';
                $checked = ( is_array( $rep[ 'exceptions' ] ) && in_array( $date->format( 'm/d/Y' ), $rep[ 'exceptions' ] ) ) ? ' checked="checked"' : '';
                $out .= '<td><label>' . $current_day . '<br /><input type="checkbox" name="_repetition[exceptions][]" value="' . $date->format( 'm/d/Y' ) . '"' . $checked . ' /></label></td>';
                $current_day++;
            } else {
                if ( isset( $current_month ) ) {
                    // we have a calendar to finish
                    $last_day = new DateTime( 'last day of ' . $current_month . ' ' . $current_year );
                    for ( $i = $current_day; $i <= $last_day->format( 'j' ); $i++ ) {
                        $cd = new DateTime( $i . ' ' . $current_month . ' ' . $current_year );
                        if ( 0 == $cd->format( 'w' ) ) $out .= '</tr><tr>';
                        $out .= "<td>$i</td>";
                    }
                    // finish the last row
                    for ( $i = $last_day->format( 'w' ); $i < 6; $i++ ) $out .= '<td>&nbsp;</td>';
                    $out .= '</tr></tbody></table>';
                }
                $current_month = $date->format( 'F' );
                $current_year  = $date->format( 'Y' );
                $out .= '<table class="um2u_exceptions"><thead><tr><th colspan="7">';
                $out .= $current_month . ' ' . $current_year . '</th></tr><tr>';
                foreach ( $dow as $d ) $out .= '<th>' . $d . '</th>';
                $out .= '</tr></thead><tbody><tr>';
                $current_day = 1;
                $first_day = new DateTime( $current_day . ' ' . $current_month . ' ' . $current_year );
                for ( $i = 0; $i < $first_day->format( 'w' ); $i++ ) $out .= '<td>&nbsp;</td>';
                while ( $current_day < $date->format( 'j' ) ) {
                    $cd = new DateTime( "$current_day $current_month $current_year" );
                    if ( $current_day > 1 && 0 == $cd->format( 'w' ) ) $out .= '</tr><tr>';
                    $out .= "<td>$current_day</td>";
                    $current_day++;
                }
                $cd = new DateTime( "$current_day $current_month $current_year" );
                if ( 0 == $cd->format( 'w' ) ) $out .= '</tr><tr>';
                $checked = ( is_array( $rep[ 'exceptions' ] ) && in_array( $date->format( 'm/d/Y' ), $rep[ 'exceptions' ] ) ) ? ' checked="checked"' : '';
                $out .= '<td><label>' . $current_day . '<br /><input type="checkbox" name="_repetition[exceptions][]" value="' . $date->format( 'm/d/Y' ) . '"' . $checked . ' /></label></td>';
                $current_day++;
            }
        }
        // we have a calendar to finish
        $last_day = new DateTime( 'last day of ' . $current_month . ' ' . $current_year );
        for ( $i = $current_day; $i <= $last_day->format( 'j' ); $i++ ) {
            $cd = new DateTime( $i . ' ' . $current_month . ' ' . $current_year );
            if ( 0 == $cd->format( 'w' ) ) $out .= '</tr><tr>';
            $out .= "<td>$i</td>";
        }
        // finish the last row
        for ( $i = $last_day->format( 'w' ); $i < 6; $i++ ) $out .= '<td>&nbsp;</td>';
        $out .= '</tr></tbody></table>';
        return $out;
    }
    
    /**
     * Calculates intervals for daily meetings
     */
    function get_daily_intervals( $rep, $num, $start ) {
        $intervals = array();
        $interval = new DateInterval( 'P' . $num . 'D' );
        $start_timestamp = '@' . $start->format( 'U' );
        $date = new DateTime( $start_timestamp );
        $intervals[] = $start->diff( $date );
        if ( isset( $rep[ 'ends' ][ 'type' ] ) && 'on' == $rep[ 'ends' ][ 'type' ] ) {
            $end = ( isset( $rep[ 'ends' ][ 'on' ] ) ) ? new DateTime( $rep[ 'ends' ][ 'on' ] ) : new DateTime( $start_timestamp );
            while ( $date < $end ) {
                $date->add( $interval );
                if ( $date > $end ) break;
                $intervals[] = $start->diff( $date );
            }
        } else {
            $occurrences = ( isset( $rep[ 'ends' ][ 'occurrences' ] ) ) ? $rep[ 'ends' ][ 'occurrences' ] : 1;
            for ( $i = 1; $i < $occurrences; $i++ ) {
                $date->add( $interval );
                $intervals[] = $start->diff( $date );
            }
        }
        return $intervals;
    }
    
    /**
     * Calculates intervals for weekly meetings
     */
    function get_weekly_intervals( $rep, $num, $start ) {
        $intervals = array();
        $start_dow = $start->format( 'w' ); // integer from 0 (Sunday) to 6 (Saturday)
        $days = ( isset( $rep[ 'repeat_on' ] ) ) ? $rep[ 'repeat_on' ] : array( $start_dow );
        $start_timestamp = '@' . $start->format( 'U' );
        $date = new DateTime( $start_timestamp );
        $intervals[] = $start->diff( $date );
        $week = 0; // number of week to start with
        if ( isset( $rep[ 'ends' ][ 'type' ] ) && 'on' == $rep[ 'ends' ][ 'type' ] ) {
            $end = ( isset( $rep[ 'ends' ][ 'on' ] ) ) ? new DateTime( $rep[ 'ends' ][ 'on' ] ) : new DateTime( $start_timestamp );
            while ( $date <= $end ) {
                foreach ( $days as $day ) {
                    $days_to_add = $week * 7 + ( $day - $start_dow );
                    if ( $days_to_add > 0 ) {
                        $date = new DateTime( $start_timestamp );
                        $date = $date->add( new DateInterval( 'P' . $days_to_add . 'D' ) );
                        if ( $date > $end ) break 2;
                        $intervals[] = $start->diff( $date );
                    }
                }
                $week += $num;
            }
        } else {
            $occurrences = ( isset( $rep[ 'ends' ][ 'occurrences' ] ) ) ? $rep[ 'ends' ][ 'occurrences' ] : 1;
            $start_index = 0;
            foreach ( $days as $index => $day ) {
                if ( $day - $start_dow > 0 ) {
                    $start_index = $index;
                    break;
                } 
            }
            if ( $start_index == 0 ) {
                // only once a week meeting
                for ( $i = 1; $i < $occurrences; $i++ ) {
                    $date = new DateTime( $start_timestamp );
                    $days_to_add = $num * $i * 7;
                    $date->add( new DateInterval( 'P' . $days_to_add . 'D' ) );
                    $intervals[] = $start->diff( $date ); 
                }
            } else {
                for ( $i = 1; $i < $occurrences; $i++ ) {
                    if ( 0 == $i % count( $days ) ) $week += $num;
                    $date = new DateTime( $start_timestamp );
                    $days_to_add = $week * 7 + ( $days[ $start_index + ( $i % count( $days ) ) - 1 ] - $start_dow );
                    $date->add( new DateInterval( 'P' . $days_to_add . 'D' ) );
                    $intervals[] = $start->diff( $date );
                }
            }
        }
        return $intervals;
    }
    
    /**
     * Calculates intervals for monthly meetings
     */
    function get_monthly_intervals( $rep, $num, $start ) {
        $start_timestamp = '@' . $start->format( 'U' );
        $date = new DateTime( $start_timestamp );
        $intervals[] = $start->diff( $date );
        $every = $num;
        $month = 0;
        if ( isset( $rep[ 'ends' ][ 'type' ] ) && 'on' == $rep[ 'ends' ][ 'type' ] ) {
            $end = ( isset( $rep[ 'ends' ][ 'on' ] ) ) ? new DateTime( $rep[ 'ends' ][ 'on' ] ) : new DateTime( $start_timestamp );
            if ( isset( $rep[ 'repeat_by' ] ) && 'dow' == $rep[ 'repeat_by' ] ) {
                // repeats on e.g. 2nd Tuesday of the month
                $dow = $start->format( 'l' );
                $dom = $start->format( 'j' );
                if ( $dom <=  7 )               $week = 'first '  . $dow . ' of ';
                if ( $dom >=  8 && $dom <= 14 ) $week = 'second ' . $dow . ' of ';
                if ( $dom >= 15 && $dom <= 21 ) $week = 'third '  . $dow . ' of ';
                if ( $dom >= 22 && $dom <= 28 ) $week = 'fourth ' . $dow . ' of ';
                if ( $dom >  28 )               $week = 'last '   . $dow . ' of ';
                while ( $date < $end ) {
                    $month = $date->add( new DateInterval( 'P' . $every . 'M' ) )->format( 'F' );
                    $year = $date->format( 'Y' );
                    $date = new DateTime( '@' . strtotime( $week . $month . ' ' . $year ) );
                    if ( $date > $end ) break;
                    $intervals[] = $start->diff( $date );
                }
            } else {
                // repeats on the 12th of every month
                while ( $date < $end ) {
                    $date = new DateTime( $start_timestamp );
                    $month += $every;
                    $date->add( new DateInterval( 'P' . $month . 'M' ) );
                    if ( $date > $end ) break;
                    $intervals[] = $start->diff( $date );
                }
            }
        } else {
            $occurrences = ( isset( $rep[ 'ends' ][ 'occurrences' ] ) ) ? $rep[ 'ends' ][ 'occurrences' ] : 1;
            if ( isset( $rep[ 'repeat_by' ] ) && 'dow' == $rep[ 'repeat_by' ] ) {
                // repeats on e.g. the 2nd Tuesday of every month
                $dow = $start->format( 'l' );
                $dom = $start->format( 'j' );
                if ( $dom <=  7 )               $week = 'first '  . $dow . ' of ';
                if ( $dom >=  8 && $dom <= 14 ) $week = 'second ' . $dow . ' of ';
                if ( $dom >= 15 && $dom <= 21 ) $week = 'third '  . $dow . ' of ';
                if ( $dom >= 22 && $dom <= 28 ) $week = 'fourth ' . $dow . ' of ';
                if ( $dom >  28 )               $week = 'last '   . $dow . ' of ';
                for ( $i = 1; $i < $occurrences; $i++ ) {
                    $month = $date->add( new DateInterval( 'P' . $every . 'M' ) )->format( 'F' );
                    $year = $date->format( 'Y' );
                    $date = new DateTime( '@' . strtotime( $week . $month . ' ' . $year ) );
                    $intervals[] = $start->diff( $date );
                }
            } else {
                // repeats on the 12th of every month
                for ( $i = 1; $i < $occurrences; $i++ ) {
                    $date = new DateTime( $start_timestamp );
                    $month += $every;
                    $date->add( new DateInterval( 'P' . $month . 'M' ) );
                    $intervals[] = $start->diff( $date );
                }
            }
        }
        return $intervals;
    }
    
    /**
     * Adds meetings custom post type 
     */
    function add_meetings_post_type() {        
        register_post_type( 'um2u_meeting', array(
            'labels'               =>             array( 
            'name'                 =>             __( 'Meetings' ),
            'singular_name'        =>             __( 'Meeting' ),
            'edit'                 =>             __( 'Edit' ),
            'edit_item'            =>             __( 'Edit Meeting' ),
            'parent'               =>             __( 'Parent Meetings' ), 
            'add_new_item'         =>             __( 'Add Meeting' ),
            'show_ui'              =>             __( 'false' ) ),
            'public'               =>             true,
            'has_archive'          =>             true,
            'rewrite'              =>             array( 
            'slug'                 =>             'meeting' ), 
            'public'               =>             true,
            'show_ui'              =>             true,
           // 'show_in_menu'       =>             false,
            'hierarchical'         =>             false,
            'publicly_queryable'   =>             true,
            'exclude_from_search'  =>             false,
            'has_archive'          =>             true,
            'supports'             =>             array( 'title', 'editor', 'thumbnail', 'custom-fields', 'revisions', 'page-attributes' ),
            'taxonomies'           =>             array( 'category', 'post_tag' ),
            'register_meta_box_cb' =>             array( &$this, 'add_custom_metabox' ),
            ) );
    }

    /**
    * Saves meta box data 
    */
    function save_meta_data( $post_id ) {
        global $post;   
        //check for you post type only
        if( $post->post_type == 'um2u_meeting' ) {
            if ( isset( $_POST[ '_start_time' ] ) ) update_post_meta( $post_id, '_start_time', $_POST[ '_start_time' ] );
            if ( isset( $_POST[ '_end_time'   ] ) ) update_post_meta( $post_id, '_end_time',   $_POST[ '_end_time'   ] );
            if ( isset( $_POST[ '_location'   ] ) ) update_post_meta( $post_id, '_location',   $_POST[ '_location'   ] );
            if ( isset( $_POST[ '_importance' ] ) ) update_post_meta( $post_id, '_importance', $_POST[ '_importance' ] );
            if ( isset( $_POST[ '_invitees'   ] ) ) update_post_meta( $post_id, '_invitees',   $_POST[ '_invitees'   ] );
            if ( isset( $_POST[ '_repetition' ] ) ) update_post_meta( $post_id, '_repetition', $_POST[ '_repetition' ] );
            // handle creating duplicate posts based on _repetition
            if ( isset( $_POST[ '_repetition' ] ) ) {
                
            }
        }
    } 

    /**
     * Change the columns for the edit CPT screen
     */
    function change_columns( $cols ) {
        $cols = array(
            'title'      => __( 'Title' ),
            'um2u_date'  => __( 'Date'       ),
            'duration'   => __( 'Start/End'  ),
            'location'   => __( 'Location'   ),
            'importance' => __( 'Importance' ),
            'author'     => __( 'Creator'    ),
            'categories' => __( 'Categories' ),
            'tags'       => __( 'Tags' )
        );
        return $cols;
    }
    /**
     * Fills columns with content
     */
    function custom_columns( $column, $post_id ) {
        $pluralize = function( $num, $str ) { return $num > 1 ? $str . 's' : $str; };
        switch ( $column ) {
            case 'um2u_date':
                $date = new DateTime( get_post_meta( $post_id, '_start_time', true ) );
                echo '<abbr title="' . $date->format( 'Y/m/d h:i:s A' ) . '">' . $date->format( 'n/j/y' ) . '</abbr>';
                break;
            case 'duration':
                $start    = new DateTime( get_post_meta( $post_id, '_start_time', true ) );
                $end      = new DateTime( get_post_meta( $post_id, '_end_time', true ) );
                $duration = $start->diff( $end );
                $format = array();
                if ( $duration->m !== 0 ) $format[] = '%m ' . $pluralize( $duration->m, 'month'  );
                if ( $duration->d !== 0 ) $format[] = '%d ' . $pluralize( $duration->d, 'day'    );
                if ( $duration->h !== 0 ) $format[] = '%h ' . $pluralize( $duration->h, 'hour'   );
                if ( $duration->i !== 0 ) $format[] = '%i ' . $pluralize( $duration->i, 'minute' );
                if ( count( $format ) > 1 ) $format = '(' . array_shift( $format ) . ' ' . array_shift( $format ) . ')';
                else $format = '(' . array_pop( $format ) . ')';
                echo '<abbr title="' . $start->format( 'Y/m/d h:i:s A' ) . '">' . $start->format( 'g:i A' ) . '</abbr> - ';
                echo '<abbr title="' . $end->format( 'Y/m/d h:i:s A' ) . '">' . $end->format( 'g:i A' ) . '</abbr> ';
                echo $duration->format( $format );
                break;
            case 'location':
                echo get_post_meta( $post_id, '_location', true );
                break;
            case 'importance':
                echo get_post_meta( $post_id, '_importance', true );
                break;
        }
    }

    /**
     *  Makes these columns sortable
     */
    function sortable_columns( $cols ) {
        $cols[ 'um2u_date' ]  = 'um2u_date';
        $cols[ 'duration' ]   = 'duration';
        $cols[ 'location' ]   = 'location';
        $cols[ 'importance' ] = 'importance';
        return $cols;
    }

    /**
     * Tells WP how to sort the custom sortable columns
     */
    function sortable_columns_orderby( $vars ) {
        // are we even sorting anything?
        if ( isset( $vars[ 'orderby' ] ) ) {
            // if so, what?
            switch ( $vars[ 'orderby' ] ) {
                case 'um2u_date':
                case 'duration':   $ob = array( 'meta_key' => '_start_time', 'orderby' => 'meta_value' ); break;
                case 'location':   $ob = array( 'meta_key' => '_location',   'orderby' => 'meta_value' ); break;
                case 'importance': $ob = array( 'meta_key' => '_importance', 'orderby' => 'meta_value' ); break;
            }
            if ( isset( $ob ) ) $vars = array_merge( $vars, $ob );
        }
        return $vars;
    }

    /**
     *  Filters the request to just give posts for the given taxonomy, if applicable.
     */
    function taxonomy_filter_restrict_manage_posts() {
        global $typenow;

        // If you only want this to work for your specific post type,
        // check for that $type here and then return.
        // This function, if unmodified, will add the dropdown for each
        // post type / taxonomy combination.

        $post_types = get_post_types( array( '_builtin' => false ) );

        if ( in_array( $typenow, $post_types ) ) {
            $filters = get_object_taxonomies( $typenow );

            foreach ( $filters as $tax_slug ) {
                $tax_obj = get_taxonomy( $tax_slug );
                wp_dropdown_categories( array(
                    'show_option_all' => __('Show All '.$tax_obj->label ),
                    'taxonomy'        => $tax_slug,
                    'name'            => $tax_obj->name,
                    'orderby'         => 'name',
                    'selected'        => $_GET[$tax_slug],
                    'hierarchical'    => $tax_obj->hierarchical,
                    'show_count'      => false,
                    'hide_empty'      => true
                ) );
            }
        }

    }

    function taxonomy_filter_post_type_request( $query ) {
      global $pagenow, $typenow;

      if ( 'edit.php' == $pagenow ) {
        $filters = get_object_taxonomies( $typenow );
        foreach ( $filters as $tax_slug ) {
          $var = &$query->query_vars[$tax_slug];
          if ( isset( $var ) ) {
            $term = get_term_by( 'id', $var, $tax_slug );
            $var = $term->slug;
          }
        }
      }

    }

    // Add a Custom Post Type to a feed
    function add_cpt_to_feed( $qv ) {
      if ( isset($qv['feed']) && !isset($qv['post_type']) )
        $qv['post_type'] = array('post', 'um2u_meeting');
      return $qv;
    }    

    /**
     * Output filter that outputs our custom fields in the actual post on the front end
     */    
    function the_content( $content ) {
        global $post;
        if ( 'um2u_meeting' == $post->post_type ) {
            $content .= get_post_meta( $post->ID, 'title',       true );
            $content .= get_post_meta( $post->ID, 'creator',     true );
            $content .= get_post_meta( $post->ID, 'date',        true );
            $content .= get_post_meta( $post->ID, 'time',        true );
            $content .= get_post_meta( $post->ID, 'location',    true );
            $content .= get_post_meta( $post->ID, 'description', true );
            $content .= get_post_meta( $post->ID, 'importance',  true );
            $content .= get_post_meta( $post->ID, 'category',    true );
            $content .= get_post_meta( $post->ID, 'invitees',    true );
        }
        return $content;
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

 
?>