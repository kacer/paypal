<?php
/**
 * Class  PayPal
 *
 * @version 1.0
 * @author Martin Maly - http://www.php-suit.com
 * @copyright (C) 2008 martin maly
 * @see  http://www.php-suit.com/paypal
 * 2.10.2008 20:30:40
 *
 * @author Michal Wiglasz - michalwiglasz.cz
 * 20.10.2012
 */

/*
* Copyright (c) 2008 Martin Maly - http://www.php-suit.com
* All rights reserved.
*
* Redistribution and use in source and binary forms, with or without
* modification, are permitted provided that the following conditions are met:
*     * Redistributions of source code must retain the above copyright
*       notice, this list of conditions and the following disclaimer.
*     * Redistributions in binary form must reproduce the above copyright
*       notice, this list of conditions and the following disclaimer in the
*       documentation and/or other materials provided with the distribution.
*     * Neither the name of the <organization> nor the
*       names of its contributors may be used to endorse or promote products
*       derived from this software without specific prior written permission.
*
* THIS SOFTWARE IS PROVIDED BY MARTIN MALY ''AS IS'' AND ANY
* EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
* WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
* DISCLAIMED. IN NO EVENT SHALL MARTIN MALY BE LIABLE FOR ANY
* DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
* (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
* LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
* ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
* (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
* SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/



namespace PayPal;



class PayPal {
	private $apiUsername;
	private $apiPassword;
	private $apiSignature;

	private $endpoint;
	private $host;
	private $gate;



	/**
	 * Constructor.
	 *
	 * To get your API credentials:
	 *   - Log into PayPal and click Profile.
     *   - Click API Access (in the Account Information column).
     *   - Click View API Signature.
     *
	 * Also @see https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_NVPAPIBasics
	 *
	 * @param string $apiUsername
	 * @param string $apiPassword
	 * @param string $apiSignature
	 * @param bool $realPaypal If FALSE, the sandbox will be used instead of the real PayPal
	 */
	function __construct($apiUsername, $apiPassword, $apiSignature, $realPaypal = FALSE) {
		$this->apiUsername = $apiUsername;
		$this->apiPassword = $apiPassword;
		$this->apiSignature = $apiSignature;

		$this->endpoint = '/nvp';
		if ($realPaypal) {
			$this->host = "api-3t.paypal.com";
			$this->gate = 'https://www.paypal.com/cgi-bin/webscr?';
		} else {
			//sandbox
			$this->host = "api-3t.sandbox.paypal.com";
			$this->gate = 'https://www.sandbox.paypal.com/cgi-bin/webscr?';
		}
	}



	/**
	 * Main payment function
	 *
	 * If OK, the customer is redirected to PayPal gateway
	 * If error, raises PayPalException
	 *
	 * @param float $amount Amount (2 numbers after decimal point)
	 * @param string $desc Item description
	 * @param string $returnUrl Callback URL if user confirms payment
	 * @param string $cancelUrl Callback URL if user cancels payment
	 * @param string $currency 3-letter currency code (USD, GBP, CZK etc.)
	 * @param string $invoice Invoice number (can be omitted)
	 *
	 * @return array error info
	 *
	 * @throws HTTPException
	 * @throws PayPalException
	 */
	public function doExpressCheckout($amount, $desc, $returnUrl, $cancelUrl, $currency='USD', $invoice=''){
		$data = array(
			'PAYMENTACTION' =>'Sale',
			'AMT' => $amount,
			'RETURNURL' => $returnUrl,
			'CANCELURL' => $cancelUrl,
			'DESC'=> $desc,
			'NOSHIPPING' => "1",
			'ALLOWNOTE' => "1",
			'CURRENCYCODE' => $currency,
			'METHOD' =>'SetExpressCheckout'
		);

		$data['CUSTOM'] = $amount.'|'.$currency.'|'.$invoice;
		if ($invoice) $data['INVNUM'] = $invoice;

		$query = $this->buildQuery($data);

		$result = $this->response($query);
		$response = $result->getContent();
		$return = $this->responseParse($response);

		if ($return['ACK'] == 'Success') {
			header('Location: '.$this->gate.'cmd=_express-checkout&useraction=commit&token='.$return['TOKEN'].'');
			die();

		} else {
			throw new PayPalException($return);
		}
	}



	/**
	 * Returns additional information about the payment.
	 *
	 * @param string $token Payment token
	 *
	 * @return array
  	 *
	 * @throws HTTPException
	 * @throws PayPalException
	 */
	public function getCheckoutDetails($token){
		$data = array(
		'TOKEN' => $token,
		'METHOD' =>'GetExpressCheckoutDetails');
		$query = $this->buildQuery($data);

		$result = $this->response($query);
		$response = $result->getContent();
		$return = $this->responseParse($response);

		if ($return['ACK'] != 'Success') {
			throw new PayPalException($return);
		}

		return($return);
	}



	/**
	 * Confirms payment. Should be called in the Return callback page.
	 *
	 * @param string $token Payment Token (passed by PayPal as 'token' GET query param)
	 * @param string $payerId Payer ID (passed by PayPal as 'PayerID' GET query param),
	 *
	 * @return array Payment info (keys: AMT, CURRENCYCODE, PAYMENTSTATUS, PENDINGREASON, REASONCODE)
	 *
	 * @throws HTTPException
	 * @throws PayPalException
	 */
	public function doPayment($token, $payerId){
		$details = $this->getCheckoutDetails($token);
		if (!$details) return false;
		list($amount,$currency,$invoice) = explode('|',$details['CUSTOM']);
		$data = array(
			'PAYMENTACTION' => 'Sale',
			'PAYERID' => $payerId,
			'TOKEN' =>$token,
			'AMT' => $amount,
			'CURRENCYCODE'=>$currency,
			'METHOD' =>'DoExpressCheckoutPayment'
		);
		$query = $this->buildQuery($data);

		$result = $this->response($query);
		$response = $result->getContent();
		$return = $this->responseParse($response);

		if ($return['ACK'] != 'Success') {
			throw new PayPalException($return);
		}

		/*
		 * [AMT] => 10.00
		 * [CURRENCYCODE] => USD
		 * [PAYMENTSTATUS] => Completed
		 * [PENDINGREASON] => None
		 * [REASONCODE] => None
		 */

		return($return);
	}



	/**
	 * @return HTTPRequest
	 * @throws HTTPException
	 */
	private function response($data){
		$r = new HTTPRequest($this->host, $this->endpoint, 'POST', true);
		$result = $r->connect($data);
		if ($result<400) {
			return $r;
		} else {
			throw new HTTPException("Request failed with code '$result'.", $result);
		}
	}



	private function buildQuery($data = array()){
		$data['USER'] = $this->apiUsername;
		$data['PWD'] = $this->apiPassword;
		$data['SIGNATURE'] = $this->apiSignature;
		$data['VERSION'] = '52.0';
		$query = http_build_query($data);
		return $query;
	}



	private function getScheme() {
		$scheme = 'http';
		if (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') {
			$scheme .= 's';
		}
		return $scheme;
	}



	private function responseParse($resp){
		$a=explode("&", $resp);
		$out = array();
		foreach ($a as $v){
			$k = strpos($v, '=');
			if ($k) {
				$key = trim(substr($v,0,$k));
				$value = trim(substr($v,$k+1));
				if (!$key) continue;
				$out[$key] = urldecode($value);
			} else {
				$out[] = $v;
			}
		}
		return $out;
	}
}

