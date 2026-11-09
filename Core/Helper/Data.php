<?php
declare(strict_types=1);

namespace Pushwoosh\Core\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    public const PUSHWOOSH_GENERAL_STATUS = 'pushwoosh/general/status';
    public const PUSHWOOSH_GENERAL_API_URL = 'pushwoosh/general/api_url';
    public const PUSHWOOSH_GENERAL_API_KEY = 'pushwoosh/general/api_key';
    public const PUSHWOOSH_GENERAL_APP_CODE = 'pushwoosh/general/app_code';


    /**
     * @var \Magento\Store\Api\StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configInterface;

    /**
     * @var \Magento\Theme\Block\Html\Header\Logo
     */
    private $logo;

    /**
     * Construct
     *
     * @param \Magento\Store\Api\StoreRepositoryInterface $storeRepository
     * @param \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Theme\Block\Html\Header\Logo $logo
     */
    public function __construct(
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Theme\Block\Html\Header\Logo $logo
    ) {
        parent::__construct($context);
        $this->storeRepository = $storeRepository;
        $this->configInterface = $configInterface;
        $this->logo = $logo;
    }

    /**
     * Is enabled
     *
     * @param int|string|null $scopeCode
     *
     * @return bool
     */
    public function isEnabled( $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::PUSHWOOSH_GENERAL_STATUS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * Get API URL
     *
     * @param int|string|null $scopeCode
     *
     * @return string|null
     */
    public function getApiUrl( $scopeCode = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::PUSHWOOSH_GENERAL_API_URL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * Get API key
     *
     * @param int|string|null $scopeCode
     *
     * @return string|null
     */
    public function getApiKey( $scopeCode = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::PUSHWOOSH_GENERAL_API_KEY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * Get App Code
     *
     * @param int|string|null $scopeCode
     *
     * @return string|null
     */
    public function getAppCode($scopeCode = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::PUSHWOOSH_GENERAL_APP_CODE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * Two-letter contact language from the store locale, e.g. ar_SA -> ar
     *
     * @param int|string|null $scopeCode
     *
     * @return string
     */
    public function getLanguage($scopeCode = null): string
    {
        $locale = (string)$this->scopeConfig->getValue(
            \Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
        $language = strtolower(substr($locale, 0, 2));

        return preg_match('/^[a-z]{2}$/', $language) ? $language : 'en';
    }

    /**
     * Get store logo
     *
     * @param int|string|null $scopeCode
     *
     * @return string
     */
    public function getStoreLogo( $scopeCode = null): string
    {
        $folderName = \Magento\Config\Model\Config\Backend\Image\Logo::UPLOAD_DIR;
        $storeLogoPath = $this->scopeConfig->getValue(
            'design/header/logo_src',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
        $path = $folderName . '/' . $storeLogoPath;
        $logoUrl = $this->_urlBuilder->getBaseUrl(['_type' => \Magento\Framework\UrlInterface::URL_TYPE_MEDIA]) . $path;

        if ($storeLogoPath !== null) {
            $url = $logoUrl;
        } else {
            $url = $this->logo->getLogoSrc();
        }

        return $url;
    }
}
