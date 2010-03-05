<?php
/**
* Domain Validation Component for CakePHP
*
* @package	DomainValidation Component
* @author	Author: Chuck Burgess <cdburgess@gmail.com>
* @license	License: http://creativecommons.org/licenses/by-sa/3.0/
* @copyright	Copyright: (c)2010 Chuck Burgess. All Rights Reserved.
*
* Please feel free to visit my blog http://blogchuck.com
* 
*/
class DomainValidationComponent extends Object
{
	/**
	 * Name of the component - permits use in other components as required
	 */
	var $name = 'DomainValidation';
	
	/**
	 *  URL to the tld list
	 *
	 *  @param string
	 *  @access private
	 */
	private $tld_url = 'http://data.iana.org/TLD/tlds-alpha-by-domain.txt';

	/**
	 * TLD File Variable
	 *
	 * Writes the file to the webroot directory by default
	 * 
	 * @filesource http://data.iana.org/TLD/tlds-alpha-by-domain.txt
	 * @param string
	 * @access private
	 */
	private $tld_list = 'tlds-alpha-by-domain.txt';
	
	/**
	 * URL to get this servers public IP address
	 *
	 * @filesource http://www.whatismyip.com/automation/n09230945.asp
	 * @param string
	 * @access private
	 */
	private $ip_url = 'http://www.whatismyip.com/automation/n09230945.asp';
	
	/**
	 * File Freshness Threshhold
	 * 
	 * set the length of time (default is 30)
	 *
	 * @param string $file_age
	 * @access private
	 */
	private $file_age = 30;
	
	/**
	 * Default Invalid Message
	 *
	 * @param string
	 * @access private
	 */
	private $invalid = 'The domain name is not RFC compliant.';
	
	/**
	 * Default timezone for date functions
	 * 
	 * the timezone for this server
	 *
	 * @param string
	 * @access private
	 */
	private $timezone = 'America/Denver';
	
	/**
	 * Status of the validation
	 *
	 * After the return status comes back, you can call $this->DomainValidation->status for detailed information
	 *
	 * @param array
	 * @access public
	 */
	public $status = array();

	/**
	 * DNS servers returned on DNS verification
	 * 
	 * After the DNS verification, dns server information is accessible via $this->DomainValidation->servers
	 *
	 * @param array
	 * @access public
	 */
	public $servers = array();
		
	/**
	 * Validate Domain variable
	 *
	 * @param string
	 * @access public
	 */
	public $valid_domain;
	
	/**
	 * Check the status of the TLD file
	 * 
	 * Check the Current Freshness of the local TLD list to see if we need to download another copy
	 *
	 * @static
	 * @access private
	 */
	function _tld_file_status_check()
	{					   
		// this is required for some versions of PHP
		date_default_timezone_set($this->timezone);
		
		// does the file does not exist or does not fall within freshness threshold
		if(!file_exists($this->tld_list) or (time() - filemtime($this->tld_list)) > 60 * 60 * 24 * $this->file_age)
		{
			// if we can pull the  file
			if($tld_file = file_get_contents($this->tld_url))
			{
				// save the file over the top of the existing tld file
				file_put_contents($this->tld_list, $tld_file);
			}
		}
	}

	
	/**
	 * Check Domain
	 *
	 * @param string $domain  The name of the domain to be validated
	 * @access private
	 * @return bool  {true|false}
	 * @uses $this->_validTLD()  	List of valid TLDs created from the TLD file
	 * @uses $this->validateIP() 	Validates an IP address (if sent as domain name)
	 */	
	function _checkDomain($domain)
	{
		// domain name (with all of it's nodes) must exist AND
		// domain must be between 4 and 256 characters long to be valid (including .tld)
		if(strlen($domain) > 256 or strlen($domain) < 4)
		{
			$this->status = array(false, 'INVALID', $this->invalid, 'The domain cannot be bigger than 256 or less than 4');
			return false;											
		}
		
		// check to see if this might be an IP address
		if(ip2long($domain))
		{
			// validate the IP
			return $this->validateIP($domain);
		} else {
		
			// split on each . to get the nodes
			$nodes = split('\.', $domain);
			
			// process each node
			foreach($nodes as $node)
			{
				// each node is limited to 63 characters
				if(strlen($node) > 63)
				{
					$this->status = array(false, 'INVALID', $this->invalid, 'Each node in the domain can only be 63 characters long');
					return false;											
				}
				
				// each node is limited to specific characters and structure
				if(!preg_match('/^[a-z\d]*(?:([a-z\d-]*[a-z\d]))$/i', $node))
				{
					$this->status = array(false, 'INVALID', $this->invalid, 'The domain name contains illegal charaters or is formatted incorrectly.');
					return false;											
				}
			}
			
			// build regex list of valid TLDs
			$tld = $this->_validTLD(); 	
			
			// make sure the domain name has a valid TLD
			if(!preg_match('/^('.$tld.')$/i', $node, $match))
			{
				$this->status = array(false, 'INVALID', $this->invalid, 'The domain does not have a valid TLD.');
				return false;											
			}
		
			// made it this far, it must be valid
			$this->valid_domain = $domain;
			return true;
		}
	}
	
	
	/**
	 * Valid TLD
	 * 
	 * Builds a list of valid TLDs to check domain against. The file is pulled from http://data.iana.org/TLD/tlds-alpha-by-domain.txt
	 * In order for the TLD list to stay up to date, _tld_files_status_check is called
	 *
	 * @static
	 * @uses $this->_tld_file_status_check() To check the freshness of the file
	 * @return array An array of all of the valid TLDs
	 */
	function _validTLD()
	{
		# first do a quick check on the TLD file
		$this->_tld_file_status_check();
		
		# foreach item in the tild list (as read into an array)
		foreach(file($this->tld_list) as $tld){
			# if the line does NOT contain NON-ALPHA characters
			if (preg_match('/^[\w]+$/', $tld)){
				# add it to the tld regex for inclusion
				$tld_regex .= preg_replace('/\W/', '', $tld)."|";
			}
		}
		# strip off the last | so the regex will not break
		$tld_regex = substr_replace($tld_regex, '', -1);
		
		#return the valid tld regex string
		return $tld_regex;
	}
	 
	
	/**
	 * Validate
	 *
	 * This will validate the domain to RFC sepcifcations. It also has the option to verify the 
	 * domain via DNS.
	 *
	 * @uses $this->_checkDomain()		Validate RFC compliance of domain passed
	 * @param string $domain  	The domain to validate
	 * @param bool {true|false}	Validate the DNS record?
	 * @return bool {true|false}	If the domain passes the validations
	 * @access public
	 * @global array $this->DomainValidation->servers	After validation of DNS, all servers are accessible via this public array	
	 */
	function validate($domain, $verify = false)
	{
		// check to make sure the domain is valid
		if(!$this->_checkDomain($domain))
		{
			// domain must be RFC compliant (status set)
			return false;
		}
		
		// if set, check to make sure an actual DNS record exists
		if($verify)
		{
			// make sure the needed function exists
			if(!function_exists('checkdnsrr')) {
				$this->status = array(false, 'NOT TESTED', 'Function needed (checkdnsrr) does not exist.');
				return false;
			}
			
			// if domain is an IP we need to try to get the domain name
			if(ip2long($domain))
			{
				$this->status = array(false, 'NOT TESTED', "Cannot determine domain from IP ($domain).", null);	
				return false;
			}
			
			// make sure we only have the last two nodes of the domain name
			$nodes = split("\.", $this->valid_domain);
			$top_domain = $nodes[count($nodes)-2].'.'.$nodes[count($nodes)-1];
			
			// get the DNS records for the server
			if(!dns_get_mx($top_domain, $this->servers))
			{
				$this->status = array(false, 'NOT TESTED', "No MX servers were returned for domain ($domain).", null);	
				return false;
			} 
			
			// must return servers or verify failed
			if(empty($this->servers))
			{
				$this->status = array(false, 'NOT TESTED', "No mailservers were found in the DNS for domain ($domain).", null);
				return false;
			}
		}
		
		return true;
	 }
	 
	/**
	 * Validate an IP Address
	 * 
	 * Allows filters (private and reserved) 
	 *
	 * @param string $ip 				The IP address to validate
	 * @param string $filter {all|private|reserved}	Check the validity of the IP based on the filters (i.e. reserved, public, private)
	 *
	 */
	function validateIP($ip, $filter = false)
	{
		// validate the IP address is valid
		if(!filter_var($ip, FILTER_VALIDATE_IP))
		{
			$this->status = array(false, 'INVALID', $this->invalid, "IP address ($ip) is invalid.");
			return false;
		}
				
		// filter private IP range
		if($filter == 'private' and !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE))
		{
			$this->status = array(false, 'INVALID', $this->invalid, "IP address ($ip) is in a private range and does not pass the filter setting.");
			return false;
		}
		
		// filter for reserved IP range
		if($filter == 'reserved' and !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE))
		{
			$this->status = array(false, 'INVALID', $this->invalid, "IP address ($ip) is in a reserved range and does not pass the filter setting.");
			return false;
		}
		
		// filter for either private or reserved IP range
		if($filter == 'all' and !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) and !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE))
		{
			$this->status = array(false, 'INVALID', $this->invalid, "IP address ($ip) is in a provate or reserved range and does not pass the filter setting.");
			return false;
		}
		
		return true;
	}
	
}
?>