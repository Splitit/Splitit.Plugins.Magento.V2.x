<?php

/**
 * Copyright © 2019 Splitit
 *
 */

namespace Splitit\Paymentmethod\Controller\Showinstallmentprice;

use Magento\Framework\Controller\ResultFactory;

class Getinstallmentprice extends \Magento\Framework\App\Action\Action {

	private $helper;
	private $helperData;
	private $payment;
	private $paymentForm;
	private $cart;
	private $requestData;

	/**
     * Contructor
     * @param \Magento\Framework\App\Action\Context $context
	 * @param \Splitit\Paymentmethod\Helper\Data $helperData
	 * @param \Magento\Checkout\Model\Session $checkoutSession
	 * @param \Splitit\Paymentmethod\Model\PaymentForm $paymentForm
	 * @param \Splitit\Paymentmethod\Model\Payment $payment
	 * @param \Magento\Framework\App\RequestInterface $request
	 * @param \Magento\Checkout\Model\Cart $cart
     */
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Splitit\Paymentmethod\Helper\Data $helperData,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Splitit\Paymentmethod\Model\PaymentForm $paymentForm,
		\Splitit\Paymentmethod\Model\Payment $payment,
		\Magento\Framework\App\RequestInterface $request,
		\Magento\Checkout\Model\Cart $cart
	) {
		$this->checkoutSession = $checkoutSession;
		$this->paymentForm = $paymentForm;
		$this->payment = $payment;
		$this->helperData = $helperData;
		$this->cart = $cart;
		$this->requestData = $request->getParams();

		parent::__construct($context);
	}

	/**
	 * Get number of available installments based on total
	 * @return Json
	 **/
	public function execute() {
		$this->helper = $this->helperData;
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

		$isEnable = $this->helper->getEnableInstallmentPrice();
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

		$totalAmount = $this->cart->getQuote()->getGrandTotal();
		$response["grandTotal"] = number_format((float) $totalAmount, 2, '.', '');
		$response["currencySymbol"] = $this->helper->getCurrencyData();

		$resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
		if ($this->paymentForm->checkProductBasedAvailability() || $this->payment->checkProductBasedAvailability()) {
			if(isset($this->requestData['pid']) && $this->requestData['pid']){
				if($this->paymentForm->isSplititTextVisibleOnProduct($this->requestData['pid']) || $this->payment->isSplititTextVisibleOnProduct($this->requestData['pid'])){
					return $resultJson->setData($response);
				} else {
					return $resultJson->setData(array('status' => false));
				}
			}
			return $resultJson->setData($response);
		} else {
			return $resultJson->setData(array('status' => false));
		}
		
	}

}
