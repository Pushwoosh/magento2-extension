<?php
namespace Pushwoosh\Customer\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Pushwoosh\Customer\Model\Config\CronConfig;
use \Magento\Framework\App\Config\ConfigResource\ConfigInterface;
class Data extends AbstractHelper
{
    const PUSHWOOSH_CUSTOMER_SYNC = "pushwoosh/customer/sync";
    const PUSHWOOSH_CUSTOMER_NUMBER_OF_CUSTOMERS = "pushwoosh/customer/number_of_customers";
    const PUSHWOOSH_CUSTOMER_UPDATE_LAST_SYNC = "pushwoosh/customer/last_customers_updated";
    const PUSHWOOSH_CUSTOMER_MAP_CUSTOM_FIELDS = "pushwoosh/customer/map_custom_fields";
    const PUSHWOOSH_CUSTOMER_SMS_CONSENT_ATTRIBUTE = "pushwoosh/customer/sms_consent_attribute";
    const PUSHWOOSH_CUSTOMER_WHATSAPP_CONSENT_ATTRIBUTE = "pushwoosh/customer/whatsapp_consent_attribute";
    const PUSHWOOSH_CUSTOMER_REQUIRE_SMS_CONSENT = "pushwoosh/customer/require_sms_consent";

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configInterface;
    /**
     * Data constructor.
     * @param Context $context
     */


    public function __construct(
        Context $context,
        ConfigInterface $configInterface
    ) {
        $this->configInterface = $configInterface;
        parent::__construct($context);
    }

    /**
     * @param null $scopeCode
     * @return bool
     */
    public function isCustomerSyncingEnabled($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::PUSHWOOSH_CUSTOMER_SYNC,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * @param null $scopeCode
     * @return mixed
     */
    public function getCronTime($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            CronConfig::CRON_MODEL_PATH,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * @param null $scopeCode
     * @return mixed
     */
    public function getNumberOfCustomers($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::PUSHWOOSH_CUSTOMER_NUMBER_OF_CUSTOMERS,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    public function getLastCustomerUpdateSync($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::PUSHWOOSH_CUSTOMER_UPDATE_LAST_SYNC,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    public function getMapCustomFields($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::PUSHWOOSH_CUSTOMER_MAP_CUSTOM_FIELDS,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    public function getSmsConsentAttribute($scopeCode = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::PUSHWOOSH_CUSTOMER_SMS_CONSENT_ATTRIBUTE,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    public function getWhatsappConsentAttribute($scopeCode = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::PUSHWOOSH_CUSTOMER_WHATSAPP_CONSENT_ATTRIBUTE,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    public function isSmsConsentRequired($scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::PUSHWOOSH_CUSTOMER_REQUIRE_SMS_CONSENT,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    public function setLastCustomerUpdateSync($date, $scopeCode = null)
    {
        $scope = \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $this->configInterface->saveConfig(self::PUSHWOOSH_CUSTOMER_UPDATE_LAST_SYNC, $date, $scope);
    }
}
