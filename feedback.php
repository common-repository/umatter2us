<?php
/*
* Title: UM2U Feedback 
* File: feedback.php
* Author: Morgan Benton
* Description:  
*/
/**
* Feedback API Class
* 
* This class instantiates the methods for accessing the Feedback API.
* 
* @package   umatter2us
* @version   1.0
* @author    Morgan Benton <bentonmc@jmu.edu>, Michael Sliwinski
*/
class UM2UFeedback {

	/**
	* Feedback Settings
	*/
	private $feedback;
 
	/**
	* Constructors
	*/
	function UM2UFeedback() {
		$this->__construct();
	}
	/**
	* old style php constructor for backwards compability
	* array( &this, '....') syntax allows us to refer to our own functions 
	* within our own class without worrying of our code overlapping elsewhere	
	*/
	function __construct() {
        // retrieve our settings
		//$this->feedback = get_option( 'um2ufeedback_settings' );
		
		// primary actions
        if ( is_admin() ) {
            add_action( 'wp_dashboard_setup', array( &$this, 'wp_dashboard_setup' ) );
            
            add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );
            add_action( 'admin_head',     array( &$this, 'admin_head'     ) );
            //add_action( 'admin_init',     array( &$this, 'admin_init'     ) );
            //add_action( 'admin_menu',     array( &$this, 'admin_menu'     ) );
		} else {
    		// front end filters
    		add_action( 'loop_start', array( &$this, 'display_user_profile_page' ) );
		}
	}
	
	/**
	 * Handles loading dashboard widgets
	 */
	function wp_dashboard_setup() {
	    wp_add_dashboard_widget( 'um2u_pages_read_widget', 'Pages Read', array( &$this, 'load_um2u_pages_read_widget' ) );
		wp_add_dashboard_widget( 'um2u_objectives_adopted_widget', 'Objectives Adopted', array( &$this, 'load_um2u_objectives_adopted_widget' ) );
		wp_add_dashboard_widget( 'um2u_hours_spent_summary_stats', 'Hours Spent Outside of Class', array( &$this, 'load_um2u_hours_spent_summary_stats' ) );
		wp_add_dashboard_widget( 'um2u_objectives_progress_widget', 'Student Progress', array( &$this, 'load_um2u_objectives_progress_widget' ) );
        wp_add_dashboard_widget( 'um2u_goals_achieved', 'Goals Achieved', array( &$this, 'load_um2u_goals_achieved' ) );
	}
    /**
     * Create widget to display goals achieved vs goals attempted for hours spent outside of class
     */
    function load_um2u_goals_achieved( $userid = null ) {
        // get all of our surveys
        $um2u_settings = get_option( 'umatter2us_settings' );
        $qtrx = new QualtricsAPI( $um2u_settings[ 'qtrxuser' ], $um2u_settings[ 'qtrxpass' ], $um2u_settings[ 'qtrxlib' ] );
        $all_surveys = $qtrx->getSurveys();
        $self_eval_surveys = array();
        foreach ( $all_surveys as $s ) {
            if ( false !== strpos( (string) $s->SurveyName, 'ISAT 252 Weekly Self-Evaluation - Spring 2012' ) ) {
                $name = (string) $s->SurveyName;
                $self_eval_surveys[] = (string) $s->SurveyID;
                $weeks[ (string) $s->SurveyID ] = substr( $name, strpos( $name, 'Week ' ) );
            }
        }
        // get all users
        $users = get_users();
        if ( $userid ) $user = get_userdata( $userid );

        // initialize necessary variables
        $hours = array();
        arsort( $weeks );
        $cu = array(); // data for current user
        
        // loop through our users
        foreach( $users as $u ) {
            // get the survey data
            $sd = get_user_meta( $u->ID, 'surveys', true );
            // if there are surveys to process
            if ( '' != $sd ) {
                // for each of the set of survey responses for this users
                foreach ( $sd as $id => $s ) {
                    if ( in_array( $id, $self_eval_surveys ) ) {
                        if ( ! is_array( $s[ 'Q9_1_1' ] ) && is_numeric( $s[ 'Q9_1_1' ] ) ) {
                            $hours[ $id ][ 'spent'   ][] = (int) $s[ 'Q4' ];
                            if ( $u->ID == $userid ) $cu[ $id ][ 'spent' ] = (int) $s[ 'Q9_1_1' ];
                        }
                        if ( ! is_array( $s[ 'Q9_1_2' ] ) && is_numeric( $s[ 'Q9_1_2' ] ) ) {
                            $hours[ $id ][ 'planned' ][] = (int) $s[ 'Q7' ];
                            if ( $u->ID == $userid ) $cu[ $id ][ 'planned' ] = (int) $s[ 'Q9_1_2' ];
                        }
                    }
                }
            }
        }
        // initialize data strings for planned and spent hours
        $planned = $spent = '';
        // create some arrays of values we need to loop through to save us some lines of code
        $type = array( 'planned', 'spent' );
        $stats = array( 'goals_attempted', 'goals_achieved' );
        // for planned and spent hours
        foreach ( $type as $t ) {
            // for each stat that we want to calculate
            foreach ( $stats as $s ) {
                // pad the beginning of the data series with -1 to prevent first box plot from overlapping y axis
                $$t .= '-1,';
                // add an extra column of padding for planned hours since there aren't any for week 1
                if ( 'planned' == $t ) $$t .= '-1,';
                // for each weekly survey
                foreach ( $hours as $sid => $h ) {
                    // calculate the stat and add it to the data string
                    $$t .= $this->{'calculate_'.$s}( $h[ $t ] ) . ',';
                    // add an empty cell every other value to keep box plots from overlapping
                    $$t .= '-1,';
                }
                // add an extra column of padding for spent hours since these don't exist yet for next week
                if ( 'spent' == $t ) $$t .= '-1,';
                // remove the final comma from the end of the string
                $$t = substr( $$t, 0, -1 );
                // add a | to close out the series
                $$t .= '|';
            }
            // tack on a series for interpolated means so we can have trend lines
            $$t .= $this->calculate_interpolated_means( $$t );
        }
        // calculate the number of box plots (2 per week, one planned, one spent)
        $wks = 2 * count( $hours );
        // upper bound on series trend line for planned means
        $wksm1 = $wks - 1;
        // create week number labels for the x axis
        $xlabels = array();
        for ( $i = 0; $i <= count( $hours ); $i++ ) {
            if ( 0 != $i && count( $hours ) != $i ) {
                $xlabels[] = $i + 1;
            }
            $xlabels[] = $i + 1;
        }
        $xlabels = '||' . implode( '|', $xlabels ) . '||';
        // format the planned hours series
        $chm  = "F,225e98,0,1:$wks,25|H,225e98,0,1:$wks,1:10|H,225e98,3,1:$wks,1:10|H,225e98,4,1:$wks,1:25|d,225e98,5,1:$wks,7|D,225e98,6,2:$wks,2|";
        // format the spent hours series
        $chm .= "F,f15a24,7,1:$wks,25|H,f15a24,7,1:$wks,1:10|H,f15a24,10,1:$wks,1:10|H,f15a24,11,1:$wks,1:25|d,f15a24,12,1:$wks,7|D,f15a24,13,1:$wksm1,2|D,ff0000,14,0,2";
        // create a series for the recommended 7 hours/week line
        for ( $i = 0; $i < $wks; $i++ ) $sevens[] = '7';
        // finalize our data string by gluing the pieces together
        $data = $planned . $spent . implode( ',', $sevens );
        // add individual user data if necessary
        if ( count( $cu ) ) {
            $cuptmp = array( '-1', '-1' );
            $custmp = array( '-1' );
            foreach ( $cu as $id => $s ) {
                $cuptmp[] = $s[ 'planned' ];
                $custmp[] = $s[ 'spent'   ];
                $cuptmp[] = '-1';
                $custmp[] = '-1';
            }
            $custmp[] = '-1';
            $cu = '|' . implode( ',', $cuptmp ) . '|' . implode( ',', $custmp );
            foreach ( $cuptmp as $k => $v ) {
                if ( -1 != $v ) if ( isset( $cuptmp[ $k + 2 ] ) ) $cuptmp[ $k + 1 ] = ( $cuptmp[ $k + 2 ] + $v ) / 2;
            }
            foreach ( $custmp as $k => $v ) {
                if ( -1 != $v ) if ( isset( $custmp[ $k + 2 ] ) ) $custmp[ $k + 1 ] = ( $custmp[ $k + 2 ] + $v ) / 2;
            }
            $cu .= '|' . implode( ',', $cuptmp ) . '|' . implode( ',', $custmp );
            $chm .= "|D,8631c4,17,2:$wks,2|D,15db00,18,1:$wksm1,2|d,8631c4,15,1:$wks,7|d,15db00,16,1:$wks,7";
            $name = ( '' != substr( $user->display_name, 0, strpos( $user->display_name, ' ' ) ) ) ? substr( $user->display_name, 0, strpos( $user->display_name, ' ' ) ) : $user->first_name;
            $chdl =  '|' . $name . "'s Actual|" . $name . "'s Planned";
            $chco = ',15db00,8631c4';
        } else $cu = '';
        // set up the basic chart parameters
        $base = "chs=433x325&amp;cht=lc&amp;chtt=Hours Spent on Goals Per Week&amp;chxt=y,x,x&amp;chxr=0,0,20,5&amp;chxl=1:{$xlabels}2:|Week&amp;chxp=2,50|3,50&amp;chds=0,20&amp;chg=0,25,1,0&amp;chbh=25,5,7&amp;chdlp=b&amp;chdl=Recommended|Planned|Actual$chdl&amp;chco=ff0000,225e98,f15a24$chco&amp;chd=t0:";
        // compile the entire query string together
        $query = $base . $data . $cu . '&amp;chm=' . $chm;
        // display the chart
        echo '<img width="433" height="325" src="https://chart.googleapis.com/chart?' . $query. '" alt="hours planned and spent on the course by week" />';
    }	
	/**
	 * Create widget to display summary stats for hours spent outside of class
	 * TODO: Re-write with d3
	 */
	function load_um2u_hours_spent_summary_stats( $userid = null ) {
		/*
		// get all of our surveys
		$um2u_settings = get_option( 'umatter2us_settings' );
		$qtrx = new QualtricsAPI( $um2u_settings[ 'qtrxuser' ], $um2u_settings[ 'qtrxpass' ], $um2u_settings[ 'qtrxlib' ] );
		$all_surveys = $qtrx->getSurveys();
		$self_eval_surveys = array();
		foreach ( $all_surveys as $s ) {
			if ( false !== strpos( (string) $s->SurveyName, 'ISAT 252 Weekly Self-Evaluation - Spring 2012' ) ) {
			    $name = (string) $s->SurveyName;
				$self_eval_surveys[] = (string) $s->SurveyID;
				$weeks[ (string) $s->SurveyID ] = substr( $name, strpos( $name, 'Week ' ) );
			}
		}
		// get all users
		$users = get_users();
		if ( $userid ) $user = get_userdata( $userid );

		// initialize necessary variables
		$hours = array();
		arsort( $weeks );
		$cu = array(); // data for current user
		
		// loop through our users
		foreach( $users as $u ) {
			// get the survey data
			$sd = get_user_meta( $u->ID, 'surveys', true );
			// if there are surveys to process
			if ( '' != $sd ) {
				// for each of the set of survey responses for this users
				foreach ( $sd as $id => $s ) {
					if ( in_array( $id, $self_eval_surveys ) ) {
						if ( ! is_array( $s[ 'Q4' ] ) && is_numeric( $s[ 'Q4' ] ) ) {
							$hours[ $id ][ 'spent'   ][] = (int) $s[ 'Q4' ];
							if ( $u->ID == $userid ) $cu[ $id ][ 'spent' ] = (int) $s[ 'Q4' ];
						}
						if ( ! is_array( $s[ 'Q7' ] ) && is_numeric( $s[ 'Q7' ] ) ) {
						    $hours[ $id ][ 'planned' ][] = (int) $s[ 'Q7' ];
							if ( $u->ID == $userid ) $cu[ $id ][ 'planned' ] = (int) $s[ 'Q7' ];
						}
					}
				}
			}
		}
		// initialize data strings for planned and spent hours
		$planned = $spent = '';
		// create some arrays of values we need to loop through to save us some lines of code
		$type = array( 'planned', 'spent' );
		$stats = array( 'min', '1st_quartile', '3rd_quartile', 'max', 'median', 'mean' );
		// for planned and spent hours
		foreach ( $type as $t ) {
		    // for each stat that we want to calculate
		    foreach ( $stats as $s ) {
		        // pad the beginning of the data series with -1 to prevent first box plot from overlapping y axis
		        $$t .= '-1,';
		        // add an extra column of padding for planned hours since there aren't any for week 1
		        if ( 'planned' == $t ) $$t .= '-1,';
		        // for each weekly survey
		        foreach ( $hours as $sid => $h ) {
		            // calculate the stat and add it to the data string
		            $$t .= $this->{'calculate_'.$s}( $h[ $t ] ) . ',';
		            // add an empty cell every other value to keep box plots from overlapping
		            $$t .= '-1,';
		        }
		        // add an extra column of padding for spent hours since these don't exist yet for next week
		        if ( 'spent' == $t ) $$t .= '-1,';
		        // remove the final comma from the end of the string
		        $$t = substr( $$t, 0, -1 );
		        // add a | to close out the series
		        $$t .= '|';
		    }
		    // tack on a series for interpolated means so we can have trend lines
		    $$t .= $this->calculate_interpolated_means( $$t );
		}
		// calculate the number of box plots (2 per week, one planned, one spent)
		$wks = 2 * count( $hours );
		// upper bound on series trend line for planned means
		$wksm1 = $wks - 1;
		// create week number labels for the x axis
		$xlabels = array();
		for ( $i = 0; $i <= count( $hours ); $i++ ) {
		    if ( 0 != $i && count( $hours ) != $i ) {
		        $xlabels[] = $i + 1;
		    }
		    $xlabels[] = $i + 1;
		}
		$xlabels = '||' . implode( '|', $xlabels ) . '||';
		// format the planned hours series
		$chm  = "F,225e98,0,1:$wks,25|H,225e98,0,1:$wks,1:10|H,225e98,3,1:$wks,1:10|H,225e98,4,1:$wks,1:25|d,225e98,5,1:$wks,7|D,225e98,6,2:$wks,2|";
		// format the spent hours series
		$chm .= "F,f15a24,7,1:$wks,25|H,f15a24,7,1:$wks,1:10|H,f15a24,10,1:$wks,1:10|H,f15a24,11,1:$wks,1:25|d,f15a24,12,1:$wks,7|D,f15a24,13,1:$wksm1,2|D,ff0000,14,0,2";
        // create a series for the recommended 7 hours/week line
		for ( $i = 0; $i < $wks; $i++ ) $sevens[] = '7';
		// finalize our data string by gluing the pieces together
		$data = $planned . $spent . implode( ',', $sevens );
		// add individual user data if necessary
		if ( count( $cu ) ) {
		    $cuptmp = array( '-1', '-1' );
		    $custmp = array( '-1' );
		    foreach ( $cu as $id => $s ) {
		        $cuptmp[] = $s[ 'planned' ];
		        $custmp[] = $s[ 'spent'   ];
		        $cuptmp[] = '-1';
		        $custmp[] = '-1';
		    }
		    $custmp[] = '-1';
		    $cu = '|' . implode( ',', $cuptmp ) . '|' . implode( ',', $custmp );
		    foreach ( $cuptmp as $k => $v ) {
		        if ( -1 != $v ) if ( isset( $cuptmp[ $k + 2 ] ) ) $cuptmp[ $k + 1 ] = ( $cuptmp[ $k + 2 ] + $v ) / 2;
		    }
		    foreach ( $custmp as $k => $v ) {
		        if ( -1 != $v ) if ( isset( $custmp[ $k + 2 ] ) ) $custmp[ $k + 1 ] = ( $custmp[ $k + 2 ] + $v ) / 2;
		    }
		    $cu .= '|' . implode( ',', $cuptmp ) . '|' . implode( ',', $custmp );
		    $chm .= "|D,8631c4,17,2:$wks,2|D,15db00,18,1:$wksm1,2|d,8631c4,15,1:$wks,7|d,15db00,16,1:$wks,7";
		    $name = ( '' != substr( $user->display_name, 0, strpos( $user->display_name, ' ' ) ) ) ? substr( $user->display_name, 0, strpos( $user->display_name, ' ' ) ) : $user->first_name;
		    $chdl =  '|' . $name . "'s Actual|" . $name . "'s Planned";
		    $chco = ',15db00,8631c4';
		} else $cu = '';
		// set up the basic chart parameters
		$base = "chs=433x325&amp;cht=lc&amp;chtt=Hours Spent on Course Per Week&amp;chxt=y,x,x&amp;chxr=0,0,20,5&amp;chxl=1:{$xlabels}2:|Week&amp;chxp=2,50|3,50&amp;chds=0,20&amp;chg=0,25,1,0&amp;chbh=25,5,7&amp;chdlp=b&amp;chdl=Recommended|Planned|Actual$chdl&amp;chco=ff0000,225e98,f15a24$chco&amp;chd=t0:";
		// compile the entire query string together
		$query = $base . $data . $cu . '&amp;chm=' . $chm;
		// display the chart
		echo '<img width="433" height="325" src="https://chart.googleapis.com/chart?' . $query. '" alt="hours planned and spent on the course by week" />';
		*/
	}
	
	/**
	 * Functions to do basic statistical calculations
	 */
	function calculate_mean( $numbers ) {
		$total = 0;
		foreach ( $numbers as $num ) $total += $num;
		return round( $total / count( $numbers ), 2 );
	}
	function calculate_median( $numbers ) {
		sort( $numbers );
		$l = count( $numbers ) / 2;
		if ( is_int( $l ) ) {
		    return ( $numbers[ $l ] + $numbers[ $l - 1 ] ) / 2;
		} else {
		    return $numbers[ round( $l, 0, PHP_ROUND_HALF_DOWN ) ];
		}
	}
	function calculate_mode( $numbers ) {
	    
	}
	function calculate_max( $numbers ) {
	    sort( $numbers );
	    return array_pop( $numbers );
	}
	function calculate_min( $numbers ) {
	    sort( $numbers );
	    return array_shift( $numbers );
	}
	function calculate_sd( $numbers ) {
	    $mean = $this->calculate_mean( $numbers );
	    $ss = 0;
	    foreach ( $numbers as $n ) $ss += pow( $n - $mean, 2 );
	    return sqrt( $ss );
	}
	function calculate_1st_quartile( $numbers ) {
	    sort( $numbers );
	    $l = count( $numbers ) / 4;
	    if ( is_int( $l ) ) {
	        return ( $numbers[ $l ] + $numbers[ $l - 1 ] ) / 2;
	    } else {
	        return $numbers[ round( $l ) ];
	    }
	}
	function calculate_3rd_quartile( $numbers ) {
	    sort( $numbers );
	    $l = count( $numbers ) * 3 / 4;
	    if ( is_int( $l ) ) {
	        return ( $numbers[ $l ] + $numbers[ $l - 1 ] ) / 2;
	    } else {
	        return $numbers[ round( $l ) ];
	    }
	}
	function calculate_interpolated_means( $data ) {
	    $data = explode( '|', $data );
	    if ( isset( $data[ 5 ] ) ) {
    	    $data = explode( ',', $data[ 5 ] );
    	    foreach ( $data as $k => $v ) {
    	        if ( -1 != $v ) {
    	            if ( isset( $data[ $k + 2 ] ) ) {
    	                $data[ $k + 1 ] = ( $data[ $k + 2 ] + $v ) / 2;
    	            }
    	        }
    	    }
    	    return implode( ',', $data ) . '|';
	    }
	    return '';
	}
	
	/**
	 * Create Pages Read dashboard widgets
	 */
	function load_um2u_pages_read_widget() {
	    // get the total number of posts we're tracking
	    global $wpdb;
	    $posts = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'um2u_read_status' AND meta_value = 1" ) );
	    
	    // get a list of users
	    $users = get_users();
	    // create an array to hold the info we want, i.e. number of articles read by each user
	    $info = array();
        if ($posts != 0){
			foreach ( $users as $u ) {
				$posts_read = get_user_meta( $u->ID, 'um2u_posts_read', true );
				$count = ( '' == $posts_read ) ? 0 : count( $posts_read );
				$pct = number_format( $count / $posts * 100 );
				$info[] = array( 'name' => $u->display_name, 'login' => $u->user_login, 'count' => $count, 'percent' => $pct );
			}            
        } else {
            echo "";
        } 

	    
	    // sort the array in ascending order by count
	    $count = array();
	    foreach ( $info as $k => $v ) $count[ $k ] = $v[ 'count' ];
	    array_multisort( $count, SORT_ASC, $info );
	    
	    // output a table with the results
	    ?>
	    <table class="um2u_dashboard_table">
	        <thead>
	            <tr>
	                <th>Name</th>
	                <th>%</th>
	                <th>#</th>
	            </tr>
	        </thead>
	        <tbody>
	        <?php
	            foreach ( $info as $i ) {
	                ?>
	                <tr>
	                    <th><a href="<?php echo site_url() . '/author/' . $i[ 'login' ]; ?>"><?php echo $i[ 'name' ]; ?></a></th>
	                    <td><?php echo $i[ 'percent' ]; ?>%</td>
	                    <td><?php echo $i[ 'count' ] . '/' . $posts; ?></td>
	                </tr>
	                <?php
	            }
	        ?>
	        </tbody>
	    </table>
	    <?php
	}
	/**
	 * Creates the Objectives Adopted dashboard widget
	 */
	 function load_um2u_objectives_adopted_widget() {
	    // get the total number of  objectives
	    global $wpdb;
	    $objectives = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'um2u_objective'" ) );
		 
	    // get a list of users
	    $users = get_users();
	    // create an array to hold the info we want, in this case, the number of objectives adopted by each student
	    $info = array();
        if ($objectives != 0){
        foreach ( $users as $u ) {
            //return an array of all of the objectives
            $objectives_adopted = get_user_meta( $u->ID, 'um2u_objectives' , true );
            // find out how many there are
            $count =  ( '' != $objectives_adopted ) ? count( $objectives_adopted ) : 0;
            // breaks the count down into a percentage
            $pct = number_format( $count / $objectives * 100 );
            $info[] = array( 'name' => $u->display_name, 'login' => $u->user_login, 'count' => $count, 'percent' => $pct );
        }
        }
         else {
            echo "";
        }            
	
			
	    // sort the array in ascending order by count
	    $count = array();
	    foreach ( $info as $k => $v ) $count[ $k ] = $v[ 'count' ];
	    array_multisort( $count, SORT_ASC, $info );
		
	    // output a table with the results
	    ?>
	    <table class="um2u_dashboard_table">
	        <thead>
	            <tr>
	                <th>Name</th>
	                <th>%</th>
	                <th>#</th>
	            </tr>
	        </thead>
	        <tbody>
	        <?php
	            foreach ( $info as $i ) {
	                ?>
	                <tr>
	                    <th><a href="<?php echo site_url() . '/author/' . $i[ 'login' ]; ?>"><?php echo $i[ 'name' ]; ?></a></th>
	                    <td><?php echo $i[ 'percent' ]; ?>%</td>
	                    <td><?php echo $i[ 'count' ] . '/' . $objectives; ?></td>
	                </tr>
	                <?php
	            }
	        ?>
	        </tbody>
	    </table>
	    <?php		
	}
	 /**
	 * Creates the Objectives Progress Widget
	 */
	 function load_um2u_objectives_progress_widget() {
				
		// get all users
		$users = get_users();
		if ( $userid ) $user = get_userdata( $userid );
		
		//create an array that will be used to create the widget's table display		
		$user_progress = array();
		//create a counter for assisting with sorting later
		$i=0;
		
		// loop through our users
		foreach( $users as $u ) {
			// get their objective progress
			$o_progress = get_user_meta( $u -> ID, 'um2u_objectives', true );

			$accomplished = $partial_progress = $little_progress = $sort_value = 0;			
			
			//check if there is any progress to check
			if( '' != $o_progress){
				//loop through each 
				foreach ( $o_progress as $id => $p) {
					if ( ! is_array( $p[ 'progress' ] ) ) {
						//increment the placeholders for objectives relatively completed, those with no progress, and those with partial progress	
						if( '2' == $p[ 'progress' ] ) $accomplished++;
						if( '1' == $p[ 'progress' ] ) $partial_progress++;
						if( '0' == $p[ 'progress' ] ) $little_progress++;
						//increment the value that will later be used to sort the data
						$sort_value +=  $p['progress'];										
					}
				}
			}
			//create an array that holds each users revelant data
			$user_progress[$i] = array('name' => $u->display_name, 'login' => $u->user_login, $accomplished, $partial_progress, $little_progress, 'sort_value' => $sort_value);				
			//increment the counter
			$i++;	
		}		
	    // sort the array in ascending order by sort value
	    $sort = array();
	    foreach ( $user_progress as $k => $v ) $sort[ $k ] = $v[ 'sort_value' ];
	    array_multisort( $sort, SORT_ASC, $user_progress );
			
		// output a table with the results
	    ?>
	    <table class="um2u_dashboard_table">
	        <thead>
	            <tr>
	                <th>Name</th>
	                <th>Objectives Accomplished</th>
	                <th>Objectives in Progress</th>
					<th>Little to No Progress</th>
	            </tr>
	        </thead>
	        <tbody>
	        <?php
	            foreach ( $user_progress as $i ) {
	                ?>
	                <tr>
	                    <th><a href="<?php echo site_url() . '/author/' . $i[ 'login' ]; ?>"><?php echo $i[ 'name' ]; ?></a></th>
	                    <td><?php echo $i[0]; ?></td>
	                    <td><?php echo $i[1]; ?></td>
						<td><?php echo $i[2]; ?></td>
	                </tr>
	                <?php
	            }
	        ?>
	        </tbody>
	    </table>
	    <?php		
	}
		 
	/**
	 * Handles the 'plugins_loaded' action hook
	 */
	function plugins_loaded () {
        // register styles
        wp_register_style( 'um2ufeedback_css', plugins_url() . '/umatter2us/css/um2ufeedback.css' );
        
        // register scripts
        wp_register_script( 'um2ufeeback_js', plugins_url() . '/umatter2us/js/um2ufeedback.js', 'jquery', '1.0', true );
	}
	
	function admin_head() {
	    $this->enqueue_um2ufeedback_styles();
	}
    /**
     * Registers all settings
     */
    function admin_init() {
        // register settings
		//callback = name of a function
		// &$this = allows callbacks within this class
        register_setting( 'um2ufeedback_settings', 'um2ufeedback_settings', array( &$this, 'validate_um2ufeedback_settings' ) );
        
        // umatter2us settings and fields, each setting will have individual functions
        add_settings_section( 'umatter2us_settings_section', null, array( &$this, 'umatter2us_settings_section' ), 'umatter2us-menu' );
        
        /* check for existence of WP Qualtrics
        if ( is_plugin_active( 'wp-qualtrics/wp-qualtrics.php' ) ) {
            //require_once plugins_url() . '/wp-qualtrics/wp-qualtrics.php';
        } */
    }

    /**
     * Sets up all the menus and submenus.  Adds actions for enqueuing styles and scripts
     */
    function admin_menu() {
        // main menu page
        //add_menu_page( 'UMatter2Us', 'UMatter2Us', 'manage_options', 'umatter2us-menu', array( &$this, 'umatter2us_menu' ), plugins_url() . '/umatter2us/images/umatter2us_icon.png', 31 );
        
        //submenus
        $menus[ 'f' ] = add_submenu_page( 'umatter2us-menu', 'Feedback::UMatter2Us'         , 'Feedback'         , 'manage_options', 'um2ufeedback-menu'      , array( &$this, 'um2ufeedback_menu' ) );
		$menus[]      = add_submenu_page( $menus[ 'f' ]    , 'Add/Edit Feedback::UMatter2Us', 'Add/Edit Feedback', 'manage_options', 'um2u-edit-feedback-menu', array (&$this, 'um2uedit_feedback_menu'));

        // enqueue styles and scripts
        foreach( $menus as $menu ) {
            add_action( 'admin_print_styles-'  . $menu, array( &$this, 'enqueue_um2ufeedback_styles' ) );
            add_action( 'admin_print_scripts-' . $menu, array( &$this, 'enqueue_um2ufeedback_scripts' ) );
        }
    }
    
    /**
     * Enqueue styles
     */
    function enqueue_um2ufeedback_styles() {
        wp_enqueue_style( 'um2ufeedback_css' );
    }
    
    /**
     * Enqueue scripts
     */
    function enqueue_um2ufeedback_scripts() {
        wp_enqueue_script( 'um2ufeedback_js' );
    }

	/**
	 * Displays a summary of all of the user's weekly survey output
	 */
	function display_user_profile_page(  ) {
        // is this an author archive?
        if ( is_archive() && is_author() ) {
            // is the site visitor logged in?
            global $current_user, $wpdb;
            get_currentuserinfo();
            if ( 0 != $current_user->ID ) {
                // yes, they're logged in, now get the author info
                $ca = ( get_query_var( 'author_name' ) ) ? get_user_by( 'slug', get_query_var( 'author_name' ) ) : get_userdata( get_query_var( 'author' ) );
                
                // get posts read and objectives adopted
                $posts_read         = get_user_meta( $ca->ID, 'um2u_posts_read', true );
                $all_posts          = get_posts( array( post_type => 'any', 'numberposts' => -1, 'meta_key' => 'um2u_read_status', 'meta_value' => 1 ) );
                $objectives_adopted = get_user_meta( $ca->ID, 'um2u_objectives', true );
                $all_objectives     = get_posts( array( 'numberposts' => -1, 'post_type' => 'um2u_objective' ) );
                
                // get other info about the user
                $ud    = wp_upload_dir();
                $img   = $ud[ 'baseurl' ] . '/userphoto/' . get_user_meta( $ca->ID, 'userphoto_image_file', true );
                $ih    = get_user_meta( $ca->ID, 'userphoto_image_height', true );
                $iw    = get_user_meta( $ca->ID, 'userphoto_image_width',  true );
                $bio   = get_user_meta( $ca->ID, 'description', true );
                $twi   = get_user_meta( $ca->ID, 'twitter',     true );
                $fb    = get_user_meta( $ca->ID, 'facebook',    true );
                $li    = get_user_meta( $ca->ID, 'linkedin',    true );
                $gplus = get_user_meta( $ca->ID, 'googleplus',  true );
                $yim   = get_user_meta( $ca->ID, 'yim',         true );
                $aim   = get_user_meta( $ca->ID, 'aim',         true );
                $msn   = get_user_meta( $ca->ID, 'msn',         true );
                $cimy  = $wpdb->get_results( "SELECT f.name, d.value
                                              FROM   {$wpdb->prefix}cimy_uef_fields f,
                                                     {$wpdb->prefix}cimy_uef_data   d
                                              WHERE  f.id = d.field_id AND 
                                                     user_id = " . $ca->ID );
                foreach ( $cimy as $c ) {
                    if ( 'MOBILE_NUMBER' == $c->name ) $mobile = $c->value;
                    if ( 'GMAIL_ACCOUNT' == $c->name ) $gmail  = $c->value;
                }
                
                // get Qualtrics survey data
				$data = array();
				$um2u_settings = get_option( 'umatter2us_settings', true );
				if ( $um2u_settings[ 'um2u_weekly_self_eval_survey_id' ] ) {
					$qtrx = $this->getQualtrics();
					// get all of the responses
					$responses = $qtrx->getLegacyResponseData( array(
						'SurveyID' => $um2u_settings[ 'um2u_weekly_self_eval_survey_id' ]
					));
					// filter out the one's that correspond to this user
					foreach ( $responses->Response as $r ) {
						if ( $ca->user_login == $r->ExternalDataReference ) {
							$data[] = $r;
						}
					}
					usort( $data, function( $a, $b ) { return strtotime( $a->StartDate ) - strtotime( $b->StartDate ); } );
					$weeklygoals = array();
					$week = 1;
					foreach ( $data as $i => $d ) {
						if ( 1 == $week ) {
							$weeklygoals[ $week ][ 'planned' ] = 4;
							$weeklygoals[ $week ][ 1 ][ 'text' ] = "Read the entire syllabus on the course website. Click \"I've read this\" when you finish each page.";
							$weeklygoals[ $week ][ 2 ][ 'text' ] = "Complete the pre-semester questionnaire that was emailed to you";
							$weeklygoals[ $week ][ 3 ][ 'text' ] = "Download and unzip XAMPP (Portable Lite version) onto a flash drive and bring it with you to class.";
							$weeklygoals[ $week ][ 4 ][ 'text' ] = "Write your first journal entry on the course website as a brainstorm about what programs you might write this semester to solve a problem that you care about.";
							$weeklygoals[ $week ][ 5 ][ 'text' ] = "Have a social outing with your new team (to be determined in class on Monday) and upload a picture of your frivolity as a post on the class website.";
						}
						for ( $g = 1; $g <= 5; $g++ ) {
							$weeklygoals[ $week     ][ $g ][ 'achieved' ] = $d->{'Q9_' . $g . '_1'};
							$weeklygoals[ $week     ][ $g ][ 'evidence' ] = $d->{'Q9_' . $g . '_2'};
							$weeklygoals[ $week + 1 ][ $g ][ 'text'     ] = $d->{'Q6_' . $g . '_TEXT'};
							$weeklygoals[ $week + 1 ][ 'planned' ] = $d->Q7;
							$weeklygoals[ $week     ][ 'spent' ]   = $d->Q4;
						}
						$week++;
					}
				}

                $surveys = get_option( 'um2u_author_page_surveys' );
                $icon_url = plugins_url( 'images/', __FILE__ );
                
                // output the profile
    			echo '<div class="author"><div class="vcard">';
    			echo "<h1 class=\"fn\">$ca->display_name</h1>";
    			echo '<img class="avatar" src="' . $img . '" alt="' . $ca->display_name . '" width="' . $iw . '" height="' . $ih . '" />';
    			echo '<h2>' . __( 'Contact Info' ) . '</h2><p>';
    			echo '<span class="tel"><span class="type">Mobile</span>: <span class="value">' . $mobile . '</span></span><br />';
    			echo '<span>Email: <a class="email" href="mailto:' . $ca->user_email . '">' . $ca->user_email . '</a></span><br />';
    			if ( $gmail ) echo '<span class="email">GMail: <a href="mailto:' . $gmail . '"><img src="' . $icon_url . 'gmail_16.png" alt="Gmail" width="16" height="16" /> ' . $gmail . '</a></span><br />';
    			if ( $twi || $fb || $li || $gplus /*|| $yim || $aim || $msn*/ ) {
    				echo '<span class="socialnets">Social Networks: ';
    				if ( $twi   ) echo '<a class="url" href="' . $twi . '"><img src="' . $icon_url . 'twitter_16.png" alt="Follow me on Twitter!" width="16" height="16" /></a> ';
    				if ( $fb    ) echo '<a class="url" href="' . $fb . '"><img src="' . $icon_url . 'facebook_16.png" alt="Friend me on Facebook!" width="16" height="16" /></a> ';
    				if ( $li    ) echo '<a class="url" href="' . $li . '"><img src="' . $icon_url . 'linkedin_16.png" alt="Let\'s get Linked In!" width="16" height="16" /></a> ';
    				if ( $gplus ) echo '<a class="url" href="' . $gplus . '"><img src="' . $icon_url . 'google_16.png" alt="Connect with me on Google+!" width="16" height="16" /></a><br />';
    				//if ( $yim   ) echo '<img src="' . $icon_url . 'yahoo.png" alt="Let\'s chat on Yahoo!" width="64" height="64" /> ' . $yim . '<br />';
    				//if ( $aim   ) echo '<img src="' . $icon_url . 'aim.png" alt="Let\'s chat on AIM!" width="64" height="64" /> ' . $aim . '<br />';
    				//if ( $msn   ) echo '<a class="url" href="' . $twi . '"><img src="" alt="Follow me on Twitter!" width="" height="" /></a> ';
    			    echo '</span>';
    			} 
    			echo '</p></div><div style="clear:both"></div>';
    			if ( $bio ) echo '<h2>Bio</h2><p>' . $bio . '</p>';
    			echo '<h2>My Progress</h2>';
    			//$this->load_um2u_hours_spent_summary_stats( $ca->ID );
    			echo '<h3>Pages I\'ve Read <span class="toggler">(<a href="#" title="my_pages_read" class="list_toggler">show</a>)</span></h3>';
    			echo '<div id="my_pages_read" style="display:none;">';
				if ( ! is_array( $posts_read ) ) {
    			    echo '<p>I haven\'t read any pages yet.</p>';
    			} else {
    			    echo '<ul>';
    			    foreach ( $posts_read as $pr ) {
    			        $p = get_post( $pr );
    			        echo '<li><a href="' . get_permalink( $pr ). '">' . $p->post_title . '</a></li>'; 
    			    }
    			    echo '</ul>';
    			}
				echo '</div>';
    			echo '<h3>Pages I Haven\'t Read <span class="toggler">(<a href="#" title="my_pages_unread" class="list_toggler">show</a>)</span></h3>';
    			echo '<div id="my_pages_unread" style="display:none;">';
				$unread_count = 0;
    			if ( ! is_array( $all_posts ) ) {
    			    echo '<p>There are no pages to read.</p>';
    			} else {
    			    echo '<ul>';
    			    foreach ( $all_posts as $ap ) {
    			        if ( ! is_array( $posts_read ) || ! in_array( $ap->ID, $posts_read ) ) {
        			        echo '<li><a href="' . get_permalink( $ap->ID ) . '">' . $ap->post_title . '</a></li>';
        			        $unread_count++;
    			        }
    			    }
    			    echo '</ul>';
    			}
    			if ( 0 == $unread_count ) echo '<p>I have read all the pages!</p>';
				echo '</div>';
    			echo '<h3>Objectives I\'ve Adopted <span class="toggler">(<a href="#" title="my_objectives_adopted" class="list_toggler">show</a>)</span></h3>';
				echo '<div id="my_objectives_adopted" style="display:none;">';
				if ( ! is_array( $objectives_adopted ) ) {
    			    echo '<p>I haven\'t adopted any objectives yet.</p>';
    			} else {
					$ok = $ca->ID == $current_user->ID ? 'true' : 'false';
					echo '<div style="display: none;" id="ok_to_update_objective_progress" title="' . $ok . '"></div>';
    			    echo '<table>';
					echo '<thead><tr><th>Progress</th><th>Objective</th></tr></thead><tbody>';
    			    foreach( $objectives_adopted as $id => $oa ) {
    			        $p = get_post( $id );
	                    echo '<tr><td><ul class="objective_progress level' . $oa[ 'progress' ] . '">';
	                    for ( $i = 0; $i < 5; $i++ ) {
	                        if ( $i == $oa[ 'progress' ] ) {
	                            echo '<li class="selected"><a href="#" onclick="um2u_update_objective_progress( ' . $p->ID . ', ' . $ca->ID . ', ' . $i . ' ); return false;">' . $i . '</a></li>';
	                        } else {
	                            echo '<li><a href="#" onclick="um2u_update_objective_progress( ' . $p->ID . ', ' . $ca->ID . ', ' . $i . ' ); return false;">' . $i . '</a></li>';
	                        }
	                    }
	                    echo '</ul></td>';
    			        echo '<td><a href="' . get_permalink( $id ) . '">' . $p->post_title . '</a></td>';
    			    }
    			    echo '</tbody></table>';
    			}
				echo '</div>';
    			echo '<h3>Objectives I Haven\'t Adopted <span class="toggler">(<a href="#" title="my_objectives_unadopted" class="list_toggler">show</a>)</span></h3>';
    			echo '<div id="my_objectives_unadopted" style="display:none;">';
				$unadopted_count = 0;
    			if ( ! is_array( $all_objectives ) ) {
    			    echo '<p>There are no objectives to be adopted.</p>';
    			} else {
    			    echo '<ul>';
    			    foreach( $all_objectives as $ao ) {
    			        if ( ! is_array( $objectives_adopted ) || ! array_key_exists( $ao->ID, $objectives_adopted ) ) {
    			            echo '<li><a href="' . get_permalink( $ao->ID ) . '">' . $ao->post_title . '</a></li>';
    			            $unadopted_count++;
    			        }
    			    }
    			    echo '</ul>';
    			}
    			if ( 0 == $unadopted_count ) echo '<p>I have adopted all of the objectives available.</p>';
    			echo '</div>';
    			
				// output the goals set/achieved by week
				echo '<h3>My Goals <span class="toggler">(<a href="#" title="my_goals" class="list_toggler">show</a>)</span></h3>';
				echo '<div id="my_goals" style="display:none;">';
				foreach ( $weeklygoals as $week => $goals ) {
					echo "<h4>Week $week" . ' <span class="toggler">(<a href="#" title="my_goals_week' . $week . '" class="list_toggler">show</a>)</span>' . "</h4>";
					echo '<ul id="my_goals_week' . $week . '" style="display:none;">';
					echo '<li>Hours Planned: ' . $goals[ 'planned' ] . '</li>';
					echo '<li>Hours Spent: '   . $goals[ 'spent'   ] . '</li>';
					foreach ( $goals as $key => $goal ) {
						if ( $key != 'planned' && $key != 'spent' ) {
							$a = $goal[ 'achieved' ];
							$e = $goal[ 'evidence' ];
							$class = '';
							if ( $a && $e ) $class = ' class="achieved evidence"';
							elseif ( $a && ! $e ) $class = ' class="achieved"';
							elseif ( ! $a && $e ) $class = ' class="evidence"';
							echo "<li$class>{$goal['text']}</li>";
						}
					}
					echo '</ul>';
				}
				echo '</div>';
				// output the results of pre-semester and weekly self-eval surveys
    			
    		    echo '</div>'; // closes <div class="author">
				echo '<h2>My Posts</h2>';
            }
        }
	}

    /**
     * Gets a Qualtrics object
     */
    function getQualtrics() {
		$settings = get_option( 'umatter2us_settings' );
        $lib = $settings[ 'qtrxlib' ];
        if ( '' == $lib ) {
            return new WP_Error( 'Qualtrics Library not set', __( 'The Qualtrics Library ID has not been set.' ) );
        } else {
            $user  = $settings[ 'qtrxuser'  ];
            $token = $settings[ 'qtrxtoken' ];
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

class UM2UObjectiveWidget extends WP_Widget {
    /**
     * Constructors
     */
    function UM2UObjectiveWidget() { $this->__construct(); }
    function __construct() {
        // initialize widget
        parent::WP_Widget( 'um2u_objective_widget', 'UM2U_Objective_Widget', array( 'description' => __( 'Puts a button in the sidebar that allows members to adopt an objective and monitor their progress.' ) ) );
        // enqueue javascripts and css
        wp_enqueue_style(   'um2u_objective_widget_css', plugins_url() . '/umatter2us/css/um2u_objective_widget.css' );
        wp_enqueue_script(  'um2u_objective_widget_js',  plugins_url() . '/umatter2us/js/um2u_objective_widget.js', array( 'jquery' ), false, true );
        wp_localize_script( 
            'um2u_objective_widget_js', 
            'UM2UObjectiveWidget', 
            array( 
                'ajaxurl' => admin_url( 'admin-ajax.php' ), 
                'um2u_objective_nonce' => wp_create_nonce( 'um2u_objective_nonce' ), 
                'um2u_objective_status_nonce' => wp_create_nonce( 'um2u_objective_status_nonce' ) 
            ) 
        );
        add_action( 'wp_ajax_um2u_adopt_objective',           array( &$this, 'wp_ajax_um2u_adopt_objective' ) );
        add_action( 'wp_ajax_um2u_update_objective_progress', array( &$this, 'wp_ajax_um2u_update_objective_progress' ) );
    }
    
    /**
     * the HTML output of the widget
     */
    function widget( $args, $instance ) {
        // get the current post
        global $post;
        // only display this on um2u_objective posts
        if ( 'um2u_objective' == $post->post_type ) {
            extract( $args );
            $title = apply_filters( 'widget_title', $instance[ 'title' ] );
            echo $before_widget;
            if ( $title ) echo $before_title . $title . $after_title;
            // get current user
            $user = wp_get_current_user();
            // is user logged in?
            if ( 0 != $user->ID ) {
                // get the list of member objectives
                $objectives = get_user_meta( $user->ID, 'um2u_objectives', true );
                // has this objective been adopted?
                if ( is_array( $objectives ) && array_key_exists( $post->ID, $objectives ) ) {
                    // yes, this objective has been adopted
                    echo '<p>' . __( 'Update your progress by clicking on the progress bar:' ) . '</p>';
                    $progress = $objectives[ $post->ID ][ 'progress' ];
					echo '<div style="display: none;" id="ok_to_update_objective_progress" title="true"></div>';
                    echo '<ul class="objective_progress level' . $progress . '">';
                    for ( $i = 0; $i < 5; $i++ ) {
                        if ( $i == $progress ) {
                            echo '<li class="selected"><a href="#" onclick="um2u_update_objective_progress( ' . $post->ID . ', ' . $user->ID . ', ' . $i . ' ); return false;">' . $i . '</a></li>';
                        } else {
                            echo '<li><a href="#" onclick="um2u_update_objective_progress( ' . $post->ID . ', ' . $user->ID . ', ' . $i . ' ); return false;">' . $i . '</a></li>';
                        }
                    }
                    echo '</ul>';
                } else {
                    // no, this objective has not been adopted
                    echo '<div id="um2u_objective_widget"><p>';
                    echo __( 'If you would like to adopt this learning objective, please click the button below.' ) . '</p>';
                    echo '<button class="button" onclick="um2u_adopt_objective( ' . $post->ID . ', ' . $user->ID . ' )"><strong>' . __( 'I want to learn this!' ) . '</strong></button></div>';
                }
                // output the list of people who have and have not adopted this objective
                $members = get_users();
                foreach ( $members as $m ) {
                    $objectives_adopted = get_user_meta( $m->ID, 'um2u_objectives', true );
                    if ( is_array( $objectives_adopted ) && array_key_exists( $post->ID, $objectives_adopted ) ) {
                        $m->progress = 'um2u_progress_' . $objectives_adopted[ $post->ID ][ 'progress' ];
                        $adopters[] = $m;
                    } else {
                        $nonadopters[] = $m;
                    }
                }
                echo '<p><a href="#" id="um2u_adopted_list_toggle">Show Adopters</a></p>';
                echo '<div id="um2u_adopted_list" style="display:none">';
                echo '<h2 class="widget-title">' . __( 'People who have adopted this objective' ) . '</h2>';
                if ( ! is_array( $adopters ) ) {
                    echo '<p id="um2u_no_adopters">' . __( 'Nobody has adopted this objective yet.' ) . '</p>';
                }
                echo '<ul id="um2u_adopters">';
                if ( is_array( $adopters ) ) {
                    foreach ( $adopters as $a ) echo '<li class="' . $a->progress . '" id="adopter_' . $a->user_login . '"><a href="' . site_url() . '/author/' . $a->user_login . '">' . $a->display_name . '</a></li>';
                }
                echo '</ul>';
                echo '<h2 class="widget-title">' . __( 'People who have NOT adopted this objective' ) . '</h2>';
                if ( ! is_array( $nonadopters ) ) {
                    echo '<p id="um2u_no_adopters">' . __( 'Everyone has adopted this objective!' ) . '</p>';
                }
                echo '<ul id="um2u_nonadopters">';
                if ( is_array( $nonadopters ) ) {
                    foreach ( $nonadopters as $a ) echo '<li id="adopter_' . $a->user_login . '"><a href="' . site_url() . '/author/' . $a->user_login . '">' . $a->display_name . '</a></li>';
                }
                echo '</ul>';
                echo '</div>';
            } else {
                // not logged in
                echo '<p>' . __(  'You are not logged in. ' );
                echo __( 'To adopt this objective or see your progress please ' );
                echo '<a href="' . wp_login_url( get_permalink() ) . '">' . __( 'log in' ) . '</a>.</p>';
            }
            echo $after_widget; 
        }
    }

    /**
     * Handle the adopt objective ajax call
     */
    function wp_ajax_um2u_adopt_objective() {
        if ( ! wp_verify_nonce( $_POST[ 'um2u_objective_nonce' ], 'um2u_objective_nonce' ) ) die( 'Unauthorized' );
        // get posted variables
        $postid = $_POST[ 'postid' ];
        $userid = $_POST[ 'userid' ];
        // get user's objectives
        $objectives = get_user_meta( $userid, 'um2u_objectives', true );
        // add the new one
        global $post, $current_user;
        $objectives[ $postid ][ 'progress' ] = 0;
        $objectives[ $postid ][ 'history' ][ date( 'Y-m-d H:i:s' ) ] = 'Adopted: ' . $post->post_title;
        $success = update_user_meta( $userid, 'um2u_objectives', $objectives );
        if ( $success ) {
            $message  = '<p>' . __( 'Update your progress by clicking on the light:' ) . '</p>';
            $message .= '<ul id="objective_progress">';
            $message .= '<li class="selected"><a href="#" onclick="um2u_update_objective_progress( ' . $postid . ', ' . $userid . ', 0 ); return false;">0</a></li>';
            $message .= '<li><a href="#" onclick="um2u_update_objective_progress( ' . $postid . ', ' . $userid . ', 1 ); return false;">1</a></li>';
            $message .= '<li><a href="#" onclick="um2u_update_objective_progress( ' . $postid . ', ' . $userid . ', 2 ); return false;">2</a></li>';
            $message .= '</ul>';
            $message .= '<script type="text/javascript">um2u_activate_progress_hover();</script>';
            $response[ 'message' ] = $message;
            $response[ 'user' ] = $current_user->user_login;
            header( 'content-type: application/json' );
            echo json_encode( $response );
        } else {
            echo 0;
        }
        exit;
    }
    
    /**
     * Handle the update objective progress ajax call
     */
    function wp_ajax_um2u_update_objective_progress() {
        // make sure we have a valid request
        if ( ! wp_verify_nonce( $_POST[ 'um2u_objective_status_nonce' ], 'um2u_objective_status_nonce' ) ) die();
        // initialize the response
        $response = array( 'nonce' => wp_create_nonce( 'um2u_objective_status_nonce' ), 'progress' => -1 );
        // attempt to update the progress
        $postid = $_POST[ 'postid' ];
        $userid = $_POST[ 'userid' ];
        $progress = $_POST[ 'progress' ];
        $objectives = get_user_meta( $userid, 'um2u_objectives', true );
        $old_progress = $objectives[ $postid ][ 'progress' ];
        $progress_values = array( 'not started/no progress', 'in progress', 'accomplished' );
        $objectives[ $postid ][ 'progress' ] = $progress;
        $objectives[ $postid ][ 'history' ][ date( 'Y-m-d H:i:s' ) ] = 'Progress changed from ' . $progress_values[ $old_progress ] . ' to ' . $progress_values[ $progress ];
        $success = update_user_meta( $userid, 'um2u_objectives', $objectives );
        if ( $success ) $response[ 'progress' ] = $progress;
        global $current_user;
        $response[ 'user' ] = $current_user->user_login;
        // send the response
        header( 'content-type: application/json' );
        echo json_encode( $response );
        exit;
    }
    
    /**
     * Form to allow the title of the widget to be changed
     */
	function form( $instance ) {
	    $title = ( $instance ) ? esc_attr( $instance[ 'title' ] ) : '';
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</p>
        <?php
	}

    /**
     * Function to update the title once changed
     */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance[ 'title' ] = strip_tags( $new_instance[ 'title' ] );
		return $instance;
	}
}
add_action( 'widgets_init', create_function( '', 'register_widget("UM2UObjectiveWidget");' ) );

class UM2UReadPostWidget extends WP_Widget {
    /**
     * Constructors
     */
    function UM2UReadPostWidget() {
        $this->__construct();
    }
    function __construct() {
        // create the widget
        parent::WP_Widget(
            'um2u_readpost_widget',
            'UM2U_Read_Post_Widget',
            array(
                'description' => __( 'Puts a button in the sidebar that allows members to mark a post as "read."' )
            )
        );
        // enqueue javascripts
        wp_enqueue_script(  'um2u_read_post_widget_js', plugins_url() . '/umatter2us/js/um2u_read_post_widget.js', 'jquery', false, true );
        wp_localize_script( 'um2u_read_post_widget_js', 'UM2UReadPostWidget', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'um2unonce' => wp_create_nonce( 'um2u_read_post_nonce' ) ) ); 
        
        add_action( 'wp_ajax_um2u_mark_read', array( &$this, 'wp_ajax_um2u_mark_read' ) );
    }
    
    /**
     * the HTML output of the widget
     */
    function widget( $args, $instance ) {
        // get the current post
        global $post;
        // check to see if this post should display this widget
        $read_status = get_post_meta( $post->ID, 'um2u_read_status', true );
        if ( $read_status ) {
            extract( $args );
            $title = apply_filters( 'widget_title', $instance[ 'title' ] );
            echo $before_widget;
            if ( $title ) echo $before_title . $title . $after_title;
            // get current user
            $user = wp_get_current_user();
            // is user logged in?
            if ( 0 != $user->ID ) {
                // get the list of posts read
                $posts_read = get_user_meta( $user->ID, 'um2u_posts_read', true );
                // check to see if this post has been read
                if ( is_array( $posts_read ) && in_array( $post->ID, $posts_read ) ) {
                    // yes, it has been read
                    echo '<p>' . __( 'You have already marked this page as "read."<br /><strong>Thank you!</strong>' ) . '</p>';
                } else {
                    // no, it hasn't been read
                    echo '<div id="um2u_read_post"><p>' . __( 'By clicking the button below, I hereby certify that I have FULLY read or watched and understand the content on this page.' ) . '</p>';
                    echo '<button class="button" onclick="um2u_mark_post_read( ' . $post->ID . ', ' . $user->ID . ' )"><strong>' . __( "I've read this!" ) . '</strong></button></div>';
                }
                // output the list of people who have and have not read this page
                $members = get_users();
                foreach ( $members as $m ) {
                    $posts_read = get_user_meta( $m->ID, 'um2u_posts_read', true );
                    if ( is_array( $posts_read ) && in_array( $post->ID, $posts_read ) ) {
                        $readers[] = $m;
                    } else {
                        $nonreaders[] = $m;
                    }
                }
                echo '<p><a href="#" id="um2u_read_list_toggle">Show Readers</a></p>';
                echo '<div id="um2u_read_list" style="display:none">';
                echo '<h2 class="widget-title">' . __( 'People who have read this page' ) . '</h2>';
                if ( ! is_array( $readers ) ) {
                    echo '<p id="um2u_no_readers">' . __( 'Nobody has read this page yet.' ) . '</p>';
                }
                echo '<ul id="um2u_readers">';
                if ( is_array( $readers ) ) {
                    foreach ( $readers as $r ) echo '<li><a href="' . site_url() . '/author/' . $r->user_login . '">' . $r->display_name . '</a></li>';
                }
                echo '</ul>';
                echo '<h2 class="widget-title">' . __( 'People who have NOT read this page' ) . '</h2>';
                if ( ! is_array( $nonreaders ) ) {
                    echo '<p>' . __( 'Everyone has read this page!' ) . '</p>';
                }
                echo '<ul id="um2u_nonreaders">';
                if ( is_array( $nonreaders ) ) {
                    foreach ( $nonreaders as $r ) echo '<li id="' . $r->user_login . '"><a href="' . site_url() . '/author/' . $r->user_login . '">' . $r->display_name . '</a></li>';
                }
                echo '</ul>';
                echo '</div>';
            } else {
                // not logged in
                echo '<p>' . __(  'You are not logged in. ' );
                echo __( 'To mark this page as "read" please ' );
                echo '<a href="' . wp_login_url( get_permalink() ) . '">' . __( 'log in' ) . '</a>.</p>';
            }
            echo $after_widget; 
        }
    }

    /**
     * Handle the "I've read this!" button click with ajax
     */
    function wp_ajax_um2u_mark_read() {
        // security check
        $nonce = $_POST[ 'um2unonce' ];
        if ( ! wp_verify_nonce( $nonce, 'um2u_read_post_nonce' ) ) die( 'Unauthorized request.' );
        
        // get the submitted parameters
        $postid = $_POST[ 'postid' ];
        $userid = $_POST[ 'userid' ];
        
        // get the posts read
        $posts_read = get_user_meta( $userid, 'um2u_posts_read', true );
        // add the current post
        $posts_read[] = $postid;
        // update it in the database
        $success = update_user_meta( $userid, 'um2u_posts_read', $posts_read );
        
        // send a response
        global $current_user;
        $response[ 'user' ] = $current_user->user_login;
        $response[ 'message' ] = '<p>' . __( 'You have marked this page as "read."  Thank you!' ) . '</p>';
        if ( $success ) {
            header( 'content-type: application/json' );
            echo json_encode( $response );
        }        
        exit;
    }

    /**
     * Form to allow the title of the widget to be changed
     */
	function form( $instance ) {
	    $title = ( $instance ) ? esc_attr( $instance[ 'title' ] ) : '';
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</p>
        <?php
	}

    /**
     * Function to update the title once changed
     */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance[ 'title' ] = strip_tags( $new_instance[ 'title' ] );
		return $instance;
	}
	
}
add_action( 'widgets_init', create_function( '', 'register_widget("UM2UReadPostWidget");' ) );
 
?>