<?php
/*
 * @package	GoogleMaps Component
 * @author	Author: Chuck Burgess <cdburgess@gmail.com>
 * @license	License: http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright	Copyright: (c)2010 Chuck Burgess. All Rights Reserved.
 *
 * Please feel free to visit my blog http://blogchuck.com
 *
 * @see http://www.google.com/apis/maps/signup.html to get your own API key
 */
class GoogleMapsComponent extends Object
{
	/**
	 * Name of the component - permits use in other components as required
	 */
	var $name = 'GoogleMaps';
	
	/**
	 * Google API Key required to access Google API
	 *
	 * @var string
	 * @access private
	 */
	private $api_key = 'your_key_here';
	
	/**
	 * Googl Maps base API URL
	 *
	 * @var string
	 * @access private
	 */
	private $base_url = "http://maps.google.com/maps/geo";
	
	/**
	* Get the geo coordinates for a given address.
	*
	* @param array $address		array containing address, city, state[, zip]
	* @return array 		containing latitude, longitude
	* @access public
	*/
	function geo($address = null){
		
		// must have a valid address with address, city, state minimum
		if($address == null or count($address) < 3)
		{
			// valid information was not passed
			return null;
		}
		
		// setup the submit fields array based on the address
		$fields = array();
		
		// urlencode the values of the array being passed
		$fields[] = urlencode($address['address']);
		$fields[] = urlencode($address['city']);
		$fields[] = urlencode($address['state']);
		if($address['zipcode'])
		{
			$fields[] = urlencode($address['zipcode']);
		}
		
		// build the url query for the maps API
		$fields['q'] = implode('+', $fields);
		$fields['output'] = 'csv';
		$fields['key'] = $this->api_key;
		
		// get the geocode data
		$result = $this->get_content($fields);
		
		// check the result from the API
		// If status is 200, we are OK
		if($result[0] == '200')
		{ 
			return array('latitude'=>$result[2],'longitude'=>$result[3]);
		}
			
		// if status is 602, there was a problem, we can attempt a retry
		if($result[0]=='602') 
		{
			// if there was a zipcode provided
			if(isset($address['zipcode']) and !empty($address['zipcode']))
			{
				// remove the zipcode
				unset($address['zipcode']);
			
				// retry the process
				return $this->geo($address);
			}
		}	 
		
		// if we failed, return NULL
		return null;
	}
	
	/**
	 * Get contents
	 *
	 * @param string $fields		The URL query string for the maps API
	 * @access private
	 * @uses HTTPSockets Core Utility
	 */
	function get_content($fields)
	{
		// use HttpSocket Core Utility
		App::Import('Core', 'HttpSocket');
		
		// open a new Socket
		$this->socket = new HttpSocket();
		
		// GET the results from Google
		$result = $this->socket->get($this->base_url, $fields);
		
		// return the results as an array
		return explode(',', $result);
	}	
}
?>