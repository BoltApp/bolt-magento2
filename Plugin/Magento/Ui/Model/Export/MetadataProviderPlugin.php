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
namespace Bolt\Boltpay\Plugin\Magento\Ui\Model\Export;

use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Ui\Model\Export\MetadataProvider;
use Bolt\Boltpay\Model\Payment;
use Bolt\Boltpay\Helper\Order as BoltOrderHelper;
use Bolt\Boltpay\Helper\Config;

/**
 * Updating export row data
 */
class MetadataProviderPlugin
{
    private const PAYMENT_METHOD_KEY = 'payment_method';

    private const CC_TYPE_KEY = 'cc_type';

    private const CC_TRANS_ID_KEY = 'cc_trans_id';

    private const ADDITIONAL_DATA_KEY = 'additional_data';

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Modify payment method data to use Bolt payment method including cc type
     *
     * @param MetadataProvider $subject
     * @param array $result
     * @param DocumentInterface $document
     * @param $fields
     * @param $options
     * @return array
     */
    public function afterGetRowData(
        MetadataProvider $subject,
        array $result,
        DocumentInterface $document,
        $fields,
        $options
    ): array {
        $paymentMethodFieldKey = $this->getPaymentMethodFieldKey($fields);
        if (!$paymentMethodFieldKey || !$this->isBoltPaymentMethod($options, $document)) {
            return $result;
        }

        if ($this->config->getShowCcTypeInOrderGrid() && $this->isSupportedCcType($document)) {
            $result[$paymentMethodFieldKey] = 'Bolt-' . BoltOrderHelper::SUPPORTED_CC_TYPES[$document->getData(self::CC_TYPE_KEY)];
        } elseif ($this->isApiFlow($document)) {
            $result[$paymentMethodFieldKey] = $document->getData(self::ADDITIONAL_DATA_KEY);
        }

        return $result;
    }

    /**
     * Get payment method field key
     *
     * @param $fields
     * @return string|null
     */
    private function getPaymentMethodFieldKey($fields): ?string
    {
        $paymentMethodFieldKeyArr = array_keys($fields, self::PAYMENT_METHOD_KEY);
        return !empty($paymentMethodFieldKeyArr) ? array_shift($paymentMethodFieldKeyArr) : null;
    }

    /**
     * Check if the document has Bolt payment method
     *
     * @param $options
     * @param DocumentInterface $document
     * @return bool
     */
    private function isBoltPaymentMethod($options, DocumentInterface $document): bool
    {
        return isset($options[self::PAYMENT_METHOD_KEY][Payment::METHOD_CODE]) && $document->getData(self::PAYMENT_METHOD_KEY) == Payment::METHOD_CODE;
    }

    /**
     * Check if the document has supported cc type
     *
     * @param DocumentInterface $document
     * @return bool
     */
    private function isSupportedCcType(DocumentInterface $document): bool
    {
        return $document->getData(self::CC_TYPE_KEY) && array_key_exists($document->getData(self::CC_TYPE_KEY), BoltOrderHelper::SUPPORTED_CC_TYPES);
    }

    /**
     * Check if the document is from API flow
     *
     * @param DocumentInterface $document
     * @return bool
     */
    private function isApiFlow(DocumentInterface $document): bool
    {
        return $document->getData(self::CC_TRANS_ID_KEY) && $document->getData(self::ADDITIONAL_DATA_KEY);
    }
}
