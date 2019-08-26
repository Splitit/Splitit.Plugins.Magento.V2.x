<?php
/**
 * Copyright © 2015 Splitit d.o.o.
 * created by Zoran Salamun(zoran.salamun@Splitit.net)
 */
namespace Splitit\Paymentmethod\Controller\Installmentplaninit;

class Installmentplaninit extends \Magento\Framework\App\Action\Action {

	protected $request;
	protected $apiModel;
	protected $logger;
	protected $resultPage;
	protected $resultJsonFactory;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\App\Request\Http $request,
		\Psr\Log\LoggerInterface $logger,
		\Splitit\Paymentmethod\Model\Api $apiModel,
		\Magento\Framework\View\Result\PageFactory $resultPage,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
	) {
		$this->request = $request;
		$this->apiModel = $apiModel;
		$this->logger = $logger;
		$this->resultPage = $resultPage;
		$this->resultJsonFactory = $resultJsonFactory;
		parent::__construct($context);
	}

	/**
	Installment init call for the Splitit
	selectedInstallment int
	guestEmail string
	@return Json
	 **/
	public function execute() {

		$request = $this->request->getParams();
		$resultJson = $this->resultJsonFactory->create();
		$response = [
			"status" => false,
			"errorMsg" => "",
			"successMsg" => "",
			"data" => "",

		];
		$selectedInstallment = "";
		if (isset($request["selectedInstallment"]) && $request["selectedInstallment"] != "") {
			$selectedInstallment = $request["selectedInstallment"];
		} else {
			$response["errorMsg"] = "Please select Number of Installments";
			return $resultJson->setData($response);

		}
		$guestEmail = "";
		if (isset($request["guestEmail"])) {
			$guestEmail = $request["guestEmail"];
		}

		$loginResponse = $this->apiModel->apiLogin();
		// check if login successfully or not
		if (!$loginResponse["status"]) {
			$this->logger->addError($loginResponse["errorMsg"]);
			$response["errorMsg"] = 'Error in processing your order. Please try again later.';
			return $resultJson->setData($response);

		}
		// call Installment Plan
		$installmentPlanInitResponse = $this->apiModel->installmentPlanInit($selectedInstallment, $guestEmail);

		if ($installmentPlanInitResponse["status"]) {
			$response["status"] = true;
			//$response["successMsg"] = $installmentPlanInitResponse["successMsg"];
			$block = $this->resultPage->create()->getLayout()
				->createBlock('Splitit\Paymentmethod\Block\Popup')
				->setTemplate('Splitit_Paymentmethod::popup.phtml')
				->setData('data', $installmentPlanInitResponse["successMsg"])
				->toHtml();

			$response["successMsg"] = $block;
		} else {
			$response["errorMsg"] = 'Error in processing your order. Please try again later.';
			$this->logger->addError($installmentPlanInitResponse["errorMsg"]);
			if ($installmentPlanInitResponse["errorMsg"]) {
				$response["errorMsg"] = $installmentPlanInitResponse["errorMsg"];
			}

		}

		$resultJson->setData($response);
		return $resultJson;

	}

}