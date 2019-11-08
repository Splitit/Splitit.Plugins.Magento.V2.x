<?php

/**
 * Copyright © 2019 Splitit
 */

namespace Splitit\Paymentmethod\Controller\Checksetting;

use Magento\Framework\Controller\ResultFactory;

class Checksetting extends \Magento\Framework\App\Action\Action {

	/**
	 * Splitit Helper
	 * @var Splitit\Paymentmethod\Helper\Data
	 */
	private $helper;

	/**
	 * Splitit API model
	 * @var Splitit\Paymentmethod\Model\Api
	 */
	private $apiModelObj;

	/**
	 * Contructor
	 * @param \Magento\Framework\App\Action\Context $context
	 * @param \Splitit\Paymentmethod\Helper\Data $helper
	 * @param \Splitit\Paymentmethod\Model\Api $apiModelObj
	 */
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Splitit\Paymentmethod\Helper\Data $helper,
		\Splitit\Paymentmethod\Model\Api $apiModelObj
	) {
		$this->helper = $helper;
		$this->apiModelObj = $apiModelObj;
		parent::__construct($context);
	}

	/**
	 * To check SplitIt Api credentials are correct
	 * @return Json
	 */
	public function execute() {
		$response = [
			"status" => false,
			"errorMsg" => "",
			"successMsg" => "",
		];

		$paramMethod = $this->getRequest()->getParam('method');
		if ($paramMethod) {
			$resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
			if (!$this->helper->getApiUsername($paramMethod) || !$this->helper->getApiPassword($paramMethod) || !$this->helper->getApiTerminalKey($paramMethod)) {
				$response['errorMsg'] = "Please enter the credentials and save configuration";
			} else {
				$dataForLogin = array(
					'UserName' => $this->helper->getApiUsername($paramMethod),
					'Password' => $this->helper->getApiPassword($paramMethod),
					'TouchPoint' => $this->helper->getApiTouchPointVersion(),
				);
				$loginResponse = $this->apiModelObj->apiLogin($dataForLogin);

				if (!$loginResponse["status"]) {
					$response["errorMsg"] = $loginResponse["errorMsg"];
					$resultJson->setData($response);
					return $resultJson;
				}
				if ($this->helper->getSandboxFlag($paramMethod)) {
					$response["successMsg"] = "[Sandbox Mode] Successfully login! API available!";
				} else {
					$response["successMsg"] = "[Production Mode] Successfully login! API available!";
				}
				$response["status"] = true;
			}

		} else {
			$response['errorMsg'] = "Unable to process request";
		}

		$resultJson->setData($response);
		return $resultJson;
	}

}
