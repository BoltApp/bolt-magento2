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

namespace Bolt\Boltpay\Block\Adminhtml\Customer\CreditCard\Tab\View;

use Magento\Customer\Controller\RegistryConstants;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Helper\Data as BackenHelper;
use Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\CollectionFactory;
use Magento\Framework\Registry;

class CreditCard extends \Magento\Backend\Block\Widget\Grid\Extended
{
    /**
     * @var Registry|null
     */
    protected $_coreRegistry = null;

    /**
     * @var CollectionFactory
     */
    protected $_collectionFactory;

    /**
     * CreditCard constructor.
     * @param Context $context
     * @param BackenHelper $backendHelper
     * @param CollectionFactory $collectionFactory
     * @param Registry $coreRegistry
     * @param array $data
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        Registry $coreRegistry,
        Context $context,
        BackenHelper $backendHelper,
        array $data = []
    ) {
        $this->_collectionFactory = $collectionFactory;
        $this->_coreRegistry = $coreRegistry;
        parent::__construct($context, $backendHelper, $data);
    }

    protected function _construct()
    {
        parent::_construct();
        $this->setSortable(false);
        $this->setPagerVisibility(false);
        $this->setFilterVisibility(false);
    }

    /**
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     */
    protected function _prepareCollection()
    {
        $collection = $this->_collectionFactory->create()
            ->addFilter(
                'customer_id',
                $this->_coreRegistry->registry(RegistryConstants::CURRENT_CUSTOMER_ID)
            );
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     * @throws \Exception
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'credit_card_id',
            [
                'header' => __('Credit Card Id'),
                'index' => 'credit_card_id'
            ]
        );

        $this->addColumn(
            'credit_card_type',
            [
                'header' => __('Credit Card Type'),
                'index' => 'credit_card_type',
                'renderer' => CardType::class,
            ]
        );

        $this->addColumn(
            'credit_card_last_4_digit',
            [
                'header' => __('Credit Card Last 4 Digit'),
                'index' => 'credit_card_last_4_digit',
                'renderer' => CardNumber::class,
            ]
        );

        $this->addColumn(
            'action',
            [
                'header' => __('Action'),
                'index' => 'delete_action',
                'renderer' => DeleteAction::class,
                'header_css_class' => 'col-actions',
                'column_css_class' => 'col-actions'
            ]
        );

        return parent::_prepareColumns();
    }
}
