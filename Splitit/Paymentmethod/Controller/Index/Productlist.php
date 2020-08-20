<?php

/**
 * Copyright © 2019 Splitit
 */

namespace Splitit\Paymentmethod\Controller\Index;

use Magento\Framework\Controller\ResultFactory;

class Productlist extends \Magento\Framework\App\Action\Action {

	protected $productSku;

	/**
	 * @param \Magento\Framework\App\Action\Context $context
	 * @param \Splitit\Paymentmethod\Model\Source\Productskus $productSku
	 */
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Splitit\Paymentmethod\Model\Source\Productskus $productSku
	) {
		$this->productSku = $productSku;
		parent::__construct($context);
	}

	/**
	 * Get the magento products list
	 *
	 * @return Json
	 */
	public function execute() {
		$params = $this->getRequest()->getParams();
		$result = array();
		$resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
		if (isset($params['isAjax']) && $params['isAjax']) {
			if ((isset($params['term']) && $params['term']) || (isset($params['prodIds']) && $params['prodIds'])) {
				$result = $this->productSku->toOptionArray($params);
			}
		}
		$resultJson->setData($result);
		return $resultJson;
	}

}
