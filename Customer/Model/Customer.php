<?php

namespace Pushwoosh\Customer\Model;

use Pushwoosh\Core\Helper\Curl;
use Pushwoosh\Core\Helper\Data as CoreHelper;
use Pushwoosh\Core\Logger\Logger as PushwooshLogger;
use Pushwoosh\Customer\Helper\Data as CustomerHelper;
use Pushwoosh\Customer\Model\Config\CronConfig;
use \Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerResourceCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer as MageCustomer;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\StoreManagerInterface;
class Customer
{
    const PW_CUSTOMER_ID = 'pw_customer_id';

    const PW_SYNC_STATUS = "pw_sync_status";

    const EVENT_REGISTERED = 'PW_CustomerRegistered';

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
     * @var CustomerHelper
     */
    protected $customerHelper;

    /**
     * @var CoreHelper
     */
    private $coreHelper;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var Attribute
     */
    protected $eavAttribute;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var PushwooshLogger
     */
    private $logger;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var SubscriberFactory
     */
    protected $subscriberFactory;

    /**
     * @var PhoneNormalizer
     */
    private $phoneNormalizer;

    /**
     * Customer constructor.
     * @param CustomerResourceCollectionFactory $customerResourceCollectionFactory
     * @param CustomerRepositoryInterface $customerRepositoryInterface
     * @param CustomerFactory $customerFactory
     * @param CustomerResource $customerResource
     * @param CustomerHelper $customerHelper
     * @param CoreHelper $coreHelper
     * @param Curl $curl
     * @param Attribute $eavAttribute
     * @param TypeListInterface $cacheTypeList
     * @param PushwooshLogger $logger
     * @param SubscriberFactory $subscriberFactory
     * @param AddressRepositoryInterface $addressRepository
     * @param StoreManagerInterface $storeManager
     * @param PhoneNormalizer $phoneNormalizer
     */
    public function __construct(
        CustomerResourceCollectionFactory $customerResourceCollectionFactory,
        CustomerRepositoryInterface $customerRepositoryInterface,
        CustomerFactory $customerFactory,
        CustomerResource $customerResource,
        CustomerHelper $customerHelper,
        CoreHelper $coreHelper,
        Curl $curl,
        Attribute $eavAttribute,
        TypeListInterface $cacheTypeList,
        PushwooshLogger $logger,
        SubscriberFactory $subscriberFactory,
        AddressRepositoryInterface $addressRepository,
        StoreManagerInterface $storeManager,
        PhoneNormalizer $phoneNormalizer
    ) {

        $this->customerResourceCollectionFactory = $customerResourceCollectionFactory;
        $this->customerRepository = $customerRepositoryInterface;
        $this->customerFactory = $customerFactory;
        $this->customerResource = $customerResource;
        $this->customerHelper = $customerHelper;
        $this->coreHelper = $coreHelper;
        $this->curl = $curl;
        $this->cacheTypeList = $cacheTypeList;
        $this->eavAttribute = $eavAttribute;
        $this->logger = $logger;
        $this->subscriberFactory = $subscriberFactory;
        $this->addressRepository = $addressRepository;
        $this->storeManager = $storeManager;
        $this->phoneNormalizer = $phoneNormalizer;
    }

    /**
     * @param $customerId
     * @return MageCustomer
     */
    public function getCustomerById($customerId){
        $customerModel = $this->customerFactory->create();
        $this->customerResource->load($customerModel, $customerId);

        return $customerModel;
    }


    /**
     * @param int|string|null $billingId
     * @return \Magento\Customer\Api\Data\AddressInterface|null
     */
    private function getBillingAddress($billingId = null)
    {
        if ($billingId) {
            try {
                return $this->addressRepository->getById($billingId);
            } catch (\Exception $exception) {
            }
        }
        return null;
    }

    public function getFieldValues($customer)
    {
        $fieldValues = [];
        $customAttributes = $this->customerHelper->getMapCustomFields();
        if (!empty($customAttributes)) {
            foreach (json_decode($customAttributes) as $attribute) {
                $value ='';
                if(strncmp($attribute->customer_field_id,'shipping__',10) === 0 ){
                    if($customer->getDefaultShippingAddress()){
                        $value = $customer->getDefaultShippingAddress()->getData(substr($attribute->customer_field_id,10));
                    }
                }elseif (strncmp($attribute->customer_field_id,'billing__',9) === 0){
                    if($customer->getDefaultBillingAddress()){
                        $value = $customer->getDefaultBillingAddress()->getData(substr($attribute->customer_field_id,9));
                    }
                }else{
                    if($attr = $customer->getResource()->getAttribute($attribute->customer_field_id)){
                        $value = $attr->getFrontend()->getValue($customer);
                    }else {
                        $value = $customer->getData($attribute->customer_field_id);
                    }
                }

                $fieldValues[$attribute->pw_customer_field_id] = $value;
            }
        }
        return $fieldValues;
    }

    /**
     * @param $customerId
     * @param $syncStatus
     * @param $contactId
     * @param $ecomCustomerId
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function saveResult($customerId, $syncStatus, $ecomCustomerId)
    {
        $customerModel = $this->customerFactory->create();
        if ($customerId) {
            $this->customerResource->load($customerModel, $customerId);
        }

        $customerModel->setPwSyncStatus($syncStatus);
        if ($ecomCustomerId) {
            $customerModel->setPwCustomerId($ecomCustomerId);
        }
        $this->customerResource->save($customerModel);
    }


    /**
     * @param int|string|null $storeId store the contact is synced for; defaults to the customer's own
     */
    public  function contactBody($customer, $storeId = null): array
    {
        $contact['email'] = $customer->getEmail();
        $contact['firstName'] = $customer->getFirstname();
        $contact['lastName'] = $customer->getLastname();
        $billing = $this->getBillingAddress($customer->getDefaultBilling());
        $contact['phone'] = $billing ? $billing->getTelephone() : null;
        $contact['country'] = $billing ? $billing->getCountryId() : null;
        $contact['acceptsMarketing'] = (int)$this->subscriberFactory->create()->loadBySubscriberEmail($customer->getEmail(), $customer->getWebsiteId())->isSubscribed();

        $storeId = $storeId ?: $this->resolveStoreId($customer);
        $consents = [
            'smsConsent' => $this->customerHelper->getSmsConsentAttribute($storeId),
            'whatsappConsent' => $this->customerHelper->getWhatsappConsentAttribute($storeId),
        ];
        foreach ($consents as $tag => $attributeCode) {
            if ($attributeCode !== '') {
                $contact[$tag] = $this->isConsentGiven($customer, $attributeCode) ? 1 : 0;
            }
        }

        $fieldValues = $this->getFieldValues($customer);
        foreach ($fieldValues as $key => $value) {
            $contact[$key] = $value;
        }

        return $contact;
    }

    /**
     * Boolean attributes store 1/0, select ones an option id, so the option label is checked as well.
     */
    private function isConsentGiven($customer, string $attributeCode): bool
    {
        $values = [$customer->getData($attributeCode)];
        $attribute = $customer->getResource()->getAttribute($attributeCode);
        if ($attribute) {
            $values[] = $attribute->getFrontend()->getValue($customer);
        }

        foreach ($values as $value) {
            if (is_scalar($value)
                && in_array(strtolower(trim((string)$value)), ['1', 'yes', 'y', 'true', 'on'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Customers created in the admin have store 0, which carries no store-level Pushwoosh settings.
     *
     * @return int|string|null
     */
    public function resolveStoreId($customer)
    {
        $storeId = $customer->getStoreId();
        if ($storeId) {
            return $storeId;
        }

        try {
            return $this->storeManager->getWebsite($customer->getWebsiteId())->getDefaultStore()->getId();
        } catch (\Exception $e) {
            return null;
        }
    }


    public function updateCustomers()
    {
        $lastUpdate = $this->customerHelper->getLastCustomerUpdateSync();
        $numberOfCustomers = (int)$this->customerHelper->getNumberOfCustomers();
        $customers = $this->customerResourceCollectionFactory->create()
            ->addAttributeToSelect(self::PW_CUSTOMER_ID)
            ->addAttributeToFilter(self::PW_SYNC_STATUS, ['eq' => CronConfig::SYNCED])
            ->addAttributeToFilter('updated_at', ['gt' => $lastUpdate])
            ->setOrder('updated_at', 'asc')
            ->setPageSize($numberOfCustomers);
        foreach ($customers as $customer) {
            try {
                $this->updateCustomer($customer);
            } catch (\Exception $e) {
                $this->logger->error('MODULE: Customer update failed', ['customer_id' => $customer->getId(), 'error' => $e->getMessage()]);
            }
            $lastUpdate = $customer->getUpdatedAt();
        }
        if (isset($lastUpdate)) {
            $this->customerHelper->setLastCustomerUpdateSync($lastUpdate);
            $this->cacheTypeList->cleanType('config');
        }
    }

    /**
     * @throws \Exception
     */
    public function createCustomer($data, $storeId): string {
        if(empty($data['email'])) {
            throw new \Exception("Email is required in createCustomer");
        }

        $data['acceptsMarketing'] = (int)$this->subscriberFactory->create()->loadBySubscriberEmail($data['email'],$this->storeManager->getStore($storeId)->getWebsiteId())->isSubscribed();
        return $this->updateCustomerInternal($data, $storeId);
    }

    /**
     * Guest shoppers exist only as the billing address of their cart or order.
     *
     * @throws \Exception
     */
    public function createGuestCustomer($billingAddress, $storeId): string
    {
        return $this->createCustomer([
            'email' => $billingAddress->getEmail(),
            'firstName' => $billingAddress->getFirstname(),
            'lastName' => $billingAddress->getLastname(),
            'phone' => $billingAddress->getTelephone(),
            'country' => $billingAddress->getCountryId(),
            'fieldValues' => [],
        ], $storeId);
    }

    /**
     * @param int|string|null $storeId store of the order or cart, so the contact lands in the same project as its event
     * @throws \Exception
     */
    public function updateCustomer($customer, $storeId = null): string
    {
        $storeId = $storeId ?: $this->resolveStoreId($customer);
        $email = $this->updateCustomerInternal($this->contactBody($customer, $storeId), $storeId);
        $this->saveResult($customer->getId(), CronConfig::SYNCED, $email);

        return $email;
    }

    /**
     * Flags a new customer so the cron sends PW_CustomerRegistered if the queue never does.
     */
    public function markRegistrationPending($customerId): void
    {
        $customerModel = $this->getCustomerById($customerId);
        if (!$customerModel->getId()) {
            return;
        }

        $customerModel->setData(self::PW_SYNC_STATUS, CronConfig::REGISTRATION_PENDING);
        $this->customerResource->saveAttribute($customerModel, self::PW_SYNC_STATUS);
    }

    /**
     * The customer is marked synced only after the event went out, so a failure leaves it for the cron.
     *
     * @throws \Exception
     */
    public function syncRegisteredCustomer($customer): string
    {
        $storeId = $this->resolveStoreId($customer);
        $email = $this->updateCustomerInternal($this->contactBody($customer, $storeId), $storeId);
        $this->curl->postEvent(self::EVENT_REGISTERED, $email, $email, [
            'customerId' => (string)$customer->getId(),
            'email' => $email,
            'firstName' => $customer->getFirstname(),
            'lastName' => $customer->getLastname(),
            'storeId' => (int)$storeId,
            'createdDate' => $customer->getCreatedAt(),
        ], $storeId);
        $this->saveResult($customer->getId(), CronConfig::SYNCED, $email);

        return $email;
    }

    /**
     * @throws \Exception
     */
    private function updateCustomerInternal($contactData, $storeId = null): string
    {
        $this->logger->info('updateCustomer internal', ['tags' => array_keys($contactData), 'store_id' => $storeId]);

        $email = $contactData['email'];
        if (empty($email)) {
            throw new \Exception("email is missing from contact data");
        }

        unset($contactData['email']);
        $rawPhone = $contactData['phone'] ?? null;
        $country = $contactData['country'] ?? null;
        unset($contactData['phone'], $contactData['country']);

        $phone = $this->phoneNormalizer->toE164($rawPhone, $country, $storeId);
        if (!empty($rawPhone) && $phone === null) {
            $this->logger->warning('MODULE: Customer phone is not a valid number, SMS contact skipped', [
                'country' => $country,
                'store_id' => $storeId,
            ]);
        }

        if ($phone !== null && $this->customerHelper->isSmsConsentRequired($storeId)
            && ($contactData['smsConsent'] ?? 0) !== 1) {
            $phone = null;
        }

        $this->curl->registerDevice($email, $email, 14, $contactData, $storeId);
        if (!empty($phone)) {
            try {
                $this->curl->registerDevice($phone, $email, 18, $contactData, $storeId);
            } catch (\Exception $e) {
                $this->logger->error('MODULE: Customer SMS contact sync failed', ['store_id' => $storeId, 'error' => $e->getMessage()]);
            }
        }

        return $email;
    }

    public function syncCustomers(){
        $this->logger->info('syncCustomers');

        if (!$this->customerHelper->isCustomerSyncingEnabled()) {
            return;
        }

        $this->logger->info('syncCustomers enabled');

        // update already synced customers
        $this->updateCustomers();
        $numberOfCustomers = (int)$this->customerHelper->getNumberOfCustomers();

        // update not synced customers
        $customers = $this->customerResourceCollectionFactory->create()
            ->addAttributeToFilter([
                ['attribute' => self::PW_SYNC_STATUS,'null' => true ],
                ['attribute' => self::PW_SYNC_STATUS,'neq' => CronConfig::SYNCED ]
            ])
            ->addAttributeToSelect(self::PW_SYNC_STATUS)
            ->setPageSize($numberOfCustomers);

        foreach ($customers as $customer) {
            try {
                if ((int)$customer->getData(self::PW_SYNC_STATUS) === CronConfig::REGISTRATION_PENDING) {
                    // give the queue consumer a head start, otherwise both send the registration event
                    if (strtotime((string)$customer->getCreatedAt()) > time() - 600) {
                        continue;
                    }
                    $this->syncRegisteredCustomer($customer);
                } else {
                    $this->updateCustomer($customer);
                }
            } catch (\Exception $e) {
                $this->logger->error('MODULE: Customer sync failed', ['customer_id' => $customer->getId(), 'error' => $e->getMessage()]);
            }
        }
    }
}

