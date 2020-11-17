<?php

/**
 * Payment payment method model
 *
 * @category    Splitit
 * @package     Splitit_Paymentmethod
 * @copyright   Splitit (http://Splitit.net)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Splitit\Paymentmethod\Model;

class PaymentForm extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'splitit_paymentredirect';

    protected $_code = self::CODE;
    protected $_isInitializeNeeded = true;
    protected $_canUseInternal = true;
    protected $_canUseForMultishipping = false;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canCaptureOnce = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = false;
    protected $_canUseCheckout = true;
    protected $_canCancel = false;
    protected $api;
    protected $helper;
    protected $checkoutSession;
    protected $customerSession;
    protected $quote;
    protected $quoteValidator;
    protected $store;
    protected $logger;
    protected $orderPlace;
    protected $productModel;
    protected $sourceInstallments;
    protected $storeManager;
    protected $cart;
    protected $urlBuilder;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        Api $api,
        \Magento\Quote\Model\QuoteValidator $quoteValidator,
        Helper\OrderPlace $orderPlace,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Store\Api\Data\StoreInterface $store,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Splitit\Paymentmethod\Helper\Data $helper,
        \Splitit\Paymentmethod\Model\Source\Installments $sourceInstallments,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Catalog\Model\ProductRepository $productModel
    ) {
        $this->api = $api;
        $this->quoteValidator = $quoteValidator;
        $this->orderPlace = $orderPlace;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->store = $store;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
        $this->quote = $this->checkoutSession->getQuote();
        $this->helper = $helper;
        $this->productModel = $productModel;
        $this->sourceInstallments = $sourceInstallments;
        $this->storeManager = $storeManager;
        $this->cart = $cart;
    }

    /**
     * Get order redirect url for hosted
     * @return array
     */
    public function orderPlaceRedirectUrl()
    {

        $this->logger->error("FILE: " . __FILE__ . "\n LINE: " . __LINE__ . "\n Method: " . __METHOD__);

        $response = array(
            "status" => false,
            "error" => "",
            "success" => "",
            "data" => "",
            "checkoutUrl" => "",
            "installmentNum" => "1",
        );

        /*check for address*/
        $quote = $this->quote;
        $billAddress = $quote->getBillingAddress();
        $customerInfo = $this->customerSession->getCustomer()->getData();
        if (!isset($customerInfo["firstname"])) {
            $customerInfo["firstname"] = $billAddress->getFirstname();
            $customerInfo["lastname"] = $billAddress->getLastname();
            $customerInfo["email"] = $billAddress->getEmail();
        }

        $validateAddress = $this->checkForBillingFieldsEmpty($billAddress, $customerInfo);
        if (!$validateAddress['status']) {
            $response["status"] = false;
            $response["error"] = true;
            $response["errorMsg"] = $validateAddress['errorMsg'];

            return $response;
        }
        $initResponse = $this->installmentplaninitForHostedSolution();

        $response["data"] = $initResponse["data"];
        if ($initResponse["status"]) {
            $response["status"] = true;
        }

        if (isset($initResponse["checkoutUrl"]) && $initResponse["checkoutUrl"] != "") {
            $response["checkoutUrl"] = $initResponse["checkoutUrl"];

            $quote = $this->quote;
            $billAddress = $quote->getBillingAddress();
            $customerInfo = $this->customerSession->getCustomer()->getData();
            if (!isset($customerInfo["firstname"])) {
                $customerInfo["firstname"] = $billAddress->getFirstname();
                $customerInfo["lastname"] = $billAddress->getLastname();
                $customerInfo["email"] = $billAddress->getEmail();
            }
            $bags = $billAddress->getStreet();

            if (!($bags[0] == "" || $billAddress->getCity() == "" || $billAddress->getPostcode() == "" || $customerInfo["firstname"] == "" || $customerInfo["lastname"] == "" || $customerInfo["email"] == "" || $billAddress->getTelephone() == "")) {
                if ($this->quoteValidator->validateBeforeSubmit($quote)) {
                }
            }
            $this->checkoutSession->setSplititQuoteId($quote->getId());
            $this->checkoutSession->setSplititCheckoutUrl($response["checkoutUrl"]);
            $this->checkoutSession->setSplititInstallmentPlanNumber($initResponse["installmentPlanNumber"]);

            return $response;
        } else {
            $this->logger->error(__($response['data']));
            $response["status"] = false;
            $response["error"] = true;
            return $response;
        }
    }

    /**
     * Validation for billing fields
     * @param billingAddress object
     * @param customerInfo object
     * @return array
     */
    public function checkForBillingFieldsEmpty($billingAddress, $customerInfo)
    {

        $response = ["errorMsg" => "", "successMsg" => "", "status" => false];
        if ($billingAddress->getStreet()[0] == "") {
            $response["errorMsg"] = __("Splitit does not accept empty street field.");
        } elseif ($customerInfo["email"] == "") {
            $response["errorMsg"] = __("Splitit does not accept empty email field.");
        } elseif ($billingAddress->getTelephone() == "") {
            $response["errorMsg"] = __("Splitit does not accept empty phone field.");
        } else if (strlen($billingAddress->getTelephone()) < 5 || strlen($billingAddress->getTelephone()) > 19) {
            $response["errorMsg"] = __("Splitit does not accept phone number less than 5 digits or greater than 19 digits.");
        } elseif (!$billingAddress->getCity()) {
            $response["errorMsg"] = __("Splitit does not accept empty city field.");
        } elseif (!$billingAddress->getCountry()) {
            $response["errorMsg"] = __("Splitit does not accept empty country field.");
        } elseif (!$billingAddress->getPostcode()) {
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
     * get checkout redirect url
     * @return array
     */
    public function getCheckoutRedirectUrl()
    {
        $data = $this->orderPlaceRedirectUrl();
        return $data['checkoutUrl'];
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     */
    public function validate()
    {
        return $this;
    }

    /**
     * create the installment plan
     * @param api object
     * @param payment object
     * @param amount float
     * @return array
     */
    protected function createInstallmentPlan($api, $payment, $amount)
    {
        $cultureName = $this->helper->getCultureName(true);
        $dataForLogin = array(
            'UserName' => $this->helper->getApiUsername("splitit_paymentredirect"),
            'Password' => $this->helper->getApiPassword("splitit_paymentredirect"),
            'TouchPoint' => $this->helper->getApiTouchPointVersion(),
        );
        $params = array(
            "RequestHeader" => array(
                "SessionId" => $this->api->getorCreateSplititSessionid($dataForLogin),
                "ApiKey" => $this->helper->getApiTerminalKey('splitit_paymentredirect'),
                "CultureName" => $cultureName,
            ),
            "InstallmentPlanNumber" => $this->customerSession->getInstallmentPlanNumber(),
            "CreditCardDetails" => array(
                "CardCvv" => $payment->getCcCid(),
                "CardNumber" => $payment->getCcNumber(),
                "CardExpYear" => $payment->getCcExpYear(),
                "CardExpMonth" => $payment->getCcExpMonth(),
            ),
            "PlanApprovalEvidence" => array(
                "AreTermsAndConditionsApproved" => "True",
            ),
        );
        $result = $api->createInstallmentPlan($this->api->getApiUrl(), $params);
        if (isset($result["ResponseHeader"]) && isset($result["ResponseHeader"]["Errors"]) && !empty($result["ResponseHeader"]["Errors"])) {
            $e = $api->getError();
            throw new \Magento\Framework\Validator\Exception($e['code'] . ' ' . $e['message']);
        }
        return $result;
    }

    /**
     * @param $storeId int
     *
     * @return PayItSimple_Payment_Model_Api
     * @throws Mage_Payment_Exception
     */
    public function _initApi()
    {
        $dataForLogin = array(
            'UserName' => $this->helper->getApiUsername("splitit_paymentredirect"),
            'Password' => $this->helper->getApiPassword("splitit_paymentredirect"),
            'TouchPoint' => $this->helper->getApiTouchPointVersion(),
        );
        $result = $this->api->apiLogin($dataForLogin);
        if (isset($result["serverError"])) {
            throw new \Magento\Framework\Validator\Exception(__($result["serverError"]));
        }
        return $result;
    }

    /**
     * get number of installments
     * @param api object
     * @return int
     */
    public function getValidNumberOfInstallments($api)
    {
        return $result = $api->getValidNumberOfInstallments();
    }

    /**
     * Update order in magento
     * @param api object
     * @param order int
     * @return array
     */
    public function updateRefOrderNumber($api, $order)
    {

        $this->logger->addDebug("FILE: " . __FILE__ . "\n LINE: " . __LINE__ . "\n Method: " . __METHOD__);
        $dataForLogin = array(
            'UserName' => $this->helper->getApiUsername("splitit_paymentredirect"),
            'Password' => $this->helper->getApiPassword("splitit_paymentredirect"),
            'TouchPoint' => $this->helper->getApiTouchPointVersion(),
        );
        $params = array(
            "RequestHeader" => array(
                "SessionId" => $this->api->getorCreateSplititSessionid($dataForLogin),
            ),
            "InstallmentPlanNumber" => $this->checkoutSession->getSplititInstallmentPlanNumber(),
            "PlanData" => array(
                "ExtendedParams" => array(
                    "CreateAck" => "Received",
                ),
                "RefOrderNumber" => '',
            ),
        );
        if ($order instanceof \Magento\Quote\Model\Quote\Interceptor) {
            $params['PlanData']['RefOrderNumber'] = $order->getId();
        } else {
            $params['PlanData']['RefOrderNumber'] = $order->getIncrementId();
        }
        $this->logger->addDebug('========== splitit update ref order number params ==============');
        $this->logger->addDebug(print_r($params, TRUE));
        $response = array("status" => false, "data" => "");
        $result = $api->updateRefOrderNumber($this->api->getApiUrl(), $params);
        $decodedResult = $this->helper->jsonDecode($result);
        if (isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1) {
            $response["status"] = true;
        } else if (isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])) {
            $errorMsg = "";
            $errorCount = count($decodedResult["ResponseHeader"]["Errors"]);
            $i = 1;
            foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
                $errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
                if ($i < $errorCount) {
                    $errorMsg .= ", ";
                }
                $i++;
            }

            $response["data"] = $errorMsg;
        }
        return $response;
    }

    /**
     * initialization of plam
     * @return array
     */
    public function installmentplaninitForHostedSolution()
    {
        $this->logger->error("FILE: " . __FILE__ . "\n LINE: " . __LINE__ . "\n Method: " . __METHOD__);
        $this->logger->addDebug("FILE: " . __FILE__ . "\n LINE: " . __LINE__ . "\n Method: " . __METHOD__);
        $session = $this->checkoutSession;
        $quote_id = $session->getQuoteId();
        $firstInstallmentAmount = $this->getFirstInstallmentAmountHosted();
        $checkout = $this->checkoutSession->getQuote();
        $billAddress = $checkout->getBillingAddress();
        $BillingAddressArr = $billAddress->getData();
        $customerInfo = $this->customerSession->getCustomer()->getData();
        $numOfInstallments = $this->checkoutSession->getInstallmentsInDropdownForPaymentForm();

        if (!isset($customerInfo["firstname"])) {
            $customerInfo["firstname"] = $billAddress->getFirstname();
            $customerInfo["lastname"] = $billAddress->getLastname();
            $customerInfo["email"] = $billAddress->getEmail();
        }
        $cultureName = $this->helper->getCultureName(true);
        $params = $this->installmentplaninitParams($firstInstallmentAmount, $billAddress, $customerInfo, $cultureName, $numOfInstallments, null);
        $this->logger->error('======= installmentplaninitForHostedSolution : params passed to Initit Api ======= : ');
        $this->logger->error(print_r($params, TRUE));
        $this->logger->error($this->helper->jsonEncode($params));
        $this->logger->error('======= END ======= : ');

        try {
            $response = array("status" => false, "data" => "", "checkoutUrl" => "");
            /*check if cunsumer dont filled data*/
            $bags = $billAddress->getStreet();
            if ($bags[0] == "" || $billAddress->getCity() == "" || $billAddress->getPostcode() == "" || $customerInfo["firstname"] == "" || $customerInfo["lastname"] == "" || $customerInfo["email"] == "" || $billAddress->getTelephone() == "") {
                $response["emptyFields"] = true;
                $response["data"] = "Please fill required fields.";
                return $response;
            }

            $result = $this->api->installmentplaninitforhostedsolution($params);
            /*check for checkout URL from response*/
            $decodedResult = $this->helper->jsonDecode($result);

            if (isset($decodedResult) && isset($decodedResult["CheckoutUrl"]) && $decodedResult["CheckoutUrl"] != "") {

                $response["status"] = true;
                $response["checkoutUrl"] = $decodedResult["CheckoutUrl"];
                $installmentPlan = $decodedResult["InstallmentPlan"]["InstallmentPlanNumber"];
                $response["installmentPlanNumber"] = $decodedResult["InstallmentPlan"]["InstallmentPlanNumber"];
                /*store installment plan number in session, so that will not call init again & again if customer clicks on radio button*/

                $this->logger->addDebug('======= installmentplaninit : response from splitit =======InstallmentPlanNumber : ' . $installmentPlan);
                $this->logger->addDebug(print_r($decodedResult, TRUE));
                /*store information in splitit_hosted_solution for successExit and Async*/
                $customerId = 0;
                if ($this->customerSession->isLoggedIn()) {
                    $customerData = $this->customerSession->getCustomer();
                    $customerId = $customerData->getId();
                }
            } else if (isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])) {
                $errorMsg = "";
                $i = 1;
                $errorCount = count($decodedResult["ResponseHeader"]["Errors"]);
                foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
                    $errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
                    if ($i < $errorCount) {
                        $errorMsg .= ", ";
                    }
                    $i++;
                }

                $response["data"] = $errorMsg;
            } else if (isset($decodedResult["serverError"])) {
                $response["data"] = $decodedResult["serverError"];
            }
        } catch (\Magento\Framework\Validator\Exception $e) {
            $response["data"] = $e->getMessage();
        }
        return $response;
    }

    /**
     * initialization of plan params
     * @param billAddress object
     * @param customerInfo object
     * @param firstInstallmentAmount int
     * @param cultureName string
     * @param numOfInstallments int
     * @param selectedInstallment int
     * @return array
     */
    public function installmentplaninitParams($firstInstallmentAmount, $billAddress, $customerInfo, $cultureName, $numOfInstallments = null, $selectedInstallment)
    {
        $paymentAction = $this->helper->getRedirectPaymentAction();
        $autoCapture = false;
        if ($paymentAction == "authorize_capture") {
            $autoCapture = true;
        }
        $getStreet = $billAddress->getStreet();

        $dataForLogin = array(
            'UserName' => $this->helper->getApiUsername("splitit_paymentredirect"),
            'Password' => $this->helper->getApiPassword("splitit_paymentredirect"),
            'TouchPoint' => $this->helper->getApiTouchPointVersion(),
        );

        $params = array(
            "RequestHeader" => array(
                "SessionId" => $this->api->getorCreateSplititSessionid($dataForLogin),
                "ApiKey" => $this->helper->getApiTerminalKey('splitit_paymentredirect'),
            ),
            "PlanData" => array(
                "Amount" => array(
                    "Value" => round($this->checkoutSession->getQuote()->getGrandTotal(), 2),
                    "CurrencyCode" => $this->store->getCurrentCurrency()->getCode(),
                ),
                "PurchaseMethod" => "ECommerce",
                "RefOrderNumber" => $this->checkoutSession->getLastOrderId(),
                "AutoCapture" => $autoCapture,
                "ExtendedParams" => array(
                    "CreateAck" => "NotReceived",
                ),
            ),
            "BillingAddress" => array(
                "AddressLine" => isset($getStreet[0]) ? $getStreet[0] : "",
                "AddressLine2" => isset($getStreet[1]) ? $getStreet[1] : "",
                "City" => $billAddress->getCity(),
                "State" => $billAddress->getRegion(),
                "Country" => $billAddress->getCountry(),
                "Zip" => $billAddress->getPostcode(),
            ),
            "ConsumerData" => array(
                "FullName" => $customerInfo["firstname"] . " " . $customerInfo["lastname"],
                "Email" => $customerInfo["email"],
                "PhoneNumber" => $billAddress->getTelephone(),
                "CultureName" => $cultureName,
            ),
        );

        if ($firstInstallmentAmount) {
            $params['PlanData']["FirstInstallmentAmount"] = array(
                "Value" => $firstInstallmentAmount,
                "CurrencyCode" => $this->store->getCurrentCurrency()->getCode(),
            );
        }

        $cart = $this->quote;
        $itemsArr = array();
        $i = 0;
        $currencyCode = $this->store->getCurrentCurrency()->getCode();
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
                "Subtotal" => round($this->checkoutSession->getQuote()->getSubtotal(), 2),
                "Tax" => round($this->checkoutSession->getQuote()->getShippingAddress()->getData('tax_amount'), 2),
                "Shipping" => round($this->checkoutSession->getQuote()->getShippingAddress()->getShippingAmount(), 2),
            ),
        );

        $paymentWizardData = array(
            "PaymentWizardData" => array(
                "RequestedNumberOfInstallments" => implode(',', array_keys($numOfInstallments)),
                "SuccessAsyncURL" => $this->urlBuilder->getUrl('splititpaymentmethod/payment/successasync'),
                "SuccessExitURL" => $this->urlBuilder->getUrl('splititpaymentmethod/payment/success'),
                "CancelExitURL" => $this->urlBuilder->getUrl('splititpaymentmethod/payment/cancel'),
            ),
        );
        /*check for 3d secure yes or no*/
        $_3d_secure = $this->helper->getSplitit3dSecure();
        $_3d_minimal_amount = $this->helper->getSplitit3dMinimalAmount();
        if (!$_3d_minimal_amount) {
            $_3d_minimal_amount = 0;
        }

        $grandTotal = round($this->checkoutSession->getQuote()->getGrandTotal(), 2);
        if ($_3d_secure != "" && $_3d_secure == 1 && $grandTotal >= $_3d_minimal_amount) {
            $params['PlanData']["Attempt3DSecure"] = true;
            $params["RedirectUrls"] = array(
                "Succeeded" => $this->urlBuilder->getUrl('splititpaymentmethod/payment/success'),
                "Failed" => $this->urlBuilder->getUrl('splititpaymentmethod/payment/cancel'),
                "Canceled" => $this->urlBuilder->getUrl('splititpaymentmethod/payment/cancel'),
            );
        }
        $params = array_merge($params, $paymentWizardData);
        return $params;
    }

    /**
     * Get first insatallment amount for hosted
     * @return float
     */
    public function getFirstInstallmentAmountHosted()
    {
        $firstPayment = $this->helper->getRedirestFirstPayment();
        $percentageOfOrder = $this->helper->getRedirectPercentageOfOrder();

        $firstInstallmentAmount = 0;
        if ($firstPayment == "shipping") {
            $firstInstallmentAmount = $this->checkoutSession->getQuote()->getShippingAddress()->getShippingAmount();
        } else if ($firstPayment == "shipping_taxes") {
            $shippingAmount = $this->checkoutSession->getQuote()->getShippingAddress()->getShippingAmount();
            $taxAmount = $this->checkoutSession->getQuote()->getShippingAddress()->getData('tax_amount');
            $firstInstallmentAmount = $shippingAmount + $taxAmount;
        } else if ($firstPayment == "percentage") {
            if ($percentageOfOrder > 50) {
                $percentageOfOrder = 50;
            }
            $firstInstallmentAmount = (($this->checkoutSession->getQuote()->getGrandTotal() * $percentageOfOrder) / 100);
        }

        return round($firstInstallmentAmount, 2);
    }

    /**
     * Get installment details from Splitit
     * @param api object
     * @return array
     */
    public function getInstallmentPlanDetails($api)
    {
        $dataForLogin = array(
            'UserName' => $this->helper->getApiUsername("splitit_paymentredirect"),
            'Password' => $this->helper->getApiPassword("splitit_paymentredirect"),
            'TouchPoint' => $this->helper->getApiTouchPointVersion(),
        );
        $params = array(
            "RequestHeader" => array(
                "SessionId" => $this->api->getorCreateSplititSessionid($dataForLogin),
            ),
            "QueryCriteria" => array(
                "InstallmentPlanNumber" => $this->checkoutSession->getSplititInstallmentPlanNumber(),
            ),
        );
        $response = array("status" => false, "data" => "", "numberOfInstallments" => "", "cardBrand" => "", "cardNumber" => "", "cardExpMonth" => "", "cardExpYear" => "");
        $result = $api->getInstallmentPlanDetails($this->api->getApiUrl(), $params);
        $decodedResult = $this->helper->jsonDecode($result);

        if (isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1) {
            $response["status"] = true;
            $response["numberOfInstallments"] = $decodedResult["PlansList"][0]["NumberOfInstallments"];
            $response["cardBrand"] = $decodedResult["PlansList"][0]["ActiveCard"]["CardBrand"];
            $response["cardNumber"] = $decodedResult["PlansList"][0]["ActiveCard"]["CardNumber"];
            $response["cardExpMonth"] = $decodedResult["PlansList"][0]["ActiveCard"]["CardExpMonth"];
            $response["cardExpYear"] = $decodedResult["PlansList"][0]["ActiveCard"]["CardExpYear"];
            $response["grandTotal"] = $decodedResult["PlansList"][0]["OriginalAmount"]["Value"];
            $response["currencyCode"] = $decodedResult["PlansList"][0]["OriginalAmount"]["Currency"]["Code"];
            $response["planStatus"] = $decodedResult["PlansList"][0]["InstallmentPlanStatus"]["Code"];
        } else if (isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])) {
            $errorMsg = "";
            $i = 1;
            $errorCount = count($decodedResult["ResponseHeader"]["Errors"]);
            foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
                $errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
                if ($i < $errorCount) {
                    $errorMsg .= ", ";
                }
                $i++;
            }

            $response["data"] = $errorMsg;
        }
        return $response;
    }

    /**
     * Cancel installment details from Splitit
     * @param api object
     * @param installmentPlanNumber string
     * @return array
     */
    public function cancelInstallmentPlan($api, $installmentPlanNumber)
    {
        $dataForLogin = array(
            'UserName' => $this->helper->getApiUsername("splitit_paymentredirect"),
            'Password' => $this->helper->getApiPassword("splitit_paymentredirect"),
            'TouchPoint' => $this->helper->getApiTouchPointVersion(),
        );
        $params = array(
            "RequestHeader" => array(
                "SessionId" => $this->api->getorCreateSplititSessionid($dataForLogin),
            ),
            "InstallmentPlanNumber" => $installmentPlanNumber,
            "RefundUnderCancelation" => "OnlyIfAFullRefundIsPossible",
        );
        $response = array("status" => false, "data" => "");
        $result = $this->api->cancelInstallmentPlan($this->api->getApiUrl(), $params);
        $decodedResult = $this->helper->jsonDecode($result);

        if (isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1) {
            $response["status"] = true;
        } else if (isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])) {
            $errorMsg = "";
            $i = 1;
            $errorCount = count($decodedResult["ResponseHeader"]["Errors"]);
            foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
                $errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
                if ($i < $errorCount) {
                    $errorMsg .= ", ";
                }
                $i++;
            }

            $response["data"] = $errorMsg;
        }
        return $response;
    }

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$quote) {
            $quote = $this->quote;
        }

        if ($this->checkAvailableInstallments($quote) && $this->checkProductBasedAvailability()) {
            return parent::isAvailable($quote);
        } else {
            return false;
        }
    }

    /**            $depandOnCart = 1;
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function checkAvailableInstallments($quote)
    {
        $installments = array();
        $installmentsInDropdown = array();
        $totalAmount = $quote->getGrandTotal();
        $selectInstallmentSetup = $this->helper->getRedirectSelectInstallmentSetup();

        $options = $this->sourceInstallments->toOptionArray();

        if ($selectInstallmentSetup == "" || $selectInstallmentSetup == "fixed") {
            /*Select Fixed installment setup*/
            $fixedInstallments = $this->helper->getRedirectFixedInstallment();
            $installments = explode(',', $fixedInstallments);
            foreach ($installments as $n) {
                if ((array_key_exists($n, $options))) {
                    $installmentsInDropdown[$n] = round($totalAmount / $n, 2);
                }
            }
        } else {
            /*Select Depanding on cart installment setup*/
            $depandingOnCartInstallments = $this->helper->getRedirectDepandingOnCartTotalValues();
            if (!$depandingOnCartInstallments) {
                return false;
            }
            $depandingOnCartInstallmentsArr = $this->helper->jsonDecode($depandingOnCartInstallments);
            $dataAsPerCurrency = [];
            foreach ($depandingOnCartInstallmentsArr as $data) {

                $dataAsPerCurrency[$data['doctv']['currency']][] = $data['doctv'];
            }
            $currentCurrencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
            if (count($dataAsPerCurrency) && isset($dataAsPerCurrency[$currentCurrencyCode])) {

                foreach ($dataAsPerCurrency[$currentCurrencyCode] as $data) {
                    if ($totalAmount >= $data['from'] && !empty($data['to']) && $totalAmount <= $data['to']) {
                        foreach (explode(',', $data['installments']) as $n) {
                            if ((array_key_exists($n, $options))) {
                                $installments[$n] = $n;
                                $installmentsInDropdown[$n] = round($totalAmount / $n, 2);
                            }
                        }
                        break;
                    } else if ($totalAmount >= $data['from'] && empty($data['to'])) {
                        foreach (explode(',', $data['installments']) as $n) {

                            if ((array_key_exists($n, $options))) {
                                $installments[$n] = $n;
                                $installmentsInDropdown[$n] = round($totalAmount / $n, 2);
                            }
                        }
                        break;
                    }
                }
            }
        }
        $this->checkoutSession->setInstallmentsInDropdownForPaymentForm($installmentsInDropdown);
        if (count($installments) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Check product based availability of module
     * @return bool
     */
    public function checkProductBasedAvailability()
    {
        $check = TRUE;
        if ($this->helper->getRedirectSplititPerProduct()) {
            $cart = $this->cart;
            $itemsVisible = $cart->getQuote()->getAllVisibleItems();
            $allowedProducts = $this->helper->getRedirectSplititProductSkus();
            $allowedProducts = explode(',', $allowedProducts);
            if ($this->helper->getRedirectSplititPerProduct() == 1) {
                $check = TRUE;
                foreach ($itemsVisible as $item) {
                    if (!in_array($item->getProductId(), $allowedProducts)) {
                        $check = FALSE;
                        break;
                    }
                }
            }
            if ($this->helper->getRedirectSplititPerProduct() == 2) {
                $check = FALSE;
                foreach ($itemsVisible as $item) {
                    if (in_array($item->getProductId(), $allowedProducts)) {
                        $check = TRUE;
                        break;
                    }
                }
            }
        }
        return $check;
    }

    /**
     * Check product is allowed to show splitit installment price text
     * @return bool
     */
    public function isSplititTextVisibleOnProduct($productId)
    {
        $show = TRUE;
        if ($this->helper->getRedirectSplititPerProduct() != 0) {
            $show = FALSE;
            $allowedProducts = $this->helper->getRedirectSplititProductSkus();
            $allowedProducts = explode(',', $allowedProducts);
            if (in_array($productId, $allowedProducts)) {
                $show = TRUE;
            }
        }
        return $show;
    }
}
