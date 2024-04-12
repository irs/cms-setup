<?php

namespace Irs\CmsSetup\Model;

use Irs\CmsSetup\Api\UpdateStrategyConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Config implements UpdateStrategyConfigInterface
{
    protected const CONFIG_STRATEGY = 'dev/irs_cmssetup/update_strategy';

    public function __construct(protected readonly ScopeConfigInterface $config)
    {}

    public function getUpdateStrategy(): UpdateStrategy
    {
        $strategy = $this->config->getValue(self::CONFIG_STRATEGY);

        return UpdateStrategy::tryFrom((string)$strategy) ?? UpdateStrategy::Error;
    }
}
