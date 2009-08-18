<?php
/***************************************************************
*  Copyright notice
*
*  (c)  2006 Franz Ripfel (fr@abezet.de)  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * 
 *
 * @package commerce
 * @subpackage payment
 * @author Franz Ripfel <fr@abezet.de>
 * 
 */
 
 /*
  * 
  * this is the specialized gateway class for saferpay
  * 
  * for testing
  *
  * Kreditkarte			Testnummer 
  * Visa      			4111 1111 1111 1111
  * MasterCard			5500 0000 0000 0004
  * American Express	3400 0000 0000 009
  * Diner's Club		3000 0000 0000 04  
  * Carte Blanche		3000 0000 0000 04
  * Discover			6011 0000 0000 0004
  * JCB					3088 0000 0000 0009
  *
  */
 
// library for credit card checks
require_once(PATH_txcommerce .'lib/class.tx_commerce_ccvs_lib.php');
require_once(t3lib_extMgm::extPath('abz_ext_commerce').'/payment/scd/Saferpay.class.php');

class tx_commerce_payment_creditcard {
	var $paymentData = '';
	var $transactionData = '';
	var $paymentRefId = '';
	
	var $accountId = '';
	var $authorizationResponse = '';
	var $captureResponse = '';
	/**
	 * The locallang array for this payment module
	 * This is only needed, if individual fields are defined
	 */
	var $LOCAL_LANG; //see $TYPO3_CONF_VARS['EXTCONF'][$this->extKey]['SYSPRODUCTS']['PAYMENT']['path_locallang']
	var $language = 'en';

	/// In this var the wrong fields are stored (for future use)
	var $errorFields = array();
	
	/// This var holds the errormessages (keys are the fieldnames)
	var $errorMessages = array();
	
  function tx_commerce_payment_creditcard() {
		global $GLOBALS;
		$basePath = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce']['SYSPRODUCTS']['PAYMENT']['types']['creditcard']['path_locallang'];
		$this->language = $GLOBALS['TSFE']->tmpl->setup['config.']['language'];
		$this->LOCAL_LANG = t3lib_div::readLLfile($basePath,$this->language);
		
	}
	
	/**
	 * Returns the localized label of the LOCAL_LANG key, $key
	 * Notice that for debugging purposes prefixes for the output values can be set with the internal vars ->LLtestPrefixAlt and ->LLtestPrefix
	 *
	 * @param	string		The key from the LOCAL_LANG array for which to return the value.
	 * @param	string		Alternative string to return IF no value is found set for the key, neither for the local language nor the default.
	 * @param	boolean		If true, the output label is passed through htmlspecialchars()
	 * @return	string		The value from LOCAL_LANG.
	 * @see class.tslib_pibase::pi_getLL()
	 */
	function getLL($key,$alt='',$hsc=FALSE)	{
		if (isset($this->LOCAL_LANG[$this->LLkey][$key]))	{
			$word = $GLOBALS['TSFE']->csConv($this->LOCAL_LANG[$this->LLkey][$key], $this->LOCAL_LANG_charset[$this->LLkey][$key]);	// The "from" charset is normally empty and thus it will convert from the charset of the system language, but if it is set (see ->pi_loadLL()) it will be used.
		} elseif ($this->altLLkey && isset($this->LOCAL_LANG[$this->altLLkey][$key]))	{
			$word = $GLOBALS['TSFE']->csConv($this->LOCAL_LANG[$this->altLLkey][$key], $this->LOCAL_LANG_charset[$this->altLLkey][$key]);	// The "from" charset is normally empty and thus it will convert from the charset of the system language, but if it is set (see ->pi_loadLL()) it will be used.
		} elseif (isset($this->LOCAL_LANG['default'][$key]))	{
			$word = $this->LOCAL_LANG['default'][$key];	// No charset conversion because default is english and thereby ASCII
		} else {
			$word = $this->LLtestPrefixAlt.$alt;
		}

		$output = $this->LLtestPrefix.$word;
		if ($hsc)	$output = htmlspecialchars($output);

		return $output;
	}

	function needAdditionalData() {
		return true;
	}

	function getAdditonalFieldsConfig() {
		$result = array(
			'cc_number.' => array ('mandatory' => '1'),
			'cc_type.' => array (
				'mandatory' => '1',
				'type' => 'select',
				'values' => array (
#					'Diners Club',
					'American Express',
#					'JCB',
#					'Carte Blanche',
					'Visa',
					'MasterCard',
#					'Australian BankCard',
#					'Discover/Novus'
				),
			),
			'cc_expirationYear.' => array ('mandatory' => '1'),
			'cc_expirationMonth.' => array ('mandatory' => '1'),
			'cc_holder.' => array ('manadatory' => '1'),
			'cc_checksum.' => array ('mandatory' => '1'),
		);
		return $result;
	}
	
	function proofData($formData) {
		$saferpay = new Saferpay(t3lib_extMgm::extPath('abz_ext_commerce').'/bin/saferpay',
        t3lib_extMgm::extPath('abz_ext_commerce').'/bin/');
    $DATA=$GLOBALS['_GET']['DATA'];
    // get attributes out of XML response message
    $attributes = $saferpay->GetAttributes(stripslashes($DATA)); 
    $resultMessage = "";
    if ($attributes["RESULT"] == "0") 
    {
	     echo "Registrierung erfolgreich!";
    }
    else
    {
      echo "Registrierung fehlgeschlagen (".$attributes["RESULT"].")";
    }

// verify the PayConfirm message sent by the Saferpay host (urlencode($DATA) for windows!)
if ($saferpay->VerifyPayConfirm(stripslashes($_GET['DATA']), stripslashes($_GET['SIGNATURE'])) == 0)
	$resultMessage = "Registrierung fehlgeschlagen (VerifyPayConfirm)!";
		//for the testaccout: do not check this data
		if ($formData['cc_number'] == '9451 1231 0000 0004') return true;

		$ccvs = new CreditCardValidationSolution();
		$result = $ccvs->validateCreditCard($formData['cc_number'], $formData['cc_checksum'],$this->language);
		//fetch error message
		if (!$result) $this->errorMessages[] = $ccvs->CCVSError;


		unset($ccvs);
		return $result;
	}
	
	private function generateRefID() {
    return 'GD'.time();
  }
	
	/**
	 * This method is called in the last step. Here can be made some final checks or whatever is
	 * needed to be done before saving some data in the database.
	 * Write any errors into $this->errorMessages!
	 * To save some additonal data in the database use the method updateOrder().
	 *
	 * @param	array	$config: The configuration from the TYPO3_CONF_VARS
	 * @param	array	$basket: The basket object
	 *
	 * @return boolean	True or false
	 */
	function finishingFunction($config,$session, $basket) {
		global $TYPO3_CONF_VARS;
		$this->paymentRefId = $this->generateRefID();
		$saferpay = new Saferpay('java -jar '.t3lib_extMgm::extPath('abz_ext_commerce').'/bin/Saferpay.jar',
        t3lib_extMgm::extPath('abz_ext_commerce').'/bin/');

		reset($basket->basket_items);
		
		#if( strtolower(current($basket->basket_items)->price->currency) == "sfr") {
		if ($_SERVER['HTTP_HOST'] == 'www.gdata.ch' || $_SERVER['HTTP_HOST'] == 'int.gdata.ch' || $_SERVER['HTTP_HOST'] == 'wwwdev.gdata.ch') {
			#$currency = "EUR";
			$currency ="CHF";
			#$amount = intval($basket->basket_sum_gross * 0.65083);
			$amount = $basket->basket_sum_gross;
		} elseif ($_SERVER['HTTP_HOST'] == 'live.gdatasoftware.co.uk' || $_SERVER['HTTP_HOST'] == 'www.gdatasoftware.co.uk' || $_SERVER['HTTP_HOST'] == 'int.gdatasoftware.co.uk' || $_SERVER['HTTP_HOST'] == 'wwwdev.gdatasoftware.co.uk') {
			$currency = "GBP";
			$amount = $basket->basket_sum_gross;
		} elseif ($_SERVER['HTTP_HOST'] == 'www.gdata-software.com'    || 
							$_SERVER['HTTP_HOST'] == 'live.gdata-software.com'   || 
							$_SERVER['HTTP_HOST'] == 'int.gdata-software.com'    || 
							$_SERVER['HTTP_HOST'] == 'wwwdev.gdata-software.com' ||
							$_SERVER['HTTP_HOST'] == 'www.gdatasoftware.com'     || 
							$_SERVER['HTTP_HOST'] == 'live.gdatasoftware.com'    || 
							$_SERVER['HTTP_HOST'] == 'int.gdatasoftware.com'     || 
							$_SERVER['HTTP_HOST'] == 'wwwdev.gdatasoftware.com') {
			
			$currency = "USD";
			$amount = $basket->basket_sum_gross;
		}	else {
			$currency = "EUR";
			$amount = $basket->basket_sum_gross;
		}
    
		$attributes = array (
        "ACCOUNTID" => $session['payment']['ACCOUNTID'],
        "CARDREFID" => $session['payment']['CARDREFID'],
        "EXP" => $session['payment']['EXPIRYMONTH'].$session['payment']['EXPIRYYEAR'],
        "AMOUNT" => $amount,
        "ORDERID" => $this->paymentRefId,
        
        /**
         *@TODO This should be configurable.
         **/                 
         "CURRENCY" => $currency,
      );
      if(array_key_exists('ECI', $session['payment'])) $attributes["ECI"]=$session['payment']['ECI'];
      if(array_key_exists('XID', $session['payment'])) $attributes["XID"]=$session['payment']['XID'];
      if(array_key_exists('CAVV', $session['payment'])) $attributes["CAVV"]=$session['payment']['CAVV'];
      if(array_key_exists('MPI_SESSIONID', $session['payment'])) $attributes["MPI_SESSIONID"]=$session['payment']['MPI_SESSIONID'];
      
      
      $result = $saferpay->Execute($attributes, 'Authorization');//, 'saferpay');
    if(is_array($result) && array_key_exists('RESULT', $result) && $result['RESULT']=='0') {
      //Authorization successfull. Capture the money
      $this->paymentAuthorized=true;
      $captureResult = $saferpay->capture($result['ID'], $result['TOKEN']);
      if($captureResult=='') {
        /**
         * @TODO: Hier muss vielleicht noch was an Logging rein, und der Artikel auf bezahlt gesetzt werden. Das Geld ist geflossen.
         **/
        
        $this->paymentCaptured=true;
  //       $this->log($attributes);
        return true;
      } else {
        $this->errorMessages[]='Capturing the money failed although authorization was given. Error is #'.$captureResult;
        return false;
      }
    }
    $this->errorMessages[]=$result['ERROR'].' Error #'.$result['AUTHMESSAGE'];
    $this->errorMessages[]=$result['ERROR'];
    
		return false;
	}
	
	function getSuccessData() {
		//todo: check with saferpay
    return 'Abbuchung über Saferpay war erfolgreich'; 
	}

	/**
	 * This method can make something with the created order. For example add the
	 * reference id for payments with creditcards.
	 */
	function updateOrder($orderUid, $session, $pObj) {
		/*
			Hier muss die vom checkout erzeugte Order geupdatet werden!
			Bei Kreditkartenzahlung muss eine Referenz ID im Feld payment_ref_id
			gespeichert werden. (Ich habe keine Ahnung voher die kommt, aber ich
			sch�tze mal das m�sste wirecard liefern!?)
			Genau, das passiert auch schon, und zwar hier.
			Die UID des angelegten order Datensatzes steht in $orderUid! Um die
			Order upzudaten m�sste folgendes reichen:
		*/
    
    $updateData = array(
        'payment_ref_id'  =>  $this->paymentRefId,
    );
    
    if($this->paymentCaptured===true) {
      //The money is actually transfered
      if($pObj->conf['abz_ext_commerce.']['captured_pid']) { //Do not move to pid 0
        $updateData['pid'] = $pObj->conf['abz_ext_commerce.']['captured_pid'];
      }
    } else if($this->paymentAuthorized===true) {
      //The money is reserved, however the transfer could not be completed
      if($pObj->conf['abz_ext_commerce.']['authorized_pid']) { //Do not move to pid 0
        $updateData['pid'] = $pObj->conf['abz_ext_commerce.']['authorized_pid'];
      }
    } else {
      //This order has failed somehow. We should not be here, however we write the paymentRefId to the database, so we can retrace it
      //Do not update the pid, as the payment is not completed
    }
    $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
			'tx_commerce_orders','uid = '.$orderUid,
			$updateData
		);
	}
	
	/**
	 * Returns the last error message, empty string if there is not
	 * @param int $finish: No idea, what this is
	 * @param tx_commerce_pi3? $pObj: The caller, not used   	 
	 */
	function getLastError($finish = 0, $pObj=NULL) {
    $errorMsg = $this->errorMessages[(count($this->errorMessages) -1)];
		if (empty($errorMsg)) return '';
		return '<div class="payment_error">'.$errorMsg.'</div>';
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']["ext/commerce/payment/class.tx_commerce_payment_creditcard.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']["ext/commerce/payment/class.tx_commerce_payment_creditcard.php"]);
}

?>