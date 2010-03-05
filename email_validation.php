<?php
/**
 * Email Validation Component for CakePHP
 *
 * @package	EmailValidation Component
 * @author	Author: Chuck Burgess <cdburgess@gmail.com>
 * @license	License: http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright	Copyright: (c)2010 Chuck Burgess. All Rights Reserved.
 * @uses DomainValidation component
 * 
 * Please feel free to visit my blog http://blogchuck.com
 * 
 */
class EmailValidationComponent extends Object
{
	/**
	 * Name of the component - permits use in other components as required
	 */
	var $name = 'EmailValidation';
	
	/**
	 * Uses the DomainValidation component
	 */
	var $components = array('DomainValidation');
	
	/**
	 * Error Messages
	 *
	 * @param array
	 * @access private
	 */
	private $error = array(
		'invalid' 	=> 'The email address is not RFC compliant.',
		'error' 	=> 'There was an error with your request.',
		'not_tested' 	=> 'There was something wrong with the system.',
		'valid' 	=> 'Email meets the RFC specifications.',
		'verify'	=> 'Email meets the RFC specification and has been verified on the email server.',
	);
	
	/**
	 * Status of the validation
	 *
	 * After the return status comes back, you can call $this->EmailValidation->status for detailed information
	 *
	 * @param array
	 * @access public
	 */
	public $status = array();
	
	/**
	 * Check the email address for RFC compliant components
	 *
	 * @param string $email 			Email to test
	 * @param bool {true|false}			Verify the DNS of the domain?
	 * @uses $this->DomainValidation->validate()	Uses the domain component to validate the domain
	 * @uses $this->_validateLocalpart()		Uses the localpart validation method
	 * @access private
	 */	
	function _checkEmail($email, $verify = false)
	{
		// email is limited to 256 characters long
		if(strlen($email) > 256 )
		{
			$this->status = array(false, 'INVALID', $this->error['invalid'], 'Email length is greater than 256 charcters.');
			return false;	// cannot be more than 256 characters
		}
		
		// get the exact position of the @ symbol 
		$atIndex = strrpos($email,'@');	
		
		// it must have one @ symbol
		if ($atIndex === false)										
		{
			$this->status = array(false, 'INVALID', $this->error['invalid'], 'Must have one @ symbol to be valid.');
			return false;											
		}
		
		// it must have a local part
		if($atIndex === 0)											
		{
			$this->status = array(false, 'INVALID', $this->error['invalid'], 'Local part of email is missing.');
			return false;											
		}
		
		// it must have a domain name
		if($atIndex === $emailLength - 1)      				 		
		{
			$this->status = array(false, 'INVALID', $this->error['invalid'], 'Domain name is missing from email.');
			return false;
		}
		
		// split out the domain and the local part
		$local_part     = substr($email, 0, $atIndex);				// extract the local part of the email
		$domain         = substr($email, $atIndex + 1);				// extract the domain
		
		if(!$this->DomainValidation->validate($domain, $verify))		return false;	// requires a valid domain (status already set)
		if(!$this->_validateLocalpart($local_part))		return false;	// requires a valid local part (status already set)
		
		return true;
	}
	
	/**
	* Validate Local Part of Email
	* 
	* RFC 5322 compliant. Does not validate obsolete email addresses.
	*
	* @param string $local		The local part of the email address to validate
	* @access private
	*/
	function _validateLocalpart($local)
	{
		// must be between 1 and 64 characters long
		if(strlen($local) > 64 or strlen($local) < 1)						
		{
			$this->status = array(false, 'INVALID', $this->error['invalid'], 'Local part of email cannot exceed 64 charcters.');
			return false;
		}
		
		// cannot start or end with a .
		if($local[0] == '.' || $local[$localLen-1] == '.')
		{
			$this->status = array(false, 'INVALID', $this->error['invalid'], 'Local part of email cannot start or end with a dot.');
			return false;
		}
		
		// cannot contain two consecutive dots ..
		if(preg_match('/\.\./', $local))
		{
			$this->status = array(false, 'INVALID', $this->error['invalid'], 'Local part of email cannot contain two consecutive dots.');
			return false;
		}
		
		// cannot contain illegal characters (on the left side of a +)
		if(!preg_match("/^[\w!\#\$\%\&\'\*\+\-\/\=\?\^\`\.\{\|\}\~]+$/iD", $local))
		{
			$this->status = array(false, 'INVALID', $this->error['invalid'], 'Local part of email cannot contain illegal characters.');
			return false;
		}
		
		// valid local part
		return true;
	}
	
	/**
	 * Validate the email.
	 * Passing a second parameter of true will also verify via smtp
	 *
	 * @param string $email			The email to validate
	 * @param bool {true|false}		Validate via SMTP (and Domain via DNS)
	 * @param string $helo			This SMTP server sending the request
	 * @access public
	 * @uses $this->_checkEmail()
	 * =============================================================================================================
	 * Note: The $helo address may need to pass certain tests in order to communicate with various mail servers. 
	 * There may be policies on the email server that will prevent you from running checks based on specific
	 * policies. For example, Yahoo! uses spamhaus (spamhaus.org) to check the status of the IP address of your
	 * sending server. (http://www.spamhaus.org/query/bl?ip=192.168.0.1). If the IP address of your server is contained
	 * in any of their lists (SBL, PBL, XBL), then the request is immediately rejected and there is no way to get
	 * a response from the server as to the validity of the email address. To better understand how spamhaus works,
	 * see http://www.spamhaus.org/dnsbl_function.html.
	 * =============================================================================================================
	 *
	 * To avoid inacurate INVALID responses, this function will indicate how it failed. There are multiple
	 * reasons the test could fail:
	 * - invalid email address (no @ or determinable domain)
	 * - cannot connect to the internet (no test was actually performed)
	 * - sent request as if valid, but the server indicates the email is invalid
	 */
	function validate($email, $verify = false, $helo = '')
	{
		
		// first validate the email address
		if(!$this->_checkEmail($email, $verify))
		{
			return false; 	// email address must be rfc compliant to pass (status is already set)
		}
		
		// are we going to verify the address vis SMTP too?
		if($verify == true)												
		{
			// set the helo domain to this host name or IP
			if(!$helo)
			{
				$this->status = array(false, 'NOT TESTED', "Valid host name required.", 'You need to pass the $helo option to identify the domain to use.');
				return false;
			}
			
			// set the static variable for the valid domain
			$domain = $this->DomainValidation->valid_domain;
			
			// get the list of valid servers from the domains class
			if(empty($this->DomainValidation->servers))
			{
				$this->status = array(false, 'NOT TESTED', "No mailservers were found in the DNS for $domain .", null);
				return false;	
			}
			
			// this email address is required to exist on the server if the server is compliant
			$postmaster = 'postmaster@'.$this->DomainValidation->valid_domain;
			
			// SMTP Commands
			$smtp_commands = array(
				"EHLO $helo",
				"MAIL FROM: <$postmaster>",
				"RCPT TO: <$email>",
				"QUIT"
			);
			
			// Process each mailserver until address is verified
			for($n=0; $n < count($this->DomainValidation->servers); $n++)
			{
				$errno = 0; $errstr = 0;											// reset error data for each connection attempt
				
				# Try to open up socket
				if($sock = @fsockopen($this->DomainValidation->servers[$n], 25, $errno , $errstr, $connect_timeout)) {
					
					$response = fgets($sock);										// get the initial response from the socket
					
					stream_set_timeout($sock, 30);									// give the stream 30 seconds to verify address
					$meta = stream_get_meta_data($sock);							// get the header/meta data from the pointers
					
					// make sure connection was initiated / no timeout
					if(!$meta['timed_out'] && !preg_match('/^2\d\d[ -]/', $response)) {				// did we connect to the server?
						
						// set the status in the case this is the last server tried
						$this->status = array(false, 'NOT TESTED', $not_tested, 'The fsockopen returned an error: $this->DomainValidation->servers[$n] said: '.$response);
						break;																		// skip to the next server
					}
						
					// check the response of each command
					foreach($smtp_commands as $cmd) {
						if(!fputs($sock, "$cmd\r\n"))
						{
							$this->status = array(false, 'NOT TESTED', "The email is valid, but none of the mailservers listed for $domain could not be contacted.", $errstr);
							return false;
						}
						
						$response = fgets($sock, 4096);				// get server response
						
						// error if thee is any response outside of 250 (meaning success)
						if(!$meta['timed_out'] && !preg_match('/^250[ -]/', $response)) {
						
							// EHLO command not accepted
							if($cmd == $cmds[0]){
								$this->status = array(false, 'INVALID', 'EHLO: Email meets the RFC specifications but is not valid on the server or cannot be verified.', $response);
							} 
							
							// VRFY returned error or this domain cannot verify forwarder
							if($cmd == $cmds[1]){
								$this->status = array(false, 'INVALID', 'MAIL FROM: Email meets the RFC specifications but is not valid on the server or cannot be verified.', $response);
							}
							
							// VRFY returned error or this domain cannot verify forwarder
							if($cmd == $cmds[2]){
								$this->status = array(false, 'INVALID', 'RCPT TO: Email meets the RFC specifications but is not valid on the server or cannot be verified.', $response);
							}
							
							// we already got an error on this server, so try the next in the loop
							break 2;
						} 
					}
					
					// close the connection
					fclose($sock);

					// if we get here, then the email is validated AND verified 
					$this->status =  array(true, 'VALID', $this->error['verify'], $response);
					return true;
					
				} else {
					$this->status = array(false, 'NOT TESTED', "The email is valid, but none of the mailservers listed for $domain could be contacted.", $errstr);
					return false;
				}
			} 
			// if we get here, we failed for some reason
			$this->status = array(false, 'NOT TESTED', "The email is valid, but none of the mailservers listed for $domain could be contacted.", $errstr);
			return false;
		} else {
			// if we get here, then the email is validated AND verified
			$this->status =  array(true, 'VALID', $this->error['valid'], $response);
			return true;
		}
	}
}
?>