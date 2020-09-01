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

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;

class EventsForThirdPartyModulesMock extends EventsForThirdPartyModules {
    const eventListeners = [
        "shouldCall" => [
            "listeners" => [
                [
                    "module" => "Bolt_Boltpay",
                    "checkClasses" => ["Bolt\Boltpay\Model\EventsForThirdPartyModules"],
                    "boltClass" => "Bolt\Boltpay\Test\Unit\Model\ListenerMock",
                ],
            ],
        ],
        "moduleDoesNotEnabled" => [
            "listeners" => [
                [
                    "module" => "Bolt_AnotherName",
                    "checkClasses" => ["Bolt\Boltpay\Model\EventsForThirdPartyModules"],
                    "boltClass" => "Bolt\Boltpay\Test\Unit\Model\ListenerMock",
                ],
            ],
        ],
        "classDoesNotExist" => [
            "listeners" => [
                [
                    "module" => "Bolt_AnotherName",
                    "checkClasses" => ["Bolt\Boltpay\Model\WrongClassName"],
                    "boltClass" => "Bolt\Boltpay\Test\Unit\Model\ListenerMock",
                ],
            ],
        ]
    ];
    const filterListeners = [
        "runFilter" => [
            "listeners" => [
                [
                    "module" => "Bolt_Boltpay",
                    "sendClasses" => ["Bolt\Boltpay\Model\EventsForThirdPartyModules"],
                    "boltClass" => "Bolt\Boltpay\Test\Unit\Model\ListenerMock",
                ],
            ],
        ],
    ];
}
