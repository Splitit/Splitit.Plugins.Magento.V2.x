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
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Directory\Model\Currency $currency,
		\Magento\Framework\HTTP\Client\Curl $curl
	) {

		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$this->helper = $objectManager->get('Splitit\Paymentmethod\Helper\Data');
		$cart = $objectManager->get("\Magento\Checkout\Model\Cart");
		$this->quote = $cart->getQuote();
		$this->grandTotal = round($cart->getQuote()->getGrandTotal(), 2);
		$this->shippingAddress = $cart->getQuote()->getShippingAddress();
		$this->shippingAmount = round($this->shippingAddress->getShippingAmount(), 2);
		$this->taxAmount = round($this->shippingAddress->getTaxAmount(), 2);

		$this->billingAddress = $cart->getQuote()->getBillingAddress();

		$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
		$this->currencyCode = $storeManager->getStore()->getCurrentCurrencyCode();
		$this->currencySymbol = $currency->load($this->currencyCode)->getCurrencySymbol();

		$this->curl = $curl;

		$this->customerSession = $customerSession;

		$this->countryFactory = $objectManager->get('\Magento\Directory\Model\CountryFactory');
		$this->productModel = $objectManager->get('Magento\Catalog\Model\Product');
		$this->logger = $objectManager->get('\Psr\Log\LoggerInterface');
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

	public function apiLogin($dataForLogin = array()) {

		$apiUrl = $this->getApiUrl();
		if (empty($dataForLogin)) {
			$dataForLogin = array(
				'UserName' => $this->helper->getConfig("payment/splitit_paymentmethod/api_username"),
				'Password' => $this->helper->getConfig("payment/splitit_paymentmethod/api_password"),
				'TouchPoint' => array("Code" => "MagentoPlugin", "Version" => "v2.1"),
			);
		}

		$result = $this->makePhpCurlRequest($apiUrl, "Login", $dataForLogin);
		$decodedResult = json_decode($result, true);

		$response = ["splititSessionId" => "", "errorMsg" => "", "successMsg" => "", "status" => false];
		if ($decodedResult) {
			// check for curl error
			if (isset($decodedResult["errorMsg"])) {
				$response["errorMsg"] = $decodedResult["errorMsg"];
				return $response;
			}

			// get splitit session id
			$response["splititSessionId"] = (isset($decodedResult['SessionId']) && $decodedResult['SessionId'] != '') ? $decodedResult['SessionId'] : null;
			// get success status
			if (isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1) {
				$response["status"] = true;
			}
			// get error message if not success
			if (is_null($response["splititSessionId"])) {
				$response["errorMsg"] = $this->getErrorFromApi($decodedResult);
				return $response;
			}
			// set splitit session id in session
			$this->customerSession->setSplititSessionid($response["splititSessionId"]);
		}
		return $response;
	}

	public function installmentPlanInit($selectedInstallment, $guestEmail) {
		try {
			$response = ["errorMsg" => "", "successMsg" => "", "status" => false];
			$apiUrl = $this->getApiUrl();
			$this->guestEmail = $guestEmail;
			$params = $this->createDataForInstallmentPlanInit($selectedInstallment);
			$this->customerSession->setSelectedInstallment($selectedInstallment);
			// check if cunsumer dont filled data in billing form in case of onepage checkout.
			$billingFieldsEmpty = $this->checkForBillingFieldsEmpty();
			if (!$billingFieldsEmpty["status"]) {
				$response["errorMsg"] = $billingFieldsEmpty["errorMsg"];
				return $response;
			}
			// call Installment Plan Initiate api to get Approval URL
			$result = $this->makePhpCurlRequest($apiUrl, "InstallmentPlan/Initiate", $params);
			$decodedResult = json_decode($result, true);
			//print_r($decodedResult);die("--sfdfs");
			// check for curl error
			if (isset($decodedResult["errorMsg"])) {
				$response["errorMsg"] = $decodedResult["errorMsg"];
				return $response;
			}

			// check for approval URL from response
			if (isset($decodedResult) && isset($decodedResult["ApprovalUrl"]) && $decodedResult["ApprovalUrl"] != "") {
				// get response from Approval Url
				$approvalUrlResponse = $this->getApprovalUrlResponse($decodedResult);
				// check for curl error
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
		} catch (Exception $e) {
			$response["errorMsg"] = $e->getMessage();
		}

		return $response;
	}

	public function createDataForInstallmentPlanInit($selectedInstallment) {

		$firstInstallmentAmount = $this->getFirstInstallmentAmount($selectedInstallment);
		$cultureName = $this->helper->getCultureName();
		//print_r($this->billingAddress->getData());die;
		//print_r($this->billingAddress->getStreet());die("--sdf");
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
		$paymentAction = $this->helper->getConfig('payment/splitit_paymentmethod/payment_action');
		if ($paymentAction == "authorize_capture") {
			$autoCapture = true;
		}
		/*if (strlen($this->billingAddress->getTelephone()) < 5) {
			throw new \Magento\Framework\Validator\Exception(__("Splitit does not accept phone number less than 5 digits."));
		}*/

		$params = [
			"RequestHeader" => [
				"SessionId" => $this->getorCreateSplititSessionid(),
				"ApiKey" => $this->helper->getConfig("payment/splitit_paymentmethod/api_terminal_key"),
			],
			"PlanData" => [
				"Amount" => [
					"Value" => $this->grandTotal,
					"CurrencyCode" => $this->currencyCode,
				],
				"NumberOfInstallments" => $selectedInstallment,
				"PurchaseMethod" => "ECommerce",
				//"RefOrderNumber" => $quote_id,
				"FirstInstallmentAmount" => [
					"Value" => $firstInstallmentAmount,
					"CurrencyCode" => $this->currencyCode,
				],
				"AutoCapture" => $autoCapture,
				"ExtendedParams" => [
					"CreateAck" => "NotReceived",
				],
			],
			"BillingAddress" => [
				"AddressLine" => $billingStreet1,
				"AddressLine2" => $billingStreet2,
				"City" => $this->billingAddress->getCity(),
				"State" => $this->billingAddress->getRegion(),
				"Country" => $this->countryFactory->create()->loadByCode($this->billingAddress->getCountry())->getName('en_US'),
				"Zip" => $this->billingAddress->getPostcode(),
			],
			"ConsumerData" => [
				"FullName" => $customerInfo["firstname"] . " " . $customerInfo["lastname"],
				"Email" => $customerInfo["email"],
				"PhoneNumber" => $this->billingAddress->getTelephone(),
				"CultureName" => $cultureName,
			],
		];
		$cart = $this->quote;
		$itemsArr = array();
		$i = 0;
		$currencyCode = $this->currencyCode;
		foreach ($cart->getAllItems() as $item) {
			$description = $this->productModel->load($item->getProductId())->getShortDescription();
			//$this->productModel->load($item->getProductId(‌​))->getShortDescription()‌​;
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
		//print_r($params);die;
		//        print_r($params);
		return $params;
	}

	public function getApiUrl() {

		$helper = $this->helper;
		if ($helper->getConfig("payment/splitit_paymentmethod/sandbox_flag")) {
			return $helper->getConfig("payment/splitit_paymentmethod/api_url_sandbox");
		}
		return $helper->getConfig("payment/splitit_paymentmethod/api_url");
	}

	public function installmentplaninitforhostedsolution($params) {
		try {
			return $this->makePhpCurlRequest($this->getApiUrl(), "InstallmentPlan/Initiate", $params);
		} catch (\Exception $e) {
			$this->setError($e->getMessage());
		}

	}

	public function getInstallmentPlanDetails($apiUrl, $params) {
		try {
			return $this->makePhpCurlRequest($apiUrl, "InstallmentPlan/Get", $params);
		} catch (\Exception $e) {
			$this->setError($e->getMessage());
		}
	}

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

	public function getErrorFromApi($decodedResult) {
		$errorMsg = "";
		if (isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])) {

			$i = 1;
			foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
				$errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
				if ($i < count($decodedResult["ResponseHeader"]["Errors"])) {
					$errorMsg .= ", ";
				}
				$i++;
			}
		}
		return $errorMsg;
	}

	public function getFirstInstallmentAmount($selectedInstallment) {

		$firstPayment = $this->helper->getConfig('payment/splitit_paymentmethod/first_payment');
		$percentageOfOrder = $this->helper->getConfig('payment/splitit_paymentmethod/percentage_of_order');

		$selectedInstallmentAmount = round($this->grandTotal / $selectedInstallment, 2);
		//print_r($this->shippingAddress->getTaxAmount());die("---sfsf");
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
		if ($this->billingAddress->getStreet()[0] == "" || $this->billingAddress->getCity() == "" || $this->billingAddress->getPostcode() == "" || $customerInfo["firstname"] == "" || $customerInfo["lastname"] == "" || $customerInfo["email"] == "" || $this->billingAddress->getTelephone() == "") {
			$response["errorMsg"] = "Please fill required fields.";
		} else if (strlen($this->billingAddress->getTelephone()) < 5 || strlen($this->billingAddress->getTelephone()) > 10) {

			$response["errorMsg"] = __("Splitit does not accept phone number less than 5 digits or greater than 10 digits.");
		} elseif (!$this->billingAddress->getCity()) {
			$response["errorMsg"] = __("Splitit does not accept empty city field.");
		} elseif (!$this->billingAddress->getCountry()) {
			$response["errorMsg"] = ("Splitit does not accept empty country field.");
		} elseif (!$this->billingAddress->getPostcode()) {
			$response["errorMsg"] = ("Splitit does not accept empty postcode field.");
		} elseif (!$customerInfo["firstname"]) {
			$response["errorMsg"] = ("Splitit does not accept empty customer name field.");
		} elseif (strlen($customerInfo["firstname"] . ' ' . $customerInfo['lastname']) < 3) {
			$response["errorMsg"] = ("Splitit does not accept less than 3 characters customer name field.");
		} elseif (!filter_var($customerInfo['email'], FILTER_VALIDATE_EMAIL)) {
			$response["errorMsg"] = ("Splitit does not accept invalid customer email field.");
		} else {
			$response["status"] = true;
		}
		return $response;
	}

	public function getApprovalUrlResponse($decodedResult) {
		$response = ["errorMsg" => "", "successMsg" => "", "status" => false];
		$intallmentPlan = $decodedResult["InstallmentPlan"]["InstallmentPlanNumber"];
		// set Installment plan number into session
		$this->customerSession->setInstallmentPlanNumber($intallmentPlan);
		$approvalUrlResponse = $this->getApprovalUrlResponseFromApi($decodedResult["ApprovalUrl"]);
		$approvalUrlRes = json_decode($approvalUrlResponse, true);
		// check for curl error
		if (isset($approvalUrlRes["errorMsg"])) {
			$response["errorMsg"] = $approvalUrlRes["errorMsg"];
			return $response;
		}
		if (isset($approvalUrlRes["Global"]["ResponseResult"]["Errors"]) && count($approvalUrlRes["Global"]["ResponseResult"]["Errors"])) {
			$i = 1;
			$errorMsg = "";
			foreach ($approvalUrlRes["Global"]["ResponseResult"]["Errors"] as $key => $value) {
				$errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
				if ($i < count($approvalUrlRes["Global"]["ResponseResult"]["Errors"])) {
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

	public function makePhpCurlRequest($gwUrl, $method, $params) {
		$url = trim($gwUrl, '/') . '/api/' . $method . '?format=JSON';
		$jsonData = json_encode($params);
		/**** As older version do not support  json request in curl***/
		if (version_compare($this->helper->getMagentoVersion(), '2.2.1', '<')) {
			$ch = curl_init($url);
			$jsonData = json_encode($params);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length:' . strlen($jsonData))
			);
			$result = curl_exec($ch);

			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			// check for curl error eg: splitit server down.
			if (curl_errno($ch)) {
				//echo 'Curl error: ' . curl_error($ch);
				$result["errorMsg"] = $this->getServerDownMsg();
				$result = json_encode($result);
			}
			curl_close($ch);
		} else {
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
				$result = json_encode($result);
			}
		}
		return $result;

	}

	public function getSplititSupportedCultures($approvalUrl) {
		$url = $approvalUrl . '?format=json';
		$ch = curl_init($url);
		//$jsonData = json_encode($params);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		$result = curl_exec($ch);

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		// check for curl error eg: splitit server down.
		if (curl_errno($ch)) {
			//echo 'Curl error: ' . curl_error($ch);
			$result["serverError"] = $this->getServerDownMsg();
			return $result = json_encode($result);
		}
		curl_close($ch);
		return $result;
	}

	public function getApprovalUrlResponseFromApi($approvalUrl) {
		$url = $approvalUrl . '&format=json';
		$ch = curl_init($url);
		$jsonData = json_encode("");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length:' . strlen($jsonData))
		);
		$result = curl_exec($ch);

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		// check for curl error eg: splitit server down.
		if (curl_errno($ch)) {
			//echo 'Curl error: ' . curl_error($ch);
			$result["errorMsg"] = $this->getServerDownMsg();
			$result = json_encode($result);
		}
		curl_close($ch);
		return $result;
	}

	public function getServerDownMsg() {
		return "Failed to connect to splitit payment server. Please retry again later.";
	}

}
