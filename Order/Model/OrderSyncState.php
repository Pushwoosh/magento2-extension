<?php
declare(strict_types=1);

namespace Pushwoosh\Order\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;
use Pushwoosh\Order\Block\Adminhtml\System\Config\OrderSyncStatus;

/**
 * Sync bookkeeping is written with plain UPDATEs: a full $order->save() would re-dispatch
 * sales_order_save_commit_after and could overwrite a concurrent change to the order.
 */
class OrderSyncState
{
    public const SENT_STATUS = 'pw_sent_status';
    public const ATTEMPTS = 'pw_sync_attempts';
    public const UPDATED_AT = 'pw_sync_updated_at';
    public const MAX_ATTEMPTS = 5;
    private const CLAIM_TTL_MINUTES = 10;

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function needsSync(OrderInterface $order): bool
    {
        $sentStatus = $order->getData(self::SENT_STATUS);

        return $sentStatus === null || (string)$sentStatus !== (string)$order->getStatus();
    }

    /**
     * An order being sent right now is left alone: markSynced() re-opens it if its status moved meanwhile.
     */
    public function markPending(OrderInterface $order): void
    {
        $this->write($order, [
            OrderSyncStatus::PW_SYNC_STATUS => OrderSyncStatus::NOT_SYNCED,
            self::ATTEMPTS => 0,
        ], ['pw_order_sync_status != ?' => OrderSyncStatus::IN_PROGRESS]);
    }

    /**
     * Takes the order for sending so the queue consumer and the cron never send it twice.
     */
    public function claim(OrderInterface $order): bool
    {
        $stale = $this->connection()->quoteInto(
            'pw_order_sync_status = ? AND pw_sync_updated_at < NOW() - INTERVAL ' . self::CLAIM_TTL_MINUTES . ' MINUTE',
            OrderSyncStatus::IN_PROGRESS
        );
        $pending = $this->connection()->quoteInto(
            'pw_order_sync_status IN (?)',
            [OrderSyncStatus::NOT_SYNCED, OrderSyncStatus::FAIL_SYNCED]
        );

        return $this->write($order, [
            OrderSyncStatus::PW_SYNC_STATUS => OrderSyncStatus::IN_PROGRESS,
        ], ["($pending) OR ($stale)"]) === 1;
    }

    public function markSynced(OrderInterface $order): void
    {
        $sentStatus = (string)$order->getStatus();
        $this->write($order, [
            OrderSyncStatus::PW_SYNC_STATUS => new \Zend_Db_Expr($this->connection()->quoteInto(
                'IF(status = ?, ' . OrderSyncStatus::SYNCED . ', ' . OrderSyncStatus::NOT_SYNCED . ')',
                $sentStatus
            )),
            self::SENT_STATUS => $sentStatus,
            self::ATTEMPTS => 0,
        ]);
    }

    public function markFailed(OrderInterface $order): void
    {
        $this->write($order, [
            OrderSyncStatus::PW_SYNC_STATUS => OrderSyncStatus::FAIL_SYNCED,
            self::ATTEMPTS => new \Zend_Db_Expr(self::ATTEMPTS . ' + 1'),
        ]);
    }

    /**
     * @return int affected rows in sales_order
     */
    private function write(OrderInterface $order, array $orderData, array $where = []): int
    {
        $orderId = (int)$order->getEntityId();
        if (!$orderId) {
            return 0;
        }

        $connection = $this->connection();
        $orderTable = $this->resource->getTableName('sales_order', 'sales');
        $orderData[self::UPDATED_AT] = new \Zend_Db_Expr('NOW()');
        $affected = $connection->update($orderTable, $orderData, ['entity_id = ?' => $orderId] + $where);

        $connection->update(
            $this->resource->getTableName('sales_order_grid', 'sales'),
            [OrderSyncStatus::PW_SYNC_STATUS => new \Zend_Db_Expr($connection->quoteInto(
                "(SELECT pw_order_sync_status FROM $orderTable WHERE entity_id = ?)",
                $orderId
            ))],
            ['entity_id = ?' => $orderId]
        );

        $fresh = $connection->fetchRow(
            $connection->select()
                ->from($orderTable, [OrderSyncStatus::PW_SYNC_STATUS, self::SENT_STATUS, self::ATTEMPTS])
                ->where('entity_id = ?', $orderId)
        );
        if ($fresh) {
            $order->addData($fresh);
        }

        return (int)$affected;
    }

    private function connection()
    {
        return $this->resource->getConnection('sales');
    }
}
