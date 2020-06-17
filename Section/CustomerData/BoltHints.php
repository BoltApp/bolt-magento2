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
namespace Bolt\Boltpay\Section\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;

class BoltHints implements SectionSourceInterface
{

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var ConfigHelper
     */
    private $configHelper;


    public function __construct(
        CartHelper $cartHelper,
        ConfigHelper $configHelper
    ) {
        $this->cartHelper = $cartHelper;
        $this->configHelper = $configHelper;
    }

    public function getSectionData()
    {
        if (!$this->configHelper->getProductPageCheckoutFlag()) {
            return [];
        }
        return [
            'data' => $this->cartHelper->getHints(null, 'product'),
        ];
    }
}
