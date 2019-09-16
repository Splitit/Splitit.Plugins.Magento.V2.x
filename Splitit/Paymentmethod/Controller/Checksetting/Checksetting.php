<?php
/**
 * Copyright © 2015 Inchoo d.o.o.
 * created by Zoran Salamun(zoran.salamun@inchoo.net)
 */
namespace Splitit\Paymentmethod\Controller\Checksetting;
use Magento\Framework\Controller\ResultFactory;

class Checksetting extends \Magento\Framework\App\Action\Action {

	private $helper;

	public function execute() {

		$response = [
			"status" => false,
			"errorMsg" => "",
			"successMsg" => "",

		];
		$paramMethod = $this->getRequest()->getParam('method');
		if ($paramMethod) {
			$this->helper = $this->_objectManager->create('Splitit\Paymentmethod\Helper\Data');
			$resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
			if (!$this->helper->getConfig("payment/" . $paramMethod . "/api_username") || !$this->helper->getConfig("payment/" . $paramMethod . "/api_password") || !$this->helper->getConfig("payment/" . $paramMethod . "/api_terminal_key")) {
				$response['errorMsg'] = "Please enter the credentials and save configuration";
			} else {
				$dataForLogin = array(
					'UserName' => $this->helper->getConfig("payment/" . $paramMethod . "/api_username"),
					'Password' => $this->helper->getConfig("payment/" . $paramMethod . "/api_password"),
					'TouchPoint' => array("Code" => "MagentoPlugin", "Version" => "v2.1"),
				);

				$apiModelObj = $this->_objectManager->get('Splitit\Paymentmethod\Model\Api');
				$loginResponse = $apiModelObj->apiLogin($dataForLogin);

				if (!$loginResponse["status"]) {
					$response["errorMsg"] = $loginResponse["errorMsg"];
					$resultJson->setData($response);
					return $resultJson;
				}
				if ($this->helper->getConfig("payment/" . $paramMethod . "/sandbox_flag")) {
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