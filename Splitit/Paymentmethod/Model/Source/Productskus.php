<?php

namespace Splitit\Paymentmethod\Model\Source;

class Productskus {
	
	public $skus;
	protected $productCollectionFactory;
	protected $productStatus;
	protected $productVisibility;
	protected $iterator;

	/**
	 * Constructor
	 */
	public function __construct(
		\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
		\Magento\Catalog\Model\Product\Attribute\Source\Status $productStatus,
		\Magento\Catalog\Model\Product\Visibility $productVisibility,
		\Magento\Framework\Model\ResourceModel\Iterator $iterator
	) {
		$this->productCollectionFactory = $productCollectionFactory;
		$this->productStatus = $productStatus;
		$this->productVisibility = $productVisibility;
		$this->iterator = $iterator;
	}

	/**
	 * Get options for product list
	 *
	 * @return array
	 */
	public function toOptionArray($params) {
		$this->skus = array();
		$collection = $this->productCollectionFactory->create();
		$collection->addAttributeToSelect('*');
		$collection->addAttributeToSort('name');
		$collection->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()]);
		if (isset($params['term']) && $params['term']) {
			$collection->addAttributeToFilter(array(
				array('attribute' => 'name', 'like' => '%' . $params['term'] . '%'),
				array('attribute' => 'sku', 'like' => '%' . $params['term'] . '%'),
			));
		}
		if (isset($params['prodIds']) && $params['prodIds']) {
			$collection->addAttributeToFilter('entity_id', ['in' => $params['prodIds']]);
		}
		$collection->setVisibility($this->productVisibility->getVisibleInSiteIds());

		$this->iterator->walk($collection->getSelect(), array(array($this, 'callBackProd')));
		return $this->skus;

	}

	/**
         * callback function for creating products array for config
         * @param array $args
         */
        public function callBackProd($args) {
		$this->skus[] = array('value' => $args['row']['entity_id'], 'label' => __($args['row']['name'] . '-' . $args['row']['sku']));
	}

}