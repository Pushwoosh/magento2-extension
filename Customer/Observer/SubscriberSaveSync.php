<?php
declare(strict_types=1);

namespace Pushwoosh\Customer\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Pushwoosh\Core\Logger\Logger as PushwooshLogger;
use Pushwoosh\Customer\Helper\Data as CustomerHelper;
use Pushwoosh\Customer\Model\Queue\CustomerSyncConsumer;

/**
 * A newsletter (un)subscription does not touch customer.updated_at, so the cron never notices it.
 */
class SubscriberSaveSync implements ObserverInterface
{
    /**
     * @var CustomerHelper
     */
    private $customerHelper;

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
        PublisherInterface $publisher,
        PushwooshLogger $logger
    ) {
        $this->customerHelper = $customerHelper;
        $this->publisher = $publisher;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        $subscriber = $observer->getEvent()->getData('subscriber');
        if (!$subscriber || !$subscriber->getId() || !$this->customerHelper->isCustomerSyncingEnabled()) {
            return;
        }

        try {
            $this->publisher->publish(CustomerSyncConsumer::TOPIC_SUBSCRIBER, (string)$subscriber->getId());
        } catch (\Throwable $e) {
            $this->logger->error('MODULE Customer: cannot queue subscriber sync', ['subscriber_id' => $subscriber->getId(), 'error' => $e->getMessage()]);
        }
    }
}
