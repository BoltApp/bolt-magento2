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

namespace Bolt\Boltpay\Api;

use Bolt\Boltpay\Api\Data\FeatureSwitchInterface;

interface FeatureSwitchRepositoryInterface
{
    /**
     * @param string $name
     * @return \Bolt\Boltpay\Api\Data\FeatureSwitchInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByName($name);

    /**
     * @param string $name
     * @param bool $value
     * @param bool $defaultValue
     * @param int $rolloutPercentage
     * @return \Bolt\Boltpay\Api\Data\FeatureSwitchInterface
     */
    public function upsertByName($name, $value, $defaultValue, $rolloutPercentage);

    /**
     * @param \Bolt\Boltpay\Api\Data\FeatureSwitchInterface $switch
     * @return \Bolt\Boltpay\Api\Data\FeatureSwitchInterface
     */
    public function save(\Bolt\Boltpay\Api\Data\FeatureSwitchInterface $switch);

    /**
     * @param \Bolt\Boltpay\Api\Data\FeatureSwitchInterface $switch
     * @return void
     */
    public function delete(\Bolt\Boltpay\Api\Data\FeatureSwitchInterface $switch);
}
