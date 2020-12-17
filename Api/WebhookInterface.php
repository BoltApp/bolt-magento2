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

 namespace Bolt\Boltpay\Api;

 /**
  * Universal webhook interface. Passes the request data to the right helper functions to handle the request
  */
 interface WebhookInterface
 {
     /**
      * Hook format:
      * {"type":"create_order", "data":{requestData}}
      * @api
      * 
      * @param string $type
      * @param mixed $data
      *
      * @return bool
      */
     public function execute(
         $type = null,
         $data = null
     );
 }