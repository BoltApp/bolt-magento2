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
 *
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Block\Adminhtml\System\Config\Form;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\Framework\Exception\LocalizedException;

/**
 * Frontend model for configuration fields that is conditionally rendered depending on feature switch value
 *
 * @example
 * <attribute type='feature_switch'>M2_STORE_PUBLISHABLE_KEYS_UNENCRYPTED</attribute>
 * <attribute type='feature_switch_value'>0</attribute>
 * <frontend_model>Bolt\Boltpay\Block\Adminhtml\System\Config\Form\FeatureSwitchAwareField</frontend_model>
 */
class FeatureSwitchAwareField extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var Decider
     */
    private $featureSwitch;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * FeatureSwitchAwareField constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param Decider                                 $featureSwitch
     * @param Bugsnag                                 $bugsnag
     * @param array                                   $data
     */
    public function __construct(\Magento\Backend\Block\Template\Context $context, Decider $featureSwitch, Bugsnag $bugsnag, array $data = [])
    {
        parent::__construct($context, $data);
        $this->featureSwitch = $featureSwitch;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Renders the element if the required feature switch
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $featureSwitch = $element->getFieldConfig('feature_switch');
        $featureSwitchRequirement = $element->getFieldConfig('feature_switch_value');
        try {
            $featureSwitchValue = $this->featureSwitch->isSwitchEnabled($featureSwitch);
            if (
                $featureSwitch !== null
                && $featureSwitchRequirement !== null
                && $featureSwitchValue != $featureSwitchRequirement
            ) {
                return '';
            }
        } catch (LocalizedException $e) {
            $this->bugsnag->notifyException(
                $e,
                function (\Bugsnag\Report $report) use ($element) {
                    $report->addMetaData(
                        [
                            'Feature Switch Aware Field' => [
                                'element data' => $element->getData(),
                            ]
                        ]
                    );
                }
            );
        }
        return parent::render($element);
    }

}
