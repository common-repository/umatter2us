<?php
/*
 * Title: WP Qualtrics--Qualtrics Class
 * File: class-qualtrics.php
 * Author: Morgan Benton
 * Description: This class instantiates methods for accessing the 
 *				Qualtrics REST API. 
 */
/**
 * Qualtrics API Class
 * 
 * This class instantiates the methods for accessing the Qualtrics REST API.
 * 
 * @package	WP-Qualtrics
 * @version	1.0
 * @author	Morgan Benton <bentonmc@jmu.edu>
 */

class QualtricsAPI {
	
	// Qualtrics Options
	private $qualtrics_username;
	private $qualtrics_password;
	private $qualtrics_library;
	
	// Constructors
	function QualtricsAPI( $user = null, $pass = null, $library = null ) {
	    $this->__construct( $user, $pass, $library );
	}
	
	function __construct( $user = null, $pass = null, $library = null ) {
		// include WP_HTTP and WP_Error APIs
		if ( ! class_exists( 'WP_Http' ) )  include_once ABSPATH . WPINC . '/class-http.php';
		if ( ! class_exists( 'WP_Error' ) ) include_once ABSPATH . WPINC . '/class-wp-error.php';
		
		$this->qualtrics_username = $user;
		$this->qualtrics_password = $pass;
		$this->qualtrics_library  = $library;
	}
	
	private function qualtrics_request( $request, $params = array() ) {
		// create a new Http object
		$req  = new WP_Http;
		
		// set the base URL for Qualtrics requests
		$url  = 'https://new.qualtrics.com/Server/RestApi.php';
		
		// set the parameters to be sent, allows files to be sent
		$body = array_merge( array(
                'Request'  => $request,
                'User'     => $this->qualtrics_username,
                'Password' => $this->qualtrics_password
            ), $params
		);
				
		// make the request
		$res  = $req->request( $url, array( 'method' => 'POST', 'body' => $body ) );

		// handle the result
		if ( ! is_wp_error( $res ) ) {
			// request was successful
			if ( false !== strpos( $res[ 'headers' ][ 'content-type' ], 'text/xml' ) ) {
    			// parse the result
    			$res = new SimpleXMLElement( $res[ 'body' ], LIBXML_NOCDATA );

    			// check for Qualtrics errors
    			if ( (string)$res->RequestStatus == 'Error' ) {
    				$msg = ( (string)$res->ErrorMessage != '' ) ? (string)$res->ErrorMessage : (string)$res->ErrorDebugComment;
    				$res = new WP_Error( (string)$res->ErrorDebugComment, __( $msg ), $res );
    			}
			} else {
			    return $res[ 'body' ];
			}
		} 

		// return the result
		return $res;
	}
	
	function is_misconfigured() {
	    if ( ! ( $this->qualtrics_username && $this->qualtrics_password ) ) {
	        // username or password is not set
	        return 'Please <a href="admin.php?page=umatter2us-menu">set a valid Qualtrics username and password</a>.';
	    } else {
	        // test the credentials
	        $res = $this->getSurveys();
	        if ( is_wp_error( $res ) ) 
	            return 'Qualtrics Error: ' . $res->get_error_message();
	    }
	    return false;
	}
	
	/**
	 * Returns an object representing the respondent data for the survey.
	 * 
	 * Returns an object representing the respondent data for the survey.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	An array which must contain a SurveyID.  See the API docs for other possible parameters.
	 * @return	object			An object representing the respondent data for the survey.
	 */
	function getResponseData( $params ) {
		$params = array_merge( array(
					'SurveyID' => null,
					'Format'   => 'XML' ), $params );
		if ( !$params[ 'SurveyID' ] ) {
			return new WP_Error( 'Missing Parameter', __('The SurveyID parameter was not specified.'), $params );
		}
		$result = $this->qualtrics_request( 'getResponseData', $params );
		return ( is_wp_error( $result ) ) ? $result : $result->xpath( '//Response' );
	}
	
	/**
	 * Returns an object representing the survey with the given SurveyID.
	 * 
	 * Returns an object representing the survey with the given SurveyID.
	 * 
	 * @since 	1.0
	 * @param	string 	$survey_id	The SurveyID of the survey.
	 * @return	object				An object representing the survey with the given SurveyID.
	 */
	function getSurvey( $survey_id ) {
		if ( ! $survey_id ) {
			return new WP_Error( 'Missing Parameter', __('The SurveyID parameter was not specified.') );
		}
		return $this->qualtrics_request( 'getSurvey', array( 'SurveyID' => $survey_id ) );
	}
	
	/**
	 * Returns the name of the survey with a given SurveyID.
	 * 
	 * Returns the name of the survey with a given SurveyID.
	 * 
	 * @since 	1.0
	 * @param	string 	$survey_id	The SurveyID of the survey.
	 * @return	string				The name of the given survey.
	 */
	function getSurveyName( $survey_id ) {
		if ( !$survey_id ) {
			return new WP_Error( 'Missing Parameter', __('The SurveyID parameter was not specified.') );
		}
		$result = $this->qualtrics_request( 'getSurveyName', array( 'SurveyID' => $survey_id ) );
		return ( is_wp_error( $result ) ) ? $result : (string)$result->SurveyName;
	}
	
	/**
	 * Returns a list of all the surveys for the user name stored in the class.
	 * 
	 * Returns a list of all the surveys for the user name stored in the class.
	 * 
	 * @since 	1.0
	 * @return	array	An array of survey objects.
	 */
	function getSurveys() {
		$result = $this->qualtrics_request( 'getSurveys' );
		return ( is_wp_error( $result) ) ? $result : $result->Surveys->element;
	}
	
	/**
	 * Returns all the panels contained in the library.
	 * 
	 * Returns all the panels contained in the library.
	 * 
	 * @since 	1.0
	 * @param	string 	$library_id	The LibraryID of the library containing the panels.
	 * @return	object				An array of panel objects.
	 */
	function getPanels( $library_id = false ) {
		$library_id = ( $library_id ) ? $library_id : $this->qualtrics_library;
		if ( !$library_id ) {
			return new WP_Error( 'Missing Parameter', __('The LibraryID parameter was not specified.') );
		}
		$result = $this->qualtrics_request( 'getPanels', array( 'LibraryID' => $library_id ) );
		return ( is_wp_error( $result ) ) ? $result : $result->Panels->element;
	}
	
	/**
	 * Returns all the panel members for the given panel.
	 * 
	 * Returns all the panel members for the given panel.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain at least a PanelID value. Optionally may specify LibraryID and/or Format => 'CSV'.
	 * @return	object			An array of panel member objects including the RecipientID needed for getting individual response data.
	 */
	function getPanel( $params ) {
		$params = array_merge( array(
					'LibraryID' => $this->qualtrics_library,
					'PanelID'   => null,
					'Format'    => 'XML' ), $params );
		if ( !$params[ 'LibraryID' ] || !$params[ 'PanelID' ] ) {
			return new WP_Error( 'Missing Parameter', __('The LibraryID and/or PanelID parameter was not specified.') );
		}
		$result = $this->qualtrics_request( 'getPanel', $params );
		if ( is_wp_error( $result ) || 'CSV' == $params[ 'Format'] ) {
		    return $result;
		} else  {
		    return $result->xpath( '//Recipient' );
		}
	}

	/**
	 * Returns the number of panel members.
	 *
	 * Returns the number of panel members.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain at least a PanelID value. Optionally may specify LibraryID
	 * @return	integer			The number of panel members
	 */
	function getPanelMemberCount( $params ) {
		$params = array_merge( array(
					'LibraryID' => $this->qualtrics_library,
					'PanelID'   => null ), $params );
		if ( !$params[ 'LibraryID' ] || !$params[ 'PanelID' ] ) {
			return new WP_Error( 'Missing Parameter', __('The PanelID and/or LibraryID parameter was not specified.') );
		}
		$result = $this->qualtrics_request( 'getPanelMemberCount', $params );
		return ( is_wp_error( $result ) ) ? $result : (int)$result->Count;
	}
	
	/**
	 * Attempts to create a panel in the given library with the given name.
	 *
	 * Attempts to create a panel in the given library with the given name.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain at least a Name value. Optionally may specify LibraryID
	 * @return	string			The PanelID of the panel that was created.
	 */
	function createPanel( $params ) {
		// set parameters
		$params = array_merge( array(
					'LibraryID' => $this->qualtrics_library,
					'Name'      => null ), $params );
					
		// check for missing parameters
		if ( !$params[ 'LibraryID' ] || !$params[ 'Name' ] ) {
			return new WP_Error( 'Missing Parameter', __('The Name and/or LibraryID parameter was not specified.') );
		}
		
		// check for duplicate panel names
		$panels = $this->getPanels( $params[ 'LibraryID' ] );
		if ( !is_wp_error( $panels ) ){
			foreach ( $panels as $panel ) {
				if ( $panel->Name == $params[ 'Name' ] ) {
					return new WP_Error( 'Duplicate Panel Name', __( 'The panel name specified is already in use. Please choose another.' ) );
				}
			}
		} else {
			// there was an error looking up the panels
			return $panels;
		}
		
		// try to create the panel
		$result = $this->qualtrics_request( 'createPanel', $params );
		
		// handle the result
		return ( is_wp_error( $result ) ) ? $result : (string)$result->PanelID;
	}
	
	/**
	 * Imports panel information from a CSV file.
	 *
	 * Imports a csv file as a new panel (optionally in can append to a previously made panel) 
	 * into the database and returns the panel id. The csv file can be posted (there is an 
	 * approximate 8 megabytes limit) or a url can be given to retrieve the file from a remote 
	 * server. The csv file must be comma separated using “ for encapsulation.  When specifying 
	 * column numbers in the parameters the first column is considered 1.
	 * 
	 * @todo 	Waiting for guidance from the Qualtrics support staff.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain at least a Name value. Optionally may specify LibraryID
	 * @return	string			The PanelID of the panel that was modified.
	 */
	function importPanel( $params ) {
		return new WP_Error( 'Unavailable', __( 'This function has not been implemented yet.  Sorry.' ) );
		$params = array_merge( array(
					'LibraryID'     => $this->qualtrics_library,
					'ColumnHeaders' => 1,
					'Email'         => 1 ), $params );
		if ( !$params[ 'LibraryID' ] ) {
			return new WP_Error( 'Missing Parameter', __('The LibraryID parameter was not specified.') );
		}
		$result = $this->qualtrics_request( 'importPanel', $params );
		return ( is_wp_error( $result ) ) ? $result : (string)$result->PanelID;
	}
	
	/**
	 * Sends a survey through the Qualtrics mailer to the panel specified.
	 *
	 * Sends a survey through the Qualtrics mailer to the panel specified.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain SurveyID, FromEmail, FromName, Subject, MessageID, and PanelID values. Optionally may specify SendDate, MessageLibraryID, LinkType and PanelLibraryID.
	 * @return	object			The EmailDistributionID and DistributionQueueID of the email that was sent.
	 */	
	function sendSurveyToPanel( $params ) {
		$params = array_merge( array(
					'SurveyID'         => null,
					'SendDate'         => date( 'Y-m-d H:i:s', time() - ( 60 * 119 ) ),
					'FromEmail'        => null,
					'FromName'         => null,
					'Subject'          => null,
					'MessageID'        => null,
					'MessageLibraryID' => $this->qualtrics_library,
					'PanelID'          => null,
					'PanelLibraryID'   => $this->qualtrics_library,
					'LinkType'		   => 'Individual' ), $params );
		if ( !$params[ 'SurveyID' ]         || 
		     !$params[ 'FromEmail' ]        ||
		     !$params[ 'FromName' ]         ||
		     !$params[ 'Subject' ]          ||
		     !$params[ 'MessageID' ]        ||
		     !$params[ 'MessageLibraryID' ] ||
		     !$params[ 'PanelID' ]          ||
		     !$params[ 'PanelLibraryID' ] ) {
			return new WP_Error( 'Missing Parameter', __('One or more of the required parameters was not specified.'), $params );
		}
		$result = $this->qualtrics_request( 'sendSurveyToPanel', $params );
		return ( is_wp_error( $result ) ) ? $result : $result;
	}
	
	/**
	 * Sends a survey through the Qualtrics mailer to the individual specified.
	 *
	 * Sends a survey through the Qualtrics mailer to the individual specified.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain SurveyID, FromEmail, FromName, Subject, MessageID, PanelID, and RecipientID values. Optionally may specify SendDate, MessageLibraryID,and PanelLibraryID.
	 * @return	object			The EmailDistributionID and DistributionQueueID of the email that was sent.
	 */	
	function sendSurveyToIndividual( $params ) {
		$params = array_merge( array(
					'SurveyID'         => null,
					'SendDate'         => date( 'Y-m-d H:i:s', time() - ( 60 * 119 ) ),
					'FromEmail'        => null,
					'FromName'         => null,
					'Subject'          => null,
					'MessageID'        => null,
					'MessageLibraryID' => $this->qualtrics_library,
					'PanelID'          => null,
					'PanelLibraryID'   => $this->qualtrics_library,
					'RecipientID'      => null ), $params );
		if ( !$params[ 'SurveyID' ]         || 
		     !$params[ 'FromEmail' ]        ||
		     !$params[ 'FromName' ]         ||
		     !$params[ 'Subject' ]          ||
		     !$params[ 'MessageID' ]        ||
		     !$params[ 'MessageLibraryID' ] ||
		     !$params[ 'PanelID' ]          ||
		     !$params[ 'PanelLibraryID' ]   ||
			 !$params[ 'RecipientID' ] ) {
				return new WP_Error( 'Missing Parameter', __('One or more of the required parameters was not specified.') );
		}
		$result = $this->qualtrics_request( 'sendSurveyToIndividual', $params );
		return ( is_wp_error( $result ) ) ? $result : $result;
	}
	
	/**
	 * Returns an object representation of the recipient and their history.
	 *
	 * Returns an object representation of the recipient and their history.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain a RecipientID value. Optionally may specify a LibraryID.
	 * @return	object			An object representing the recipient and their history.
	 */	
	function getRecipient( $params ) {
		$params = array_merge( array(
					'RecipientID' => null,
					'LibraryID'   => $this->qualtrics_library ), $params );
		if ( !$params[ 'RecipientID' ] || !$params[ 'LibraryID' ] ) {
			return new WP_Error( 'Missing Parameter', __('The RecipientID and/or LibraryID parameter was not specified.') );
		}
		$result = $this->qualtrics_request( 'getRecipient', $params );
		return ( is_wp_error( $result ) ) ? $result : $result;
	}
	
	/**
	 * Updates the recipient's data.
	 *
	 * Updates the recipient’s data—any value not specified will be left alone and 
	 * not updated. NOTICE: This method replaces setRecipient. Calling setRecipient 
	 * will continue to work but is now deprecated.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain a RecipientID value. Optionally may specify a LibraryID.
	 * @return	object			An object representing the recipient and their history.
	 */	
	function updateRecipient( $params ) {
		$params = array_merge( array(
					'RecipientID' => null,
					'LibraryID'   => $this->qualtrics_library ), $params );
		if ( !$params[ 'RecipientID' ] || !$params[ 'LibraryID' ] ) {
			return new WP_Error( 'Missing Parameter', __('The RecipientID and/or LibraryID parameter was not specified.') );
		}
		$result = $this->qualtrics_request( 'updateRecipient', $params );
		return ( is_wp_error( $result ) ) ? $result : $result;
	}
	
	/**
	 * Add a new recipient to a panel.
	 *
	 * Add a new recipient to a panel.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain a PanelID value. Generally will specify FirstName, LastName, and Email.  May also include other embedded data.
	 * @return	string			The RecipientID of the new panel member.
	 */	
	function addRecipient( $params ) {
		$params = array_merge( array(
					'LibraryID' => $this->qualtrics_library,
					'PanelID'   => null ), $params );
		if ( !$params[ 'LibraryID' ] || !$params[ 'PanelID' ] ) {
			return new WP_Error( 'Missing Parameter', __('The PanelID and/or LibraryID parameter was not specified.') );
		}
		$result = $this->qualtrics_request( 'addRecipient', $params );
		return ( is_wp_error( $result ) ) ? $result : (string)$result->RecipientID;
	}
	
	/**
	 * Sends a reminder through the Qualtrics mailer to the panel or individual as specified by the parent distribution Id.
	 *
	 * Sends a reminder through the Qualtrics mailer to the panel or individual as specified by the parent distribution Id.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain ParentEmailDistributionID, FromEmail, FromName, Subject, and MessageID values. May also specify SendDate and LibraryID.
	 * @return	object			The EmailDistributionID and DistributionQueueID of the reminder email.
	 */	
	function sendReminder( $params ) {
		$params = array_merge( array(
					'ParentEmailDistributionID' => null,
					'SendDate'                  => date( 'Y-m-d H:i:s' ),
					'FromEmail'                 => null,
					'FromName'                  => null,
					'Subject'                   => null,
					'MessageID'                 => null,
					'LibraryID'                 => $this->qualtrics_library ), $params );
		if ( !$params[ 'ParentEmailDistributionID' ] || 
		     !$params[ 'FromEmail' ]                 ||
		     !$params[ 'FromName' ]                  ||
		     !$params[ 'Subject' ]                   ||
		     !$params[ 'MessageID' ]                 ||
		     !$params[ 'LibraryID' ] ) {
			return new WP_Error( 'Missing Parameter', __('One or more of the required parameters was not specified.') );
		}
		$result = $this->qualtrics_request( 'sendReminder', $params );
		return ( is_wp_error( $result ) ) ? $result : $result;
	}
	
	/**
	 * Gets the poll definition of an existing poll.
	 *
	 * Gets the poll definition of an existing poll. Additionally, the JavaScript code 
	 * used to insert the poll, the last modified date, the creation date, the active 
	 * status, and the current number of responses are returned.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain a PollID.
	 * @return	object			An object representing the poll.
	 */	
	function getPollDefinition( $params ) {
		$params = array_merge( array( 'PollID' => null ) );
		if ( !$params[ 'PollID' ] ) {
			return new WP_Error( 'Missing Parameter', __('The PollID was not specified.') );
		}
		$result = $this->qualtrics_request( 'getPollDefinition', $params );
		return ( is_wp_error( $result ) ) ? $result : $result;
	}
	
	/**
	 * Gets the results of the specified poll.
	 *
	 * Gets the results of the specified poll.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain a PollID.
	 * @return	object			An object representing the poll results.
	 */	
	function getPollResults( $params ) {
		$params = array_merge( array( 'PollID' => null ) );
		if ( !$params[ 'PollID' ] ) {
			return new WP_Error( 'Missing Parameter', __('The PollID was not specified.') );
		}
		return $this->qualtrics_request( 'getPollResults', $params );
		return ( is_wp_error( $result ) ) ? $result : $result;
	}
	
	/**
	 * Creates a new poll.
	 *
	 * Creates a new poll and returns the PollID, and JavaScript code. All look and 
	 * feel options are set to the default, while other poll attributes, such as title, 
	 * question, choices, and button text, may be submitted as parameters.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Should contain a Title, Question, Choices (separated by %0A) and ButtonText.
	 * @return	object			An object representing the poll.
	 */	
	function createPoll( $params = null ) {
		$result = $this->qualtrics_request( 'createPoll', $params );
		return ( is_wp_error( $result ) ) ? $result : $result;
	}
	
	/**
	 * Edits any parameter of the poll definition.
	 *
	 * Edits any parameter of the poll definition.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain a PollID.  See API for other possible parameters (there are many).
	 * @return	object			An object representing the edited poll.
	 */	
	function editPoll( $params ) {
		$params = array_merge( array( 'PollID' => null ) );
		if ( !$params[ 'PollID' ] ) {
			return new WP_Error( 'Missing Parameter', __('The PollID was not specified.') );
		}
		$result = $this->qualtrics_request( 'editPoll', $params );
		return ( is_wp_error( $result ) ) ? $result : $result;
	}
	
	/**
	 * Deletes the specified poll.
	 *
	 * Deletes the specified poll.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain a PollID.
	 * @return	string			The PollID of the deleted poll.
	 */	
	function deletePoll( $poll_id ) {
		if ( !$poll_id ) {
			return new WP_Error( 'Missing Parameter', __('The PollID was not specified.') );
		}
		return $this->qualtrics_request( 'deletePoll', $params );
		return ( is_wp_error( $result ) ) ? $result : (string)$result->PollID;
	}
	
	/**
	 * Resets the poll results to 0.
	 *
	 * Resets the poll results to 0. If the poll was restricted to allow a single response per 
	 * respondent, the limitation is also removed so that the respondent can again take the poll.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain a PollID.
	 * @return	string			The PollID of the reset poll.
	 */	
	function resetPoll( $poll_id ) {
		if ( !$poll_id ) {
			return new WP_Error( 'Missing Parameter', __('The PollID was not specified.') );
		}
		$result = $this->qualtrics_request( 'resetPoll', $params );
		return ( is_wp_error( $result ) ) ? $result : (string)$result->PollID;
	}
	
	/**
	 * Activates the specified poll.
	 *
	 * Activates the specified poll.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain a PollID.
	 * @return	string			The PollID of the activated poll.
	 */	
	function activatePoll( $poll_id ) {
		if ( !$poll_id ) {
			return new WP_Error( 'Missing Parameter', __('The PollID was not specified.') );
		}
		$result = $this->qualtrics_request( 'activatePoll', $params );
		return ( is_wp_error( $result ) ) ? $result : (string)$result->PollID;
	}
	
	/**
	 * Deactivates the specified poll.
	 *
	 * Deactivates the specified poll.  An inactive poll will display the results only.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain a PollID.
	 * @return	string			The PollID of the deactivated poll.
	 */	
	function deactivatePoll( $poll_id ) {
		if ( !$poll_id ) {
			return new WP_Error( 'Missing Parameter', __('The PollID was not specified.') );
		}
		$result = $this->qualtrics_request( 'deactivatePoll', $params );
		return ( is_wp_error( $result ) ) ? $result : (string)$result->PollID;
	}
	
	/**
	 * Allows a poll response to be submitted.
	 *
	 * Allows a response to be submitted. Note that this does not enforce restrictions on multiple voting.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain a PollID and Results value (example: Results=PO_12345_3).
	 * @return	object			An object representing the given poll's results.
	 */	
	function submitPollResults( $params ) {
		$params = array_merge( array( 
					'PollID'  => null,
					'Results' => null ) );
		if ( !$params[ 'PollID' ] || !$params[ 'Results' ] ) {
			return new WP_Error( 'Missing Parameter', __('The PollID and/or Results were not specified.') );
		}
		$result = $this->qualtrics_request( 'submitPollResults', $params );
		return ( is_wp_error( $result ) ) ? $result : $result;
	}
	
	/**
	 * Retrieves a list of polls for the specified user.
	 *
	 * Retrieves a list of polls for the specified user.
	 * 
	 * @since 	1.0
	 * @param	mixed 	$params	Must contain a PollID and Results value (example: Results=PO_12345_3).
	 * @return	object			An object representing the given poll's results.
	 */	
	function getPolls() {
		$result = $this->qualtrics_request( 'getPolls' );
		return ( is_wp_error( $result ) ) ? $result : $result;
	}
	
}