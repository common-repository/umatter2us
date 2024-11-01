<?php
/**
 * Qualtrics Class
 *
 * This class implements access functions for all of the API 
 * functions available in the Qualtrics 2.0 API.  It is meant to
 * be used from within WordPress and as such requires that 
 * WordPress has been loaded first in order to access the WP_HTTP
 * library for making remote requests, and also for using the
 * WP_Error class.
 * 
 * @author Morgan Benton <morgan.benton@gmail.com>
 * @since 1.0
 * @package personalitypad
 */

if ( ! class_exists( 'Qualtrics' ) ) {

	class Qualtrics {
		/**
		 * Class variables
		 */
	
		/**
		 * Qualtrics username
		 *
		 * This is the email address that serves as the Qualtrics 
		 * username that has access to the api
		 *
		 * @var string $username
		 */
		var $username;
	
		/**
		 * Qualtrics token
		 *
		 * This is the Qualtrics-generated that gives a user 
		 * access to the api
		 *
		 * @var string $token
		 */
		var $token;
	
		/**
		 * Qualtrics library
		 *
		 * This is the library ID associated with the 
		 * username that has access to the api
		 *
		 * @var string $library
		 */
		var $library;
	
		/**
		 * Qualtrics API URL
		 *
		 * This is the URL of the Qualtrics API
		 *
		 * @var string $url
		 */
		var $url = 'https://survey.qualtrics.com/WRAPI/ControlPanel/api.php';

		/**
		 * Constructors
		 */
	
		function Qualtrics( $username, $token, $library ) {
			$this->__construct( $username, $token, $library );
		}
	
		function __construct( $username, $token, $library = '' ) {
		
			// make sure that the WP_Error and WP_Http classes are loaded
			if ( ! class_exists( 'WP_Error' ) ) include_once ABSPATH . WPINC . '/class-wp-error.php';
			if ( ! class_exists( 'WP_Http'  ) ) include_once ABSPATH . WPINC . '/class-http.php';
		
			// check the parameters to make sure they all exist
			if ( ! $username ) return new WP_Error( 'Missing Qualtrics Username', __( 'Could not initialize the Qualtrics class. The Qualtrics username was not supplied.' ) );
			if ( ! $token    ) return new WP_Error( 'Missing Qualtrics Token',    __( 'Could not initialize the Qualtrics class. The Qualtrics token was not supplied.' ) );
			//if ( ! $library  ) return new WP_Error( 'Missing Qualtrics Library',  __( 'Could not initialize the Qualtrics class. The Qualtrics library ID for this user was not supplied.' ) );

			// set the username, token, and library
			$this->username = $username;
			$this->token    = $token;
			$this->library  = $library;
		}
		
		/**
		 * Lets us know if Qualtrics is misconfigured
		 */
		function is_misconfigured() {
		    if ( ! ( $this->username && $this->token ) ) {
		        // username or token is not set
		        return 'Please <a href="admin.php?page=umatter2us-menu">set a valid Qualtrics username and token</a>.';
		    } else {
		        // test the credentials
		        $res = $this->getSurveys();
		        if ( is_wp_error( $res ) ) return 'Qualtrics Error: ' . $res->get_error_message();
		    }
		    return false;
		}

		/**
		 * Internal function for making a generic Qualtrics API request
		 *
		 * This function takes an API function name and an array of parameters 
		 * and makes an attempt to fetch the data using the Qualtrics API.
		 * 
		 * @param string $request The name of the API function to be called
		 * @param mixed $params An array which contains any necessary parameters for the API call
		 * @return object Returns either an object representing the requested data or a WP_Error object
		 */
		function request( $request, $params = array() ) {
		
			// create a new WP_Http object
			$http = new WP_Http;
		
			// set the parameters
			$body = array_merge( array(
				'Request' => $request,
				'User'    => $this->username,
				'Token'   => $this->token,
				'Version' => '2.0',
				), $params
			);
		
			// make the request
			$res = $http->request( $this->url, array( 'method' => 'POST', 'body' => $body, 'timeout' => 0 ) );

			// handle the result
			if ( ! is_wp_error( $res ) ) {
				// request was successful, check to see that XML was returned
				if ( false !== strpos( $res[ 'headers' ][ 'content-type' ], 'text/xml' ) ) {
					// we got XML, parse the result
					try {
						$xml = new SimpleXMLElement( $res[ 'body' ], LIBXML_NOCDATA );
					} catch( Exception $e ) {
						return new WP_Error( 'XML Error', $e->getMessage(), array( $body, $res ) );
					}
					
					// this is kind of a hack; need to find a more legit way to do this
					if ( 'getLegacyResponseData' == $request ) {
						// return the result
						return json_decode( json_encode( $this->xmlToArray( $xml ) ) );
					}

					// check for Qualtrics errors
					if ( ! isset( $xml->Questions ) && (string) $xml->Meta->Status != 'Success' ) {
						return new WP_Error( 
							(string) $xml->Meta->ErrorCode . ' ' . (string) $xml->Meta->Status , 
							(string) $xml->Meta->ErrorMessage, 
							$xml
						);
					} else {
						// return the result
						return json_decode( json_encode( $this->xmlToArray( $xml ) ) );
					}
				} elseif ( false !== strpos( $res[ 'headers' ][ 'content-type' ], 'json' ) ) {
					// we got JSON, parse the result
					$json = json_decode( $res[ 'body' ] );
				
					// check for Qualtrics errors
					if ( $json->Meta->Status != 'Success' ) {
						return new WP_error( 
							$json->Meta->ErrorCode . ' ' . $json->Meta->Status, 
							$json->Meta->ErrorMessage, 
							$json
						);
					} else {
						// return the result
						return $json->Result;
					}
				} else {
					// we got something other than XML or JSON
					return new WP_Error( 'Unrecognized Response Format', __( 'The Qualtrics response was neither JSON nor XML.' ), $res );
				}
			} else {
				// request was unsuccessful, return the error
				return $res;
			}
		}
    
	    /**
	     * Converts XML to Array that can then be converted to JSON
	     */
	    function xmlToArray( $xml ) {
	        $array = json_decode( json_encode( $xml ), true );
	        foreach ( array_slice( $array, 0 ) as $key => $value ) {
	            if ( empty( $value ) ) $array[ $key ] = null;
	            elseif ( is_array( $value ) ) $array[ $key ] = $this->xmlToArray( $value );
	        }
	        return $array;
	    }
    
		/**
		 * Gets the total number of responses for a survey in a given date range
		 *
		 * @param string Format Must be XML or JSON, defaults to JSON
		 * @param string StartDate Start date of responses to include, format is YYYY-MM-DD
		 * @param string EndDate End date of responses to include, format is YYYY-MM-DD, default is today's date
		 * @param string SurveyID The Qualtrics ID of the survey in question.  The user must have permission to view this survey's responses.
		 * @return object Returns a PHP object with the resulting counts of "Auditable", "Generated" and "Deleted" responses
		 */
		function getResponseCountsBase( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'    => 'JSON',
				'StartDate' => null,
				'EndDate'   => date( 'Y-m-d' ),
				'SurveyID'  => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'StartDate' ] ) return new WP_Error( 'Missing Parameter: StartDate', __( 'The StartDate parameter was not specified' ), $params );
			if ( ! $params[ 'SurveyID'  ] ) return new WP_Error( 'Missing Parameter: SurveyID',  __( 'The SurveyID parameter was not specified'  ), $params );
		
			// try to fetch and return the result
			return $this->request( 'getResponseCountsBase', $params );
		}
	
		/**
		 * Gets the total number of responses for a survey in a given date range
		 *
		 * @param string Format Must be XML or JSON, defaults to JSON
		 * @param string StartDate Start date of responses to include, format is YYYY-MM-DD
		 * @param string EndDate End date of responses to include, format is YYYY-MM-DD, default is today's date
		 * @param string SurveyID The Qualtrics ID of the survey in question.  The user must have permission to view this survey's responses.
		 * @return object Returns a PHP object with the resulting counts of "Auditable", "Generated" and "Deleted" responses
		 */
		function getResponseCountsBySurvey( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'    => 'JSON',
				'StartDate' => null,
				'EndDate'   => date( 'Y-m-d' ),
				'SurveyID'  => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'StartDate' ] ) return new WP_Error( 'Missing Parameter: StartDate', __( 'The StartDate parameter was not specified' ), $params );
			if ( ! $params[ 'SurveyID'  ] ) return new WP_Error( 'Missing Parameter: SurveyID',  __( 'The SurveyID parameter was not specified'  ), $params );
		
			// try to fetch and return the result
			return $this->request( 'getResponseCountsBySurvey', $params );
		}
	
		/**
		 * Gets information about a user
		 *
		 * @param string Format Must be XML or JSON.  Defaults to JSON.
		 * @return object Information about the user in question
		 */
		function getUserInfo( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array( 'Format' => 'JSON' ), $params );
		
			// try to fetch  and return the results
			return $this->request( 'getUserInfo', $params );
		}
	
		/**
		 * Add a new recipient to a panel
		 *
		 * @param string Format           Must be XML or JSON, defaults to JSON
		 * @param string LibraryID        The LibraryID of the user who owns the panel
		 * @param string PanelID          The ID of the Panel to which the user is to be added
		 * @param string FirstName        First name of the new recipient
		 * @param string LastName         Last name of the new recipient
		 * @param string Email            Email address of the new recipient
		 * @param string ExternalDataRef  (optional) A value to store in the external data ref for the user (should default to the WordPress username)
		 * @param string Language         (optional) The language code for the user, e.g. EN, defaults to EN
		 * @param string ED[***]          (optional) An embedded data value, there can be many, and takes the form e.g. ED[skypeID]=mcbenton
		 * @return object Returns true on success or WP_Error on failure
		 */
		function addRecipient( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'    => 'JSON',
				'LibraryID' => $this->library,
				'PanelID'   => null,
				'FirstName' => null,
				'LastName'  => null,
				'Email'     => null,
				'Language'  => 'EN',
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'PanelID'   ] ) return new WP_Error( 'Missing Parameter: PanelID',   __( 'The PanelID parameter was not specified'   ), $params );
			if ( ! $params[ 'FirstName' ] ) return new WP_Error( 'Missing Parameter: FirstName', __( 'The FirstName parameter was not specified' ), $params );
			if ( ! $params[ 'LastName'  ] ) return new WP_Error( 'Missing Parameter: LastName',  __( 'The LastName parameter was not specified'  ), $params );
			if ( ! $params[ 'Email'     ] ) return new WP_Error( 'Missing Parameter: Email',     __( 'The Email parameter was not specified'     ), $params );
		
			// fetch the response
			return $this->request( 'addRecipient', $params );
		}
	
		/**
		 * Creates a distribution for survey and a panel.  No emails will be sent.  Distribution links can be generated later to take the survey.
		 *
		 * @param string Format Must be XML or JSON, JSON is default
		 * @param string SurveyID The ID of the survey to create a distribution for
		 * @param string PanelID The ID of the panel for the distribution
		 * @param string Description A description of the distribution
		 * @param string PanelLibraryID The library ID of the panel
		 * @return object An object containing the DistributionID
		 */
		function createDistribution( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'         => 'JSON',
				'PanelLibraryID' => $this->library,
				'PanelID'        => null,
				'SurveyID'       => null,
				'Description'    => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'PanelID'     ] ) return new WP_Error( 'Missing Parameter: PanelID',     __( 'The PanelID parameter was not specified'     ), $params );
			if ( ! $params[ 'SurveyID'    ] ) return new WP_Error( 'Missing Parameter: SurveyID',    __( 'The SurveyID parameter was not specified'    ), $params );
			if ( ! $params[ 'Description' ] ) return new WP_Error( 'Missing Parameter: Description', __( 'The Description parameter was not specified' ), $params );
		
			// fetch and return the response
			return $this->request( 'createDistribution', $params );
		}
	
		/**
		 * Creates a new Panel in the Qualtrics System and returns the ID of the new panel
		 *
		 * @param string Format Must be JSON or XML, defaults to JSON
		 * @param string LibraryID The library ID of the user creating the panel
		 * @param string Name A name for the new panel
		 * @param string Category (optional) The category the panel is created in
		 * @return object Returns the ID of the new panel
		 */
		function createPanel( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'    => 'JSON',
				'LibraryID' => $this->library,
				'Name'      => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'Name' ] ) return new WP_Error( 'Missing Parameter: Name', __( 'The Name parameter was not specified' ), $params );
		
			// fetch and return the response
			return $this->request( 'createPanel', $params );
		}
	
		/**
		 * Gets all the panel members for the given panel
		 * 
		 * @param string Format Must be XML, CSV, or HTML, defaults to CSV
		 * @param string LibraryID The library ID of the panel
		 * @param string PanelID The panel ID you want to export
		 * @param string EmbeddedData A comma-separated list of the embedded data keys you want to export. Only required for CSV export. XML includes all embedded data.
		 * @return object The panel members and any requested data
		 */
		function getPanel( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'    => 'CSV',
				'LibraryID' => $this->library,
				'PanelID'   => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'PanelID' ] ) return new WP_Error( 'Missing Parameter: PanelID', __( 'The PanelID parameter was not specified' ), $params );
		
			// fetch and return the response
			return $this->request( 'getPanel', $params );
		}
	
		/**
		 * Deletes a panel
		 * 
		 * @param string Format Must be XML, CSV, or HTML, defaults to CSV
		 * @param string LibraryID The library ID of the panel
		 * @param string PanelID The panel ID you want to export
		 * @return object The results of the deletion process
		 */
		function deletePanel( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'    => 'JSON',
				'LibraryID' => $this->library,
				'PanelID'   => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'PanelID' ] ) return new WP_Error( 'Missing Parameter: PanelID', __( 'The PanelID parameter was not specified' ), $params );
		
			// fetch and return the response
			return $this->request( 'deletePanel', $params );
		}
	
		/**
		 * Gets the number of panel members
		 * 
		 * @param string Format Must be XML or JSON, defaults to JSON
		 * @param string LibraryID The library ID of the panel
		 * @param string PanelID The panel ID you want to get a count for
		 * @return object The number of members of the panel
		 */
		function getPanelMemberCount( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'    => 'JSON',
				'LibraryID' => $this->library,
				'PanelID'   => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'PanelID' ] ) return new WP_Error( 'Missing Parameter: PanelID', __( 'The PanelID parameter was not specified' ), $params );
		
			// fetch and return the response
			return $this->request( 'getPanelMemberCount', $params );
		}
	
		/**
		 * Gets all of the panels in a given library
		 * 
		 * @param string Format Must be XML or JSON, defaults to JSON
		 * @param string LibraryID The library ID of the panel
		 * @return object The panels in the library
		 */
		function getPanels( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'    => 'JSON',
				'LibraryID' => $this->library,
				), $params
			);
		
			// fetch and return the response
			return $this->request( 'getPanels', $params );
		}
	
		/**
		 * Gets a representation of the recipient and their history
		 * 
		 * @param string Format Must be XML or JSON, defaults to JSON
		 * @param string LibraryID The library ID of the panel
		 * @param string RecipientID The recipient ID you want to get
		 * @return object The representation of the recipient
		 */
		function getRecipient( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'      => 'JSON',
				'LibraryID'   => $this->library,
				'RecipientID' => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'RecipientID' ] ) return new WP_Error( 'Missing Parameter: RecipientID', __( 'The RecipientID parameter was not specified' ), $params );
		
			// fetch and return the response
			return $this->request( 'getRecipient', $params );
		}
	
		/**
		 * Imports a csv file as a new panel
		 *
		 * Imports a csv file as a new panel (optionally it can append to a previously made panel) 
		 * into the database and returns the panel id.  The csv file can be posted (there is an 
		 * approximate 8 megabytes limit) or a url can be given to retrieve the file from a remote server.  
		 * The csv file must be comma separated using " for encapsulation.
		 * 
		 * @param string Format         Must be XML or JSON, JSON is default
		 * @param string LibraryID      The library ID into which you want to import the panel
		 * @param string ColumnHeaders  0:1 If headers exist, these can be used when importing embedded data, defaults to 1
		 * @param string Email          The number of the column that contains the email address
		 * @param string URL            (optional) If given, then the CSV file will be downloaded into Qualtrics from this URL
		 * @param string Name           (optional) The name of the panel if creating a new one
		 * @param string PanelID        (optional) If given, indicates the ID of the panel to be updated
		 * @param string FirstName      (optional) The number of the column containing recipients' first names
		 * @param string LastName       (optional) The number of the column containing recipients' last names
		 * @param string ExternalRef    (optional) The number of the column containing recipients' external data reference
		 * @param string Language       (optional) The number of the column containing recipients' languages
		 * @param string AllED          (optional) 0:1 If set to 1, will import all non-used columns as embedded data, and you won't have to set the EmbeddedData parameter
		 * @param string EmbeddedData   (optional) Comma-separated list of column numbers to treat as embedded data
		 * @param string Category       (optional) Sets the category for the panel
		 * @return object Contains the new PanelID, a count of imported recipients, and a count of ignored recipients
		 */
		function importPanel( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'        => 'JSON',
				'LibraryID'     => $this->library,
				'ColumnHeaders' => 1,
				'Email'         => null,
				), $params
			);
		
			// TODO: Figure out how to handle POST file uploads with this method
		
			// check for missing values
			if ( ! $params[ 'Email' ] ) return new WP_Error( 'Missing Parameter: Email', __( 'The Email parameter was not specified' ), $params );
		
			// fetch and return the response
			return $this->request( 'importPanel', $params );
		}
	
		/**
		 * Removes a specified panel member from a specified panel
		 * 
		 * @param string Format Must be XML or JSON, defaults to JSON
		 * @param string LibraryID The library ID of the panel
		 * @param string PanelID The ID of the panel from which the recipient will be removed
		 * @param string RecipientID The recipient ID you want to remove
		 * @return object The representation of the recipient
		 */
		function removeRecipient( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'      => 'JSON',
				'LibraryID'   => $this->library,
				'PanelID'     => null,
				'RecipientID' => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'PanelID'     ] ) return new WP_Error( 'Missing Parameter: PanelID',     __( 'The PanelID parameter was not specified'     ), $params );
			if ( ! $params[ 'RecipientID' ] ) return new WP_Error( 'Missing Parameter: RecipientID', __( 'The RecipientID parameter was not specified' ), $params );
		
			// fetch and return the response
			return $this->request( 'removeRecipient', $params );
		}
	
		/**
		 * Sends a reminder through the Qualtrics mailer to the panel or individual as specified by the parent distribution Id
		 *
		 * @param string Format                     Must be XML or JSON, JSON is default
		 * @param string ParentEmailDistributionID  The parent distribution you are reminding
		 * @param string SendDate                   YYYY-MM-DD hh:mm:ss when you wan the mailing to go out, defaults to now
		 * @param string FromEmail                  The email address from which the email should appear to originate
		 * @param string FromName                   The name the message will appear to be from
		 * @param string Subject                    The subject for the email
		 * @param string MessageID                  The ID of the message from the message library to be sent
		 * @param string LibraryID                  The ID of the library that contains the message to be sent
		 * @return object The email distribution ID and distribution queue id
		 */
		function sendReminder( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'                    => 'JSON',
				'LibraryID'                 => $this->library,
				'ParentEmailDistributionID' => null,
				'SendDate'                  => date( 'Y-m-d H:i:s', time() - ( 60 * 119 ) ), //TODO: investigate if this time shuffling is still necessary...
				'FromEmail'                 => null,
				'FromName'                  => null,
				'Subject'                   => null,
				'MessageID'                 => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'ParentEmailDistributionID' ] ) return new WP_Error( 'Missing Parameter: ParentEmailDistributionID', __( 'The ParentEmailDistributionID parameter was not specified' ), $params );
			if ( ! $params[ 'FromEmail' ] ) return new WP_Error( 'Missing Parameter: FromEmail', __( 'The FromEmail parameter was not specified' ), $params );
			if ( ! $params[ 'FromName'  ] ) return new WP_Error( 'Missing Parameter: FromName',  __( 'The FromName parameter was not specified'  ), $params );
			if ( ! $params[ 'Subject'   ] ) return new WP_Error( 'Missing Parameter: Subject',   __( 'The Subject parameter was not specified'   ), $params );
			if ( ! $params[ 'MessageID' ] ) return new WP_Error( 'Missing Parameter: MessageID', __( 'The MessageID parameter was not specified' ), $params );
		
			// fetch and return the response
			return $this->request( 'sendReminder', $params );
		}
	
		/**
		 * Sends a survey through the Qualtrics mailer to the individual specified
		 *
		 * @param string Format            Must be XML or JSON, JSON is default
		 * @param string SurveyID          The ID of the survey to be sent
		 * @param string SendDate          YYYY-MM-DD hh:mm:ss when you wan the mailing to go out, defaults to now
		 * @param string FromEmail         The email address from which the email should appear to originate
		 * @param string FromName          The name the message will appear to be from
		 * @param string Subject           The subject for the email
		 * @param string MessageID         The ID of the message from the message library to be sent
		 * @param string MessageLibraryID  The ID of the library that contains the message to be sent
		 * @param string PanelID           The ID of the message from the message library to be sent
		 * @param string PanelLibraryID    The ID of the library that contains the message to be sent
		 * @param string RecipientID       The recipient ID of the person to whom to send the survey
		 * @return object The email distribution ID and distribution queue id
		 */
		function sendSurveyToIndividual( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'           => 'JSON',
				'MessageLibraryID' => $this->library,
				'PanelLibraryID'   => $this->library,
				'SurveyID'         => null,
				'SendDate'         => date( 'Y-m-d H:i:s', time() - ( 60 * 119 ) ), //TODO: investigate if this time shuffling is still necessary...
				'FromEmail'        => null,
				'FromName'         => null,
				'Subject'          => null,
				'MessageID'        => null,
				'PanelID'          => null,
				'RecipientID'      => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'SurveyID'    ] ) return new WP_Error( 'Missing Parameter: SurveyID',    __( 'The SurveyID parameter was not specified'    ), $params );
			if ( ! $params[ 'FromEmail'   ] ) return new WP_Error( 'Missing Parameter: FromEmail',   __( 'The FromEmail parameter was not specified'   ), $params );
			if ( ! $params[ 'FromName'    ] ) return new WP_Error( 'Missing Parameter: FromName',    __( 'The FromName parameter was not specified'    ), $params );
			if ( ! $params[ 'Subject'     ] ) return new WP_Error( 'Missing Parameter: Subject',     __( 'The Subject parameter was not specified'     ), $params );
			if ( ! $params[ 'MessageID'   ] ) return new WP_Error( 'Missing Parameter: MessageID',   __( 'The MessageID parameter was not specified'   ), $params );
			if ( ! $params[ 'PanelID'     ] ) return new WP_Error( 'Missing Parameter: PanelID',     __( 'The PanelID parameter was not specified'     ), $params );
			if ( ! $params[ 'RecipientID' ] ) return new WP_Error( 'Missing Parameter: RecipientID', __( 'The RecipientID parameter was not specified' ), $params );
		
			// fetch and return the response
			return $this->request( 'sendSurveyToIndividual', $params );
		}
	
		/**
		 * Sends a survey through the Qualtrics mailer to the panel specified
		 *
		 * @param string Format            Must be XML or JSON, JSON is default
		 * @param string SurveyID          The ID of the survey to be sent
		 * @param string SendDate          YYYY-MM-DD hh:mm:ss when you wan the mailing to go out, defaults to now
		 * @param string FromEmail         The email address from which the email should appear to originate
		 * @param string FromName          The name the message will appear to be from
		 * @param string Subject           The subject for the email
		 * @param string MessageID         The ID of the message from the message library to be sent
		 * @param string MessageLibraryID  The ID of the library that contains the message to be sent
		 * @param string PanelID           The ID of the message from the message library to be sent
		 * @param string PanelLibraryID    The ID of the library that contains the message to be sent
		 * @param string ExpirationDate    (optional) YYYY-MM-DD hh:mm:ss when the survey invitation expires
		 * @param string LinkType          (optional) {Individual,Multiple,Anonymous} Defaults to Individual
		 * @return object The email distribution ID and distribution queue id
		 */
		function sendSurveyToPanel( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'           => 'JSON',
				'MessageLibraryID' => $this->library,
				'PanelLibraryID'   => $this->library,
				'SurveyID'         => null,
				'SendDate'         => date( 'Y-m-d H:i:s', time() - ( 60 * 119 ) ), //TODO: investigate if this time shuffling is still necessary...
				'FromEmail'        => null,
				'FromName'         => null,
				'Subject'          => null,
				'MessageID'        => null,
				'PanelID'          => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'SurveyID'  ] ) return new WP_Error( 'Missing Parameter: SurveyID',  __( 'The SurveyID parameter was not specified'  ), $params );
			if ( ! $params[ 'FromEmail' ] ) return new WP_Error( 'Missing Parameter: FromEmail', __( 'The FromEmail parameter was not specified' ), $params );
			if ( ! $params[ 'FromName'  ] ) return new WP_Error( 'Missing Parameter: FromName',  __( 'The FromName parameter was not specified'  ), $params );
			if ( ! $params[ 'Subject'   ] ) return new WP_Error( 'Missing Parameter: Subject',   __( 'The Subject parameter was not specified'   ), $params );
			if ( ! $params[ 'MessageID' ] ) return new WP_Error( 'Missing Parameter: MessageID', __( 'The MessageID parameter was not specified' ), $params );
			if ( ! $params[ 'PanelID'   ] ) return new WP_Error( 'Missing Parameter: PanelID',   __( 'The PanelID parameter was not specified'   ), $params );
		
			// fetch and return the response
			return $this->request( 'sendSurveyToPanel', $params );
		}
	
		/**
		 * Updates the recipient’s data—any value not specified will be left alone and not updated
		 *
		 * @param string Format           Must be XML or JSON, defaults to JSON
		 * @param string LibraryID        The LibraryID of the user who owns the panel
		 * @param string RecipientID      The ID of the recipient whose data will be updated
		 * @param string FirstName        First name of the new recipient
		 * @param string LastName         Last name of the new recipient
		 * @param string Email            Email address of the new recipient
		 * @param string ExternalDataRef  (optional) A value to store in the external data ref for the user (should default to the WordPress username)
		 * @param string Language         (optional) The language code for the user, e.g. EN, defaults to EN
		 * @param string ED[***]          (optional) An embedded data value, there can be many, and takes the form e.g. ED[skypeID]=mcbenton
		 * @return object Returns true on success or WP_Error on failure
		 **/
		function updateRecipient( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'      => 'JSON',
				'LibraryID'   => $this->library,
				'RecipientID' => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'RecipientID' ] ) return new WP_Error( 'Missing Parameter: RecipientID', __( 'The RecipientID parameter was not specified' ), $params );
		
			// fetch the response
			$response = $this->request( 'updateRecipient', $params );
			return ( ! is_wp_error( $response ) ) ? true : $response;
		}
	
		/**
		 * Returns all of the response data for a survey in the original (legacy) data format
		 *
		 * @param string Format             {XML,CSV,HTML} default is XML
		 * @param string SurveyID           The ID of the survey you'll be getting responses for
		 * @param string LastResponseID     (optional) When specified it will export all responses after the ID specified
		 * @param string Limit              (optional) Max number of responses to return
		 * @param string ResponseID         (optional) ID of an individual response to be returned
		 * @param string ResponseSetID      (optional) ID of a response set to return, if unspecified returns default response set
		 * @param string SubgroupID         (optional) Subgroup you want to download data for
		 * @param string StartDate          (optional) YYYY-MM-DD hh:mm:ss Date the responses must be after
		 * @param string EndDate            (optional) YYYY-MM-DD hh:mm:ss Date the responses must be before
		 * @param string Questions          (optional) Comma-separated list of question ids
		 * @param string Labels             (optional) If 1 (true) the label for choices and answers will be used, not the ID. Default is 0
		 * @param string ExportTags         (optional) If 1 (true) the export tags will be used rather than the V labels. Default is 0
		 * @param string ExportQuestionIDs  (optional) If 1 (true) the internal question IDs will be used rather than export tags or V labels. Default is 0
		 * @param string LocalTime          (optional) If 1 (true) the StartDate and EndDate will be exported using the specified user's local time zone. Default is 1
		 * @param string UnansweredRecode   (optional) The recode value for seen but unanswered questions. If not specified a blank value is put in for these questions.
		 * @param string PanelID            (optional) If supplied it will only get the results for the members of the panel specified
		 * @return object An object representing the responses for the specified panel members
		 */
		function getLegacyResponseData( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format'   => 'XML',
				'SurveyID' => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'SurveyID' ] ) return new WP_Error( 'Missing Parameter: SurveyID', __( 'The SurveyID parameter was not specified' ), $params );
		
			// fetch the response
			return $this->request( 'getLegacyResponseData', $params );
		}
	
		/**
		 * This request returns an xml export of the survey. NOTE: Custom response format!
		 *
		 * @param string SurveyID     The ID of the survey to be exported
		 * @param string ExportLogic  If 1 (true) it will export the logic. EXPERIMENTAL
		 * @return object  On object representing the survey.
		 */
		function getSurvey( $params = array() )	{
			// set the parameters for this request
			$params = array_merge( array(
				'SurveyID' => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'SurveyID' ] ) return new WP_Error( 'Missing Parameter: SurveyID', __( 'The SurveyID parameter was not specified' ), $params );
		
			// fetch the response
			// TODO: This function returns data in a non-standard format.  Needs to be handled specially.
			return $this->request( 'getSurvey', $params );
		}
	
		/**
		 * This request returns the name of the survey as well as some addition information
		 *
		 * @param string Format    {XML,JSON}, default is JSON
		 * @param string SurveyID  ID of the survey
		 * @param string Language  (optional) Language in which to export the survey
		 * @return object Represents information related to the survey including the name
		 */
		function getSurveyName( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'SurveyID' => null,
				), $params
			);
		
			// check for missing values
			if ( ! $params[ 'SurveyID' ] ) return new WP_Error( 'Missing Parameter: SurveyID', __( 'The SurveyID parameter was not specified' ), $params );
		
			// fetch the response
			return $this->request( 'getSurveyName', $params );
		}
	
		/**
		 * This request returns a list of all the surveys for the user
		 *
		 * @param string Format  {XML,JSON} Default is JSON
		 * @return object  A list of surveys available to the user
		 */
		function getSurveys( $params = array() ) {
			// set the parameters for this request
			$params = array_merge( array(
				'Format' => 'JSON',
				), $params
			);
		
			// fetch the response
			return $this->request( 'getSurveys', $params );
		}
	}

}