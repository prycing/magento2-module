<?php

declare(strict_types=1);

namespace Qualide\Prycing\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    const DEFAULT_PATH = 'qualide_prycing/%s/%s';

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getValue($key, $path = 'general')
    {
        return $this->scopeConfig->getValue(sprintf(self::DEFAULT_PATH, $path, $key));
    }

    public function isEnabled(): bool
    {
        return (bool)$this->getValue('enable');
    }

    public function getFeedUrl(): string
    {
        return (string)$this->getValue('feed_url');
    }
}
