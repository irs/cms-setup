<?php

namespace Irs\CmsSetup\Model;

use Magento\Framework\Data\OptionSourceInterface;

class UpdateStrategyOptions implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return array_map(
            fn ($s) => ['value' => $s->value, 'label' => $s->name],
            UpdateStrategy::cases(),
        );
    }
}
