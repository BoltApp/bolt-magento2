<?php

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Aheadworks;

use Bolt\Boltpay\ThirdPartyModules\Aheadworks\Sarp2;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Sarp2Test extends TestCase
{
    const CHECK_SPECIFICATION_FACTORY_CLASS = 'Magento\Payment\Model\Checks\SpecificationFactory';

    /**
     * @var \Magento\Framework\ObjectManager\ObjectManager|MockObject
     */
    private $objectManagerMock;

    /**
     * @var \Magento\Framework\App\ObjectManager\ConfigLoader\Compiled|MockObject
     */
    private $compiledConfigLoaderMock;

    /**
     * @var \Magento\Framework\App\ObjectManager\ConfigLoader|MockObject
     */
    private $configLoaderMock;

    /**
     * @var Sarp2|MockObject
     */
    private $currentMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->objectManagerMock = $this->createMock(\Magento\Framework\ObjectManager\ObjectManager::class);
        $this->configLoaderMock = $this->createMock(\Magento\Framework\App\ObjectManager\ConfigLoader::class);
        $this->compiledConfigLoaderMock = $this->createMock(
            \Magento\Framework\App\ObjectManager\ConfigLoader\Compiled::class
        );
    }

    /**
     * @test
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new Sarp2($this->objectManagerMock, $this->configLoaderMock);
        static::assertAttributeEquals($this->objectManagerMock, 'objectManager', $instance);
        static::assertAttributeEquals($this->configLoaderMock, 'configLoader', $instance);
    }

    /**
     * @param ConfigLoaderInterface|MockObject $configLoader
     * @param array                            $globalConfig
     * @param array                            $adminConfig
     */
    private function adminhtmlControllerActionPredispatchSalesOrderCreateIndexSetUp(
        ConfigLoaderInterface $configLoader,
        $globalConfig,
        $adminConfig
    ) {
        $configLoader->method('load')->willReturnMap(
            [
                [\Magento\Framework\App\Area::AREA_GLOBAL, $globalConfig],
                [\Magento\Framework\App\Area::AREA_ADMINHTML, $adminConfig],
            ]
        );
        $this->currentMock = $this->getMockBuilder(Sarp2::class)
            ->setConstructorArgs([$this->objectManagerMock, $configLoader])
            ->setMethods(null)
            ->getMock();
    }

    /**
     * @test
     * that adminhtmlControllerActionPredispatchSalesOrderCreateIndex reconfigures DI configuration to resolve issue
     * with Aheadworks_Sarp2 module
     *
     * @dataProvider adminhtmlControllerActionPredispatchSalesOrderCreateIndexProvider
     *
     * @param array $globalConfig
     * @param array $adminConfig
     * @param bool  $isProductionMode
     * @param array $mergedConfiguration
     */
    public function adminhtmlControllerActionPredispatchSalesOrderCreateIndex_withVariousModesAndConfigurations_reconfiguresDI(
        $globalConfig,
        $adminConfig,
        $isProductionMode,
        $mergedConfiguration
    ) {
        $this->adminhtmlControllerActionPredispatchSalesOrderCreateIndexSetUp(
            $isProductionMode ? $this->compiledConfigLoaderMock : $this->configLoaderMock,
            $globalConfig,
            $adminConfig
        );
        $this->objectManagerMock->expects(static::once())->method('configure')->with($mergedConfiguration);
        $this->currentMock->adminhtmlControllerActionPredispatchSalesOrderCreateIndex();
    }

    /**
     * Data provider for {@see adminhtmlControllerActionPredispatchSalesOrderCreateIndex_withVariousModesAndConfigurations_reconfiguresOM}
     *
     * @return array[] containing global and admin DI config arrays, whether the production mode is set
     * and the expected result
     */
    public function adminhtmlControllerActionPredispatchSalesOrderCreateIndexProvider()
    {
        return [
            [
                'globalConfig'        => [
                    self::CHECK_SPECIFICATION_FACTORY_CLASS => [
                        'arguments' => [
                            'mapping' => [
                                'country'    => ['instance' => 'Magento\\Payment\\Model\\Checks\\CanUseForCountry',],
                                'currency'   => ['instance' => 'Magento\\Payment\\Model\\Checks\\CanUseForCurrency',],
                                'checkout'   => ['instance' => 'Magento\\Payment\\Model\\Checks\\CanUseCheckout',],
                                'internal'   => ['instance' => 'Magento\\Payment\\Model\\Checks\\CanUseInternal',],
                                'total'      => ['instance' => 'Magento\\Payment\\Model\\Checks\\TotalMinMax',],
                                'zero_total' => ['instance' => 'Magento\\Payment\\Model\\Checks\\ZeroTotal',],
                            ],
                        ],
                    ]
                ],
                'adminConfig'         => [
                    self::CHECK_SPECIFICATION_FACTORY_CLASS => [
                        'arguments' => [
                            'mapping' => [
                                'check_recurring' => [
                                    'instance' => 'Aheadworks\\Sarp2\\Model\\Payment\\Checks\\Recurring',
                                ],
                            ],
                        ],
                    ]
                ],
                'isProductionMode'    => false,
                'mergedConfiguration' => [
                    self::CHECK_SPECIFICATION_FACTORY_CLASS => [
                        'arguments' => [
                            'mapping' => [
                                'country'         => [
                                    'instance' => 'Magento\\Payment\\Model\\Checks\\CanUseForCountry'
                                ],
                                'currency'        => [
                                    'instance' => 'Magento\\Payment\\Model\\Checks\\CanUseForCurrency'
                                ],
                                'checkout'        => [
                                    'instance' => 'Magento\\Payment\\Model\\Checks\\CanUseCheckout'
                                ],
                                'internal'        => [
                                    'instance' => 'Magento\\Payment\\Model\\Checks\\CanUseInternal'
                                ],
                                'total'           => [
                                    'instance' => 'Magento\\Payment\\Model\\Checks\\TotalMinMax'
                                ],
                                'zero_total'      => [
                                    'instance' => 'Magento\\Payment\\Model\\Checks\\ZeroTotal'
                                ],
                                'check_recurring' => [
                                    'instance' => 'Aheadworks\\Sarp2\\Model\\Payment\\Checks\\Recurring'
                                ],
                            ],
                        ],
                    ],
                ]
            ],
            [
                'globalConfig'        => [
                    'arguments' => [
                        self::CHECK_SPECIFICATION_FACTORY_CLASS => [
                            'compositeFactory' => ['_i_' => 'Magento\\Payment\\Model\\Checks\\CompositeFactory',],
                            'mapping'          => [
                                '_vac_' => [
                                    'country'    => ['_i_' => 'Magento\\Payment\\Model\\Checks\\CanUseForCountry',],
                                    'currency'   => ['_i_' => 'Magento\\Payment\\Model\\Checks\\CanUseForCurrency',],
                                    'checkout'   => ['_i_' => 'Magento\\Payment\\Model\\Checks\\CanUseCheckout',],
                                    'internal'   => ['_i_' => 'Magento\\Payment\\Model\\Checks\\CanUseInternal',],
                                    'total'      => ['_i_' => 'Magento\\Payment\\Model\\Checks\\TotalMinMax\\Interceptor',],
                                    'zero_total' => ['_i_' => 'Magento\\Payment\\Model\\Checks\\ZeroTotal\\Interceptor',],
                                ],
                            ],
                        ],
                    ]
                ],
                'adminConfig'         => [
                    'arguments' => [
                        self::CHECK_SPECIFICATION_FACTORY_CLASS => [
                            'compositeFactory' => ['_i_' => 'Magento\\Payment\\Model\\Checks\\CompositeFactory',],
                            'mapping'          => [
                                '_vac_' => [
                                    'check_recurring' => [
                                        '_i_' => 'Aheadworks\\Sarp2\\Model\\Payment\\Checks\\Recurring',
                                    ],
                                ],
                            ],
                        ],
                    ]
                ],
                'isProductionMode'    => true,
                'mergedConfiguration' => [
                    'arguments' => [
                        self::CHECK_SPECIFICATION_FACTORY_CLASS => [
                            'compositeFactory' => ['_i_' => 'Magento\\Payment\\Model\\Checks\\CompositeFactory',],
                            'mapping'          => [
                                '_vac_' => [
                                    'country'         => ['_i_' => 'Magento\\Payment\\Model\\Checks\\CanUseForCountry',],
                                    'currency'        => ['_i_' => 'Magento\\Payment\\Model\\Checks\\CanUseForCurrency',],
                                    'checkout'        => ['_i_' => 'Magento\\Payment\\Model\\Checks\\CanUseCheckout',],
                                    'internal'        => ['_i_' => 'Magento\\Payment\\Model\\Checks\\CanUseInternal',],
                                    'total'           => ['_i_' => 'Magento\\Payment\\Model\\Checks\\TotalMinMax\\Interceptor',],
                                    'zero_total'      => ['_i_' => 'Magento\\Payment\\Model\\Checks\\ZeroTotal\\Interceptor',],
                                    'check_recurring' => ['_i_' => 'Aheadworks\\Sarp2\\Model\\Payment\\Checks\\Recurring',],
                                ],
                            ],
                        ],
                    ],
                ]
            ]
        ];
    }
}
