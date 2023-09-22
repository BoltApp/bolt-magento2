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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\ShipperHQ\Backend;

use Magento\Backend\Model\Session\Quote as BackendQuoteSession;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Framework\DataObjectFactory;

/**
 * Session fix for backend orders with ShipperHQ admin custom shipping method selected.
 * During RestAPI calls, we don't have access to the customer session, which is used in ShipperHQ.
 * See: ShipperHQ\Shipper\Observer\SaveShippingAdmin
 * See: ShipperHQ\Shipper\Service\Backend\SetAdminShipData.
 * See: ShipperHQ\Shipper\Service\Backend\GetAdminShipData.
 * To fix this in this class, we retrieve AdminShipData from the quote and set it to the backend session.
 */
class AdminShipmentMethod
{
    public const SHIPPER_HQ_MODULE_NAME = 'ShipperHQ_Shipper';
    private const SHIPPER_HQ_ADMIN_CUSTOM_SHIPPING_METHOD = 'shipperadmin_adminshipping';

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var BackendQuoteSession
     */
    private $backendQuoteSession;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    private $customCarrierDesctiptionPartsToRemove = [
        'Custom Shipping Rate - ',
        'Custom Shipping Rate'
    ];

    /**
     * @param CartHelper $cartHelper
     * @param BackendQuoteSession $backendQuoteSession
     * @param DataObjectFactory $dataObjectFactory
     */
    public function __construct(
        CartHelper $cartHelper,
        BackendQuoteSession $backendQuoteSession,
        DataObjectFactory $dataObjectFactory
    ) {
        $this->cartHelper = $cartHelper;
        $this->backendQuoteSession = $backendQuoteSession;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Restore custom admin shipment data in the backend quote session
     *
     * @param int|string $cartId
     * @return void
     */
    public function restoreAdminShipDataInSession($cartId): void
    {
        $quote = $this->cartHelper->getQuoteById($cartId);
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getShippingMethod() === self::SHIPPER_HQ_ADMIN_CUSTOM_SHIPPING_METHOD) {
            $adminShipData = $this->dataObjectFactory->create();
            // To prevent description duplication in the order,
            // we need to remove hardcoded ShipperHQ parts.
            // see: ShipperHQ\Shipper\Observer\SaveShippingAdmin
            $adminShipData->setCustomCarrier(
                str_replace(
                    $this->customCarrierDesctiptionPartsToRemove,
                    '',
                    $shippingAddress->getShippingDescription()
                )
            );
            $adminShipData->setCustomPrice($shippingAddress->getShippingAmount());
            $this->backendQuoteSession->setShqAdminShipData($adminShipData);
        }
    }
}
