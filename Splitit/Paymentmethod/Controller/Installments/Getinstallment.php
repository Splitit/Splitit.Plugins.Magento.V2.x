<?php
/**
 * Copyright © 2019 Splitit
 *
 */
namespace Splitit\Paymentmethod\Controller\Installments;
use Magento\Framework\Controller\ResultFactory;

class Getinstallment extends \Magento\Framework\App\Action\Action {

	protected $helper;
	protected $cart;
	protected $splititSource;
	protected $storeManager;
	protected $currency;
	protected $jsonHelper;
	protected $resultPage;

	/**
     * Contructor
     * @param \Magento\Framework\App\Action\Context $context
	 * @param \Splitit\Paymentmethod\Helper\Data $helper
	 * @param \Magento\Checkout\Model\Cart $cart
	 * @param \Splitit\Paymentmethod\Model\Source\Installments $splititSource
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManager
	 * @param \Magento\Framework\Json\Helper\Data $jsonHelper
	 * @param \Magento\Directory\Model\Currency $currency
	 * @param \Magento\Framework\View\Result\PageFactory $resultPage
     */
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Splitit\Paymentmethod\Helper\Data $helper,
		\Magento\Checkout\Model\Cart $cart,
		\Splitit\Paymentmethod\Model\Source\Installments $splititSource,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\Json\Helper\Data $jsonHelper,
		\Magento\Directory\Model\Currency $currency,
		\Magento\Framework\View\Result\PageFactory $resultPage
	) {
		$this->storeManager = $storeManager;
		$this->currency = $currency;
		$this->helper = $helper;
		$this->cart = $cart;
		$this->splititSource = $splititSource;
		$this->jsonHelper = $jsonHelper;
		$this->resultPage = $resultPage;
		parent::__construct($context);
	}

	/**
	 * Get number of installment dropdown
	 * @return Json
	 **/
	public function execute() {
		$response = [
			"status" => true,
			"errorMsg" => "",
			"successMsg" => "",
			"installmentHtml" => "",
			"helpSection" => "",

		];

		$totalAmount = $this->cart->getQuote()->getGrandTotal();

		$selectInstallmentSetup = $this->helper->getRedirectSelectInstallmentSetup();
		$options = $this->splititSource->toOptionArray();
		$currentCurrencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
		$currencySymbol = $this->currency->load($currentCurrencyCode)->getCurrencySymbol();

		$installmentHtml = '<option value="">--' . __('No Installment available') . '--</option>';
		$countInstallments = $installmentValue = 0;
		if ($selectInstallmentSetup == "" || $selectInstallmentSetup == "fixed") {
			$installments = $this->helper->getRedirectFixedInstallment();

			if ($installments) {
				$installmentHtml = '<option value="">--' . __('Please Select') . '--</option>';
				foreach (explode(',', $installments) as $value) {
					$installmentValue = $value;
					$countInstallments++;
					$installmentHtml .= '<option value="' . $value . '">' . $value . ' ' . __('Installments') . '</option>';
				}

			}
		} else {
			$installmentHtml = '<option value="">--' . __('Please Select') . '--</option>';
			$depandingOnCartInstallments = $this->helper->getRedirectDepandingOnCartTotalValues();
			$depandingOnCartInstallmentsArr = $this->jsonHelper->jsonDecode($depandingOnCartInstallments);
			$dataAsPerCurrency = [];
			foreach ($depandingOnCartInstallmentsArr as $data) {
				$dataAsPerCurrency[$data['doctv']['currency']][] = $data['doctv'];
			}

			if (count($dataAsPerCurrency) && isset($dataAsPerCurrency[$currentCurrencyCode])) {

				foreach ($dataAsPerCurrency[$currentCurrencyCode] as $data) {
					if ($totalAmount >= $data['from'] && !empty($data['to']) && $totalAmount <= $data['to']) {
						foreach (explode(',', $data['installments']) as $n) {
							$installmentValue = $n;
							$countInstallments++;
							$installmentHtml .= '<option value="' . $n . '">' . $n . ' ' . __('Installments') . '</option>';
						}
						break;
					} else if ($totalAmount >= $data['from'] && empty($data['to'])) {
						foreach (explode(',', $data['installments']) as $n) {
							$installmentValue = $n;
							$countInstallments++;
							$installmentHtml .= '<option value="' . $n . '">' . $n . ' ' . __('Installments') . '</option>';
						}
						break;
					}
				}
			}

		}
		$response["installmentHtml"] = $installmentHtml;
		$response["installmentShow"] = true;
		if ($countInstallments == 1 && $installmentValue == 1) {
			$response["installmentShow"] = false;
		}
		$response["helpSection"] = $this->getHelpSection();
		$resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
		return $resultJson->setData($response);

	}

	/**
	 * Get help variable frm configurations
	 * @return array
	 **/
	private function getHelpSection() {
		$baseUrl = $this->storeManager->getStore()->getBaseUrl();
		$help = [];

		if ($this->helper->getFaqLinkEnabled()) {
			$help["title"] = $this->helper->getFaqLinkTitle();
			$help["link"] = $baseUrl . "splititpaymentmethod/help/help";
			$help["link"] = $this->helper->getFaqLinkTitleUrl();
		}
		return $help;
	}

}
