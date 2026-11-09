<?php
declare(strict_types=1);

namespace Pushwoosh\Customer\Model\Config\Source;

use Magento\Customer\Model\ResourceModel\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class CustomerAttributes implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- Not used --')]];
        foreach ($this->collectionFactory->create() as $attribute) {
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => $attribute->getFrontendLabel() ?: $attribute->getAttributeCode(),
            ];
        }

        return $options;
    }
}
