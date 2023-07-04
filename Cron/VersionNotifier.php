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

namespace Bolt\Boltpay\Cron;

/**
 * New magento plugin version notifier
 */
class VersionNotifier
{
    /**
     * @var \Bolt\Boltpay\Model\VersionNotifier\VersionValidator
     */
    private $versionValidator;

    public function __construct(
        \Bolt\Boltpay\Model\VersionNotifier\VersionValidator $versionValidator
    ) {
        $this->versionValidator = $versionValidator;
    }
    /**
     * Getting last version data from git API
     */
    public function execute(): void
    {
        $this->versionValidator->checkVersions();
    }
}
