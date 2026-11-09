<?php
declare(strict_types=1);

namespace Pushwoosh\Web\Block\Widget;

use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;
use Pushwoosh\Web\Helper\Data as HelperData;

/**
 * The [data-pw-recommendations] placeholder the Web SDK widget fills.
 */
class Recommendations extends Template implements BlockInterface
{
    private const STRATEGIES = ['viewed', 'bestsellers_30d', 'also_bought'];

    // the cart page is never full-page cached, so it is the only place cart contents may go into markup
    private const CART_PAGE = 'checkout_cart_index';
    private const MAX_EXCLUDE = 50;

    protected $_template = 'Pushwoosh_Web::widget/recommendations.phtml';

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var HelperData
     */
    private $helperData;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    public function __construct(
        Context $context,
        Registry $registry,
        HelperData $helperData,
        CheckoutSession $checkoutSession,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->helperData = $helperData;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context, $data);
    }

    /**
     * The data-pw-* attributes; an empty array means nothing is rendered.
     *
     * @return array<string, string>
     */
    public function getPlaceholderAttributes(): array
    {
        if (!$this->helperData->isOnsiteRecommendationsEnabled()) {
            return [];
        }

        $strategy = in_array($this->getData('strategy'), self::STRATEGIES, true) ? $this->getData('strategy') : 'viewed';
        $fallback = (string) $this->getData('fallback');
        $limit = max(1, min(12, (int) ($this->getData('limit') ?: 6)));
        $block = preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $this->getData('block_id')) ? $this->getData('block_id') : '';

        $attributes = [
            'data-pw-recommendations' => '',
            'data-pw-strategy'        => $strategy,
            'data-pw-limit'           => (string) $limit,
        ];
        if (in_array($fallback, array_merge(self::STRATEGIES, ['none']), true)) {
            $attributes['data-pw-fallback'] = $fallback;
        }
        $optional = ['data-pw-title' => 'title', 'data-pw-fallback-title' => 'fallback_title'];
        foreach ($optional as $attribute => $key) {
            if ((string) $this->getData($key) !== '') {
                $attributes[$attribute] = (string) $this->getData($key);
            }
        }
        if ($block !== '') {
            $attributes['data-pw-block'] = $block;
        }

        // also_bought anchors on what the visitor is looking at: the first cart item on the cart page,
        // the product shown on a product page; the block never offers what is already there
        $anchors = $this->isCartPage() ? $this->cartProductIds() : [];
        $product = $this->registry->registry('current_product');
        if (!$anchors && $product instanceof Product) {
            $anchors = [(string) $product->getId()];
        }
        if ($anchors) {
            $attributes['data-pw-product-id'] = $anchors[0];
            $attributes['data-pw-exclude'] = implode(',', array_slice($anchors, 0, self::MAX_EXCLUDE));
        }

        return $attributes;
    }

    private function isCartPage(): bool
    {
        return $this->getRequest()->getFullActionName() === self::CART_PAGE;
    }

    /**
     * Catalog ids of the cart, in cart order; a configurable or bundle line gives its parent product.
     *
     * @return string[]
     */
    private function cartProductIds(): array
    {
        $ids = [];
        foreach ($this->checkoutSession->getQuote()->getAllVisibleItems() as $item) {
            $ids[(string) $item->getProductId()] = true;
        }

        // numeric string keys turn into ints in PHP
        return array_map('strval', array_keys($ids));
    }

    /**
     * Varies the cache with the markup: the anchor and the excluded ids depend on the product or cart.
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        return array_merge(parent::getCacheKeyInfo(), [
            'pushwoosh_recommendations',
            json_encode($this->getPlaceholderAttributes()),
        ]);
    }
}
