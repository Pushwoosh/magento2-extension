<?php
declare(strict_types=1);

namespace Pushwoosh\Web\Block;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Pushwoosh\Web\Helper\Data as HelperData;

/**
 * PW_ProductView for the product page: the input of the "viewed" recommendations and of the product pairs.
 */
class ProductView extends Template
{
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var ImageHelper
     */
    private $imageHelper;

    /**
     * @var HelperData
     */
    private $helperData;

    public function __construct(
        Context $context,
        Registry $registry,
        ImageHelper $imageHelper,
        HelperData $helperData,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->imageHelper = $imageHelper;
        $this->helperData = $helperData;
        parent::__construct($context, $data);
    }

    /**
     * The product as WebSDK trackProductView takes it, or null off a product page.
     *
     * @return array|null
     */
    public function getProductView(): ?array
    {
        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product || !$this->helperData->isEnabled()) {
            return null;
        }

        $category = $product->getCategoryCollection()->addAttributeToSelect('name')->getFirstItem();

        return [
            'id'       => (string) $product->getId(),
            'name'     => (string) $product->getName(),
            'price'    => (float) $product->getFinalPrice(),
            'currency' => (string) $this->_storeManager->getStore()->getCurrentCurrencyCode(),
            'category' => (string) $category->getName(),
            'url'      => (string) $product->getProductUrl(),
            'imageUrl' => (string) $this->imageHelper->init($product, 'product_page_image_large')->getUrl(),
        ];
    }
}
