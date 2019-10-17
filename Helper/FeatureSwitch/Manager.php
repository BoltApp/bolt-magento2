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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper\FeatureSwitch;


use Magento\Framework\App\Helper\Context;
use Bolt\Boltpay\Helper\GraphQL\Client as GQL;
use Bolt\Boltpay\Model\FeatureSwitchRepository;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Manager extends AbstractHelper {

    /**
     * @var GQL
     */
    private $gql;

    /**
     * @var fsRepo
     */
    private $fsRepo;

    /**
     * @param Context $context
     * @param GQL $gql
     * @param FeatureSwitchRepository $fsRepo
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        GQL $gql,
        FeatureSwitchRepository $fsRepo
    ) {
        parent::__construct($context);
        $this->gql = $gql;
        $this->fsRepo = $fsRepo;
    }

    /**
     * This method gets feature switches from Bolt and updates the local DB with
     * the latest values. To be used in upgrade data and webhooks.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateSwitchesFromBolt() {
        $switchesResponse = $this->gql->getFeatureSwitches();

        $switches = array();
        if ($switchesResponse->getData()) {
            $data = $switchesResponse->getData();
            $switches = @$data["response"]->data->plugin->features;
        }

        if (!is_array($switches) || count($switches) == 0) {
            return;
        }

        foreach ($switches as $fs) {
            if (isset($fs->name)) {
                $this->fsRepo->upsertByName(
                    $fs->name,
                    $fs->value,
                    $fs->defaultValue,
                    $fs->rolloutPercentage
                );
            }
        }
    }

    /**
     * The returns if the switch is enabled.
     * TODO(roopakv): Take sessions & rollout percentage into account.
     *
     * @param string $switchName name of the switch
     *
     * @throws LocalizedException
     * @return bool
     */
    public function isSwitchEnabled($switchName) {
        $defaultDef = isset(Definitions::SWITCHES[$switchName]) ? Definitions::SWITCHES[$switchName] : null;
        if (!$defaultDef) {
            throw new LocalizedException(__("Unknown feature switch"));
        }
        try {
            $switch = $this->fsRepo->getByName($switchName);
            return $switch->getValue();
        } catch (NoSuchEntityException $e) {
            // Switch is not in DB. Fall back to defaults.
            return $defaultDef[Definitions::VAL_KEY];
        }
    }

}