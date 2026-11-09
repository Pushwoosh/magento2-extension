<?php
declare(strict_types=1);

namespace Pushwoosh\Customer\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Pushwoosh\Core\Logger\Logger as PushwooshLogger;
use Pushwoosh\Customer\Helper\Data as CustomerHelper;
use Pushwoosh\Customer\Model\Customer;
use Pushwoosh\Customer\Model\Queue\CustomerSyncConsumer;

/**
 * customer_save_after_data_object comes from CustomerRepository::save, so it covers storefront,
 * REST/GraphQL (mobile app) and admin; no previous data object means the customer was just created.
 */
class CustomerSaveSync implements ObserverInterface
{
    /**
     * @var CustomerHelper
     */
    private $customerHelper;

    /**
     * @var Customer
     */
    private $customer;

    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var PushwooshLogger
     */
    private $logger;

    public function __construct(
        CustomerHelper $customerHelper,
        Customer $customer,
        PublisherInterface $publisher,
        PushwooshLogger $logger
    ) {
        $this->customerHelper = $customerHelper;
        $this->customer = $customer;
        $this->publisher = $publisher;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        $customer = $observer->getEvent()->getData('customer_data_object');
        if (!$customer || !$customer->getId() || !$this->customerHelper->isCustomerSyncingEnabled()) {
            return;
        }

        $isNew = !$observer->getEvent()->getData('orig_customer_data_object');
        $topic = $isNew ? CustomerSyncConsumer::TOPIC_REGISTERED : CustomerSyncConsumer::TOPIC_CUSTOMER;

        try {
            if ($isNew) {
                $this->customer->markRegistrationPending($customer->getId());
            }
            $this->publisher->publish($topic, (string)$customer->getId());
        } catch (\Throwable $e) {
            $this->logger->error('MODULE Customer: cannot queue customer sync', ['customer_id' => $customer->getId(), 'error' => $e->getMessage()]);
        }
    }
}
