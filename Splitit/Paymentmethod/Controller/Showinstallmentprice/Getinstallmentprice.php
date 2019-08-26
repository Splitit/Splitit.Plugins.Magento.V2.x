<?php

/**
 * Copyright © 2015 Inchoo d.o.o.
 * created by Zoran Salamun(zoran.salamun@inchoo.net)
 */

namespace Splitit\Paymentmethod\Controller\Showinstallmentprice;

use Magento\Framework\Controller\ResultFactory;

class Getinstallmentprice extends \Magento\Framework\App\Action\Action {

	private $helper;
	private $payment;
	private $paymentForm;

	public function execute() {
		$this->helper = $this->_objectManager->create('Splitit\Paymentmethod\Helper\Data');
		$this->payment = $this->_objectManager->create('Splitit\Paymentmethod\Model\Payment');
		$this->paymentForm = $this->_objectManager->create('Splitit\Paymentmethod\Model\PaymentForm');
		$response = [
			"status" => true,
			"help" => ['splitit_paymentmethod' => [], 'splitit_paymentredirect' => []],
			"isActive" => "",
			"pageType" => "",
			"displayInstallmentPriceOnPage" => "",
			"numOfInstallmentForDisplay" => "",
			"installmetPriceText" => "",
			"grandTotal" => "",
			"currencySymbol" => "",
			"splititLogoSrc" => "",
			"splititLogoBackgroundSrc" => "",
		];

		$isEnable = $this->helper->getConfig("payment/splitit_paymentmethod/enable_installment_price");
		if ($isEnable == "") {
			$isEnable = 0;
		}

		$displayInstallmentPriceOnPage = '';
		$numOfInstallmentForDisplay = '';

		$splititLogoArray = $this->helper->getInstallmentPriceText();
		$installmetPriceText = "";
		$SplititLogoSrc = "";
		$SplititLogoBackgroundSrc = "";
		$helpLink = "";
		if ($splititLogoArray) {
			$installmetPriceText = $splititLogoArray['price_text'];
			$SplititLogoSrc = $splititLogoArray['logo_src'];
			$SplititLogoBackgroundSrc = $splititLogoArray['bakcground_href'];
			$displayInstallmentPriceOnPage = $splititLogoArray['installment_price_on_pages'];
			$numOfInstallmentForDisplay = $splititLogoArray['installments_count'];
			$helpLink = $splititLogoArray['help_link'];
			$helpTitle = $splititLogoArray['help_title'];
		}

		if (is_null($installmetPriceText)) {
			$installmetPriceText = "";
		} else {
			$installmetPriceText = str_replace('{NOI}', $numOfInstallmentForDisplay, $installmetPriceText);
			$textArr = explode(' ', $installmetPriceText);
			$changeindex = array_search('SPLITIT', $textArr);
			if ($changeindex > -1) {
				$replace = "<a id='tell-me-more' href='" . $SplititLogoBackgroundSrc . "' target='_blank'><img class='logoWidthSrc' src='" . $SplititLogoSrc . "' alt='SPLITIT'/></a>";
				$textToChange = str_replace('SPLITIT', $replace, $textArr[$changeindex]);
				unset($textArr[$changeindex]);
				$newText = __(implode(' ', $textArr));
				$newTextArr = explode(' ', $newText);
				$newVal = array($changeindex => $textToChange);
				$newList = array_merge(array_slice($newTextArr, 0, $changeindex), $newVal, array_slice($newTextArr, $changeindex));
				$installmetPriceText = implode(' ', $newList);
			}
			if ($helpLink) {
				$installmetPriceText = $installmetPriceText . " <a class='tellLink' id='tell-me-more' href='" . $helpLink . "' target='_blank'>" . $helpTitle . "</a>";
			}
		}

		if ($SplititLogoSrc && $SplititLogoBackgroundSrc) {
			$response["splititLogoSrc"] = $SplititLogoSrc;
			$response["splititLogoBackgroundSrc"] = $SplititLogoBackgroundSrc;
		}

		$response["isActive"] = $isEnable;
		$response["displayInstallmentPriceOnPage"] = $displayInstallmentPriceOnPage;
		$response["numOfInstallmentForDisplay"] = $numOfInstallmentForDisplay;
		$response["installmetPriceText"] = __($installmetPriceText);

		$cart = $this->_objectManager->get("\Magento\Checkout\Model\Cart");
		$totalAmount = $cart->getQuote()->getGrandTotal();
		$response["grandTotal"] = number_format((float) $totalAmount, 2, '.', '');
		$response["currencySymbol"] = $this->helper->getCurrencyData();

		$resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
		if ($this->paymentForm->checkProductBasedAvailability() || $this->payment->checkProductBasedAvailability()) {
			return $resultJson->setData($response);
		} else {
			return $resultJson->setData(array('status' => false));
		}
		/* echo $data = $this->helper->encodeData($response);
          return; */
	}

}
