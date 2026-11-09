<?php
declare(strict_types=1);

namespace Pushwoosh\Customer\Model\Queue;

use Magento\Newsletter\Model\SubscriberFactory;
use Pushwoosh\Core\Logger\Logger as PushwooshLogger;
use Pushwoosh\Customer\Model\Customer;

/**
 * Failures are not requeued: the customer keeps an unsynced status, so the cron picks it up.
 */
class CustomerSyncConsumer
{
    public const TOPIC_CUSTOMER = 'pushwoosh.customer.sync';
    public const TOPIC_REGISTERED = 'pushwoosh.customer.registered';
    public const TOPIC_SUBSCRIBER = 'pushwoosh.subscriber.sync';

    /**
     * @var Customer
     */
    private $customer;

    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var PushwooshLogger
     */
    private $logger;

    public function __construct(
        Customer $customer,
        SubscriberFactory $subscriberFactory,
        PushwooshLogger $logger
    ) {
        $this->customer = $customer;
        $this->subscriberFactory = $subscriberFactory;
        $this->logger = $logger;
    }

    public function processCustomer(string $customerId): void
    {
        $this->run($customerId, function () use ($customerId) {
            $this->updateIfExists((int)$customerId);
        });
    }

    public function processRegistered(string $customerId): void
    {
        $this->run($customerId, function () use ($customerId) {
            $customer = $this->customer->getCustomerById((int)$customerId);
            if ($customer->getId()) {
                $this->customer->syncRegisteredCustomer($customer);
            }
        });
    }

    public function processSubscriber(string $subscriberId): void
    {
        $this->run($subscriberId, function () use ($subscriberId) {
            $subscriber = $this->subscriberFactory->create()->load((int)$subscriberId);
            if (!$subscriber->getId() || !$subscriber->getSubscriberEmail()) {
                return;
            }

            if (!$this->updateIfExists((int)$subscriber->getCustomerId())) {
                $this->customer->createCustomer(['email' => $subscriber->getSubscriberEmail()], $subscriber->getStoreId());
            }
        });
    }

    private function updateIfExists(int $customerId): bool
    {
        $customer = $customerId ? $this->customer->getCustomerById($customerId) : null;
        if (!$customer || !$customer->getId()) {
            return false;
        }

        $this->customer->updateCustomer($customer);
        return true;
    }

    private function run(string $id, callable $sync): void
    {
        try {
            $sync();
        } catch (\Throwable $e) {
            $this->logger->error('MODULE Customer queue: sync failed', ['id' => $id, 'error' => $e->getMessage()]);
        }
    }
}
