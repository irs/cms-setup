<?php

namespace Irs\CmsSetup\Model;

use Irs\CmsSetup\Api\UpdateStrategyConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Phrase;
use Magento\Framework\Profiler\Driver\Standard\Stat;
use Magento\Ui\DataProvider\Modifier\ModifierInterface;

class DisallowOverwriteFormModifier implements ModifierInterface
{
    public function __construct(
        protected readonly UpdateStrategyConfigInterface $config,
        protected readonly State $appState,
    ) {
    }

    public function modifyData(array $data)
    {
        return $data;
    }

    public function modifyMeta(array $meta)
    {
        $strategy = $this->config->getUpdateStrategy();
        $this->setAllowOverwrite($meta, 'visible',$strategy != UpdateStrategy::Force);

        if ($this->appState->getMode() == State::MODE_DEVELOPER) {
            $this->setAllowOverwrite($meta, 'notice', $this->getNotice($strategy));
        }

        return $meta;
    }

    protected function setAllowOverwrite(array &$meta, string $field, $value): void
    {
        $meta["general"]["children"]["allow_overwrite"]["arguments"]["data"]["config"][$field] = $value;
    }

    protected function getNotice(UpdateStrategy $strategy): ?Phrase
    {
        return match ($strategy) {
            UpdateStrategy::Error => __(
                'If overwrite is disallowed an error will occur on attempt to update this page with '
                    . 'bin/magento setup:cms:import command or Irs\CmsSetup\Setup\ContentUpdatePatch. '
                    . 'This can be changed in Stores > Configuration > Advanced > Developer > CMS Setup > Content Update Strategy.',
            ),
            UpdateStrategy::Skip  => __(
                'If overwrite is disallowed this page will not be updated with '
                    . 'bin/magento setup:cms:import command or Irs\CmsSetup\Setup\ContentUpdatePatch. '
                    . 'This can be changed in Stores > Configuration > Advanced > Developer > CMS Setup > Content Update Strategy.',
            ),
            default => null,
        };
    }
}
