<?php
/**
 * Billing Validation Component
 *
 * Provides functionality to validate credit card information.
 * @package	BillingValidation Component
 * @author	Author: Chuck Burgess <cdburgess@gmail.com>
 * @license	License: http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright	Copyright: (c)2010-2013 Chuck Burgess. All Rights Reserved.
 *
 * Please feel free to visit my blog http://blogchuck.com
 * 
 */
App::uses('Component', 'Controller');
class BillingValidationComponent extends Object {
	
/**
 * Valid Credit Cards
 * identify the credit cards accepted by this merchant (comment out the lines not accepted)
 *
 * @param array
 * @acces private
 */
	private $valid_cc_types = array(
		'american_express',
		'diners_club_carte_blanche',
		'diners_club_international',
		'diners_club_us_canada',
		'discover',
		'jcb',
		'maestro',
		'mastercard',
		'solo',
		'switch',
		'visa',
		'visa_electron',
	);
	
/**
 * Verify the Credit Card is a Valid Number
 * 
 * @uses cc_type
 * @uses _luhn_validation
 * @param string $number  The credit card number
 * @return array  The validation information 'valid' => {true|false} [, 'error' => {message}]
 * @access public
 */
	public function validate_card($number) {
		
		// strip all posible invalid characters
		$number = preg_replace('/\D/', '', $number);
		
		// get the type of credit card
		$type = $this->cc_type($number);
		
		// is the credit card a known type
		if (!$type) {
			return array('valid' => false, 'error' => 'Does not match known card types.');
		}
		
		// is the credit card accepted
		if (!in_array($type, $this->valid_cc_types)) {
			return array('valid' => false, 'error' => 'This type of Credit Card is not accepted.');
		}

		// does the credit card pass Luhn (modulus 10) validation
		if (!$this->_luhn_validation($number)) {
			return array('valid' => false, 'error' => 'Invalid credit card number.');
		}

		// return the status of the card
		return array('valid' => true);
	}
	
/**
 * Credit Card Types
 *
 * Currently this method will detect all valid credit cards
 *
 * @param string $number		The credit card to check
 * @return string $type		The credit card type
 * @access public
 */
	public function cc_type($number) {
		
		// Strip any non-digits (useful for credit card numbers with spaces and hyphens)
		$number = preg_replace('/\D/', '', $number);
		
		// American Express (AmEx) P: 34,37 L: 15
		if (preg_match('/^3(?:4|7)\d{13}$/', $number)) {
			$type = 'american_express';
		
		// Diners Club Carte Blanche (DC-CB) P: 300-305, 3095 L: 14
		// processed by Discover (https://www.discovernetworkvar.com/common/pdf/var/9-2_VAR_ALERT_Sep_2009.pdf)
		} elseif (preg_match('/^30(?:[0-5][0-9]|95)\d{10}$/', $number)) {
			$type = 'diners_club_carte_blanche';
			
		// Diners Club International (DC-int) P: 36, 38, 39 L: 14
		// processed by Discover (https://www.discovernetworkvar.com/common/pdf/var/9-2_VAR_ALERT_Sep_2009.pdf)
		} elseif (preg_match('/^3(?:6|8|9)\d{12}$/', $number)) {
			$type = 'diners_club_international';
		
		// Diners Club US & Canada (DC-UC) P: 54,55 L: 16
		} elseif (preg_match('/^5(?:[4-5])\d{14}$/', $number)) {
			$type = 'diners_club_us_canada';
		
		// Discover (Disc) P: 6011([0000-0999]|[2000-4999]|[7400-7499]|[7700-7999]|[8600-9999]), 644-659 L: 16
		// VAR information (https://www.discovernetworkvar.com/varWeb/TestingQA.do)
		// Discover also handles the China Union Pay cards (622126-622925, 624-626, 6282-6288) 
		//   - however, validation method unknown so not included
		} elseif (preg_match('/^6011(?:0[0-9]|[2-4][0-9]|74|7[7-9]|8[6-9]|9[0-9])\d{10}|(64[4-9]|65[0-9])\d{13}$/', $number)) {
			$type = 'discover';
		
		// JCB P: 3528-3589 L: 16
		// processed by Discover (https://www.discovernetworkvar.com/common/pdf/var/9-2_VAR_ALERT_Sep_2009.pdf)
		} elseif (preg_match('/^35(?:2[8-9]|[3-8][0-9])\d{12}$/', $number)) {
			$type = 'jcb';
			
		// Maestro (Maes) P: 5018,5020,5038,6304,6759,6761,6763 L: 12-19
		} elseif (preg_match('/^(?:[5018|5020|5038|6304|6759|6761|6763])\d{8-15}$/', $number)) {
			$type = 'maestro';
		
		// MasterCard (MC) P: 51-55 L: 16
		} elseif (preg_match('/^5[1-5]\d{14}$/', $number)) {
			$type = 'mastercard';
		
		// Solo (Solo) P: 6334, 6767 L: 16,18,19
		} elseif (preg_match('/^(?:[6334|6767])\d{8,14,15}$/', $number)) {
			$type = 'solo';
		
		// Switch (Swch) P: 4903,4905,4911,4936,564182,633110,6333,6759 L: 16,18,19
		} elseif (preg_match('/^(?:[4903[0-9][0-9]|4905[0-9][0-9]|4911[0-9][0-9]|4936[0-9][0-9]|564182|633110|6333[0-9][0-9]|6759[0-9][0-9]])\d{10,12,13}$/', $number)) {
			$type = 'switch';
		
		// Visa (Visa) P: 4 L: 13,16
		} elseif (preg_match('/^4(?:\d{13}|\d{15})$/', $number)) {
			$type = 'visa';
		
		// Visa Electron (Visa) P: 417500, 4917, 4913, 4508, 4844 L: 16
		} elseif (preg_match('/^4(17500|917[0-9][0-9]|913[0-9][0-9]|508[0-9][0-9]|844[0-9][0-9])\d{10}$/', $number)) {
			$type = 'visa_electron';
			
		// no match, non-active, or no validation
		} else {
			$type = false;
		}

		return $type;
	}
	
/**
 * The Luhn Validation
 *
 * The algorithm (aka Modulus 10) is the checksum used to validate a variety of identification numbers.
 * Most credit cards use this same formula. It is only intended to prevent errors, not mailcious attack.
 *
 * @param string $number			The credit card number
 * @return bool {true|false}
 * @access protected
 */
	protected function _luhn_validation($number) {
		// Strip any non-digits (useful for credit card numbers with spaces and hyphens)
		$number = preg_replace('/\D/', '', $number);
		
		// Set the string length and parity
		$number_length = strlen($number);
		
		// will be 1/0 for odd/even digits in number so we know which number to x by 2
		$parity = $number_length % 2;
		
		// Loop through each digit and do the math
		$total=0;
		
		for ($i=0; $i<$number_length; $i++) {
			$digit = $number[$i];
			
			// Multiply alternate digits by two
			if ($i % 2 == $parity) {
				$digit *= 2;
				// If the sum is two digits, add them together (in effect)
				if ($digit > 9) {
					// same as 1+1 = 11-9, 1+2 = 12-9, etc. Number will never exceed 18
					$digit -= 9;
				}
			}
			// Total up the digits
			$total += $digit;
		}
		
		// If the total mod 10 equals 0, the number is valid
		return ($total % 10 == 0) ? true : false;
	}
}