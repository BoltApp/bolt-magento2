<?php


namespace Bolt\Boltpay\Helper\GraphQL;


class Constants {

    // The const via which Bolt identifies the type of plugin.
    const PLUGIN_TYPE='MAGENTO_2';

    /**
     * The graphql query to retrieve feature switches. This will be maintained backward compatible.
     */
    const GET_FEATURE_SWITCHES_QUERY = <<<'GQL'
query GetFeatureSwitches($type: PluginType!, $version: String!) {
  plugin(type: $type, version: $version) {
    features {
      name
      value
      defaultValue
      rolloutPercentage
    }
  }
}
GQL;

    /**
     * The mutation to send logs from plugin to Bolt.
     */
    const SEND_LOGS_QUERY = <<<'GQL'
mutation LogMerchantLogs($logs: [LogLine!]!) {
  logMerchantLogs(logs: $logs){
    isSuccessful
  }
}
GQL;


    // Operation name for graphql query to retrieve feature switches.
    const GET_FEATURE_SWITCHES_OPERATION = 'GetFeatureSwitches';

    const SEND_LOGS_OPERATION = 'LogMerchantLogs';
}