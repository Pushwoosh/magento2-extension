<?php
declare(strict_types=1);

namespace Pushwoosh\Order\Model\Queue;

use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Pushwoosh\Core\Logger\Logger as PushwooshLogger;
use Pushwoosh\Order\Model\OrderData\OrderDataSend;
use Pushwoosh\Order\Model\OrderSyncState;

class OrderSyncConsumer
{
    public const TOPIC = 'pushwoosh.order.sync';
    private const MAX_ROUNDS = 3;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var OrderResource
     */
    private $orderResource;

    /**
     * @var OrderDataSend
     */
    private $orderDataSend;

    /**
     * @var OrderSyncState
     */
    private $syncState;

    /**
     * @var PushwooshLogger
     */
    private $logger;

    public function __construct(
        OrderFactory $orderFactory,
        OrderResource $orderResource,
        OrderDataSend $orderDataSend,
        OrderSyncState $syncState,
        PushwooshLogger $logger
    ) {
        $this->orderFactory = $orderFactory;
        $this->orderResource = $orderResource;
        $this->orderDataSend = $orderDataSend;
        $this->syncState = $syncState;
        $this->logger = $logger;
    }

    /**
     * A failed send is left with FAIL_SYNCED for the cron to retry, so the message is never requeued.
     * Another round runs when the order status moved while the previous one was being sent.
     */
    public function process(string $orderId): void
    {
        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            // a fresh load each round: the repository would hand back the cached order with the old status
            $order = $this->orderFactory->create();
            $this->orderResource->load($order, (int)$orderId);
            if (!$order->getEntityId() || !$this->syncState->claim($order)) {
                return;
            }

            try {
                $this->orderDataSend->orderDataSend($order);
            } catch (\Throwable $e) {
                $this->logger->error('MODULE Order queue: order sync failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
                return;
            }
        }
    }
}
