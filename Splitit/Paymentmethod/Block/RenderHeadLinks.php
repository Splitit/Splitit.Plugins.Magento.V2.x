<?php

namespace Splitit\Paymentmethod\Block;

use Magento\Framework\View\Element\Template;
use Splitit\Paymentmethod\Helper\Data as Helper;

class RenderHeadLinks extends Template
{
    private $helper;
    private $filesToRender;

    public function __construct(
        Template\Context $context,
        Helper $helper,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->filesToRender = $data;
        parent::__construct($context, $data);
    }

    public function toHtml()
    {
        $html = '';
        if ($this->helper->getRedirectIsActive() && $this->helper->getRedirectEnableInstallmentPrice()) {
            if (isset($this->filesToRender['js'])) {
                $html .= '<script  type="text/javascript"  src="' . $this->getViewFileUrl($this->filesToRender['js']) . '"></script>';
            }
            if (isset($this->filesToRender['css'])) {
                $html .= '<link  rel="stylesheet" type="text/css"  media="all" href="' . $this->getViewFileUrl($this->filesToRender['css']) . '" />';
            }
        }
        return $html;
    }
}
