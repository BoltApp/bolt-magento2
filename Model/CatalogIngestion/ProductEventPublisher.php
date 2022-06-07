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

use Magento\Framework\Bulk\BulkManagementInterface;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory;
use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ObjectManager;

/**
 * Product event async consumer job publisher
 */
class ProductEventPublisher
{
    private const TOPIC_NAME = 'async.bolt.boltpay.api.producteventmanagerinterface.sendproductevent.post';

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
     * @var string|null
     */
    private $operationFactoryClass;

    /**
     * @var string|null
     */
    private $bulkManagementClass;
    /**
     * @param IdentityGeneratorInterface $identityGenerator
     * @param UserContextInterface $userContext
     * @param Json $jsonSerializer
     * @param string|null $operationFactoryClass
     * @param string|null $bulkManagementClass
     */
    public function __construct(
        IdentityGeneratorInterface $identityGenerator,
        UserContextInterface $userContext,
        Json $jsonSerializer,
        string $operationFactoryClass = null,
        string $bulkManagementClass = null
    ) {
        $this->objectManager = ObjectManager::getInstance();
        $this->identityGenerator = $identityGenerator;
        $this->userContext = $userContext;
        $this->jsonSerializer = $jsonSerializer;
        $this->operationFactoryClass = $operationFactoryClass;
        $this->bulkManagementClass = $bulkManagementClass;
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
        $operationFactory = $this->initOperationFactory();
        $bulkManagement = $this->initBulkManagement();

        if (!$operationFactory || !$bulkManagement) {
            throw new LocalizedException(
                __(
                    'Magento Asynchronous Operations is not supported on your magento version, please verify.'
                )
            );
        }
        $description = __('Publish product event, product_id: %1', $productId);
        $userId = $this->userContext->getUserId();
        $bulkId = $this->identityGenerator->generateId();
        try {
            if (!$bulkManagement->scheduleBulk($bulkId, [], $description, $userId)) {
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
                    OperationInterface::TOPIC_NAME => self::TOPIC_NAME,
                    OperationInterface::SERIALIZED_DATA => $this->jsonSerializer->serialize($serializedData),
                    OperationInterface::STATUS => OperationInterface::STATUS_TYPE_OPEN,
                ]
            ];

            $operations[] = $operationFactory->create($data);
            if (!$bulkManagement->scheduleBulk($bulkId, $operations, $description, $userId)) {
                throw new LocalizedException(
                    __(
                        'Something went wrong while scheduling product event bulk %1 Check logs for details.',
                        $description
                    )
                );
            }
        } catch (\Exception $e) {
            if (isset($operations)) {
                $bulkManagement->deleteBulk($bulkId);
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

    /**
     * Init operation factory class, for Magento 2.2 support
     *
     * @return mixed|null
     */
    private function initOperationFactory()
    {
        if (!$this->operationFactoryClass) {
            return null;
        }
        return (class_exists($this->operationFactoryClass) || interface_exists($this->operationFactoryClass))
            ? $this->objectManager->get($this->operationFactoryClass) : null;
    }

    /**
     * Init bulk management instance, for Magento 2.2 support
     *
     * @return mixed|null
     */
    private function initBulkManagement()
    {
        if (!$this->bulkManagementClass) {
            return null;
        }

        if (class_exists($this->bulkManagementClass) || interface_exists($this->bulkManagementClass)) {
            $publisher = $this->objectManager->create('Magento\Framework\MessageQueue\PublisherPool', [
                'publishers' => [
                    'async' => [
                        'amqp' => $this->objectManager->get('Magento\AsynchronousOperations\Model\MassPublisher'),
                        'db' => $this->objectManager->get('Magento\AsynchronousOperations\Model\MassPublisher')
                    ]
                ]
            ]);
            return $this->objectManager->create($this->bulkManagementClass, ['publisher' => $publisher]);
        }
        return null;
    }
}
