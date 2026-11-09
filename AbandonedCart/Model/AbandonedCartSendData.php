<?php

namespace Pushwoosh\AbandonedCart\Model;

use Pushwoosh\AbandonedCart\Helper\Data as AbandonedCartHelper;
use Pushwoosh\AbandonedCart\Model\Config\CronConfig;
use Pushwoosh\Core\Helper\Curl;
use Pushwoosh\Core\Helper\Data as CoreHelper;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Catalog\Api\ProductRepositoryInterfaceFactory;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer as CustomerModel;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerResourceCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteResourceCollectionFactory;
use Magento\Quote\Model\ResourceModel\Quote\Item\Collection;
use Magento\Quote\Model\ResourceModel\Quote\Item\CollectionFactory as QuoteItemCollectionFactory;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Store\Model\StoreManagerInterface;
use Pushwoosh\Core\Logger\Logger;
use Pushwoosh\Customer\Model\Customer;

class AbandonedCartSendData extends AbstractModel
{
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var AddressRepositoryInterface
     */
    protected $addressRepository;

    /**
     * @var CustomerResourceCollectionFactory
     */
    protected $customerResourceCollectionFactory;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var CustomerResource
     */
    protected $customerResource;

    /**
     * @var Attribute
     */
    protected $eavAttribute;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ImageFactory
     */
    protected $imageHelperFactory;

    /**
     * @var ProductRepositoryInterfaceFactory
     */
    protected $_productRepositoryFactory;

    /**
     * @var AppEmulation
     */
    protected $appEmulation;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var DateTime
     */
    private $dateTime;
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    private $customerId;


    /**
     * AbandonedCartSendData constructor.
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressRepositoryInterface $addressRepository
     * @param CustomerResourceCollectionFactory $customerResourceCollectionFactory
     * @param CustomerFactory $customerFactory
     * @param CustomerResource $customerResource
     * @param Attribute $eavAttribute
     * @param AbandonedCartHelper $abandonedCartHelper
     * @param QuoteResourceCollectionFactory $quoteResourceCollectionFactory
     * @param Curl $curl
     * @param Logger $logger
     * @param CartRepositoryInterface $cartRepositoryInterface
     * @param CoreHelper $coreHelper
     * @param QuoteItemCollectionFactory $quoteItemCollectionFactory
     * @param ProductRepositoryInterfaceFactory $productRepositoryFactory
     * @param ImageFactory $imageHelperFactory
     * @param QuoteFactory $quoteFactory
     * @param AppEmulation $appEmulation
     * @param StoreManagerInterface $storeManager
     * @param CustomerModel $customerModel
     * @param TimezoneInterface $dateTime
     * @param CartRepositoryInterface $quoteRepository
     * @param UrlInterface $urlBuilder
     * @param Customer $customer
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository,
        CustomerResourceCollectionFactory $customerResourceCollectionFactory,
        CustomerFactory $customerFactory,
        CustomerResource $customerResource,
        Attribute $eavAttribute,
        AbandonedCartHelper $abandonedCartHelper,
        QuoteResourceCollectionFactory $quoteResourceCollectionFactory,
        Curl $curl,
        Logger $logger,
        CartRepositoryInterface $cartRepositoryInterface,
        CoreHelper $coreHelper,
        QuoteItemCollectionFactory $quoteItemCollectionFactory,
        ProductRepositoryInterfaceFactory $productRepositoryFactory,
        ImageFactory $imageHelperFactory,
        QuoteFactory $quoteFactory,
        AppEmulation $appEmulation,
        StoreManagerInterface $storeManager,
        CustomerModel $customerModel,
        TimezoneInterface $dateTime,
        CartRepositoryInterface $quoteRepository,
        UrlInterface $urlBuilder,
        Customer $customer
    )
    {
        $this->urlBuilder = $urlBuilder;
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
        $this->customerResourceCollectionFactory = $customerResourceCollectionFactory;
        $this->customerFactory = $customerFactory;
        $this->customerResource = $customerResource;
        $this->eavAttribute = $eavAttribute;
        $this->abandonedCartHelper = $abandonedCartHelper;
        $this->quoteResourceCollectionFactory = $quoteResourceCollectionFactory;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->coreHelper = $coreHelper;
        $this->quoteItemCollectionFactory = $quoteItemCollectionFactory;
        $this->_productRepositoryFactory = $productRepositoryFactory;
        $this->imageHelperFactory = $imageHelperFactory;
        $this->quoteFactory = $quoteFactory;
        $this->appEmulation = $appEmulation;
        $this->storeManager = $storeManager;
        $this->customerModel = $customerModel;
        $this->dateTime = $dateTime;
        $this->quoteRepository = $quoteRepository;
        $this->customer = $customer;
    }

    /**
     * @param $quoteId
     * @return int the number of carts sent; a cart the filters skip (too fresh, empty, no email) is not counted
     * @throws NoSuchEntityException|LocalizedException
     * @throws GuzzleException|GuzzleException
     * @throws \Exception
     */
    public function sendAbandonedCartData($quoteId = null): int
    {
        $numberOfAbandonedCart = (int)$this->abandonedCartHelper->getNumberOfAbandonedCart();
        $minInactiveTime = (int) $this->abandonedCartHelper->getMinInactiveTime();

        $this->logger->info('iterating abandoned carts');
        $sent = 0;

        $abandonedCarts = $this->quoteResourceCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('main_table.updated_at',
                ['eq' => new \Zend_Db_Expr('IF( pw_synced_date is not null or main_table.updated_at < DATE_SUB(NOW(), INTERVAL '.$minInactiveTime.' minute), main_table.updated_at,-1)')]
            )
            ->addFieldToFilter(
                'is_active',
                '1'
            )
            ->addFieldToFilter(
                'items_count',
                ['gt' => 0]
            );

        if ($quoteId) {
            $abandonedCarts->addFieldToFilter('entity_id', ['eq' => $quoteId]);
        } else {
            $abandonedCarts->addFieldToFilter('pw_synced_date', [
                ['lt' => new \Zend_Db_Expr('main_table.updated_at')],
                ['null' =>  true]
            ]);
        }

        $abandonedCarts->setPageSize($numberOfAbandonedCart)->setOrder('main_table.updated_at',"desc");
        $abandonedCarts->getSelect()->join(array('address' => $abandonedCarts->getResource()->getTable('quote_address')), 'main_table.entity_id = address.quote_id')
            ->where("address.address_type='billing' and (main_table.customer_email is not null or  address.email is not null)");

        foreach ($abandonedCarts as $abandonedCart) {
            // one broken cart must not block the rest of the batch; it is retried on the next run
            try {
                $this->sendCart($abandonedCart);
                $sent++;
            } catch (\Throwable $e) {
                $this->logger->error('MODULE AbandonedCart: cart sync failed', [
                    'cart_id' => $abandonedCart->getEntityId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * @throws \Exception
     */
    private function sendCart($abandonedCart): void
    {
        $this->logger->info('cart',  array('cart' => $abandonedCart));

        $quote = $this->quoteRepository->get($abandonedCart->getEntityId());
        if ($this->isGuest($quote) || ($abandonedCart->getCustomerId() && (!$this->getCustomer($abandonedCart->getCustomerId())->getId() || !$this->getCustomer($abandonedCart->getCustomerId())->getEmail() ))) {
            $customerEmail = $quote->getBillingAddress()->getEmail();
            if (!$customerEmail) {
                throw new \Exception("Customer Email does not exist.");
            }

            $pwCustomer = $this->customer->createGuestCustomer($quote->getBillingAddress(), $quote->getStoreId());

        } else {
            $pwCustomer = $this->customer->updateCustomer($this->getCustomer($abandonedCart->getCustomerId()), $abandonedCart->getStoreId());
        }

        $this->customerId = $pwCustomer;

        $abandonedCart->collectTotals();
        $quoteItemsData = $this->getQuoteItemsData($abandonedCart->getEntityId(), $abandonedCart->getStoreId());
        $abandonedCartRepository = $this->quoteRepository->get($abandonedCart->getId());
        $abandonedUpdateDate = $abandonedCartRepository->getUpdatedAt();
        if(is_null($abandonedUpdateDate)){
            $abandonedUpdateDate = $abandonedCartRepository->getCreatedAt();
        }

        $timezone = $this->dateTime->getConfigTimezone(\Magento\Store\Model\ScopeInterface::SCOPE_STORES, $abandonedCart->getStoreId());

        $abandonedCartData = [
            "email" => $quote->getBillingAddress()->getEmail(),
            "items" => $quoteItemsData,
            "orderUrl" => $this->urlBuilder->setScope($abandonedCart->getStoreId())->getDirectUrl('checkout/cart'),
            "abandonedDate" => $this->dateTime->date(strtotime($abandonedUpdateDate),NULL,$timezone)->format('Y-m-d\TH:i:sP'),
            "createdDate" => $this->dateTime->date(strtotime($abandonedCartRepository->getCreatedAt()),NULL,$timezone)->format('Y-m-d\TH:i:sP'),
            "shippingMethod" => $abandonedCart->getShippingAddress()->getShippingMethod(),
            "totalPrice" => (float)$abandonedCart->getGrandTotal(),
            "shippingAmount" => (float)$abandonedCart->getShippingAmount(),
            "taxAmount" => (float)$abandonedCart->getTaxAmount(),
            "discountAmount" => (float)$abandonedCart->getDiscountAmount(),
            "currency" => $abandonedCart->getGlobalCurrencyCode(),
            "orderId" => $abandonedCart->getEntityId(),
            "customerId" => $this->customerId,
        ];

        try {
            $email = $this->customerId;

            // a cart never synced before is new to Pushwoosh; journeys start on PW_AbandonedCart
            $event = is_null($abandonedCart->getPwSyncedDate()) ? "PW_AbandonedCart" : "PW_AbandonedCartUpdate";
            $this->curl->postEvent($event, $email, $email, $abandonedCartData, $abandonedCart->getStoreId());

            $this->saveResult($abandonedCart->getEntityId(), CronConfig::SYNCED);

        } catch (\Throwable $e) {
            $this->saveResult($abandonedCart->getEntityId(), CronConfig::FAIL_SYNCED);
            throw $e;
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    private function getQuoteItemsData($entityId, $storeId): array
    {

        $quoteItemsData = [];
        $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        $quoteItems = $this->getQuoteItems($entityId);
        foreach ($quoteItems as $quoteItem) {


            $product = $this->_productRepositoryFactory->create()
                ->getById($quoteItem->getProductId(),false,$storeId);

            $imageUrl = $this->imageHelperFactory->create()
                ->init($product, 'product_page_image_medium')->getUrl();

            if(str_contains($imageUrl, 'images/product/placeholder') && $product->getImage()){
                $store = $this->storeManager->getStore($storeId);
                $baseUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';
                $imageUrl = $baseUrl . $product->getImage();
            }

            $this->appEmulation->stopEnvironmentEmulation();
            $categories = $product->getCategoryCollection()->addAttributeToSelect('name');
            $categoriesName = [];
            foreach($categories as $category)
            {
                $categoriesName[] = $category->getName();
            }
            $categoriesName = implode(', ', $categoriesName);
            // the catalog product, not the quote line: a top-level line of a configurable or bundle
            // carries the parent, the same id product views and order events send
            $quoteItemsData[] = [
                "productId" => (string) $quoteItem->getProductId(),
                "name" => $quoteItem->getName(),
                "price" => (float)$quoteItem->getPriceInclTax(),
                "quantity" => $quoteItem->getQty(),
                "sku" => $quoteItem->getSku(),
                "description" => $product->getDescription(),
                "imageUrl" => $imageUrl,
                "productUrl" => $product->getProductUrl(),
                "category" => $categoriesName
            ];
        }
        return $quoteItemsData;
    }

    /**
     * @param $quoteId
     * @return Collection
     */
    private function getQuoteItems($quoteId): Collection
    {
        $quoteItemCollection = $this->quoteItemCollectionFactory->create();
        return $quoteItemCollection
            ->addFieldToSelect('*')
            ->addFieldToFilter('quote_id', [$quoteId])
            ->addFieldToFilter('parent_item_id', ['null' => true]);
    }

    /**
     * @param $quoteId
     * @param $pwOrderId
     * @param $syncStatus
     * @throws AlreadyExistsException|NoSuchEntityException
     */
    private function saveResult($quoteId, $syncStatus): void
    {
        $quoteModel = $this->cartRepositoryInterface->get($quoteId);
        if ($quoteModel->getEntityId()) {
            $quoteModel->setPwSyncStatus($syncStatus);
            $quoteModel->setPwSyncedDate(new \Zend_Db_Expr('CURRENT_TIMESTAMP'));

        }
        $quoteModel->save();
    }

    /**
     * @param null $billingId
     * @return string|null
     * @throws LocalizedException
     */
    private function getTelephone($billingId = null): ?string
    {
        if ($billingId) {
            return $this->addressRepository->getById($billingId)->getTelephone();
        }
        return null;
    }


    public function isGuest($quote): bool
    {
        return is_null($quote->getCustomerId());
    }

    /**
     * @param $customerId
     * @return CustomerModel
     */
    private function getCustomer($customerId): CustomerModel
    {
        $customerModel = $this->customerFactory->create();
        if (is_numeric($customerId)) {
            $this->customerResource->load($customerModel, $customerId);
            return $customerModel;
        }
        return $customerModel;
    }
}
