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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\CatalogIngestion;

use Bolt\Boltpay\Helper\Config as BoltConfig;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory;
use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ObjectManager;
use Magento\AsynchronousOperations\Model\BulkManagement;

/**
 * Product event async consumer job publisher
 */
class ProductEventPublisher
{
    private const TOPIC_NAME = 'async.bolt.boltpay.api.producteventmanagerinterface.sendproductevent.post';

    private const TOPIC_NAME_V1 = 'async.V1.bolt.boltpay.producteventrequest.POST';

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var IdentityGeneratorInterface
     */
    private $identityGenerator;

    /**
     * @var UserContextInterface
     */
    private $userContext;

    /**
     * @var Json
     */
    private $jsonSerializer;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var BulkManagement
     */
    private $bulkManagement;

    /**
     * @var OperationInterfaceFactory
     */
    private $operationFactory;

    /**
     * @var BoltConfig
     */
    private $boltConfig;

    /**
     * @param IdentityGeneratorInterface $identityGenerator
     * @param UserContextInterface $userContext
     * @param Json $jsonSerializer
     * @param BoltConfig $boltConfig
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        IdentityGeneratorInterface $identityGenerator,
        UserContextInterface $userContext,
        Json $jsonSerializer,
        BoltConfig $boltConfig,
        ModuleManager $moduleManager
    ) {
        $this->objectManager = ObjectManager::getInstance();
        $this->identityGenerator = $identityGenerator;
        $this->userContext = $userContext;
        $this->jsonSerializer = $jsonSerializer;
        $this->boltConfig = $boltConfig;
        $this->moduleManager = $moduleManager;
        if ($this->moduleManager->isEnabled('Magento_AsynchronousOperations')) {
            $this->operationFactory = $this->objectManager
                ->get('Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory');

            $publisher = $this->objectManager->create('Magento\Framework\MessageQueue\PublisherPool', [
                'publishers' => [
                    'async' => [
                        'amqp' => $this->objectManager->get('Magento\AsynchronousOperations\Model\MassPublisher'),
                        'db' => $this->objectManager->get('Magento\AsynchronousOperations\Model\MassPublisher')
                    ]
                ]
            ]);

            $this->bulkManagement = $this->objectManager
                ->create('Magento\AsynchronousOperations\Model\BulkManagement', ['publisher' => $publisher]);
        }
    }

    /**
     * Publish product event to the bulk async message queue
     *
     * @param int $productId
     * @param string $type
     * @param string $date
     * @return string
     * @throws LocalizedException
     */
    public function publishBulk(int $productId, string $type, string $date): string
    {
        if (!$this->operationFactory || !$this->bulkManagement) {
            throw new LocalizedException(
                __(
                    'Magento Asynchronous Operations is not supported on your magento version, please verify.'
                )
            );
        }
        $topicName = (version_compare($this->boltConfig->getStoreVersion(), '2.4.0', '<')) ?
            self::TOPIC_NAME_V1 : self::TOPIC_NAME;

        $description = __('Publish product event, product_id: %1', $productId);
        $userId = $this->userContext->getUserId();
        $bulkId = $this->identityGenerator->generateId();
        try {
            if (!$this->bulkManagement->scheduleBulk($bulkId, [], $description, $userId)) {
                throw new LocalizedException(
                    __(
                        'Something went wrong while scheduling product event bulk %1 Check logs for details.',
                        $description
                    )
                );
            }
            $operations = [];
            $entityParams = [
                'productEvent' => [
                    'product_id' => $productId,
                    'type' => $type,
                    'created_at' => $date
                ]
            ];
            $encodedMessage =  $this->jsonSerializer->serialize($entityParams);
            $serializedData = [
                'entity_id' => null,
                'entity_link' => '',
                'meta_information' => $encodedMessage
            ];

            $data = [
                'data' => [
                    OperationInterface::ID => 0,
                    OperationInterface::BULK_ID => $bulkId,
                    OperationInterface::TOPIC_NAME => $topicName,
                    OperationInterface::SERIALIZED_DATA => $this->jsonSerializer->serialize($serializedData),
                    OperationInterface::STATUS => OperationInterface::STATUS_TYPE_OPEN,
                ]
            ];

            $operations[] = $this->operationFactory->create($data);
            if (!$this->bulkManagement->scheduleBulk($bulkId, $operations, $description, $userId)) {
                throw new LocalizedException(
                    __(
                        'Something went wrong while scheduling product event bulk %1 Check logs for details.',
                        $description
                    )
                );
            }
        } catch (\Exception $e) {
            if (isset($operations)) {
                $this->bulkManagement->deleteBulk($bulkId);
            }
            throw new LocalizedException(
                __(
                    'Error during publishing product event bulk "%1". Message: %2',
                    $description,
                    $e->getMessage()
                )
            );
        }
        return $bulkId;
    }
}
