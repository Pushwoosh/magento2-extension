<?php
declare(strict_types=1);

namespace Pushwoosh\Core\Model\Config\Source;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Attribute\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Data\OptionSourceInterface;
use Pushwoosh\Core\Helper\Data;

/**
 * Customer attributes whose value can serve as the Pushwoosh UserID, a string.
 */
class UserIdAttribute implements OptionSourceInterface
{
    public const ENTITY_ID = 'entity_id';

    private const INPUT_TYPES = ['text', 'textarea'];

    private const BACKEND_TYPES = ['static', 'varchar', 'text', 'int'];

    // A value the shopper types in could be another customer's UserID, and would take over their messages.
    private const SHOPPER_FORMS = ['customer_account_create', 'customer_account_edit', 'checkout_register'];

    // Text attributes that are shared by many customers, secret or internal to Magento and this extension.
    private const EXCLUDED_CODES = [
        'prefix', 'firstname', 'middlename', 'lastname', 'suffix', 'created_in',
        'password_hash', 'rp_token', 'rp_token_created_at', 'confirmation',
        'failures_num', 'first_failure', 'lock_expires',
        'default_billing', 'default_shipping',
        'pw_customer_id', 'pw_sync_status',
    ];

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var EavConfig
     */
    private $eavConfig;

    public function __construct(CollectionFactory $collectionFactory, EavConfig $eavConfig)
    {
        $this->collectionFactory = $collectionFactory;
        $this->eavConfig = $eavConfig;
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->collectionFactory->create() as $attribute) {
            $code = (string)$attribute->getAttributeCode();
            if ($code === Data::USER_ID_ATTRIBUTE_DEFAULT || !$this->isSuitable($attribute)) {
                continue;
            }
            $label = (string)($attribute->getFrontendLabel() ?: $code);
            $options[] = ['value' => $code, 'label' => sprintf('%s (%s)', $label, $code)];
        }
        usort($options, static function (array $a, array $b): int {
            return strcasecmp($a['label'], $b['label']);
        });

        return array_merge([
            ['value' => Data::USER_ID_ATTRIBUTE_DEFAULT, 'label' => __('Email (default)')],
            ['value' => self::ENTITY_ID, 'label' => __('Customer ID (entity_id)')],
        ], $options);
    }

    private function isSuitable($attribute): bool
    {
        return !in_array($attribute->getAttributeCode(), self::EXCLUDED_CODES, true)
            && in_array($attribute->getFrontendInput(), self::INPUT_TYPES, true)
            && in_array($attribute->getBackendType(), self::BACKEND_TYPES, true)
            && !$this->isFilledByShopper((string)$attribute->getAttributeCode());
    }

    // The collection item is a bare EAV attribute without forms; the customer attribute model loads them.
    private function isFilledByShopper(string $code): bool
    {
        $forms = (array)$this->eavConfig->getAttribute(Customer::ENTITY, $code)->getUsedInForms();

        return (bool)array_intersect(self::SHOPPER_FORMS, $forms);
    }
}
