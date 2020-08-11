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

namespace Bolt\Boltpay\Model;

use Magento\Framework\Composer\ComposerInformation as ComposerInformation;
use Magento\Framework\Notification\MessageInterface as NotificationMessage;

/**
 * Class for checking for available updates
 *
 * @method string|null getType() returns type of available update if available, otherwise null
 * @method string|null getVersion() returns newer version compared to current if available, otherwise null
 * @method int|null getSeverity() returns integer representation of the update severity
 * @method bool getIsUpdateAvailable() returns if update is available
 * @method string getUpdateTitle() returns update title for display that depends on update severity
 */
class Updater extends \Magento\Framework\DataObject
{
    /** @var string Identifier for caching update information */
    const CACHE_IDENTIFIER = 'BOLT_UPDATE';

    /** @var string Identifier for Magento Web Setup installation type */
    const INSTALLATION_TYPE_MAGENTO_WEB_SETUP = 'magento_web_setup';

    /** @var string Identifier for default composer installation type */
    const INSTALLATION_TYPE_DEFAULT_COMPOSER = 'default_composer';

    /** @var string Identifier for manual installation type (in app/code) */
    const INSTALLATION_TYPE_MANUAL = 'manual';

    /**
     * @var \Magento\Framework\Composer\ComposerFactory composer factory
     */
    private $composerFactory;

    /**
     * @var \Bolt\Boltpay\Helper\Config Bolt configuration helper
     */
    private $boltConfig;

    /**
     * @var \Composer\Composer composer instance
     */
    private $_composer;

    /**
     * @var \Magento\Framework\App\CacheInterface system cache model
     */
    private $cache;

    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag Bolt Bugsnag helper
     */
    private $bugsnag;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface serializing model
     */
    private $serializer;

    /**
     * @var \Bolt\Boltpay\Helper\FeatureSwitch\Decider Bolt feature switch decider
     */
    private $deciderHelper;

    /**
     * @var bool flag to prevent unnecessary update checks
     */
    private $_updateRetrieved = false;

    /**
     * Updater constructor.
     *
     * @param \Magento\Framework\Composer\ComposerFactory      $composerFactory
     * @param \Magento\Framework\App\CacheInterface            $cache
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Bolt\Boltpay\Helper\Config                      $boltConfig
     * @param \Bolt\Boltpay\Helper\Bugsnag                     $bugsnag
     * @param \Bolt\Boltpay\Helper\FeatureSwitch\Decider       $deciderHelper
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\Composer\ComposerFactory $composerFactory,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Bolt\Boltpay\Helper\Config $boltConfig,
        \Bolt\Boltpay\Helper\Bugsnag $bugsnag,
        \Bolt\Boltpay\Helper\FeatureSwitch\Decider $deciderHelper,
        array $data = []
    ) {
        $this->composerFactory = $composerFactory;
        $this->boltConfig = $boltConfig;
        $this->cache = $cache;
        $this->bugsnag = $bugsnag;
        $this->serializer = $serializer;
        $this->deciderHelper = $deciderHelper;
        parent::__construct($data);
    }

    /**
     * Gets Composer dependency manager object
     *
     * @return \Composer\Composer Composer dependency manager object
     *
     * @throws \Exception if unable to instantiate composer
     */
    public function getComposer()
    {
        if (!$this->_composer) {
            $this->_composer = $this->composerFactory->create();
        }
        return $this->_composer;
    }

    /**
     * Updater data getter
     * Overridden to try and retrieve available update on any data retrieving request, but only once per execution
     *
     * @param string          $key for which to retrieve the data
     * @param string|int|null $index additional identifier used to retrieve sub-sections of data stored under $key
     *
     * @return mixed data under $key if available, otherwise null
     */
    public function getData($key = '', $index = null)
    {
        if (!$this->_updateRetrieved) {
            $this->setData($this->getUpdate());
            $this->_updateRetrieved = true;
        }
        return parent::getData($key, $index);
    }

    /**
     * Gets update data, either from cache if present or by using {@see \Bolt\Boltpay\Model\Updater::checkForUpdate}
     *
     * @return array containing update data
     */
    public function getUpdate()
    {
        $updateData = [];
        if ($cacheData = $this->cache->load(self::CACHE_IDENTIFIER)) {
            $updateData = $this->serializer->unserialize($cacheData);
        } else {
            try {
                $updateData = $this->checkForUpdate();
                $this->cache->save(
                    $this->serializer->serialize($updateData),
                    self::CACHE_IDENTIFIER,
                    [],
                    86400
                );
            } catch (\Exception $e) {
                $this->bugsnag->notifyException($e);
            }
        }
        return $updateData;
    }

    /**
     * Checks availabilty of an update for the Bolt module
     *
     * @return array containing update data
     *
     * @throws \Exception if unable to retrieve update
     */
    protected function checkForUpdate()
    {
        if (!$this->deciderHelper->isNewReleaseNotificationsEnabled()) {
            return ['is_update_available' => false];
        }
        $package = $this->boltConfig->getPackageLock(\Bolt\Boltpay\Helper\Config::BOLT_COMPOSER_NAME);
        if ($package) {
            $installationType = $this->isUrlMagentoRepo($package['dist']['url'])
                ? self::INSTALLATION_TYPE_MAGENTO_WEB_SETUP
                : self::INSTALLATION_TYPE_DEFAULT_COMPOSER;
        } else {
            $installationType = self::INSTALLATION_TYPE_MANUAL;
        }
        $versionSelector = $this->getVersionSelector();
        $currentVersion = $this->boltConfig->getModuleVersion();
        $hasPatch = $versionSelector->findBestCandidate(
            \Bolt\Boltpay\Helper\Config::BOLT_COMPOSER_NAME,
            sprintf('>%1$s ~%1$s', $currentVersion)
        );
        $updatePackage = $versionSelector->findBestCandidate(
            \Bolt\Boltpay\Helper\Config::BOLT_COMPOSER_NAME,
            sprintf('>%1$s', $currentVersion)
        );
        if (!$updatePackage) {
            return ['is_update_available' => false];
        }
        $updateVersion = $updatePackage->getPrettyVersion();
        $versionDifference = array_diff_assoc(
            explode('.', $updateVersion),
            explode('.', $currentVersion)
        );
        switch (array_keys($versionDifference)[0]) {
            default:
            case 0:
                $updateSeverity = NotificationMessage::SEVERITY_MAJOR;
                $updateTitle = __('Bolt version %1 is now available!', $updateVersion);
                break;
            case 1:
                $updateSeverity = NotificationMessage::SEVERITY_NOTICE;
                $updateTitle = __('Bolt version %1 is now available!', $updateVersion);
                break;
            case 2:
                $updateSeverity = NotificationMessage::SEVERITY_CRITICAL;
                $updateTitle = __('Bolt version %1 is available to address a CRITICAL issue.', $updateVersion);
                break;
        }

        if ($hasPatch) {
            $updateSeverity = NotificationMessage::SEVERITY_CRITICAL;
            $updateTitle = __('Bolt version %1 is available to address a CRITICAL issue.', $updateVersion);
        }

        if ($updateSeverity == NotificationMessage::SEVERITY_NOTICE
            && $this->boltConfig->getShouldDisableNotificationsForNonCriticalUpdates()) {
            return ['is_update_available' => false];
        }
        return [
            'is_update_available' => true,
            'update_title'        => $updateTitle,
            'severity'            => $updateSeverity,
            'version'             => $updateVersion,
            'type'                => $installationType
        ];
    }

    /**
     * Gets composer version selector instance used to determine available updates
     *
     * @return \Composer\Package\Version\VersionSelector
     *
     * @throws \Exception if unable to create repository
     */
    protected function getVersionSelector()
    {
        $pool = new \Composer\DependencyResolver\Pool();
        $repositoriesConfig = $this->getComposer()->getConfig()->getRepositories();
        if ($this->deciderHelper->isUseGithubForUpdateEnabled()) {
            $repositoryConfig = $repositoriesConfig[ComposerInformation::COMPOSER_DEFAULT_REPO_KEY];
        } else {
            $repositoryConfig = array_reduce(
                $repositoriesConfig,
                function ($res, $repositoryConfig) {
                    return $this->isUrlMagentoRepo($repositoryConfig['url'])
                        ? $repositoryConfig
                        : $res;
                }
            );
        }
        if (empty($repositoryConfig)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Unable to select composer repository'));
        }

        $pool->addRepository(
            $this->getComposer()->getRepositoryManager()
                ->createRepository($repositoryConfig['type'], $repositoryConfig)
        );
        return new \Composer\Package\Version\VersionSelector($pool);
    }

    /**
     * Determines if provided URL is related to Magento composer repository
     *
     * @param string $url to be checked
     *
     * @return bool true if url from Magento repository, otherwise false
     */
    protected function isUrlMagentoRepo($url)
    {
        return strpos($url, 'repo.magento.com') !== false;
    }
}
