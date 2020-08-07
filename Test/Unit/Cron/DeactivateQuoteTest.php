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

namespace Bolt\Boltpay\Test\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Cron\DeactivateQuote;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Zend\Http\Header\Connection;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Class DeactivateQuoteTest
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class DeactivateQuoteTest extends TestCase
{
    /**
     * @var DeactivateQuote
     */
    private $deactivateQuote;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var Connection
     */
    private $connection;

    public function setUp()
    {
        $this->resourceConnection = $this->createPartialMock(
            ResourceConnection::class,
            ['getConnection', 'getTableName']
        );

        $this->bugsnag = $this->createPartialMock(
            Bugsnag::class,
            ['notifyException']
        );

        $this->deactivateQuote = $this->getMockBuilder(DeactivateQuote::class)
            ->setConstructorArgs(
                [
                    $this->resourceConnection,
                    $this->bugsnag
                ]
            )
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $this->connection = $this->createPartialMock(Mysql::class, ['query']);
    }

    /**
     * @test
     * @param $data
     * @dataProvider execute_dataProvider
     */
    public function execute($data)
    {
        $this->resourceConnection->expects(self::once())->method('getConnection')->willReturn($this->connection);

        $this->resourceConnection->method('getTableName')->withConsecutive(
            ['quote'],
            ['sales_order'],
            ['sales_order_payment']
        )->willReturnOnConsecutiveCalls('quote', 'sales_order', 'sales_order_payment');

        $this->connection->expects(self::once())->method('query')->will($this->returnCallback(
            function ($data) {
                $this->assertEquals(
                    'UPDATE quote SET is_active = 0 WHERE is_active = 1 AND entity_id IN (SELECT so.quote_id FROM sales_order AS so INNER JOIN sales_order_payment AS sop ON so.entity_id = sop.parent_id WHERE sop.method = "boltpay")',
                    $data
                );
            }
        ));

        if ($data['exception']) {
            $this->connection->expects(self::once())->method('query')->willThrowException(new \Exception($data['exception']));
            $this->bugsnag->expects(self::once())->method('notifyException')->will(
                $this->returnCallback(
                    function ($data) {
                        $this->assertEquals(__('Error when run query'), $data->getMessage());
                    }
                )
            );
        } else {
            $this->connection->expects(self::once())->method('query')->willReturnSelf();
        }

        $this->deactivateQuote->execute();
    }

    public function execute_dataProvider()
    {
        return [
            ['data' => [
                'exception' => __('Error when run query'),
            ]
            ],
            ['data' => [
                'exception' => false,
            ]
            ]
        ];
    }
}
