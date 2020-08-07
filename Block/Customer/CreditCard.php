<?php
/**
 * Bolt magento2 plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Block\Customer;

use Bolt\Boltpay\Block\BlockTrait;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\Framework\View\Element\Template;
use Magento\Theme\Block\Html\Pager;
use Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\CollectionFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey;

class CreditCard extends Template
{
    use BlockTrait;

    const CURRENT_PAGE = 1;
    const PAGE_SIZE = 10;
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var FormKey
     */
    protected $formKey;

    protected $_creditCardCollection;

    /**
     * CreditCard constructor.
     * @param Template\Context $context
     * @param CollectionFactory $collectionFactory
     * @param Session $customerSession
     * @param FormKey $formKey
     * @param Config $configHelper
     * @param Decider $featureSwitches
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        CollectionFactory $collectionFactory,
        Session $customerSession,
        FormKey $formKey,
        Config $configHelper,
        Decider $featureSwitches,
        array $data = []
    ) {
        $this->formKey = $formKey;
        $this->collectionFactory = $collectionFactory;
        $this->customerSession = $customerSession;
        $this->configHelper = $configHelper;
        $this->featureSwitches = $featureSwitches;
        parent::__construct($context, $data);
    }

    /**
     * @return $this|Template
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function _prepareLayout()
    {
        parent::_prepareLayout();

        if ($this->getCreditCardCollection()) {
            $pager = $this->getLayout()->createBlock(
                Pager::class,
                'bolt.creditcards.pager'
            )->setCollection(
                $this->_creditCardCollection
            );
            $this->setChild('pager', $pager);
        }

        return $this;
    }

    /**
     * Render pagination HTML
     *
     * @return string
     */
    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }

    /**
     * @return mixed
     */
    public function getCreditCardCollection()
    {
        $page = ($this->_request->getParam('p')) ?: self::CURRENT_PAGE;
        $pageSize = ($this->_request->getParam('limit')) ?: self::PAGE_SIZE;
        $collection = $this->collectionFactory->create()
            ->getCreditCardInfosByCustomerId(
                $this->customerSession->getCustomerId()
            )
            ->setPageSize($pageSize)
            ->setCurPage($page);
        $this->_creditCardCollection = $collection;

        return $collection;
    }

    /**
     * @return string
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }
}
