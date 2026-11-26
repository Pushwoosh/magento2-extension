<?php
declare(strict_types=1);

namespace Pushwoosh\Core\Model;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Pushwoosh\Core\Helper\Data;
use Pushwoosh\Core\Logger\Logger;
use Pushwoosh\Core\Model\Config\Source\UserIdAttribute;

/**
 * Picks the Pushwoosh UserID of a customer from the attribute chosen in Pushwoosh → General → UserID.
 */
class UserIdResolver
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    public function __construct(Data $helper, Logger $logger, CustomerFactory $customerFactory)
    {
        $this->helper = $helper;
        $this->logger = $logger;
        $this->customerFactory = $customerFactory;
    }

    /**
     * Guests and customers with an empty attribute value keep the email, so every contact still gets a UserID.
     *
     * @param Customer|null $customer
     * @param int|string|null $storeId
     * @param string $email
     *
     * @return string
     */
    public function resolve(?Customer $customer, $storeId, string $email): string
    {
        $code = $this->helper->getUserIdAttribute($storeId);
        if ($code === Data::USER_ID_ATTRIBUTE_DEFAULT || $customer === null || !$customer->getId()) {
            return $email;
        }

        $value = $code === UserIdAttribute::ENTITY_ID ? $customer->getId() : $this->getValue($customer, $code);
        $value = is_scalar($value) ? trim((string)$value) : '';
        if ($value === '') {
            $this->logger->info('MODULE Core: UserID attribute is empty, email used instead', [
                'customer_id' => $customer->getId(),
                'attribute' => $code,
                'store_id' => $storeId,
            ]);

            return $email;
        }

        return $value;
    }

    /**
     * Cron collections load only a few attributes, so a missing one is read from the database.
     */
    private function getValue(Customer $customer, string $code)
    {
        if ($customer->hasData($code)) {
            return $customer->getData($code);
        }

        $fresh = $this->customerFactory->create();
        $customer->getResource()->load($fresh, $customer->getId(), [$code]);

        return $fresh->getData($code);
    }
}
