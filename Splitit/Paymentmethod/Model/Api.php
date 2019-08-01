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

	public function __construct(
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Directory\Model\Currency $currency
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
			$popupHtml = $this->createPopupHtml($approvalUrlResponse);
			$response["status"] = true;
			$response["successMsg"] = $popupHtml;
		}

		return $response;
	}

	public function makePhpCurlRequest($gwUrl, $method, $params) {
		$url = trim($gwUrl, '/') . '/api/' . $method . '?format=JSON';
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

	public function createPopupHtml($approvalUrlResponse) {
		$approvalUrlResponseArr = json_decode($approvalUrlResponse, true);
		$html = '';
		if (!empty($approvalUrlResponseArr) && isset($approvalUrlResponseArr["Global"]["ResponseResult"]) && isset($approvalUrlResponseArr["Global"]["ResponseResult"]["Succeeded"]) && $approvalUrlResponseArr["Global"]["ResponseResult"]["Succeeded"] == 1) {

			$currencySymbol = $approvalUrlResponseArr["Global"]["Currency"]["Symbol"];
			$totalAmount = $approvalUrlResponseArr["HeaderSection"]["InstallmentPlanTotalAmount"]["Amount"];
			$totalText = $approvalUrlResponseArr["HeaderSection"]["InstallmentPlanTotalAmount"]["Text"];

			$scheduleChargedDateText = $approvalUrlResponseArr["ScheduledPaymentSection"]["ChargedDateText"];
			$scheduleChargedAmountText = $approvalUrlResponseArr["ScheduledPaymentSection"]["ChargedAmountText"];
			$scheduleRequiredAvailableCreditText = $approvalUrlResponseArr["ScheduledPaymentSection"]["RequiredAvailableCreditText"];

			$termsConditionsText = $approvalUrlResponseArr["ImportantNotesSection"]["AcknowledgeLink"]["Text"];
			$termsConditionsLink = $approvalUrlResponseArr["ImportantNotesSection"]["AcknowledgeLink"]["Link"];
			$servicesText = $approvalUrlResponseArr["LinksSection"]["PrivacyPolicy"]["Text"];
			$servicesLink = $approvalUrlResponseArr["LinksSection"]["PrivacyPolicy"]["Link"];

			$html .= '<div class="approval-popup_ovelay" style=""></div>';

			$html .= '<div id="approval-popup" style="">';

			$html .= '<div id="main">';
			$html .= '<div class="_popup_overlay"></div>';
			$html .= '<!-- Start small inner popup -->';

			// Start Term and Condition Popup
			$html .= '<div id="termAndConditionpopup" style=" ">
                    <div class="popup-block">';

			$html .= '<div class="popup-content" style="">';
			// start close button on terms-condition popup
			$html .= '<div class="popup-footer" style="">';
			$html .= '<div id="payment-schedule-close-btn" class="popup-btn"  style="">';
			$html .= '<div class="popup-btn-area-terms" style=""><span id="termAndConditionpopupCloseBtn" class="popup-btn-icon-terms" style="">x</span></div>';
			$html .= '</div>';
			$html .= '</div>';
			// end close button on terms-condition popup
			$html .= $this->getTermnConditionText() . '

                    </div>';

			$html .= '</div>';
			$html .= '</div>';
			// Close Term and Condition Popup
			$html .= '<div id="payment-schedule" style=" ">';
			$html .= '<div class="popup-block">';
			$html .= '<div class="popup-content" style="">';
			$html .= '<table class="popupContentTable" style="">';
			$html .= '<thead>';
			$html .= '<tr>';
			$html .= '<th style="width: 1em;"></th>';
			$html .= '<th style="text-align:center;">' . $scheduleChargedDateText . '</th>';
			$html .= '<th style="text-align:center;">' . $scheduleChargedAmountText . '</th>';
			$html .= '<th style="text-align:center;">' . $scheduleRequiredAvailableCreditText . '</th>';
			$html .= '</tr>';
			$html .= '</thead>';
			$html .= '<tbody>';
			$schedulePayment = ""; //echo $value["DateOfCharge"];//substr($value["DateOfCharge"], 0, strpos($value["DateOfCharge"], "To"));
			if (isset($approvalUrlResponseArr["ScheduledPaymentSection"]["ScheduleItems"])) {

				foreach ($approvalUrlResponseArr["ScheduledPaymentSection"]["ScheduleItems"] as $key => $value) {
					$dateOfChargeTemp = (string) $value["DateOfCharge"];
					$dataOfCharge = substr($dateOfChargeTemp, 0, strpos($dateOfChargeTemp, "T"));
					$date = date_create($dataOfCharge);

					$schedulePayment .= '<tr>';
					$schedulePayment .= '<td style="text-align: left;">' . $value["InstallmentNumber"] . '.</td>';
					$schedulePayment .= '<td>' . date_format($date, "m/d/Y") . '</td>';
					$schedulePayment .= '<td>' . $currencySymbol . $value["ChargeAmount"] . '</td>';
					$schedulePayment .= '<td>' . $currencySymbol . $value["RequiredAvailableCredit"] . '</td>';
					$schedulePayment .= '</tr>';
				}
			}
			$html .= $schedulePayment;
			$html .= '</tbody>';
			$html .= '</table>';
			$html .= '</div>';
			$html .= '<div class="popup-footer" style="">';
			$html .= '<div id="payment-schedule-close-btn" class="popup-btn"  style="">';
			$html .= '<div class="popup-btn-area" style=""><span id="complete-payment-schedule-close" class="popup-btn-icon" style="">Close</span></div>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '<!-- End small inner popup -->';

			$html .= '<div class="mainHeader">';
			$html .= '<span class="closeapprovalpopup_btn" style="" onclick="closeApprovalPopup();">x</span>';
			$html .= '<table id="wiz-header" width="100%;">';
			$html .= '<tbody>';
			$html .= '<tr>';
			$html .= '<td class="wiz-header-side wiz-header-left" style=""></td>';
			$html .= '<td class="wiz-header-center" style="">';
			$html .= '<div>TOTAL PURCHASE:</div>';
			$html .= '<div class="currencySymbolIcon" style="">' . $currencySymbol . $totalAmount . '</div></td><td class="wiz-header-side wiz-header-right" style="">';
			$html .= '</td>';
			$html .= '</tr>';
			$html .= '</tbody>';
			$html .= '</table>';
			$html .= '</div>';
			$html .= '<div style="margin-top: auto;">';

			$html .= '<div class="form-block" style="">';
			$html .= '<div class="form-block-area" style="">';
			$html .= '<div class="spacer15" style=""></div>';
			$html .= '<div class="tableResponsive"><table class="tablePage2" style="" cellspacing="0" cellpadding="0">';
			$html .= '<tbody>';

			$planDataSection = '';
			$planDataSectionHtml = '';
			$planDataSection = $approvalUrlResponseArr["PlanDataSection"];
			if (isset($approvalUrlResponseArr["PlanDataSection"])) {
				$planDataSectionHtml .= '<tr class="tablePage2TD"  style="">';
				$planDataSectionHtml .= '<td>' . $planDataSection["NumberOfInstallments"]["Text"] . '</td>';
				$planDataSectionHtml .= '<td class="text-right" style="">';
				$planDataSectionHtml .= '<span>' . $planDataSection["NumberOfInstallments"]["NumOfInstallments"] . '</span>';
				$planDataSectionHtml .= '</td></tr>';

				$planDataSectionHtml .= '<tr class="tablePage2TD" style="">';
				$planDataSectionHtml .= '<td>' . $planDataSection["FirstInstallmentAmount"]["Text"] . '</td>';
				$planDataSectionHtml .= '<td class="text-right" style="">';
				$planDataSectionHtml .= '<span>' . $currencySymbol . $planDataSection["FirstInstallmentAmount"]["Amount"] . '</span>';
				$planDataSectionHtml .= '</td></tr>';

				$planDataSectionHtml .= '<tr class="tablePage2TD" style="">';
				$planDataSectionHtml .= '<td>' . $planDataSection["SubsequentInstallmentAmount"]["Text"] . '</td>';
				$planDataSectionHtml .= '<td class="text-right">';
				$planDataSectionHtml .= '<span>' . $currencySymbol . $planDataSection["SubsequentInstallmentAmount"]["Amount"] . '</span>';
				$planDataSectionHtml .= '</td></tr>';

				$planDataSectionHtml .= '<tr class="tablePage2TD" style="">';
				$planDataSectionHtml .= '<td>' . $planDataSection["RequiredAvailableCredit"]["Text"] . '</td>';
				$planDataSectionHtml .= '<td class="text-right" style="">';
				$planDataSectionHtml .= '<span>' . $currencySymbol . $planDataSection["RequiredAvailableCredit"]["Amount"] . '</span>';
				$planDataSectionHtml .= '</td></tr>';
			}

			$html .= $planDataSectionHtml;
			$html .= '</tbody>';
			$html .= '</table></div>';
			$html .= '<a id="payment-schedule-link" style="">See Complete Payment Schedule</a>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '<div class="form-block right" style="">';
			$html .= '<div class="form-block-area">';
			$html .= '<div>';
			$html .= '<div class="important_note_sec" style="">' . $approvalUrlResponseArr["ImportantNotesSection"]["ImportantNotesHeader"]["Text"] . ':</div>';
			$html .= '<div class="pnlEula" style="">' . $approvalUrlResponseArr["ImportantNotesSection"]["ImportantNotesBody"]["Text"] . '</div>';
			$html .= '<div id="i_acknowledge_area"><input type="checkbox" id="i_acknowledge" class="i_acknowledge" name="i_acknowledge" value="" />';
			$html .= '<label for="i_acknowledge" class="i_acknowledge_lbl">';
			$html .= 'I acknowledge that I have read and agree to the <a href="#" id="i_acknowledge_content_show" > terms and conditions </a> </label><div style="display:none" class="i_ack_err"> Please select I acknowledge.</div></div>';

			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '<div class="iAgreeBtn" style=""><input id="iagree" type="button" onclick="paymentSave();" value="I agree" style=" ">  </div>';
			$html .= '<div class="termAndConditionBtn" style=""> <a href="' . $termsConditionsLink . '" target="_blank" style="">' . $termsConditionsText . '</a> | <a href="' . $servicesLink . '" style="" target="_blank">' . $servicesText . '</a>

</div>';

			$html .= '</div>';
		}

		return $html;
	}

	public function getTermnConditionText() {
		$str = '<p style="text-align: left;">1. &nbsp;Buyer, whose name appears below ("Buyer", "You", or "Your"), promises to pay the full amount of the Total Authorized Purchase Price in the number of installment payments set forth in the Recurring Installment Payment Authorization ("Authorization") to Seller ("Seller", "We" or "Us") by authorizing Seller to charge Buyer’s credit card in equal monthly installments as set forth in the Authorization (each an "Installment") each month until paid in full.</p>
<p style="text-align: left;">2. &nbsp;Buyer agrees that Seller will obtain authorization on Buyer’s credit card for the full amount of the Purchase at the time of sale, and Seller will obtain authorizations on Buyer’s credit card each month for the Installment and the entire remaining balance of the Purchase. Buyer understands that this authorization will remain in effect until Buyer cancels it in writing.</p>
<p style="text-align: left;">3. &nbsp;Buyer acknowledges that Seller obtaining initial authorization for the Purchase, along with monthly authorization for each Installment and the outstanding balance, may adversely impact Buyer’s available credit on Buyer’s credit card. Buyer agrees to hold Seller harmless for any adverse consequences to Buyer.</p>
<p style="text-align: left;">4. &nbsp;Buyer agrees to notify Seller in writing via Buyer’s user account at <a title="consumer.Splitit.com" href="http://consumer.Splitit.com" target="_blank">consumer.splitit.com</a> of any changes to Buyer’s credit card account information or termination of this authorization. We will update such information and process such requests within 30 days after our receipt of such request. Buyer understands that the Installment payments may be authorized and charged on the next business day. Buyer further understands that because these are electronic transactions, any authorizations and charges may be posted to Your account as soon as the Installment payment dates.</p>
<p style="text-align: left;">5. &nbsp;Any Installment amounts due under this contract that have been charged to Buyer’s credit card and not paid when due, pursuant to Your agreement with Your credit card issuer ("Issuer"), will be charged interest at the Annual Percentage Rate stated in Your Issuer’s Federal Truth-in-Lending Disclosure statement until the Installments are fully paid. So long as You timely pay each Installment to Your Issuer when due, Issuer will not charge Buyer interest on such Installment. Issuer may charge Buyer interest on any other balance You may have on Your credit card in excess of the Installment amount.</p>
<p style="text-align: left;">6. &nbsp;In the case of an authorization being rejected for any reason, Buyer understands that Seller may, in its discretion, attempt to process the charge again within seven&nbsp;(7) days.</p>
<p style="text-align: left;">7. &nbsp;In the event that Buyer’s Issuer fails to pay an Installment for any reason, Seller, at its discretion, may charge Buyer’s credit card at any time for the full outstanding amount due.</p>
<p style="text-align: left;">8. &nbsp;In consideration for services provided by Splitit&nbsp;USA, Inc. ("Splitit") to Seller, Buyer agrees that Splitit will have the right to communicate with and solicit Buyer via e-mail (or other means). This provision is operational for not less than five (5) years from the date of the initial authorization.<br>
9. &nbsp;Buyer understands that Splitit is not a party to this Agreement, which is solely between Buyer and Seller.</p>
<p style="text-align: left;">10. &nbsp;Buyer understands and agrees that Splitit is not responsible for the delivery and quality of goods purchased in this transaction.</p>
<p style="text-align: left;">11. &nbsp;Buyer acknowledges that the origination of any authorized transactions to the Buyer’s account must comply with the provisions of U.S. law. Buyer certifies that Buyer is an authorized user of the credit card utilized for this transaction and the Installments and will not dispute these transactions with Buyer’s credit card company, so long as the authorizations correspond to the terms indicated in the authorization form.</p>
<p style="text-align: left;">12. &nbsp;Buyer agrees that if delivery of the goods or services are not made at the time of execution of this contract, the description of the goods or services and the due date of the first Installment may be inserted by Seller in Seller’s counterpart of the contract after it has been signed by Buyer.</p>
<p style="text-align: left;">13. &nbsp;If any provision of this contract is determined to be invalid, it shall not affect the remaining provisions hereof.</p>
<p style="text-align: left;">14. &nbsp;PRIVACY POLICY. Buyer’s privacy is important to us. You may obtain a copy of Splitit’s Privacy Policy by visiting their website at <a title="consumer.Splitit.com" href="http://consumer.Splitit.com" target="_blank">consumer.splitit.com</a>. As permitted by law, Seller and Splitit may share information about our transactions and experiences with Buyer with other affiliated companies and unaffiliated third parties, including consumer reporting agencies and other creditors. However, except as permitted by law, neither Seller nor Splitit may share information which was obtained from credit applications, consumer reports, and any third parties with companies affiliated with us if Buyer instructs us not to share this information. If Buyer does not want us to share this information, Buyer shall notify us in writing via Buyer’s user account at <a title="consumer.Splitit.com" href="http://consumer.Splitit.com" target="_blank">consumer.splitit.com</a> using the password Buyer was provided with for such notification and for accessing information on Splitit’s website. Buyer shall include Buyer’s name, address, account number and the last four digits of Buyer’s credit card number used in this transaction so such request can be honored. Seller may report about Your account to consumer reporting agencies. Late payments, missed payments, or other defaults on Your credit card account may be reflected by Your Issuer in Your credit report.</p>
<p style="text-align: left;">15. &nbsp;ARBITRATION. Any claim, dispute or controversy ("Claim") arising from or connected with this Agreement, including the enforceability, validity or scope of this arbitration clause or this Agreement, shall be governed by this provision. Upon the election of Buyer or Seller by written notice to the other party, any Claim shall be resolved by arbitration before a single arbitrator, on an individual basis, without resort to any form of class action ("Class Action Waiver"), pursuant to this arbitration provision and the applicable rules of the American Arbitration Association ("AAA") in effect at the time the Claim is filed. Any arbitration hearing shall take place within the State of New York, County of New York. At the written request of Buyer, any filing and administrative fees charged or assessed by the AAA which are required to be paid by Buyer and that are in excess of any filing fee Buyer would have been required to pay to file a Claim in state court in New York shall be advanced and paid for by Seller. The arbitrator may not award punitive or exemplary damages against any party. IF ANY PARTY COMMENCES ARBITRATION WITH RESPECT TO A CLAIM, NEITHER BUYER OR SELLER WILL HAVE THE RIGHT TO LITIGATE THAT CLAIM IN COURT OR HAVE A JURY TRIAL ON THAT CLAIM, OR TO ENGAGE IN PRE-ARBITRATION DISCOVERY, EXCEPT AS PROVIDED FOR IN THE APPLICABLE ARBITRATION RULES. FURTHER, BUYER WILL NOT HAVE THE RIGHT TO PARTICIPATE AS A REPRESENTATIVE OR MEMBER OF ANY CLASS OF CLAIMANTS PERTAINING TO THAT CLAIM, AND BUYER WILL HAVE ONLY THOSE RIGHTS THAT ARE AVAILABLE IN AN INDIVIDUAL ARBITRATION. THE ARBITRATOR’S DECISION WILL BE FINAL AND BINDING ON ALL PARTIES, EXCEPT AS PROVIDED IN THE FEDERAL ARBITRATION ACT ("the FAA"). This Arbitration Provision shall be governed by the FAA, and, if and where applicable, the internal laws of the State of New York. If any portion of this Arbitration provision is deemed invalid or unenforceable, it shall not invalidate the remaining portions of this Arbitration provision or the Agreement, provided however, if the Class Action Waiver is deemed invalid or unenforceable, then this entire Arbitration provision shall be null and void and of no force or effect, but the remaining terms of this Agreement shall remain in full force and effect. Any appropriate court having jurisdiction may enter judgment on any award.</p>';

		return $str;
	}

}
