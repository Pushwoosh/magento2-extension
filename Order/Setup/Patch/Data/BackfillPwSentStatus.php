<?php
declare(strict_types=1);

namespace Pushwoosh\Order\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Pushwoosh\Order\Block\Adminhtml\System\Config\OrderSyncStatus;
use Pushwoosh\Order\Model\OrderSyncState;

/**
 * Orders synced before pw_sent_status existed must not be re-sent as PW_OrderCreated.
 */
class BackfillPwSentStatus implements DataPatchInterface
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function apply()
    {
        $connection = $this->resource->getConnection('sales');
        $connection->update(
            $this->resource->getTableName('sales_order', 'sales'),
            [OrderSyncState::SENT_STATUS => new \Zend_Db_Expr('status')],
            [
                OrderSyncStatus::PW_SYNC_STATUS . ' = ?' => OrderSyncStatus::SYNCED,
                OrderSyncState::SENT_STATUS . ' IS NULL',
            ]
        );

        return $this;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
