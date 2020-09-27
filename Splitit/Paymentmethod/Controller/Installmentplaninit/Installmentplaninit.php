<?php

/**
 * Copyright © 2019 Splitit
 */

namespace Splitit\Paymentmethod\Controller\Installmentplaninit;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\View\Result\PageFactory;
use Splitit\Paymentmethod\Model\Api;
use Psr\Log\LoggerInterface;

class Installmentplaninit extends Action
{
    /**
     * @var Http
     */
    private $request;

    /**
     * @var Api
     */
    private $apiModel;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PageFactory
     */
    private $resultPage;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    public function __construct(
        Context $context,
        Http $request,
        LoggerInterface $logger,
        Api $apiModel,
        PageFactory $resultPage,
        JsonFactory $resultJsonFactory
    ) {
        $this->request = $request;
        $this->apiModel = $apiModel;
        $this->logger = $logger;
        $this->resultPage = $resultPage;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    /**
     * Installment init call for the Splitit
     * selectedInstallment int
     * guestEmail string
     * @return Json
     */
    public function execute()
    {
        $request = $this->request->getParams();
        $resultJson = $this->resultJsonFactory->create();
        $response = [
            "status" => false,
            "errorMsg" => "",
            "successMsg" => "",
            "data" => "",
        ];

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
        if (!$loginResponse["status"]) {
            $loginResponse = $this->apiModel->apiLogin();
        }
        /* check if login successfully or not */
        if (!$loginResponse["status"]) {
            $this->logger->addError("FILE: " . __FILE__ . "\n LINE: " . __LINE__ . "\n Method: " . __METHOD__);
            $this->logger->addError($loginResponse["errorMsg"]);
            $response["errorMsg"] = 'Error in processing your order. Please try again later.';
            return $resultJson->setData($response);
        }
        /* call Installment Plan */
        $installmentPlanInitResponse = $this->apiModel->installmentPlanInit($selectedInstallment, $guestEmail);

        if ($installmentPlanInitResponse["status"]) {
            $response["status"] = true;
            $block = $this->resultPage->create()->getLayout()
                ->createBlock('Magento\Framework\View\Element\Template')
                ->setTemplate('Splitit_Paymentmethod::popup.phtml')
                ->setData('data', json_decode($installmentPlanInitResponse["successMsg"], true))
                ->toHtml();

            $response["successMsg"] = $block;
        } else {
            $response["errorMsg"] = 'Error in processing your order. Please try again later.';
            $this->logger->addError("FILE: " . __FILE__ . "\n LINE: " . __LINE__ . "\n Method: " . __METHOD__);
            $this->logger->addError($installmentPlanInitResponse["errorMsg"]);
            if ($installmentPlanInitResponse["errorMsg"]) {
                $response["errorMsg"] = $installmentPlanInitResponse["errorMsg"];
            }
        }

        $resultJson->setData($response);
        return $resultJson;
    }
}
