<?php
namespace Splitit\Paymentmethod\Block\Adminhtml\System\Config\Form\Field;

class Feetable extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray {
	/**
	 * @var \Magento\Framework\Data\Form\Element\Factory
	 */
	protected $elementFactory;

	/**
	 * @param \Magento\Backend\Block\Template\Context $context
	 * @param \Magento\Framework\Data\Form\Element\Factory $elementFactory
	 * @param array $data
	 */
	public function __construct(
		\Magento\Backend\Block\Template\Context $context,
		\Magento\Framework\Data\Form\Element\Factory $elementFactory,
		array $data = []
	) {
		$this->elementFactory = $elementFactory;
		parent::__construct($context, $data);
	}

	/**
	 * Add Fee table to config
	 */
	protected function _construct() {
		$this->addColumn('noi', ['label' => __('NOI'), 'readonly' => 'readonly']);
		$this->addColumn('fixed', ['label' => __('Fixed')]);
		$this->addColumn('percent', ['label' => __('Percent')]);
		$this->_addAfter = false;
		$this->_addButtonLabel = __('Add More');
		parent::_construct();
	}

}