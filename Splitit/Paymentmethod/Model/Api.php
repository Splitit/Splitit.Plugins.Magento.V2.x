<?php

namespace Splitit\Paymentmethod\Model;

use Magento\Store\Model\StoreManagerInterface;

class Api extends \Magento\Payment\Model\Method\AbstractMethod {

	private $helper;
	private $customerSession;
	private $grandTotal;
	private $taxAmount;
	private $shippingAmount;
	private $shippingAddress;
	private $billingAddress;
	private $currencySymbol;
	private $currencyCode;
	private $storeManager;
	private $currency;
	private $countryFactory;
	private $guestEmail;
	private $quote;
	protected $productModel;
	protected $logger;
	/**
	 * @var \Magento\Framework\HTTP\Client\Curl
	 */
	protected $curl;

	/**
	 * @param \Magento\Customer\Model\Session $customerSession
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManager
	 * @param \Magento\Directory\Model\Currency $currency
	 * @param \Magento\Framework\HTTP\Client\Curl $curl
	 */
	public function __construct(
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Directory\Model\Currency $currency,
		\Magento\Framework\HTTP\Client\Curl $curl,
		\Magento\Checkout\Model\Cart $cart,
		\Magento\Directory\Model\CountryFactory $countryFactory,
		\Magento\Catalog\Model\ProductRepository $productModel,
		\Psr\Log\LoggerInterface $logger,
		\Splitit\Paymentmethod\Helper\Data $helper,
		\Magento\Store\Model\StoreManagerInterface $storeManager
	) {

		$this->helper = $helper;
		$this->quote = $cart->getQuote();
		$this->grandTotal = round($cart->getQuote()->getGrandTotal(), 2);
		$this->shippingAddress = $cart->getQuote()->getShippingAddress();
		$this->shippingAmount = round($this->shippingAddress->getShippingAmount(), 2);
		$this->taxAmount = round($this->shippingAddress->getTaxAmount(), 2);

		$this->billingAddress = $cart->getQuote()->getBillingAddress();

		$this->storeManager = $storeManager;
		$this->currencyCode = $storeManager->getStore()->getCurrentCurrencyCode();
		$this->currencySymbol = $currency->load($this->currencyCode)->getCurrencySymbol();

		$this->curl = $curl;

		$this->customerSession = $customerSession;

		$this->countryFactory = $countryFactory;
		$this->productModel = $productModel;
		$this->logger = $logger;
	}

	/** check if splititsession id not exist create new
	 **	return string
	 **/
	public function getorCreateSplititSessionid() {
		if (!$this->customerSession->getSplititSessionid()) {
			$this->apiLogin();
			$this->logger->error('New Session Id :' . $this->customerSession->getSplititSessionid());
		} else {
			$this->logger->error('Old Session Id :' . $this->customerSession->getSplititSessionid());
		}

		return $this->customerSession->getSplititSessionid();

	}

	/**
	 * Responsible for login to Splitit and generate session id
	 *
	 * @return array
	 */
	public function apiLogin($dataForLogin = array()) {

		$apiUrl = $this->getApiUrl();
		if (empty($dataForLogin)) {
			$dataForLogin = array(
				'UserName' => $this->helper->getApiUsername("splitit_paymentmethod"),
				'Password' => $this->helper->getApiPassword("splitit_paymentmethod"),
				'TouchPoint' => $this->helper->getApiTouchPointVersion(),
			);
		}

		$result = $this->makePhpCurlRequest($apiUrl, "Login", $dataForLogin);
		$decodedResult = $this->helper->jsonDecode($result);

		$response = ["splititSessionId" => "", "errorMsg" => "", "successMsg" => "", "status" => false];
		if ($decodedResult) {
			/*check for curl error*/
			if (isset($decodedResult["errorMsg"])) {
				$response["errorMsg"] = $decodedResult["errorMsg"];
				return $response;
			}

			/*get splitit session id*/
			$response["splititSessionId"] = (isset($decodedResult['SessionId']) && $decodedResult['SessionId'] != '') ? $decodedResult['SessionId'] : null;
			/*get success status*/
			if (isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1) {
				$response["status"] = true;
			}
			/*get error message if not success*/
			if (is_null($response["splititSessionId"])) {
				$response["errorMsg"] = $this->getErrorFromApi($decodedResult);
				return $response;
			}
			/*set splitit session id in session*/
			$this->customerSession->setSplititSessionid($response["splititSessionId"]);
		}
		return $response;
	}

	/**
	 * Init call for installments
	 *
	 * @return array
	 */
	public function installmentPlanInit($selectedInstallment, $guestEmail) {
		try {
			$response = ["errorMsg" => "", "successMsg" => "", "status" => false];
			$apiUrl = $this->getApiUrl();
			$this->guestEmail = $guestEmail;
			$params = $this->createDataForInstallmentPlanInit($selectedInstallment);
			$this->customerSession->setSelectedInstallment($selectedInstallment);
			/*call Installment Plan Initiate api to get Approval URL*/
			$result = $this->makePhpCurlRequest($apiUrl, "InstallmentPlan/Initiate", $params);
			$decodedResult = $this->helper->jsonDecode($result);
			/*check for curl error*/
			if (isset($decodedResult["errorMsg"])) {
				$response["errorMsg"] = $decodedResult["errorMsg"];
				return $response;
			}

			/*check for approval URL from response*/
			if (isset($decodedResult) && isset($decodedResult["ApprovalUrl"]) && $decodedResult["ApprovalUrl"] != "") {
				/*get response from Approval Url*/
				$approvalUrlResponse = $this->getApprovalUrlResponse($decodedResult);
				/*check for curl error*/
				if (isset($approvalUrlResponse["errorMsg"]) && $approvalUrlResponse["errorMsg"] != "") {
					$response["errorMsg"] = $approvalUrlResponse["errorMsg"];
					return $response;
				}

				$response["status"] = true;
				$response["successMsg"] = $approvalUrlResponse["successMsg"];
			} else if (isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])) {
				$response["errorMsg"] = $this->getErrorFromApi($decodedResult);
			} else if (isset($decodedResult["serverError"])) {
				$response["errorMsg"] = $decodedResult["serverError"];
			}
		} catch (\Exception $e) {
			$response["errorMsg"] = $e->getMessage();
		}

		return $response;
	}

	/**
	 * Prepare data for installment init call
	 *
	 * @return array
	 */
	public function createDataForInstallmentPlanInit($selectedInstallment) {

		$firstInstallmentAmount = $this->getFirstInstallmentAmount($selectedInstallment);
		$cultureName = $this->helper->getCultureName();
		
		$customerInfo = $this->customerSession->getCustomer()->getData();
		if (!isset($customerInfo["firstname"])) {
			$customerInfo["firstname"] = $this->billingAddress->getFirstname();
			$customerInfo["lastname"] = $this->billingAddress->getLastname();
			$customerInfo["email"] = $this->billingAddress->getEmail();
		}
		if ($customerInfo["email"] == "") {
			$customerInfo["email"] = $this->guestEmail;
		}
		$billingStreet1 = "";
		$billingStreet2 = "";
		if (isset($this->billingAddress->getStreet()[0])) {
			$billingStreet1 = $this->billingAddress->getStreet()[0];
		}
		if (isset($this->billingAddress->getStreet()[1])) {
			$billingStreet2 = $this->billingAddress->getStreet()[1];
		}
		$autoCapture = false;
		$paymentAction = $this->helper->getPaymentAction();
		if ($paymentAction == "authorize_capture") {
			$autoCapture = true;
		}
		$params = [
			"RequestHeader" => [
				"SessionId" => $this->getorCreateSplititSessionid(),
				"ApiKey" => $this->helper->getApiTerminalKey("splitit_paymentmethod"),
			],
			"PlanData" => [
				"Amount" => [
					"Value" => $this->grandTotal,
					"CurrencyCode" => $this->currencyCode,
				],
				"NumberOfInstallments" => $selectedInstallment,
				"PurchaseMethod" => "ECommerce",
				"FirstInstallmentAmount" => [
					"Value" => $firstInstallmentAmount,
					"CurrencyCode" => $this->currencyCode,
				],
				"AutoCapture" => $autoCapture,
				"ExtendedParams" => [
					"CreateAck" => "NotReceived",
				],
			],
		];
		$cart = $this->quote;
		$itemsArr = array();
		$i = 0;
		$currencyCode = $this->currencyCode;
		foreach ($cart->getAllItems() as $item) {
			$description = $this->productModel->getById($item->getProductId())->getShortDescription();
			$itemsArr[$i]["Name"] = $item->getName();
			$itemsArr[$i]["SKU"] = $item->getSku();
			$itemsArr[$i]["Price"] = array("Value" => round($item->getPrice(), 2), "CurrencyCode" => $currencyCode);
			$itemsArr[$i]["Quantity"] = $item->getQty();
			$itemsArr[$i]["Description"] = strip_tags($description);
			$i++;
		}
		$params['CartData'] = array(
			"Items" => $itemsArr,
			"AmountDetails" => array(
				"Subtotal" => round($this->quote->getSubtotal(), 2),
				"Tax" => round($this->quote->getShippingAddress()->getData('tax_amount'), 2),
				"Shipping" => round($this->quote->getShippingAddress()->getShippingAmount(), 2),
			),
		);

		return $params;
	}

	/**
	 * Get Api url
	 *
	 * @return array
	 */
	public function getApiUrl() {

		$helper = $this->helper;
		if ($helper->getSandboxFlag("splitit_paymentmethod")) {
			return $helper->getApiUrlSandbox("splitit_paymentmethod");
		}
		return $helper->getApiUrl("splitit_paymentmethod");
	}

	/**
	 * Init for hosted solution
	 * @param params array
	 * @return json
	 */
	public function installmentplaninitforhostedsolution($params) {
		try {
			return $this->makePhpCurlRequest($this->getApiUrl(), "InstallmentPlan/Initiate", $params);
		} catch (\Exception $e) {
			$this->setError($e->getMessage());
		}

	}

	/**
	 * Get installment details from Splitit
	 * @param apiurl string
	 * @param params array
	 * @return json
	 */
	public function getInstallmentPlanDetails($apiUrl, $params) {
		try {
			return $this->makePhpCurlRequest($apiUrl, "InstallmentPlan/Get", $params);
		} catch (\Exception $e) {
			$this->setError($e->getMessage());
		}
	}

	/**
	 * Cancel installment details from Splitit
	 * @param apiurl string
	 * @param params array
	 * @return json
	 */
	public function cancelInstallmentPlan($apiUrl, $params) {
		try {
			if (!$apiUrl) {
				$apiUrl = $this->getApiUrl();
			}
			return $this->makePhpCurlRequest($apiUrl, "InstallmentPlan/Cancel", $params);
		} catch (\Exception $e) {
			$this->setError($e->getMessage());
		}
	}

	/**
	 * Update installment details from Splitit
	 * @param apiurl string
	 * @param params array
	 * @return json
	 */
	public function updateRefOrderNumber($apiUrl = '', $params) {
		try {
			if (!$apiUrl) {
				$apiUrl = $this->getApiUrl();
			}
			return $this->makePhpCurlRequest($apiUrl, "InstallmentPlan/Update", $params);
		} catch (\Exception $e) {
			$this->setError($e->getMessage());
		}
	}

	/**
	 * Get error details
	 * @param decodedResult array
	 * @return string
	 */
	public function getErrorFromApi($decodedResult) {
		$errorMsg = "";
		$errorCount = count($decodedResult["ResponseHeader"]["Errors"]);
		if (isset($decodedResult["ResponseHeader"]) && $errorCount) {

			$i = 1;
			foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
				$errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
				if ($i < $errorCount) {
					$errorMsg .= ", ";
				}
				$i++;
			}
		}
		return $errorMsg;
	}

	/**
	 * Get first insatallment amount
	 * @param selectedInstallment int
	 * @return float
	 */
	public function getFirstInstallmentAmount($selectedInstallment) {

		$firstPayment = $this->helper->getFirstPayment();
		$percentageOfOrder = $this->helper->getPercentageOfOrder();

		$selectedInstallmentAmount = round($this->grandTotal / $selectedInstallment, 2);

		$firstInstallmentAmount = 0;
		if ($firstPayment == "equal") {
			$firstInstallmentAmount = $selectedInstallmentAmount;
		} else if ($firstPayment == "shipping_taxes") {
			$firstInstallmentAmount = $selectedInstallmentAmount + $this->shippingAmount + $this->taxAmount;
		} else if ($firstPayment == "shipping") {
			$firstInstallmentAmount = $selectedInstallmentAmount + $this->shippingAmount;
		} else if ($firstPayment == "tax") {
			$firstInstallmentAmount = $selectedInstallmentAmount + $this->taxAmount;
		} else if ($firstPayment == "percentage") {
			if ($percentageOfOrder > 50) {
				$percentageOfOrder = 50;
			}
			$firstInstallmentAmount = (($this->grandTotal * $percentageOfOrder) / 100);
		}

		return round($firstInstallmentAmount, 2);
	}

	/**
	 * Get first insatallment amount
	 * @return array
	 */
	public function checkForBillingFieldsEmpty() {
		$customerInfo = $this->customerSession->getCustomer()->getData();
		if (!isset($customerInfo["firstname"])) {
			$customerInfo["firstname"] = $this->billingAddress->getFirstname();
			$customerInfo["lastname"] = $this->billingAddress->getLastname();
			$customerInfo["email"] = $this->billingAddress->getEmail();
		}
		if ($customerInfo["email"] == "") {
			$customerInfo["email"] = $this->guestEmail;
		}
		$response = ["errorMsg" => "", "successMsg" => "", "status" => false];
		if ($this->billingAddress->getStreet()[0] == ""){
			$response["errorMsg"] = __("Splitit does not accept empty street field.");
		} elseif($customerInfo["email"] == ""){
			$response["errorMsg"] = __("Splitit does not accept empty email field.");
		} elseif($this->billingAddress->getTelephone() == "") {
			$response["errorMsg"] = __("Splitit does not accept empty phone field.");
		} else if (strlen($this->billingAddress->getTelephone()) < 5 || strlen($this->billingAddress->getTelephone()) > 19) {
			$response["errorMsg"] = __("Splitit does not accept phone number less than 5 digits or greater than 19 digits.");
		} elseif (!$this->billingAddress->getCity()) {
			$response["errorMsg"] = __("Splitit does not accept empty city field.");
		} elseif (!$this->billingAddress->getCountry()) {
			$response["errorMsg"] = __("Splitit does not accept empty country field.");
		} elseif (!$this->billingAddress->getPostcode()) {
			$response["errorMsg"] = __("Splitit does not accept empty postcode field.");
		} elseif (!$customerInfo["firstname"]) {
			$response["errorMsg"] = __("Splitit does not accept empty customer name field.");
		} elseif (strlen($customerInfo["firstname"] . ' ' . $customerInfo['lastname']) < 3) {
			$response["errorMsg"] = __("Splitit does not accept less than 3 characters customer name field.");
		} elseif (!filter_var($customerInfo['email'], FILTER_VALIDATE_EMAIL)) {
			$response["errorMsg"] = __("Splitit does not accept invalid customer email field.");
		} else {
			$response["status"] = true;
		}
		return $response;
	}

	/**
	 * Get approval url details from Splitit
	 * @param decodedResult string
	 * @return array
	 */
	public function getApprovalUrlResponse($decodedResult) {
		$response = ["errorMsg" => "", "successMsg" => "", "status" => false];
		$intallmentPlan = $decodedResult["InstallmentPlan"]["InstallmentPlanNumber"];
		/*set Installment plan number into session*/
		$this->customerSession->setInstallmentPlanNumber($intallmentPlan);
		$approvalUrlResponse = $this->getApprovalUrlResponseFromApi($decodedResult["ApprovalUrl"]);
		$approvalUrlRes = $this->helper->jsonDecode($approvalUrlResponse);
		/*check for curl error*/
		if (isset($approvalUrlRes["errorMsg"])) {
			$response["errorMsg"] = $approvalUrlRes["errorMsg"];
			return $response;
		}
		if (isset($approvalUrlRes["Global"]["ResponseResult"]["Errors"]) && count($approvalUrlRes["Global"]["ResponseResult"]["Errors"])) {
			$i = 1;
			$errorMsg = "";
			$errorCount = count($approvalUrlRes["Global"]["ResponseResult"]["Errors"]);
			foreach ($approvalUrlRes["Global"]["ResponseResult"]["Errors"] as $key => $value) {
				$errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
				if ($i < $errorCount) {
					$errorMsg .= ", ";
				}
				$i++;
			}
			$response["errorMsg"] = $errorMsg;
		} else if (isset($approvalUrlRes["serverError"])) {
			$response["errorMsg"] = $decodedResult["serverError"];
		} else {
			$popupHtml = $approvalUrlResponse;
			$response["status"] = true;
			$response["successMsg"] = $popupHtml;
		}

		return $response;
	}

	/**
	 * Curl requests
	 * @param gwUrl string
	 * @param method string
	 * @param params array
	 * @return json
	 */
	public function makePhpCurlRequest($gwUrl, $method, $params) {
		$url = trim($gwUrl, '/') . '/api/' . $method . '?format=JSON';
		$jsonData = $this->helper->jsonEncode($params);
		/**** As older version do not support  json request in curl***/
		try {

			$this->curl->setOption(CURLOPT_FOLLOWLOCATION, 1);
			$this->curl->setOption(CURLOPT_SSL_VERIFYPEER, 0);
			$this->curl->setHeaders(array(
				'Content-Type' => 'application/json',
				'Content-Length' => strlen($jsonData),
			));
			$this->curl->post($url, $jsonData);
			$result = $this->curl->getBody();

		} catch (\Exception $e) {
			$result["errorMsg"] = $this->getServerDownMsg();
			echo $e->getMessage();
			$result = $this->helper->jsonEncode($result);
		}
		return $result;

	}

	/**
	 * Get approval url response from Splitit
	 * @param approvalUrl string
	 * @return json
	 */
	public function getApprovalUrlResponseFromApi($approvalUrl) {
		$url = $approvalUrl . '&format=json';
		try {
			$this->curl->curlGetRequest($url);
			$result = $this->curl->getBody();

		} catch (\Exception $e) {
			$result["errorMsg"] = $this->getServerDownMsg();
			$result = $this->helper->jsonEncode($result);
		}
		return $result;
	}

	/**
	 * Set server down message
	 * @return string
	 */
	public function getServerDownMsg() {
		return "Failed to connect to splitit payment server. Please retry again later.";
	}

}
