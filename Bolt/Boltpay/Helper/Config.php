<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ResourceInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\Store;

/**
 * Boltpay Configuration helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Config extends AbstractHelper
{
    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;

	/**
	 * Path for API Key
	 */
	const XML_PATH_API_KEY = 'payment/boltpay/api_key';

	/**
	 * Path for Signing Secret
	 */
	const XML_PATH_SIGNING_SECRET = 'payment/boltpay/signing_secret';

	/**
	 * Path for publishable key payment only
	 */
	const XML_PATH_PUBLISHABLE_KEY_PAYMENT = 'payment/boltpay/publishable_key_payment';

	/**
	 * Path for publishable key multi-step
	 */
	const XML_PATH_PUBLISHABLE_KEY_CHECKOUT = 'payment/boltpay/publishable_key_checkout';

	/**
	 * Path for Replace Selectors
	 */
	const XML_PATH_REPLACE_SELECTORS = 'payment/boltpay/replace_selectors';

	/**
     * Automatic capture mode
     */
    const XML_PATH_AUTOMATIC_CAPTURE_MODE = 'payment/boltpay/automatic_capture_mode';

    /**
     * Enabled
     */
    const XML_PATH_ACTIVE = 'payment/boltpay/active';

	/**
	 * Debug
	 */
	const XML_PATH_DEBUG = 'payment/boltpay/debug';

	/**
	 * Create cart
	 */
	const CREATE_ORDER_ACTION = 'boltpay/cart/data';

	/**
	 * Save order
	 */
	const SAVE_ORDER_ACTION = 'boltpay/order/save';

	/**
	 * Save order
	 */
	const XML_PATH_SUCCESS_PAGE_REDIRECT = 'payment/boltpay/success_page';

	/**
	 * Path for sandbox mode
	 */
	const XML_PATH_SANDBOX_MODE = 'payment/boltpay/sandbox_mode';

	/**
	 * @var ResourceInterface
	 */
	protected $moduleResource;
	/**
	 * @var ProductMetadataInterface
	 */
	private $productMetadata;


	/**
	 * @param Context $context
	 * @param EncryptorInterface $encryptor
	 * @param ResourceInterface $moduleResource
	 *
	 * @param ProductMetadataInterface $productMetadata
	 *
	 * @codeCoverageIgnore
	 */
    public function __construct(
        Context                  $context,
        EncryptorInterface       $encryptor,
	    ResourceInterface        $moduleResource,
	    ProductMetadataInterface $productMetadata
    ) {
        parent::__construct($context);
        $this->_encryptor      = $encryptor;
	    $this->moduleResource  = $moduleResource;
	    $this->productMetadata = $productMetadata;
    }

	/**
	 * Get module version
	 * @return false|string
	 */
	public function getModuleVersion() {
		return $this->moduleResource->getDataVersion('Bolt_Boltpay');
	}

	/**
	 * Get store version
	 * @return false|string
	 */
	public function getStoreVersion() {
		return $this->productMetadata->getVersion();
	}

	/**
	 * Helper method to Get Encrypted Key from config.
	 * Return decrypted.
	 *
	 * @param string $path
	 *
	 * @return  string
	 */
	private function getEncryptedKey($path)
	{
		//Get management key
		$key =  $this->scopeConfig->getValue(
			$path,
			ScopeInterface::SCOPE_STORE
		);

		//Decrypt management key
		$key = $this->_encryptor->decrypt($key);
		return $key;
	}

	/**
	 * Get one Publishable Key from config
	 *
	 * @return  string
	 */
	public function getAnyPublishableKey()
	{
		return $this->getPublishableKeyCheckout() ?: $this->getPublishableKeyPayment();
	}

	/**
	 * Get API Key from config
	 *
	 * @return  string
	 */
	public function getApiKey()
	{
		return $this->getEncryptedKey(self::XML_PATH_API_KEY);
	}

	/**
	 * Get Signing Secret Key from config
	 *
	 * @return  string
	 */
	public function getSigningSecret()
	{
		return $this->getEncryptedKey(self::XML_PATH_SIGNING_SECRET);
	}

	/**
	 * Get Multi-step Publishable Key from config
	 *
	 * @return  string
	 */
	public function getPublishableKeyCheckout()
	{
		return $this->getEncryptedKey(self::XML_PATH_PUBLISHABLE_KEY_CHECKOUT);
	}

	/**
	 * Get Payment Only Publishable Key from config
	 *
	 * @return  string
	 */
	public function getPublishableKeyPayment()
	{
		return $this->getEncryptedKey(self::XML_PATH_PUBLISHABLE_KEY_PAYMENT);
	}

	/**
	 * Get Replace Selectors from config
	 *
	 * @return  string
	 */
	public function getReplaceSelectors()
	{
		return $this->scopeConfig->getValue(
			self::XML_PATH_REPLACE_SELECTORS,
			ScopeInterface::SCOPE_STORE
		);
	}

	/**
	 * Get Success Page Redirect from config
	 *
	 * @return  string
	 */
	public function getSuccessPageRedirect()
	{
		return $this->scopeConfig->getValue(
			self::XML_PATH_SUCCESS_PAGE_REDIRECT,
			ScopeInterface::SCOPE_STORE
		);
	}

	/**
     * Get Automatic Capture mode from config
     *
     * @param int|string|Store $store
     *
     * @return  boolean
     */
    public function getAutomaticCaptureMode($store = null)
    {
        //Automatic capture mode
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_AUTOMATIC_CAPTURE_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }


    /**
     * Check if module is enabled
     *
     * @param int|string|Store $store
     *
     * @return  boolean
     */
    public function isActive($store = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if debug mode is on
     *
     * @param int|string|Store $store
     *
     * @return  boolean
     */
    public function isDebugModeOn($store = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

	/**
	 * Get sandbox mode config value
	 *
	 * @param int|string|Store $store
	 *
	 * @return bool
	 * @SuppressWarnings(PHPMD.BooleanGetMethodName)
	 * @codeCoverageIgnore
	 */
	public function isSandboxModeSet($store = null) {
		return $this->scopeConfig->isSetFlag(
			self::XML_PATH_SANDBOX_MODE,
			ScopeInterface::SCOPE_STORE, $store
		);
	}
}
