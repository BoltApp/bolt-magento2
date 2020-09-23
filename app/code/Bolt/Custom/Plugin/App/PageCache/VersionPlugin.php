<?php
namespace Bolt\Custom\Plugin\App\PageCache;

/**
 * Plugin for {@see \Magento\Framework\App\PageCache\Version}
 */
class VersionPlugin
{
    /**
     * @var \Magento\Framework\App\Http\Context Context data for requests
     */
    private $httpContext;

    /**
     * @var \Bolt\Boltpay\Helper\Config Bolt configuration helper
     */
    private $configHelper;

    /**
     * Plugin constructor
     * @param \Magento\Framework\App\Http\Context $httpContext Context data for requests
     * @param \Bolt\Boltpay\Helper\Config         $configHelper Bolt configuration helper
     */
    public function __construct(\Magento\Framework\App\Http\Context $httpContext, \Bolt\Boltpay\Helper\Config $configHelper)
    {
        $this->httpContext = $httpContext;
        $this->configHelper = $configHelper;
    }

    /**
     * Plugin for {@see \Magento\Framework\App\PageCache\Version::process}
     * Makes Magento take into account whether the current visitor is Bolt IP restricted when serving
     * and creating full page cache by adding a value to {@see \Magento\Framework\App\Http\Context::$data},
     * later used by {@see \Magento\Framework\App\PageCache\Identifier::getValue} to create unique page identifier
     *
     * @param \Magento\Framework\App\PageCache\Version $subject plugged version model
     * @param null                                     $result result of the original method call
     *
     * @return null unchanged result
     */
    public function afterProcess(\Magento\Framework\App\PageCache\Version $subject, $result)
    {
        $this->httpContext->setValue('bolt_is_ip_restricted', $this->configHelper->isIPRestricted(), false);
        return $result;
    }
}