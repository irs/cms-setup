<?php

namespace Irs\CmsSetup\Setup;

use Irs\CmsSetup\Model\ContentUpdate;
use Irs\CmsSetup\Model\SetupLogger;
use Irs\CmsSetup\Model\UpdateStrategy;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ContentUpdatePatch implements DataPatchInterface
{
    protected string $contentDir = 'content';
    protected UpdateStrategy $strategy;

    public function __construct(
        protected readonly ContentUpdate $contentUpdate,
        protected readonly Filesystem $filesystem,
        SetupLogger $logger,
    ) {
        $this->contentUpdate->logger = $logger;
    }

    public function apply()
    {
        if (isset($this->strategy)) {
            $this->contentUpdate->strategy = $this->strategy;
        }
        $contentDir = $this->getDirectory($this->contentDir)->getAbsolutePath();
        $this->contentUpdate->apply($contentDir);
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }

    /**
     * Returns directory in class module by relative path
     */
    protected function getDirectory(string $pathInModule): Directory\ReadInterface
    {
        $class = get_class($this);
        $moduleName = substr($class, 0, strpos($class, '\\Setup'));
        $modulePath = str_replace('\\', '/', $moduleName);
        $modulePath = "app/code/$modulePath/" . trim($pathInModule, '/');

        return $this->filesystem->getDirectoryReadByPath($modulePath);
    }
}
