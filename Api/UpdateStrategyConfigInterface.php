<?php

namespace Irs\CmsSetup\Api;

use Irs\CmsSetup\Model\UpdateStrategy;

interface UpdateStrategyConfigInterface
{
    public function getUpdateStrategy(): UpdateStrategy;
}
