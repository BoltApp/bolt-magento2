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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\Listrak;

use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

class Remarketing
{
    const BOLT_LISTRAK_FIELD_ID = 'bolt_listrak';
    const XML_PATH_LISTRAK_REMARKETING_TRACK_WITH_LISTRAK = 'remarketing/onescript/enabled';
    const XML_PATH_LISTRAK_REMARKETING_MERCHANT_ID = 'remarketing/onescript/merchant_id';

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfigInterface;

    /**
     * @var Decider
     */
    private $decider;

    /**
     * Remarketing constructor.
     * @param Bugsnag $bugsnagHelper
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param Decider $decider
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        ScopeConfigInterface $scopeConfigInterface,
        Decider $decider
    )
    {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->bugsnagHelper = $bugsnagHelper;
        $this->decider = $decider;
    }

    /**
     * Add js to capture email to Listrak whenever customer enter email in the Botl modal
     *
     * @param string $result
     * @return string
     */
    public function getOnEmailEnter($result)
    {
        if (!$this->decider->isCaptureEmailToListrakEnabled() || !$this->isListrakEnabled()) {
            return $result;
        }

        try {
            $listrakId = self::BOLT_LISTRAK_FIELD_ID;
            $result .= "jQuery('#$listrakId').val(email);
        _ltk.SCA.CaptureEmail('$listrakId');";
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }

    /**
     * Add the hidden Listrak field
     *
     * @param $result
     * @return string
     */
    public function getAdditionalHtml($result)
    {
        if (!$this->decider->isCaptureEmailToListrakEnabled() || !$this->isListrakEnabled()) {
            return $result;
        }
        try {
            $listrakId = self::BOLT_LISTRAK_FIELD_ID;
            $result .= "<input id='$listrakId' name='$listrakId' type='hidden'>";
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }

    /**
     * @param null $storeId
     * @return bool
     */
    private function isListrakEnabled()
    {
        return $this->scopeConfigInterface->getValue(self::XML_PATH_LISTRAK_REMARKETING_TRACK_WITH_LISTRAK, ScopeInterface::SCOPE_STORE)
            && $this->scopeConfigInterface->getValue(self::XML_PATH_LISTRAK_REMARKETING_MERCHANT_ID, ScopeInterface::SCOPE_STORE);
    }
}
