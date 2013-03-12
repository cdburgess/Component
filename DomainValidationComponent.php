<?php
/**
 * Domain Validation Component for CakePHP
 *
 * @subpackage Components
 *
 * @author	Author: Chuck Burgess <cdburgess@gmail.com>
 * @license	License: http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright	Copyright: (c)2010 Chuck Burgess. All Rights Reserved.
 *
 * Please feel free to visit my blog http://blogchuck.com
 *
 */
App::uses('Component', 'Controller');
App::uses('Folder', 'Utility');
class DomainValidationComponent extends Component {

/**
 * URL to the tld list
 *
 * @param string
 * @access private
 */
	private $__tldUrl = 'http://data.iana.org/TLD/tlds-alpha-by-domain.txt';

/**
 * TLD File Variable
 *
 * Writes the file to the webroot directory by default
 *
 * @filesource http://data.iana.org/TLD/tlds-alpha-by-domain.txt
 * @param string
 * @access private
 */
	private $__tldList = 'tlds-alpha-by-domain.txt';

/**
 * File Freshness Threshhold
 *
 * set the length of time (default is 30)
 *
 * @param string $__fileAge
 * @access private
 */
	private $__fileAge = 30;

/**
 * Default timezone for date functions
 *
 * the timezone for this server
 *
 * @param string
 * @access private
 */
	private $__timezone = 'America/Denver';

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
 */
	public function validate($domain, $verify = false) {
		if (!$domain) {
			return false;
		}
		//$this->__tldList = TMP . $this->__tldList;
		$url = $this->urlParts($domain);
		if (!$this->_checkDomain($url['host'])) {
			return false;
		}
		if ($verify) {
			$domain = $this->domainOnly($domain);
			$handle = curl_init($domain);
			curl_setopt($handle, CURLOPT_HEADER, true);
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($handle, CURLOPT_NOBODY, true);
			curl_setopt($handle, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($handle, CURLOPT_TIMEOUT, 5);
			curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($handle, CURLOPT_FAILONERROR, true);

			$response = curl_exec($handle);
			$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
			curl_close($handle);
			if (!$httpCode) {
				return false;
			}
		}
		return true;
	}

/**
 * URL Parts
 *
 * Take a supplied url and build its parts. If there is no scheme, add the scheme so the parts
 * will parse correctly, then remove the scheme before sending back the parts. This will preserve
 * the raw url as it was passed. We will also remove any unwanted spaces from the end of each
 * part of the url.
 *
 * @param string $url The url to build the parts for.
 * @return array $parts The parts of the URL
 * @access public
 * @throws NotFoundException A url must be passed to urlParts
 **/
	public function urlParts($url = null) {
		if (!$url) {
			throw new NotFoundException(__('URL was not passed (urlParts).'));
		}
		$parts = parse_url($url);
		if (!isset($parts['scheme']) || empty($parts['scheme'])) {
			$url = 'http' . '://' . $url;
			$parts = parse_url($url);
			unset($parts['scheme']);
		}
		$parts = array_map('trim', $parts);
		return $parts;
	}

/**
 * hasScheme
 *
 * Parse a url name and check if there is a scheme associated with the url.
 * @return boolean, True if the URL has a scheme, else false.
 * @throws NotFoundException A url must be passed to addScheme
 **/
	public function hasScheme($url = null) {
		if (!$url) {
			throw new NotFoundException(__('URL was not passed (hasScheme).'));
		}
		$parts = $this->urlParts($url);
		if (!empty($parts['scheme'])) {
			return true;
		}
		return false;
	}

/**
 * addScheme
 *
 * Add a scheme to the url.
 * @return string $url The url with the appropriate scheme attached
 * @throws NotFoundException A url must be passed to addScheme
 * @todo : validate the scheme $this->_validateScheme($scheme);
 **/
	public function addScheme($url, $scheme = 'http') {
		if (!$url) {
			throw new NotFoundException(__('URL was not passed (addScheme).'));
		}
		if ($this->hasScheme($url)) {
			$url = $this->removeScheme($url);
		}
		return $scheme . '://' . $url;
	}

/**
 * removeScheme
 *
 * Remove the scheme from the url.
 *
 * @return string $url The url you want to remove the scheme from.
 * @access public
 * @throws NotFoundException A url must be passed to addScheme
 **/
	public function removeScheme($url = null) {
		if (!$url) {
			throw new NotFoundException(__('URL was not passed (removeScheme).'));
		}
		if ($this->hasScheme($url)) {
			$parts = $this->urlParts($url);
			$replacement = '/^' . $parts['scheme'] . '\:\/\//';
			$url = preg_replace($replacement, '', $url);
		}
		return $url;
	}

/**
 * Domain Only
 *
 * Disect a URL and return the domain name only.
 *
 * @param string $url The url to get the domain name from.
 * @return string $domain The Domain Name of the URL
 * @access public
 * @throws NotFoundException A domain must be passed to addScheme
 **/
	public function domainOnly($url = null) {
		if (!$url) {
			throw new NotFoundException(__('URL was not passed or is invalid (domainOnly).'));
		}
		$parts = $this->urlParts($url);
		$username = '/';
		if ($this->isTemporaryUrl($url)) {
			$username = $this->getUsernameFromTempUrl($url);
		}
		$port = null;
		if (isset($parts['port'])) {
			$port = ':' . $parts['port'];
		}
		if (isset($parts['scheme']) && !empty($parts['scheme'])) {
			$parts['host'] = $parts['scheme'] . '://' . $parts['host'];
		}
		return $parts['host'] . $port . $username;
	}

/**
 * getUsernameFromTempUrl
 *
 * Get the username from the temporary URL. This is determined by the beginning of the PATH of
 * a given parsed URL that begins with /~. The username portion is the part between /~ and the
 * next /. We make sure there is an ending slash on the path before we check the results.
 *
 * Example: /~USERNAME_HERE/
 *
 * @param  string $url The url to check
 * @return string $username The username (/~USERNAME/)
 * @throws NotFoundException A url must be passed
 **/
	public function getUsernameFromTempUrl($url = null) {
		if (!$url) {
			throw new NotFoundException(__('URL was not passed or is invalid (getUsernameFromTempUrl).'));
		}
		if (!$this->isTemporaryUrl($url)) {
			return false;
		}
		$parts = $this->urlParts($url);
		if (!preg_match('/\/$/', $parts['path'])) {
			$parts['path'] .= '/';
		}
		preg_match('/^\/\~(.*?)\//', $parts['path'], $matches);
		return $matches[0];
	}

/**
 * hasWww
 *
 * Check the first node of the domain. If it contains www return true. If not return false.
 *
 * @param string $url The url to check the nodes for.
 * @return bool
 * @throws NotFoundException A URL must be passed
 * @access public
 **/
	public function hasWww($url = null) {
		if (!$url) {
			throw new NotFoundException(__('Domain was not passed (hasWww).'));
		}
		$parts = $this->urlParts($url);
		$nodes = explode('.', $parts['host']);
		if (strtolower($nodes[0]) == 'www') {
			return true;
		}
		return false;
	}

/**
 * addWww
 *
 * We only add www to domains, not sub domains. We need to remove all valid TLDs from the
 * end of the domain. If there are no dots (.) left in the domain, we can add www. to the
 * beginning and return the url.
 *
 * @param  string $url The url to validate and add the www to
 * @return string $url The updated url (if www can in fact be added)
 * @throws NotFoundException A url must be passed
 * @access  public
 **/
	public function addWww($url = null) {
		if (!$url) {
			throw new NotFoundException(__('URL was not passed or is invalid (addWww).'));
		}
		if ($this->isIp($url) || $this->hasWww($url) || $this->isSubdomain($url)) {
			return $url;
		}
		$parts = $this->urlParts($url);
		$parts['host'] = 'www.' . $parts['host'];
		return $this->_unparseUrl($parts);
	}

/**
 * removeWww
 *
 * Remove the www from the url.
 *
 * @param  string $url The url remove the www from
 * @return string $url The updated url (or the original if there is no WWW)
 * @throws NotFoundException A url must be passed
 * @access  public
 **/
	public function removeWww($url = null) {
		if (!$url) {
			throw new NotFoundException(__('URL was not passed or is invalid (addWww).'));
		}
		if ($this->isIp($url) || !$this->hasWww($url) || $this->isSubdomain($url)) {
			return $url;
		}
		$parts = $this->urlParts($url);
		$nodes = explode('.', $parts['host']);
		if (strtolower($nodes[0]) == 'www') {
			unset($nodes[0]);
			$parts['host'] = implode('.', $nodes);
		}
		return $this->_unparseUrl($parts);
	}

/**
 * isIp
 *
 * Is the domain an IP address?
 *
 * @param string $url The URL to check
 * @return boolean, If it is an IP address return true, else false
 * @throws NotFoundException A URL must be passed
 **/
	public function isIp($url = null) {
		if (!$url) {
			throw new NotFoundException(__('Domain was not passed (isIp).'));
		}
		$parts = $this->urlParts($url);
		if (filter_var($parts['host'], FILTER_VALIDATE_IP)) {
			return true;
		}
		return false;
	}

/**
 * isTemporaryUrl
 *
 * Check to see if the URL is a temporary URL. To determine if a URL is a temporary
 * url, we need to check the beginning of the $url['path'] for a tilde (~). If it
 * has one, then it is a temporary URL.
 *
 * @param  string $url The url to check
 * @return boolean True if the url is a temporary url, else false
 * @access public
 * @throws NotFoundException A url must be passed to
 **/
	public function isTemporaryUrl($url = null) {
		if (!$url) {
			throw new NotFoundException(__('URL was not passed (isTemporaryUrl).'));
		}
		$parts = $this->urlParts($url);
		if (isset($parts['path']) && preg_match('/^\/\~/', $parts['path'])) {
			return true;
		}
		return false;
	}

/**
 * isSubdomain
 *
 * We check a domain to see if it is a subdomain. We remove all valid TLDs from the domain
 * name and we also remove any preceding www from the beginning of the domain name as well.
 *
 * @param string $url The url to check
 * @return boolean True if we detect a subdomain, else false
 * @access public
 * @throws NotFoundException A url must be passed to
 **/
	public function isSubdomain($url = null) {
		if (!$url) {
			throw new NotFoundException(__('URL was not passed (isSubdomain).'));
		}
		if ($this->isIp($url)) {
			return false;
		}
		$url = $this->addScheme($url, 'http');
		$parts = $this->urlParts($url);
		$nodes = explode('.', $parts['host']);
		if ($nodes[0] == 'www') {
			unset($nodes[0]);
			$parts['host'] = implode('.', $nodes);
			$nodes = explode('.', $parts['host']);
		}
		$tld = $this->__validTLD();
		foreach ($nodes as $node) {
			if (preg_match('/^(' . $tld . ')$/i', $node, $match)) {
				$parts['host'] = str_replace('.' . $node, '', $parts['host']);
			}
		}
		if (substr_count($parts['host'], '.') === 0) {
			return false;
		}
		return true;
	}

/**
 * Build URL
 *
 * Take a url and put together a valid URL with the desired scheme.
 *
 * @param mixed $url The URL you want to build.
 * @param string $scheme The scheme you want to use for the URL (http default)
 * @return string $new_url The newly confirmed URL
 * @access public
 * @todo : this needs to be deprecated and replaces with a better process.
 **/
	public function buildUrl($url = null, $scheme = null) {
		if (!$url) {
			return false;
		}
		if (!is_array($url)) {
			$url = $this->urlParts($url);
		}
		if (isset($scheme) && !empty($scheme)) {
			$url['scheme'] = $scheme;
		}
		if (!isset($url['scheme']) || empty($url['scheme'])) {
			$url['scheme'] = 'http';
		}
		return ((isset($url['scheme'])) ? $url['scheme'] . '://' : '') . ((isset($url['user'])) ? $url['user'] . ((isset($url['pass'])) ? ':' . $url['pass'] : '') . '@' : '') . ((isset($url['host'])) ? $url['host'] : '') . ((isset($url['port'])) ? ':' . $url['port'] : '') . ((isset($url['path'])) ? $url['path'] : '') . ((isset($url['query'])) ? '?' . $url['query'] : '') . ((isset($url['fragment'])) ? '#' . $url['fragment'] : '');
	}

/**
 * Check Domain
 *
 * Domain name with all of its node must be between 4 and 256 characters long
 * to be valid, including the .tld
 *
 * Each node is limited to 63 characters as well as specific characters and structure:
 * - must start with  a letter	number
 * - can contain numbers, letters, and dashes
 *
 * The domain must also contain a valid .tld from iana.org.
 *
 * @param string $domain  The name of the domain to be validated
 * @access protected
 * @return bool  {true|false}
 * @uses $this->__validTLD()  	List of valid TLDs created from the TLD file
 */
	protected function _checkDomain($domain) {
		if ($this->isIp($domain)) {
			return true;
		}
		$nodes = explode('.', $domain);
		$count = count($nodes);
		$domainLength = strlen($domain) - $count + 1;
		if ($domainLength > 256 || $domainLength < 4) {
			return false;
		}
		$tld = $this->__validTLD();

		// needs at least one valid TLD on the end of the domain
		if (!preg_match('/^(' . $tld . ')$/i', $nodes[$count - 1], $match)) {
			return false;
		}

		// if the last node is a TLD (and not a country code)
		// the second to last node must be more than 1 character in length
		if (preg_match('/^(' . $tld . ')$/i', $nodes[$count - 1], $match) && strlen($nodes[$count - 1]) > 2 && strlen($nodes[$count - 2]) < 2) {
			return false;
		}
		// if the last two nodes are country codes (and not a TLDs)
		// the third to last node must be more than 1 character in length
		if (preg_match('/^(' . $tld . ')$/i', $nodes[$count - 1], $match) && preg_match('/^(' . $tld . ')$/i', $nodes[$count - 2], $match) && strlen($nodes[$count - 1]) == 2 && strlen($nodes[$count - 2]) == 2 && strlen($nodes[$count - 3]) < 2) {
			return false;
		}

		foreach ($nodes as $node) {
			if (empty($node)) {
				return false; // cannot have a double period (www..example.com)
			}
			if (strlen($node) > 63) {
				return false;
			}
			if (!preg_match('/^([a-zA-Z0-9]{1,2}|[a-zA-Z0-9][a-zA-Z0-9\-]+[a-zA-Z0-9])$/', $node)) {
				return false;
			}
		}
		return true;
	}

/**
 * _unparseUrl
 *
 * Take the parse_url parts and put the URL back together.
 *
 * @param array $parts The parsed parts of a URL.
 * @return string $url The URL that is put back together.
 * @access protected
 **/
	protected function _unparseUrl($parts) {
		return ((isset($parts['scheme'])) ? $parts['scheme'] . '://' : '') . ((isset($parts['user'])) ? $parts['user'] . ((isset($parts['pass'])) ? ':' . $parts['pass'] : '') . '@' : '') . ((isset($parts['host'])) ? $parts['host'] : '') . ((isset($parts['port'])) ? ':' . $parts['port'] : '') . ((isset($parts['path'])) ? $parts['path'] : '') . ((isset($parts['query'])) ? '?' . $parts['query'] : '') . ((isset($parts['fragment'])) ? '#' . $parts['fragment'] : '');
	}

/**
 * Valid TLD
 *
 * Builds a list of valid TLDs to check domain against. The file is pulled from
 * http://data.iana.org/TLD/tlds-alpha-by-domain.txt
 * In order for the TLD list to stay up to date, _tld_files_status_check is called
 *
 * @static
 * @uses $this->__tldFileStatusCheck() To check the freshness of the file
 * @return array An array of all of the valid TLDs
 */
	private function __validTLD() {
		$this->__tldFileStatusCheck();
		$tldRegex = null;
		foreach (file($this->__tldList) as $tld) {
			if (preg_match('/^[\w]+$/', $tld)) {
				$tldRegex .= preg_replace('/\W/', '', $tld) . "|";
			}
		}
		$tldRegex = rtrim($tldRegex, '|');
		return $tldRegex;
	}

/**
 * Check the status of the TLD file
 *
 * Check the Current Freshness of the local TLD list to see if we need to download another copy
 *
 * @static
 * @access private
 */
	private function __tldFileStatusCheck() {
		date_default_timezone_set($this->__timezone);
		if (!file_exists($this->__tldList) || (time() - filemtime($this->__tldList)) > 60 * 60 * 24 * $this->__fileAge) {
			if ($tldFile = file_get_contents($this->__tldUrl)) {
				file_put_contents($this->__tldList, $tldFile);
			}
		}
	}
}