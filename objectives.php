<?php
/*
 * Title: WP Objectives  
 * File: Objectives .php
 * Author: Morgan Benton
 * Description: This class will set up a Objectives  menu where you can
 *              view/add/edit Objectives 
 */
/**
 * Objectives  API Class
 * 
 * This class instantiates the methods for accessing the Objectives API.
 * 
 * @package WP-Objectives 
 * @version 1.0
 * @author  Morgan Benton <bentonmc@jmu.edu>,
 * @author 	Alex Mastro <mastroaf@dukes.jmu.edu> 
 */
	
 class UM2UObjectives {
	
	// retrieve objectives settings
	private $objectives;
	
	/**
	*Constructors
	*/
	function UM2UObjectives()	{
		$this->__construct ();
	}

	function __construct() {
		// retrieve our settings
		$this->objectives = get_option( 'um2uobjectives_settings' );
		// primary actions, &this refers it to just this class, so we can use the same string names
		add_action( 'init', array( &$this, 'add_objectives_post_type' ) );
		add_action( 'save_post', array( &$this, 'save_meta_data' ) );
		
		// setup a custom template to be used with our custom post type
		add_filter( 'the_content', array( &$this, 'the_content' ) );
		
		// primary actions
        if ( is_admin() ) {
            add_action( 'plugins_loaded',        array( &$this, 'plugins_loaded'        ) );
            add_action( 'admin_init',            array( &$this, 'admin_init'            ) );
			add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
            add_action( 'admin_menu',            array( &$this, 'admin_menu'            ) );
            add_action( 'admin_head',            array( &$this, 'admin_head'            ) );
        }
	}
 
	/**
	* Handles the plugins_loaded action hook
	*/
	function plugins_loaded() {
	  // register styles
        //wp_register_style( 'um2uobjectives_css', plugins_url( 'css/um2uobjectives.css', __FILE__ ) );
        
        // register scripts
        wp_register_script( 'um2uobjectives_js', plugins_url( 'js/um2uobjectives.js', __FILE__ ), 'jquery', '1.0', true );
		
	}
	  /**
     * Registers all settings
     */
    function admin_init() {
        // register settings
        register_setting( 'um2uobjectives_settings', 'um2uobjectives', array( &$this, 'validate_um2uobjectives_settings' ) );
        
        // umatter2us settings and fields that the user can update 
        //add_settings_section( 'umatter2us_settings_section', null, array( &$this, 'umatter2us_settings_section' ), 'umatter2us-menu' );
        
    }
	 /**
     * Sets up all the menus and submenus.  Adds actions for enqueuing styles and scripts
     */
    function admin_menu() {
    }

    /**
     * Load tinymce
     */
    function admin_head() {
        wp_tiny_mce( false );
    }
    
	 /**
     * Enqueue styles
     */
    function enqueue_um2uobjectives_styles() {
        wp_enqueue_style( 'um2uobjectives_css' );
    }
    
    /**
     * Enqueue scripts
     */
    function enqueue_um2uobjectives_scripts() {
        wp_enqueue_script( 'um2uobjectives_js' );
    }
	
	/**
	 * Adds meta boxes for custom data elements
	 */
	function add_custom_metabox(){  
		add_meta_box('rationale-meta', __( 'Rationale' ),                   array( &$this, 'rationale_meta_options' ), 'um2u_objective', 'normal', 'high');  
		add_meta_box('mastery-meta',   __( 'Ways to Demonstrate Mastery' ), array( &$this, 'mastery_meta_options'   ), 'um2u_objective', 'normal', 'high');  
		add_meta_box('video-meta',     __( 'How To Video' ),                array( &$this, 'video_meta_options'     ), 'um2u_objective', 'side',   'high');  
	}

	function video_meta_options(){
		$values = get_post_custom( $post->ID );
		$selected = isset( $values[ 'meta_box_video_embed' ] ) ? $values[ 'meta_box_video_embed' ][ 0 ] : '';

		wp_nonce_field( 'my_meta_box_nonce', 'meta_box_nonce' );
		?>
		<p>
			<label for="meta_box_video_embed"><p>Video Embed</p></label>
			<textarea name="meta_box_video_embed" id="meta_box_video_embed" cols="33" rows="5" ><?php echo $selected; ?></textarea>
		</p>
		<p>Leave it Empty ( if you want to use an image thumbnail ) .</p>
		<?php   
	}
	
	/**
	 * Outputs the tiny_mce editor for rationale for learning this objective
	 */
	function rationale_meta_options(){
		global $post;
		echo '<p>' . __( 'Please explain why someone would want to adopt this objective.' ) . '</p>';
	    the_editor( get_post_meta( $post->ID, '_rationale', true ), '_rationale' );
    }
    
	/**
	 * Outputs the tiny_mce editor for ways to demonstrate mastery
	 */
	function mastery_meta_options(){  
		global $post;
	    echo '<p>' . __( 'Please give some examples of how someone can master this objective.' ) . '</p>';
	    the_editor( get_post_meta( $post->ID, '_mastery', true ), '_mastery' );
    }
 
	/**
	 * Adds the UM2U Objective custom post type to WP
	 */
	function add_objectives_post_type() {
		register_post_type( 'um2u_objective', array(
			'labels' => array(
				'name' 			=> __( 'Objectives' ),
				'singular_name'	=> __( 'Objective' ),
				'edit' 			=> __( 'Edit' ),
				'edit_item'		=> __( 'Edit Objective' ),
				'parent'		=> __( 'Parent Objectives' ), 
				'add_new_item' => __( 'Add Learning Objective:' ),
				'show_ui' => ('false')
			), 
			'public' 			   => true,
			'show_ui' 			   => true,
           // 'show_in_menu'         => false,
			'hierarchical' 		   => true,
			'publicly_queryable'   => true,
			'exclude_from_search'  => false,
			'has_archive' 		   => true,
			'supports' 			   => array( 'title', 'editor', 'page-attributes', 'custom-fields', 'revisions' ),
			'taxonomies'		   => array( 'post_tag' ),
			'rewrite' 			   => array( 'slug' => 'objective' ),
			'register_meta_box_cb' => array( &$this, 'add_custom_metabox' ),
			) 
		);
	}
	
	/**
	 * Handles the saving of custom fields in this post type
	 */
	function save_meta_data( $post_id ) {
		global $post;   
		//check for um2u_objective post type only
		if( $post->post_type == "um2u_objective" ) {
			if ( isset( $_POST[ '_rationale' ] ) ) update_post_meta( $post_id, '_rationale', $_POST[ '_rationale' ] );
			if ( isset( $_POST[ '_mastery' ]   ) ) update_post_meta( $post_id, '_mastery',   $_POST[ '_mastery' ]   );
			if ( isset( $_POST[ '_video' ]     ) ) update_post_meta( $post_id, '_video',     $_POST[ '_video' ]     );
			update_post_meta( $post_id, 'um2u_read_status', true );
		}
	}
	
	/**
	 * Output filter that outputs our custom fields in the actual post on the front end
	 */	
	function the_content( $content ) {
		global $post;
		if ( 'um2u_objective' == $post->post_type ) {
			$content .= '<h2>Why Learn This?</h2>' .get_post_meta( $post->ID, '_rationale', true );
			$content .= '<h2>Ways to Show Mastery</h2>' .get_post_meta( $post->ID, '_mastery',   true );
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
 	