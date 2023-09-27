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

namespace Bolt\Boltpay\Plugin\Magento\Framework\Session;

use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\App\ObjectManager;
use Bolt\Boltpay\Model\RestApiRequestValidator as BoltRestApiRequestValidator;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Plugin adds magento session data during Web API calls from bolt request payload
 */
class SessionManagerPlugin
{
    const BOLT_SESSION_PARAMS_KEY = 'bolt_session_params';

    const BOLT_SESSION_PARAM_BUGSNAG_ERROR_NAME = self::BOLT_SESSION_PARAMS_KEY . '_error';

    const BOLT_SESSION_PARAM_NAME_KEY = 'name';

    const BOLT_SESSION_PARAM_SESSION_CLASS_NAME_KEY = 'session_class_name';

    const BOLT_SESSION_PARAM_TYPE_CLASS_NAME_KEY = 'type_class_name';

    const BOLT_SESSION_PARAM_VALUE_KEY = 'value';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var BoltRestApiRequestValidator
     */
    private $boltRestApiRequestValidator;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var Decider
     */
    private $decider;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * Available types of bolt_session_param properties
     *
     * @var string[]
     */
    private $requiredPropertiesTypes = [
        self::BOLT_SESSION_PARAM_NAME_KEY => 'string',
        self::BOLT_SESSION_PARAM_VALUE_KEY => 'array|string|integer|double|boolean'
    ];

    /**
     * @param Request $request
     * @param BoltRestApiRequestValidator $boltRestApiRequestValidator
     * @param Decider $decider
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        Request $request,
        BoltRestApiRequestValidator $boltRestApiRequestValidator,
        Decider $decider,
        Bugsnag $bugsnag
    ) {
        $this->request = $request;
        $this->boltRestApiRequestValidator = $boltRestApiRequestValidator;
        $this->decider = $decider;
        $this->bugsnag = $bugsnag;
        $this->objectManager = ObjectManager::getInstance();
    }

    /**
     * Validate and set new session param data from the WebAPI Rest request
     *
     * @param SessionManagerInterface $subject
     * @param SessionManagerInterface $resultSubject
     * @return SessionManagerInterface
     */
    public function afterStart(
        SessionManagerInterface $subject,
        SessionManagerInterface $resultSubject
    ): SessionManagerInterface {
        try {
            if (!$this->decider->isBoltSessionParamsEnabled()) {
                return $resultSubject;
            }

            // skip if request is not from bolt
            if (!$this->boltRestApiRequestValidator->isValidBoltRequest($this->request)) {
                return $resultSubject;
            }

            $boltSessionParams = $this->getBoltSessionParamsFromRequest();

            if (!$this->isBoltSessionParamsValid($boltSessionParams)) {
                return $resultSubject;
            }

            foreach ($boltSessionParams as $boltSessionParam) {
                $this->processParam($resultSubject, $boltSessionParam);
            }

        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }

        return $resultSubject;
    }

    /**
     * Returns bolt session params data from request
     *
     * @return array|null
     */
    private function getBoltSessionParamsFromRequest(): ?array
    {
        $bodyParams = $this->request->getBodyParams();
        return $bodyParams[self::BOLT_SESSION_PARAMS_KEY] ?? null;
    }

    /**
     * Validate bolt session params input data
     *
     * @param mixed $boltSessionParams
     * @return bool
     */
    private function isBoltSessionParamsValid($boltSessionParams): bool
    {
        if ($boltSessionParams === null) {
            return false;
        }

        try {
            $this->validateBoltSessionParamsType($boltSessionParams);
            foreach ($boltSessionParams as $boltSessionParam) {
                $this->validateBoltSessionParam($boltSessionParam);
            }

        } catch (\InvalidArgumentException $e) {
            $this->bugsnag->notifyError(
                self::BOLT_SESSION_PARAM_BUGSNAG_ERROR_NAME,
                sprintf(
                    'The %s request param validation issue: %s',
                    self::BOLT_SESSION_PARAMS_KEY,
                    $e->getMessage()
                )
            );
            return false;
        }

        return true;
    }

    /**
     * Validate type of input bolt session params
     *
     * @param mixed $boltSessionParams
     * @return void
     */
    private function validateBoltSessionParamsType($boltSessionParams): void
    {
        if (!is_array($boltSessionParams)) {
            $msg = sprintf(
                'The [%s] request param should be array, [%s] given',
                self::BOLT_SESSION_PARAMS_KEY,
                gettype($boltSessionParams)
            );
            throw new \InvalidArgumentException($msg);
        }
    }

    /**
     * Validate bolt session param data
     *
     * @param array $boltSessionParam
     * @return void
     */
    private function validateBoltSessionParam(array $boltSessionParam): void
    {
        $this->validateRequiredProperties($boltSessionParam);
        $this->validateTypeClassNameIfExist($boltSessionParam);
        $this->validateSessionClassNameIfExist($boltSessionParam);
    }

    /**
     * Validate required properties of bolt session param
     *
     * @param array $boltSessionParam
     * @return void
     */
    private function validateRequiredProperties(array $boltSessionParam): void
    {
        foreach ($this->requiredPropertiesTypes as $property => $type) {
            if (!isset($boltSessionParam[$property])) {
                $msg = sprintf(
                    'the [%s] param is required',
                    $property
                );
                throw new \InvalidArgumentException($msg);
            }

            if (!in_array(gettype($boltSessionParam[$property]), explode('|', $type))) {
                $msg =  sprintf(
                    'the [%s] param type is not valid : [%s] required [%s] given',
                    $property,
                    $type,
                    gettype($boltSessionParam[$property])
                );
                throw new \InvalidArgumentException($msg);
            }
        }
    }

    /**
     * Type class name validation if exist
     *
     * @param array $boltSessionParam
     * @return void
     */
    private function validateTypeClassNameIfExist(array $boltSessionParam): void
    {
        if (!isset($boltSessionParam[self::BOLT_SESSION_PARAM_TYPE_CLASS_NAME_KEY])) {
            return;
        }

        if (!class_exists($boltSessionParam[self::BOLT_SESSION_PARAM_TYPE_CLASS_NAME_KEY])) {
            $msg = sprintf(
                'The provided [%s] class is not exist',
                $boltSessionParam[self::BOLT_SESSION_PARAM_TYPE_CLASS_NAME_KEY]
            );
            throw new \InvalidArgumentException($msg);
        }

        if (!is_array($boltSessionParam[self::BOLT_SESSION_PARAM_VALUE_KEY])) {
            $msg = sprintf(
                'The %s param should be an array if type_class_name is used, the %s provided',
                self::BOLT_SESSION_PARAM_VALUE_KEY,
                gettype($boltSessionParam[self::BOLT_SESSION_PARAM_VALUE_KEY])
            );
            throw new \InvalidArgumentException($msg);
        }
    }

    /**
     * Session class name validation if exist
     * In that session type we should set our bolt session param
     *
     * @param array $boltSessionParam
     * @return void
     */
    private function validateSessionClassNameIfExist(array $boltSessionParam): void
    {
        if (!isset($boltSessionParam[self::BOLT_SESSION_PARAM_SESSION_CLASS_NAME_KEY])) {
            return;
        }

        if (!class_exists($boltSessionParam[self::BOLT_SESSION_PARAM_SESSION_CLASS_NAME_KEY])) {
            $msg = sprintf(
                'The provided [%s] class is not exist',
                $boltSessionParam[self::BOLT_SESSION_PARAM_SESSION_CLASS_NAME_KEY]
            );
            throw new \InvalidArgumentException($msg);
        }
    }

    /**
     * Set bolt session param to the magento session
     *
     * @param SessionManagerInterface $sessionManager
     * @param array $boltSessionParam
     * @return void
     */
    private function processParam(SessionManagerInterface $sessionManager, array $boltSessionParam): void
    {
        // if current session is not of required session type, skipping new param processing
        if (isset($boltSessionParam[self::BOLT_SESSION_PARAM_SESSION_CLASS_NAME_KEY]) &&
            !is_a($sessionManager, $boltSessionParam[self::BOLT_SESSION_PARAM_SESSION_CLASS_NAME_KEY])
        ) {
            return;
        }

        $value = $boltSessionParam[self::BOLT_SESSION_PARAM_VALUE_KEY];
        // if type_class_name used
        if (isset($boltSessionParam[self::BOLT_SESSION_PARAM_TYPE_CLASS_NAME_KEY])) {
            // set value as new instance of "type_class_name" with arguments from request
            $value = $this->objectManager->create(
                $boltSessionParam[self::BOLT_SESSION_PARAM_TYPE_CLASS_NAME_KEY],
                $boltSessionParam[self::BOLT_SESSION_PARAM_VALUE_KEY]
            );
        }
        $sessionManager->setData($boltSessionParam[self::BOLT_SESSION_PARAM_NAME_KEY], $value);
    }
}
