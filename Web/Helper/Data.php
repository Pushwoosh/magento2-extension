<?php
namespace Pushwoosh\Web\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use \Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Pushwoosh\Core\Helper\Data as CoreHelper;
use Magento\Store\Model\StoreManagerInterface;

class Data extends AbstractHelper
{
    /**
     * @var \Pushwoosh\Core\Helper\Data
     */
    protected $coreHelper;

    const PUSHWOOSH_WEB_ENABLED = "pushwoosh/web/enabled";
    const PUSHWOOSH_WEB_SDK_TOKEN = "pushwoosh/web/web_sdk_token";
    const PUSHWOOSH_WEB_ONSITE_RECOMMENDATIONS = "pushwoosh/web/onsite_recommendations";
    const PUSHWOOSH_WEB_SAFARI_WEBSITE_PUSH_ID = "pushwoosh/web/safari_website_push_id";
    const PUSHWOOSH_WEB_DEFAULT_NOTIFICATION_TITLE = "pushwoosh/web/default_notification_title";
    const PUSHWOOSH_WEB_DEFAULT_NOTIFICATION_IMAGE = "pushwoosh/web/default_notification_image";
    const STORE_INFORMATION_NAME = "general/store_information/name";

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configInterface;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * Data constructor.
     * @param Context $context
     */


    public function __construct(
        Context $context,
        ConfigInterface $configInterface,
        CoreHelper $coreHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->configInterface = $configInterface;
        $this->coreHelper = $coreHelper;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    /**
     * @param null $scopeCode
     * @return bool
     */
    public function isEnabled($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::PUSHWOOSH_WEB_ENABLED,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * @param null $scopeCode
     * @return string|null
     */
    public function getApplicationCode($scopeCode = null)
    {
        return $this->coreHelper->getAppCode($scopeCode);
    }

    /**
     * The Device API token the storefront WebSDK sends. Never the server API Key:
     * this value is printed in every page.
     *
     * @param null $scopeCode
     * @return string
     */
    public function getApiToken($scopeCode = null)
    {
        return $this->getTrimmed(self::PUSHWOOSH_WEB_SDK_TOKEN, $scopeCode);
    }

    /**
     * @param null $scopeCode
     * @return bool
     */
    public function isOnsiteRecommendationsEnabled($scopeCode = null)
    {
        return $this->isEnabled($scopeCode) && $this->scopeConfig->isSetFlag(
            self::PUSHWOOSH_WEB_ONSITE_RECOMMENDATIONS,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * @param null $scopeCode
     * @return string empty when Safari web push is not configured
     */
    public function getSafariWebsitePushId($scopeCode = null): string
    {
        return $this->getTrimmed(self::PUSHWOOSH_WEB_SAFARI_WEBSITE_PUSH_ID, $scopeCode);
    }

    /**
     * @param null $scopeCode
     * @return string
     */
    public function getDefaultNotificationTitle($scopeCode = null): string
    {
        $title = $this->getTrimmed(self::PUSHWOOSH_WEB_DEFAULT_NOTIFICATION_TITLE, $scopeCode);
        if ($title !== '') {
            return $title;
        }

        $storeName = $this->getTrimmed(self::STORE_INFORMATION_NAME, $scopeCode);
        if ($storeName !== '') {
            return $storeName;
        }

        try {
            return (string)$this->storeManager->getStore($scopeCode)->getFrontendName();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return '';
        }
    }

    /**
     * @param null $scopeCode
     * @return string
     */
    public function getDefaultNotificationImage($scopeCode = null): string
    {
        $image = $this->getTrimmed(self::PUSHWOOSH_WEB_DEFAULT_NOTIFICATION_IMAGE, $scopeCode);
        return $image !== '' ? $image : $this->coreHelper->getStoreLogo($scopeCode);
    }

    private function getTrimmed(string $path, $scopeCode): string
    {
        return trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORES, $scopeCode));
    }
}
