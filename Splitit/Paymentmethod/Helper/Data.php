<?php

namespace Splitit\Paymentmethod\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Framework\View\Element\Template\Context;

//use \Magento\Framework\Json\Helper\Data;
class Data extends AbstractHelper {

	/**
	 * Grand Total Code
	 */
	const GRAND_TOTAL_CODE = 'grand_total';

	public $checkoutSession;
	public $productMetadataInterface;
	public $jsonObject;
	public $storeManager;
	public $currency;
	public $storeLocale;
	public $SupportedCulturesSource;

	protected $jsonHelper;
	public static $selectedIns;

	/**
	 * Constructor
	 */
	public function __construct(
		\Magento\Framework\App\Helper\Context $context,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Magento\Framework\App\ProductMetadataInterface $productMetadataInterface,
		\Magento\Framework\Json\Helper\Data $jsonObject,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Directory\Model\Currency $currency,
		\Splitit\Paymentmethod\Model\Source\Getsplititsupportedcultures $SupportedCulturesSource,
		\Magento\Framework\Json\Helper\Data $jsonHelper,
		\Magento\Framework\Locale\Resolver $storeLocale
	) {
		$this->checkoutSession = $checkoutSession;
		$this->productMetadataInterface = $productMetadataInterface;
		$this->jsonObject = $jsonObject;
		$this->storeManager = $storeManager;
		$this->currency = $currency;
		$this->storeLocale = $storeLocale;
		$this->SupportedCulturesSource = $SupportedCulturesSource;
		$this->jsonHelper = $jsonHelper;
		parent::__construct($context);
	}

	/**
	 * To get the encoded value
	 * @return string
	 */
	public function jsonEncode($array = []) {
		return $this->jsonHelper->jsonEncode($array);
	}

	/**
	 * To get the decoded value
	 * @return array
	 */
	public function jsonDecode($encoded) {
		return $this->jsonHelper->jsonDecode($encoded);
	}

	/**
	 * To get the config value
	 * @return string
	 */
	public function getConfig($config_path) {
		return $this->scopeConfig->getValue(
			$config_path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE
		);
	}

	/**
	 * To get the config value of Api UserName
	 * @param method string
	 * @return string
	 */
	public function getApiUsername($method) {
		return $this->getConfig("payment/" . $method . "/api_username");
	}

	/**
	 * To get the config value of Api password
	 * @param method string
	 * @return string
	 */
	public function getApiPassword($method) {
		return $this->getConfig("payment/" . $method . "/api_password");
	}

	/**
	 * To get the config value of api_terminal_key
	 * @param method string
	 * @return string
	 */
	public function getApiTerminalKey($method) {
		return $this->getConfig("payment/" . $method . "/api_terminal_key");
	}

	/**
	 * To get the config value of sandbox_flag
	 * @param method string
	 * @return string
	 */
	public function getSandboxFlag($method) {
		return $this->getConfig("payment/" . $method . "/sandbox_flag");
	}

	/**
	 * To get the config value of api_url_sandbox
	 * @param method string
	 * @return string
	 */
	public function getApiUrlSandbox($method) {
		return $this->getConfig("payment/" . $method . "/api_url_sandbox");
	}

	/**
	 * To get the config value of api_url
	 * @param method string
	 * @return string
	 */
	public function getApiUrl($method) {
		return $this->getConfig("payment/" . $method . "/api_url");
	}

	/**
	 * To get the config value of api touch point version
	 * @return array
	 */
	public function getApiTouchPointVersion() {
		return array("Code" => "MagentoPlugin", "Version" => "v2.1");
	}

	/**
	 * To get the config value of select_installment_setup
	 * @return string
	 */
	public function getSelectInstallmentSetup() {
		return $this->getConfig('payment/splitit_paymentmethod/select_installment_setup');
	}

	/**
	 * To get the config value of select_installment_setup
	 * @return string
	 */
	public function getRedirectSelectInstallmentSetup() {
		return $this->getConfig('payment/splitit_paymentredirect/select_installment_setup');
	}

	/**
	 * To get the config value of fixed_installment
	 * @return string
	 */
	public function getFixedInstallment() {
		return $this->getConfig('payment/splitit_paymentmethod/fixed_installment');
	}

	/**
	 * To get the config value of fixed_installment
	 * @return string
	 */
	public function getRedirectFixedInstallment() {
		return $this->getConfig('payment/splitit_paymentredirect/fixed_installment');
	}

	/**
	 * To get the config value of depanding_on_cart_total_values
	 * @return string
	 */
	public function getDepandingOnCartTotalValues() {
		return $this->getConfig('payment/splitit_paymentmethod/depanding_on_cart_total_values');
	}

	/**
	 * To get the config value of depanding_on_cart_total_values
	 * @return string
	 */
	public function getRedirectDepandingOnCartTotalValues() {
		return $this->getConfig('payment/splitit_paymentredirect/depanding_on_cart_total_values');
	}

	/**
	 * To get the config value of faq_link_enabled
	 * @return string
	 */
	public function getFaqLinkEnabled() {
		return $this->getConfig('payment/splitit_paymentmethod/faq_link_enabled');
	}

	/**
	 * To get the config value of faq_link_title
	 * @return string
	 */
	public function getFaqLinkTitle() {
		return $this->getConfig('payment/splitit_paymentmethod/faq_link_title');
	}

	/**
	 * To get the config value of faq_link_title_url
	 * @return string
	 */
	public function getFaqLinkTitleUrl() {
		return $this->getConfig('payment/splitit_paymentmethod/faq_link_title_url');
	}

	/**
	 * To get the config value of payment_action
	 * @return string
	 */
	public function getRedirectFaqLinkTitleUrl() {
		return $this->getConfig('payment/splitit_paymentredirect/faq_link_title_url');
	}

	/**
	 * To get the config value of payment_action
	 * @return string
	 */
	public function getRedirectPaymentAction() {
		return $this->getConfig('payment/splitit_paymentredirect/payment_action');
	}

	/**
	 * To get the config value of payment_action
	 * @return string
	 */
	public function getPaymentAction() {
		return $this->getConfig('payment/splitit_paymentmethod/payment_action');
	}

	/**
	 * To get the config value of enable_installment_price
	 * @return string
	 */
	public function getEnableInstallmentPrice() {
		return $this->getConfig('payment/splitit_paymentmethod/enable_installment_price');
	}

	/**
	 * To get the config value of enable_installment_price
	 * @return string
	 */
	public function getRedirectEnableInstallmentPrice() {
		return $this->getConfig('payment/splitit_paymentredirect/enable_installment_price');
	}

	/**
	 * To get the config value of first_payment
	 * @return string
	 */
	public function getFirstPayment() {
		return $this->getConfig('payment/splitit_paymentmethod/first_payment');
	}

	/**
	 * To get the config value of first_payment
	 * @return string
	 */
	public function getRedirestFirstPayment() {
		return $this->getConfig('payment/splitit_paymentredirect/first_payment');
	}

	/**
	 * To get the config value of percentage_of_order
	 * @return string
	 */
	public function getPercentageOfOrder() {
		return $this->getConfig('payment/splitit_paymentmethod/percentage_of_order');
	}

	/**
	 * To get the config value of percentage_of_order
	 * @return string
	 */
	public function getRedirectPercentageOfOrder() {
		return $this->getConfig('payment/splitit_paymentredirect/percentage_of_order');
	}

	/**
	 * To get the config value of splitit_3d_secure
	 * @return string
	 */
	public function getSplitit3dSecure() {
		return $this->getConfig('payment/splitit_paymentredirect/splitit_3d_secure');
	}

	/**
	 * To get the config value of splitit_3d_minimal_amount
	 * @return string
	 */
	public function getSplitit3dMinimalAmount() {
		return $this->getConfig('payment/splitit_paymentredirect/splitit_3d_minimal_amount');
	}

	/**
	 * To get the config value of splitit_per_product
	 * @return string
	 */
	public function getRedirectSplititPerProduct() {
		return $this->getConfig('payment/splitit_paymentredirect/splitit_per_product');
	}

	/**
	 * To get the config value of splitit_product_skus
	 * @return string
	 */
	public function getRedirectSplititProductSkus() {
		return $this->getConfig('payment/splitit_paymentredirect/splitit_product_skus');
	}

	/**
	 * To get the config value of splitit_product_skus
	 * @return string
	 */
	public function getSplititProductSkus() {
		return $this->getConfig('payment/splitit_paymentmethod/splitit_product_skus');
	}

	/**
	 * To get the config value of splitit_per_product
	 * @return string
	 */
	public function getSplititPerProduct() {
		return $this->getConfig('payment/splitit_paymentmethod/splitit_per_product');
	}

	/**
	 * To get the config value of splitit_fallback_language
	 * @return string
	 */
	public function getSplititFallbackLanguage() {
		return $this->getConfig('payment/splitit_paymentmethod/splitit_per_product');
	}

	/**
	 * To get the config value of splitit_per_product
	 * @return string
	 */
	public function getRedirectSplititFallbackLanguage() {
		return $this->getConfig('payment/splitit_paymentredirect/splitit_per_product');
	}

	/**
	 * To get the config value of active
	 * @return string
	 */
	public function getIsActive() {
		return $this->getConfig('payment/splitit_paymentmethod/active');
	}

	/**
	 * To get the config value of active
	 * @return string
	 */
	public function getRedirectIsActive() {
		return $this->getConfig('payment/splitit_paymentredirect/active');
	}

	/**
	 * To get the config value of splitit_logo_src
	 * @return string
	 */
	public function getSplititLogoSrc() {
		return $this->getConfig('payment/splitit_paymentmethod/splitit_logo_src');
	}

	/**
	 * To get the config value of splitit_logo_src
	 * @return string
	 */
	public function getRedirectSplititLogoSrc() {
		return $this->getConfig('payment/splitit_paymentredirect/splitit_logo_src');
	}

	/**
	 * To get the config value of splitit_logo__bakcground_href
	 * @return string
	 */
	public function getSplititLogoHref() {
		return $this->getConfig('payment/splitit_paymentmethod/splitit_logo__bakcground_href');
	}

	/**
	 * To get the config value of splitit_logo__bakcground_href
	 * @return string
	 */
	public function getRedirectSplititLogoHref() {
		return $this->getConfig('payment/splitit_paymentredirect/splitit_logo__bakcground_href');
	}

	/**
	 * To get the config value of installments_count
	 * @return string
	 */
	public function getInstallmentsCount() {
		return $this->getConfig('payment/splitit_paymentmethod/installments_count');
	}

	/**
	 * To get the config value of installments_count
	 * @return string
	 */
	public function getRedirectInstallmentsCoun() {
		return $this->getConfig('payment/splitit_paymentredirect/installments_count');
	}

	/**
	 * To get the config value of installment_price_on_pages
	 * @return string
	 */
	public function getInstallmentsPriceOnPage() {
		return $this->getConfig('payment/splitit_paymentmethod/installment_price_on_pages');
	}

	/**
	 * To get the config value of installment_price_on_pages
	 * @return string
	 */
	public function getRedirectInstallmentsPriceOnPage() {
		return $this->getConfig('payment/splitit_paymentredirect/installment_price_on_pages');
	}

	/**
	 * To json encode
	 * @return Json
	 */
	public function encodeData($dataToEncode) {

		$encodedData = $this->jsonObject->jsonEncode($dataToEncode);
		return $encodedData;
	}

	/**
	 * To get the currency symbol
	 * @return string
	 */
	public function getCurrencyData() {
		$currencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
		$currencyRate = $this->storeManager->getStore()->getCurrentCurrencyRate();

		$currency = $this->currency->load($currencyCode);
		return $currencySymbol = $currency->getCurrencySymbol();
	}

	/**
	 * To get Splitit culture names
	 * @return string
	 */
	public function getCultureName($paymentForm = false) {
		$storelang = $this->storeLocale->getLocale();
		$splititSupportedCultures = $this->getSplititSupportedCultures();

		if (count($splititSupportedCultures) && in_array(str_replace('_', '-', $storelang), $splititSupportedCultures)) {
			return str_replace('_', '-', $storelang);
		} else {
			if ($paymentForm) {
				return $this->getSplititFallbackLanguage();
			}

			return $this->getRedirectSplititFallbackLanguage();
		}
	}

	/**
	 * To get Splitit supported culture names
	 * @return array
	 */
	public function getSplititSupportedCultures() {
		$apiUrl = $this->SupportedCulturesSource->getApiUrl();
		$getSplititSupportedCultures = $this->SupportedCulturesSource->getSplititSupportedCultures($apiUrl . "api/Infrastructure/SupportedCultures");

		$decodedResult = $this->jsonHelper->jsonDecode($getSplititSupportedCultures);
        if (isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1 && count($decodedResult["Cultures"])) {
            return array_map(function($culture) {
                return $culture['CultureName'];
            }, $decodedResult["Cultures"]);
		}
		return array();
	}

	/**
	 * Retrieve Current Magento Version
	 * @return string
	 */
	public function getMagentoVersion() {
		return $this->productMetadataInterface->getVersion();
	}

	/**
	 * Retrieve Installment price text
	 * @return string
	 */
	public function getInstallmentPriceText() {
		$text = [];

		if ($this->getRedirectIsActive() && $this->getRedirectEnableInstallmentPrice()) {

			$text['price_text'] = 'or {NOI} interest-free payments of {AMOUNT} with SPLITIT';
			$text['logo_src'] = $this->getRedirectSplititLogoSrc();
			$text['bakcground_href'] = $this->getRedirectSplititLogoHref();
			$text['installments_count'] = $this->getRedirectInstallmentsCoun();
			$text['installment_price_on_pages'] = $this->getRedirectInstallmentsPriceOnPage();
			$text['help_link'] = $this->getRedirectFaqLinkTitleUrl();
			$text['help_title'] = __('Learn More');
		}

		if ($this->getIsActive() && $this->getEnableInstallmentPrice()) {
			$text['price_text'] = 'or {NOI} interest-free payments of {AMOUNT} with SPLITIT';
			$text['logo_src'] = $this->getSplititLogoSrc();
			$text['bakcground_href'] = $this->getSplititLogoHref();
			$text['installments_count'] = $this->getInstallmentsCount();
			$text['installment_price_on_pages'] = $this->getInstallmentsPriceOnPage();
			$text['help_link'] = $this->getFaqLinkTitleUrl();
			$text['help_title'] = __('Learn More');
		}
		return $text;
	}

}
