<?php
namespace Pushwoosh\Order\Ui\Component\Listing\Column;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use Pushwoosh\Order\Block\Adminhtml\System\Config\OrderSyncStatus;

class OrderStatus extends Column
{
    protected $_orderRepository;
    protected $_searchCriteria;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $criteria,
        array $components = [],
        array $data = []
    ) {
        $this->_orderRepository = $orderRepository;
        $this->_searchCriteria  = $criteria;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $order  = $this->_orderRepository->get($item["entity_id"]);
                $status = (int)$order->getData(OrderSyncStatus::PW_SYNC_STATUS);

                $message = [
                    OrderSyncStatus::SYNCED => 'Synced',
                    OrderSyncStatus::FAIL_SYNCED => 'Failed',
                    OrderSyncStatus::IN_PROGRESS => 'In progress',
                ][$status] ?? 'Not Synced';

                $item[$this->getData('name')] = $message;
            }
        }

        return $dataSource;
    }
}
