<?php

declare(strict_types=1);

namespace Prycing\Prycing\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    const DEFAULT_PATH = 'prycing_prycing/%s/%s';

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getValue($key, $path = 'general', $scope = null, $scopeId = null)
    {
        return $this->scopeConfig->getValue(
            sprintf(self::DEFAULT_PATH, $path, $key),
            $scope,
            $scopeId
        );
    }

    public function isEnabled($scope = null, $scopeId = null): bool
    {
        return (bool)$this->getValue('enable', 'general', $scope, $scopeId);
    }

    public function getFeedUrl($scope = null, $scopeId = null): string
    {
        return (string)$this->getValue('feed_url', 'general', $scope, $scopeId);
    }

    public function isStoreSpecificPricingEnabled($scope = null, $scopeId = null): bool
    {
        return (bool)$this->getValue('store_specific_pricing', 'general', $scope, $scopeId);
    }

    public function getEanField(): string
    {
        return (string)$this->getValue('ean_field') ?: 'sku';
    }
}
