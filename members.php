<?php
/*
 * Title: UMatter2Us Members 
 * File: members.php
 * Author: Morgan Benton
 * Description: This class will implement functionality for managing learning community members 
 */
/**
 * UMatter2UsMembers Class
 * 
 * This class will implement functionality for managing learning community members.
 * 
 * @package		umatter2us
 * @version		1.0
 * @author		Morgan Benton <bentonmc@jmu.edu>
 */
 
 class UM2UMembers {
	
	/**
	 * Member settings
	 */
	private $members;
	
	/**
	 * Constructors
	 */
	function UM2UMembers() {
		$this->__construct();
	}

	function __construct() {
		// retrieve our settings
		$this->members = get_option( 'um2umember_settings' );
		
		// create roles
		$member_caps = array(
			'read',
			'delete_posts',
			'edit_posts',
			'delete_published_posts'
		);
		add_role( 'community_member', 'Community Member', array( ) );
		add_role( 'community_mentor', 'Community Mentor', array( ) );
		
		// create shortcodes
		add_shortcode( 'um2u_members', array( &$this, 'um2u_members_shortcode' ) );
		
		// primary actions
        if ( is_admin() ) {
            //add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );
            //add_action( 'admin_init',     array( &$this, 'admin_init'     ) );
            //add_action( 'admin_menu',     array( &$this, 'admin_menu'     ) );
        }
	}
 
	/**
	 * Handles the 'plugins_loaded' action hook
	 */
	function plugins_loaded() {
        // register styles
        wp_register_style( 'um2umembers_css', plugins_url() . '/umatter2us/css/um2umembers.css' );
        
        // register scripts
        wp_register_script( 'um2umembers_js', plugins_url() . '/umatter2us/js/um2umembers.js', 'jquery', '1.0', true );
	}
 
    /**
     * Registers all settings
     */
    function admin_init() {
        // register settings
        register_setting( 'um2umember_settings', 'um2umember_settings', array( &$this, 'validate_um2umember_settings' ) );
        
        // um2u members settings and fields
        add_settings_section( 'umatter2us_settings_section', null, array( &$this, 'umatter2us_settings_section' ), 'umatter2us-menu' );
        
    }
	
    /**
     * Sets up all the menus and submenus.  Adds actions for enqueuing styles and scripts
     */
    function admin_menu() {
        // main menu page
        // add_menu_page( 'UMatter2Us', 'UMatter2Us', 'manage_options', 'umatter2us-menu', array( &$this, 'umatter2us_menu' ), plugins_url() . '/umatter2us/images/umatter2us_icon.png', 31 );
        
        //submenus
        $menus[ 'm' ] = add_submenu_page( 'umatter2us-menu', 'Members::UMatter2Us'        , 'Members'        , 'manage_options', 'um2umembers-menu'     , array( &$this, 'um2umembers_menu'     ) );
		$menus[]      = add_submenu_page( $menus[ 'm' ]    , 'Add/Edit Member::UMatter2Us', 'Add/Edit Member', 'manage_options', 'um2u-edit-member-menu', array (&$this, 'um2uedit_member_menu' ) );

        // enqueue styles and scripts
        foreach( $menus as $menu ) {
            add_action( 'admin_print_styles-'  . $menu, array( &$this, 'enqueue_um2umembers_styles'  ) );
            add_action( 'admin_print_scripts-' . $menu, array( &$this, 'enqueue_um2umembers_scripts' ) );
        }
    }

    /**
     * Enqueue styles
     */
    function enqueue_um2umembers_styles() {
        wp_enqueue_style( 'um2umembers_css' );
    }
    
    /**
     * Enqueue scripts
     */
    function enqueue_um2umembers_scripts() {
        wp_enqueue_script( 'um2umembers_js' );
    }

    /**
     * Utility function to prepare messages
     */
	function prepareMessages( $menu ) {
		$messages = false;
		if ( 'true' == $_GET[ 'updated' ] ) {
			$errors = get_settings_errors( $menu );
			if ( is_array( $errors ) && count( $errors ) ) {
				foreach ( $errors as $err ) {
					$messages[] = '<div id="' . $err[ 'code' ] . '" class="error fade"><p><strong>' . $err[ 'message' ] . '</strong></p></div>';
				}
			} else {
				$messages[] = '<div id="message" class="updated fade"><p><strong>' . __( 'Settings saved.' ) . '</strong></p></div>';
			}
		}
		return $messages;
	}

	/*
	 * Generic function for outputting menus that don't include tables
	 */
	function genericMenu( $messages, $page_title, $options, $menu_slug ) {
		if ( is_array( $messages ) ) foreach ( $messages as $m ) echo $m;
		?>
		<div class="wrap">
			<h2><?php _e( $page_title ); ?></h2>
			<form method="post" action="options.php">
				<?php
					settings_fields( $options );
					do_settings_sections( $menu_slug );
				?>
				<p class="submit">
					<input name="submit" type="submit" class="button-primary" value="<?php _e( 'Save Changes' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Add/Edit Member menu
	 */
	function um2uedit_member_menu() {
		$messages = $this->prepareMessages( 'um2umember_settings' );
		$this->genericMenu( $messages, 'Add/Edit Member', 'um2umember_settings', 'um2u-edit-member-menu' );
	}
    
	/**
	 * Displays a table with all the members in it
	 */
	function um2umembers_menu() {
		$filter = ( isset( $_GET[ 'filter' ] ) ) ? $_GET[ 'filter' ] : null;
		if ( isset( $_GET[ 'action' ] ) ) {
			switch ( $_GET[ 'action' ] ) {
				case 'trash-members':
					if ( isset( $_GET[ 'um2u_members' ] ) ) {
						foreach ( $_GET[ 'um2u_members' ] as $id ) {
							$this->members[ $id ][ 'trash' ] = true;
						}
						update_option( 'um2umember_settings', $this->members );
					}
					$this->members_table( $filter );
					break;
				case 'untrash-members':
					if ( isset( $_GET[ 'um2u_members' ] ) ) {
						foreach ( $_GET[ 'um2u_members' ] as $id ) {
							$this->members[ $id ][ 'trash' ] = false;
						}
						update_option( 'um2umember_settings', $this->members );
					}
					$this->members_table( $filter );
					break;
				case 'delete-members-permanently':
					if ( isset( $_GET[ 'um2u_members' ] ) ) {
						foreach ( $_GET[ 'um2u_members' ] as $id ) {
							unset( $this->members[ $id ] );
						}
						update_option( 'um2umember_settings', $this->members );
					}
					$this->members_table( $filter );
					break;
			}
		} else {
			$this->members_table( $filter );
		}
	}
	
	/**
	 * Display a table with all of the members
	 */
	function members_table( $filter = null ) {
		if ( $messages = $this->prepareMessages( 'um2umember_settings' ) ) foreach ( $messages as $m ) echo $m;
		?>
		<div class="wrap">
			<h2><?php _e( 'Members' ); ?> <a href="admin.php?page=um2u-edit-member-menu" class="button add-new-h2"><?php _e( 'Add New' ); ?></a></h2>
			<?php
				do_settings_sections( 'um2umembers-menu' );
				if ( is_array( $this->members ) && count( $this->members ) ) :
					$all = $trash = 0;
					foreach ( $this->members as $member ) {
						if ( ! $member[ 'trash' ] ) {
							++$all;
						} else ++$trash;
					}
			?>
			<form id="members-form" action="options.php" method="get">
				<ul class="subsubsub">
					<li><a href="admin.php?page=um2umembers-menu"><?php _e( 'All' ); ?></a> (<?php echo $all; ?>) |</li>
					<li><a href="admin.php?page=um2umembers-menu&filter=trash"><?php _e( 'Trash' ); ?></a> (<?php echo $trash; ?>)</li>
				</ul>
				<?php settings_fields( 'um2umember_settings' ); ?>
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action" id="action">
							<option value="-1" selected="selected"><?php _e( 'Bulk Actions' ); ?></options>
							<?php if ( 'trash' == $filter ): ?>
                                <option value="untrash-members"><?php _e( 'Remove from Trash' ); ?></option>
                                <option value="delete-members-permanently"><?php _e( 'Delete Permanently' ); ?></option>
							<?php else: ?>
                                <option value="trash-members"><?php _e( 'Move to Trash' ); ?></option>
							<?php endif;?>
						</select>
                        <input type="button" value="<?php _e( 'Apply' ); ?>" name="doaction" id="doaction" class="button-secondary action" />
                    </div>
                </div>
                <div class="clear"></div>
                <table class="widefat post fixed">
                    <thead>
                        <tr>
                            <th scope="col" id="cb" class="manage-column column-cb check-column">
                                <input type="checkbox" />
                            </th>
                            <th scope="col" id="name" class="manage-column column-name"><?php _e( 'Name' ); ?></th>
                            <th scope="col" id="status" class="manage-column column-status"><?php _e( 'Status' ); ?></th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th scope="col" id="cb2" class="manage-column column-cb check-column">
                                <input type="checkbox" />
                            </th>
                            <th scope="col" id="name2" class="manage-column column-name"><?php _e( 'Name' ); ?></th>
                            <th scope="col" id="status2" class="manage-column column-status"><?php _e( 'Status' ); ?></th>
                        </tr>
                    </tfoot>
					<tbody>
						<?php 
							foreach ( $this->members as $id => $member ): 
								switch ( $filter ) {
									case 'trash': if ( ! $member[ 'trash' ] ) continue 2; break;
									default:      if (   $member[ 'trash' ] ) continue 2; break;
								}
						?>
						<tr id="member-<?php echo $id; ?>" class="alternate">
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="um2u_members[]" value="<?php echo $id; ?>" />
                            </th>
                            <td class="member-name column-name">
                                <?php if ( 'trash' != $filter ) : ?>
                                <strong>
                                    <a class="row-title" href="admin.php?page=um2u-edit-member-menu&action=edit&member=<?php echo $id; ?>" title="<?php echo __( 'Edit' ), ' ', $member[ 'name' ]; ?>">
                                        <?php echo $member[ 'name' ]; ?>
                                    </a>
                                </strong>
                                <?php else: ?>
                                <strong><?php echo $member[ 'name' ]; ?></strong><br />
                                <span class="trashed-member-options">
                                    <a href="admin.php?page=um2umembers-menu&filter=trash&updated=true&action=untrash-members&um2u_members[0]=<?php echo $id; ?>"><?php _e( 'Remove from Trash' ); ?></a> |
                                    <a href="admin.php?page=um2umembers-menu&filter=trash&updated=true&action=delete-members-permanently&um2u_members[0]=<?php echo $id; ?>" class="delete-permanently"><?php _e( 'Delete Permanently' ); ?></a>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="member-status column-status">
								<?php echo $member[ 'status' ]; ?>
                            </td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action2" id="action2">
							<option value="-1" selected="selected"><?php _e( 'Bulk Actions' ); ?></options>
							<?php if ( 'trash' == $filter ): ?>
                                <option value="untrash-members"><?php _e( 'Remove from Trash' ); ?></option>
                                <option value="delete-members-permanently"><?php _e( 'Delete Permanently' ); ?></option>
							<?php else: ?>
                                <option value="trash-members"><?php _e( 'Move to Trash' ); ?></option>
							<?php endif;?>
						</select>
                        <input type="button" value="<?php _e( 'Apply' ); ?>" name="doaction2" id="doaction2" class="button-secondary action" />
                    </div>
                </div>
			</form>
			<?php else: ?>
				<p><?php _e( 'There are no members to manage.  Click "Add New" above to add one.' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}


	function validate_um2umember_settings( $input ) {
		// sanitize the input
		return $input;
	}
    
    /**
     * Output the member directory
     */
    function um2u_members_shortcode( $atts ) {
        global $wpdb, $current_user;
        if ( 0 == $current_user->ID ) {
            $out = '<p>You must <a href="' . wp_login_url( get_permalink() ) .'">log in</a> if you would like to see info about the community members.</p>';
            return $out;
        }
        // get the number of columns
        extract( shortcode_atts( array( 'cols' => 2 ), $atts ) );
        // initialize necessary variables
        $pls = get_option( 'pagelines-settings' );
        $cw = $pls[ 'layout' ][ 'content_width' ] - 120;
        $w = round( $cw / $cols, 0, PHP_ROUND_HALF_DOWN ); // width of member profile
        $imgdir = wp_upload_dir();
        $imgdir = $imgdir[ 'baseurl' ] . '/userphoto/';
        $gmicon = '<img src="' . plugins_url() . '/pagelines-customize/icons/gmail_16.png" alt="Gmail" height="16" width="16" />';
        $micon  = '<img src="' . plugins_url() . '/pagelines-customize/icons/mail_16.png"  alt="Email" height="16" width="16" />';
        $members = array();
        // get all the users
        $users = get_users();
        // get the required metadata
        foreach ( $users as $k => $u ) {
            $members[ $k ][ 'login' ] = $u->user_login;
            $members[ $k ][ 'email' ] = $u->user_email;
            $members[ $k ][ 'name' ]  = $u->display_name;
            $members[ $k ][ 'img' ]   = $imgdir . get_user_meta( $u->ID, 'userphoto_thumb_file', true );
            $members[ $k ][ 'imgh' ]  = get_user_meta( $u->ID, 'userphoto_thumb_height', true );
            $members[ $k ][ 'imgw' ]  = get_user_meta( $u->ID, 'userphoto_thumb_width',  true );
            $members[ $k ][ 'fname' ] = get_user_meta( $u->ID, 'first_name', true );
            $members[ $k ][ 'lname' ] = get_user_meta( $u->ID, 'last_name', true );
            $lname[ $k ] = $members[ $k ][ 'lname' ];  // for sorting
			$cimy_data = $wpdb->get_results( "SELECT f.name, d.value FROM {$wpdb->prefix}cimy_uef_fields f, {$wpdb->prefix}cimy_uef_data d WHERE f.id = d.field_id AND user_id = " . $u->ID );
			foreach ( $cimy_data as $cd ) {
				if ( 'MOBILE_NUMBER' == $cd->name ) $members[ $k ][ 'mobile' ] = $cd->value;
				if ( 'GMAIL_ACCOUNT' == $cd->name ) $members[ $k ][ 'gmail' ]  = $cd->value;
			}
            // format the phone number
            if ( isset( $members[ $k ][ 'mobile' ] ) && strlen( $members[ $k ][ 'mobile' ] ) ) {
                $m = preg_replace( '/\D/', '', $members[ $k ][ 'mobile' ] );
                $members[ $k ][ 'mobile' ] = substr( $m, 0, 3 ) . '-' . substr( $m, 3, 3 ) . '-' . substr( $m, 6 );
            }
        }
        // sort them by last name
        array_multisort( $lname, SORT_ASC, $members );
        
        // begin output
        $out = '<div id="um2u_members">';
        foreach ( $members as $m ) {
            $mlink = '<a href="' . site_url() . '/author/' . $m[ 'login' ] . '/">';
            $out .= '<div class="um2u_member" style="width: ' . $w . 'px;">';
            $out .= $mlink . '<img class="pic" src="' . $m[ 'img' ] . '" alt="' . $m[ 'name' ] . '" width="' . $m[ 'imgw' ] . '" height="' . $m[ 'imgh' ] . '" /></a>';
            $out .= '<strong>' . $mlink . $m[ 'name' ] . '</a></strong><br />';
            if ( isset( $m[ 'gmail' ]  ) ) $out .= '<a href="mailto:' . $m[ 'gmail' ] . '">' . $gmicon . '</a> ';
            $out .= '<a href="mailto:' . $m[ 'email' ] . '">' . $micon . '</a><br />';
            if ( isset( $m[ 'mobile' ] ) ) $out .= $m[ 'mobile' ];
            $out .= '</div>';
        }
        $out .= '</div>';
        return $out;
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