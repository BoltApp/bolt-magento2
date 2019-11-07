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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin\Mirasvit\Rewards\Model;

use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\UrlInterface;

/**
 * Class PurchasePlugin
 *
 * This overrides {@see \Mirasvit\Rewards\Model\Purchase}
 *
 * @package Bolt\Boltpay\Plugin
 */
class PurchasePlugin
{

    /**
     * @var Bugsnag Error reporting interface
     */
    private $bugsnag;

    /**
     * @var UrlInterface Used for Manipulating Magento Interfaces
     */
    private $urlInterface;

    /**
     * MirasvitRewardsPlugin constructor.
     *
     * @param Bugsnag $bugsnag Error reporting interface
     */
    public function __construct(Bugsnag $bugsnag, UrlInterface $urlInterface)
    {
        $this->bugsnag = $bugsnag;
        $this->urlInterface = $urlInterface;
    }

    /**
     * Due to Mirasvit implementation error in the context of the shopping cart page, refreshing the
     * point is causing the points used to be reset to zero due to multiple calls such as
     * in the event that the full amount of allowed points are used.  There is no need for the points to be force
     * reset in the shopping cart context, so we prevent the reset from happening so that Bolt can record and
     * properly display the Mirasvit Rewards Points in the model.
     *
     * Additionally, this change actually fixes the frontend presentation errors of Mirasvit when using points
     * above that of the shopping cart subtotal
     *
     * @param \Mirasvit\Rewards\Model\Purchase  $subject  The Mirasvit Purchase object
     * @param callable                          $proceed  The `refreshPointsNumber` function of the subject object
     * @param mixed                             $args     "bool $forceRefresh - must be used only if we 100% sure
     *                                                     that it will be called once." (inherited doc)
     *
     * @return \Mirasvit\Rewards\Model\Purchase The Mirasvit Purchase object
     *
     * @throws \Exception (inherited doc)
     */
    public function aroundRefreshPointsNumber(\Mirasvit\Rewards\Model\Purchase $subject, callable $proceed, ...$args)
    {
        if (
            stripos($this->urlInterface->getCurrentUrl(), '/rewards/checkout/updatePaymentMethodPost') !== false
        ) {
            return $subject;
        }

        return $proceed(...$args);
    }
}
