<?php
declare(strict_types=1);

namespace Pushwoosh\Order\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Pushwoosh\Core\Logger\Logger as PushwooshLogger;
use Pushwoosh\Order\Helper\Data as PushwooshOrderHelper;
use Pushwoosh\Order\Model\OrderSyncState;
use Pushwoosh\Order\Model\Queue\OrderSyncConsumer;

/**
 * Fires on every committed order save, whatever placed it: storefront, REST/GraphQL (mobile app), admin.
 */
class OrderSaveSync implements ObserverInterface
{
    /**
     * @var PushwooshOrderHelper
     */
    private $orderHelper;

    /**
     * @var OrderSyncState
     */
    private $syncState;

    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var PushwooshLogger
     */
    private $logger;

    public function __construct(
        PushwooshOrderHelper $orderHelper,
        OrderSyncState $syncState,
        PublisherInterface $publisher,
        PushwooshLogger $logger
    ) {
        $this->orderHelper = $orderHelper;
        $this->syncState = $syncState;
        $this->publisher = $publisher;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getEntityId()) {
            return;
        }

        $storeId = $order->getStoreId();
        $realTime = $this->orderHelper->isOrderSyncInRealTime($storeId);
        if (!$realTime && !$this->orderHelper->isOrderSyncEnabled($storeId)) {
            return;
        }

        if (!$this->syncState->needsSync($order)) {
            return;
        }

        try {
            $this->syncState->markPending($order);
            if ($realTime) {
                $this->publisher->publish(OrderSyncConsumer::TOPIC, (string)$order->getEntityId());
            }
        } catch (\Throwable $e) {
            // the order stays pending, so the cron still picks it up
            $this->logger->error('MODULE Order: cannot queue order sync: ' . $e->getMessage());
        }
    }
}
