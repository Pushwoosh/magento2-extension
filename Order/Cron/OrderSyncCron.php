<?php

namespace Pushwoosh\Order\Cron;

use Pushwoosh\Core\Helper\Curl;
use Pushwoosh\Order\Block\Adminhtml\System\Config\OrderSyncStatus;
use Pushwoosh\Order\Helper\Data as PushwooshOrderHelper;
use Pushwoosh\Core\Logger\Logger as PushwooshLogger;
use Pushwoosh\Order\Model\OrderData\OrderDataSend;
use Pushwoosh\Order\Model\OrderSyncState;
use Magento\Framework\App\State;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class OrderSyncCron
{
    /**
     * @var PushwooshOrderHelper
     */
    private $pushwooshHelper;

    /**
     * @var OrderDataSend
     */
    protected $orderDataSend;

    /**
     * @var CollectionFactory
     */
    protected $_orderCollectionFactory;

    /**
     * @var State
     */
    private $state;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var Curl
     */
    protected $curl;
    /**
     * @var PushwooshLogger
     */
    private $logger;

    /**
     * @var OrderSyncState
     */
    private $syncState;

    /**
     * OrderSyncCron constructor.
     * @param OrderDataSend $orderDataSend
     * @param CollectionFactory $orderCollectionFactory
     * @param PushwooshOrderHelper $pushwooshHelper
     * @param State $state
     * @param Curl $curl
     * @param CartRepositoryInterface $quoteRepository
     * @param PushwooshLogger $logger
     * @param OrderSyncState $syncState
     */
    public function __construct(
        OrderDataSend $orderDataSend,
        CollectionFactory $orderCollectionFactory,
        PushwooshOrderHelper $pushwooshHelper,
        State $state,
        Curl $curl,
        CartRepositoryInterface $quoteRepository,
        PushwooshLogger $logger,
        OrderSyncState $syncState
    ) {
        $this->orderDataSend = $orderDataSend;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->pushwooshHelper = $pushwooshHelper;
        $this->state = $state;
        $this->curl = $curl;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->syncState = $syncState;
    }

    /**
     * With the sync switched off, only orders the real-time observer already queued are retried.
     */
    public function execute(): void
    {
        try {
            $orderCollection = $this->_orderCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addFieldToFilter(
                    OrderSyncStatus::PW_SYNC_STATUS,
                    ['in' => [OrderSyncStatus::NOT_SYNCED, OrderSyncStatus::FAIL_SYNCED, OrderSyncStatus::IN_PROGRESS]]
                )
                ->addFieldToFilter(OrderSyncState::ATTEMPTS, ['lt' => OrderSyncState::MAX_ATTEMPTS])
                ->setPageSize($this->pushwooshHelper->getOrderSyncNum())
                ->setOrder('main_table.entity_id', "desc");

            if (!$this->pushwooshHelper->isOrderSyncEnabled()) {
                $orderCollection->addFieldToFilter(OrderSyncState::UPDATED_AT, ['notnull' => true]);
            }

            foreach ($orderCollection as $order) {
                if (!$this->syncState->claim($order)) {
                    continue;
                }
                try {
                    $this->orderDataSend->orderDataSend($order);
                } catch (\Exception $e) {
                    $this->logger->error('MODULE Order: order sync failed', ['order_id' => $order->getEntityId(), 'error' => $e->getMessage()]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('MODULE Order: ' . $e->getMessage());
        }
    }
}
