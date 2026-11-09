<?php
declare(strict_types=1);

namespace Pushwoosh\Web\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Pushwoosh\Core\Helper\Curl;

/**
 * The "On-site recommendations" switch: saving it opens or closes the Pushwoosh onsite feed
 * for the application, limited to the origins of this Magento's storefronts.
 */
class OnsiteRecommendations extends Value
{
    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        Curl $curl,
        StoreManagerInterface $storeManager,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->curl = $curl;
        $this->storeManager = $storeManager;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return $this
     * @throws LocalizedException
     */
    public function afterSave()
    {
        if ($this->isValueChanged()) {
            try {
                $this->curl->setOnsiteRecommendations((bool) $this->getValue(), $this->storefrontOrigins());
            } catch (\Exception $e) {
                throw new LocalizedException(__(
                    'Pushwoosh did not switch on-site recommendations: %1. Check the API Key and Application Code.',
                    $e->getMessage()
                ));
            }
        }

        return parent::afterSave();
    }

    /**
     * @return string[]
     */
    private function storefrontOrigins(): array
    {
        $origins = [];
        foreach ($this->storeManager->getStores() as $store) {
            foreach ([false, true] as $secure) {
                $parts = parse_url((string) $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB, $secure));
                if (!empty($parts['scheme']) && !empty($parts['host'])) {
                    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
                    $origins[$parts['scheme'] . '://' . $parts['host'] . $port] = true;
                }
            }
        }

        return array_keys($origins);
    }
}
