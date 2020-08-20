<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Splitit\Paymentmethod\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface {
	const CODE = 'splitit_paymentmethod';

	/**
	 * Payment ConfigProvider constructor.
	 * @param \Magento\Payment\Helper\Data $paymentHelper
	 */
	public function __construct(
		\Magento\Payment\Helper\Data $paymentHelper
	) {
		$this->method = $paymentHelper->getMethodInstance(self::CODE);
	}

	/**
	 * Retrieve assoc array of checkout configuration
	 *
	 * @return array
	 */

	public function getConfig() {
		return [
			'payment' => [
				self::CODE => [
					'transactionResults' => [
						ClientMock::SUCCESS => __('Success'),
						ClientMock::FAILURE => __('Fraud'),
					],
					'fixedamount' => $this->getFixedAmount(),
				],
			],
		];
	}

	/**
         * Get fixed amount
         */
	protected function getFixedAmount() {
		return $this->method->getFixedAmount();
	}
}
