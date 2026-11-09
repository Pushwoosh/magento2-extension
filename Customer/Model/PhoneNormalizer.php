<?php
declare(strict_types=1);

namespace Pushwoosh\Customer\Model;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Pushwoosh accepts SMS contacts only in E.164 (+971501234567), shoppers type local numbers.
 */
class PhoneNormalizer
{
    private const DEFAULT_COUNTRY_PATH = 'general/country/default';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param string|null $phone as typed by the shopper
     * @param string|null $countryId ISO-2 country of the address the phone came with
     * @param int|string|null $storeId store whose default country is the fallback
     * @return string|null E.164 number, or null when it cannot be made one
     */
    public function toE164(?string $phone, ?string $countryId = null, $storeId = null): ?string
    {
        $phone = trim((string)$phone);
        if ($phone === '') {
            return null;
        }

        $region = strtoupper((string)($countryId ?: $this->scopeConfig->getValue(
            self::DEFAULT_COUNTRY_PATH,
            ScopeInterface::SCOPE_STORES,
            $storeId
        )));

        $util = PhoneNumberUtil::getInstance();
        try {
            $number = $util->parse($phone, $region ?: null);
        } catch (NumberParseException $e) {
            return null;
        }

        if (!$util->isPossibleNumber($number)) {
            return null;
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }
}
