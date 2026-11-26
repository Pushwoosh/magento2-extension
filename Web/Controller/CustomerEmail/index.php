<?php

namespace Pushwoosh\Web\Controller\CustomerEmail;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Customer\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Pushwoosh\Core\Model\UserIdResolver;

class Index extends Action
{
    protected $customerSession;

    private $userIdResolver;

    private $storeManager;

    public function __construct(
        Context $context,
        Session $customerSession,
        UserIdResolver $userIdResolver,
        StoreManagerInterface $storeManager
    )
    {
        $this->customerSession = $customerSession;
        $this->userIdResolver = $userIdResolver;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    public function execute()
    {
        $email = null;
        $userId = null;
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();
            $email = $customer->getEmail();
            $userId = $this->userIdResolver->resolve($customer, $this->storeManager->getStore()->getId(), (string)$email);
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData(['email' => $email, 'userId' => $userId]);
    }
}
