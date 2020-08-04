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

namespace Bolt\Boltpay\Controller\Cart;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Bolt\Boltpay\Helper\Bugsnag;
use Exception;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Class Hints
 * Return Hints data for Product Page Checkout
 */
class Hints extends Action
{
    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var Bugsnag */
    private $bugsnag;

    /** @var CartHelper */
    private $cartHelper;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory,
     * @param Bugsnag $bugsnag
     * @param CartHelper $cartHelper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Bugsnag $bugsnag,
        CartHelper $cartHelper
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cartHelper = $cartHelper;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Get hints for Product page checkout
     *
     * @return Json
     */
    public function execute()
    {
        try {
            $result = $this->resultJsonFactory->create();
            $hints = $this->cartHelper->getHints(null, 'product');
            $result->setData([
                'hints' => $hints
            ]);

        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);

            $result->setData([
                'status' => 'failure',
            ]);
        } finally {
            return $result;
        }
    }
}
