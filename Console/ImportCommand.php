<?php

namespace Irs\CmsSetup\Console;

use Irs\CmsSetup\Model\ContentUpdate;
use Irs\CmsSetup\Model\ContentUpdateError;
use Irs\CmsSetup\Model\UpdateStrategy;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
{
    protected const ARG_PATH = 'path';
    protected const OPT_FORCE = 'force';
    protected const OPT_SKIP = 'skip';
    protected const OPT_DRY_RUN = 'dry-run';

    public function __construct(
        protected readonly ContentUpdate $contentUpdate,
        protected readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('setup:cms:import')
            ->setDescription('Imports CMS content: blocks, pages & media assets')
            ->addArgument(self::ARG_PATH, InputArgument::REQUIRED, 'Path to content archive or directory')
            ->addOption(self::OPT_FORCE, 'f', InputOption::VALUE_NONE, 'Disregard Allow Overwrite attribute of CMS blocks & pages')
            ->addOption(self::OPT_SKIP, 's', InputOption::VALUE_NONE, 'Skip update CMS blocks & pages that cannot be updated due to Allow Overwrite setting')
            ->addOption(self::OPT_DRY_RUN, 'd', InputOption::VALUE_NONE, 'Run without actual modifications of the database and file system');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->contentUpdate->logger = new ConsoleLogger($output);
        $contentPath = $input->getArgument(self::ARG_PATH);

        if (!is_readable($contentPath)) {
            throw new \InvalidArgumentException("Unable to read $contentPath");
        }
        if ($input->getOption(self::OPT_DRY_RUN)) {
            $this->contentUpdate->dryRun = true;
        }
        $force = $input->getOption(self::OPT_FORCE);
        $skip = $input->getOption(self::OPT_SKIP);

        if ($force && $skip) {
            throw new \InvalidArgumentException('--' . self::OPT_SKIP . ' and --' . self::OPT_FORCE . ' cannot be used simultaneously');
        }
        if ($force) {
            $this->contentUpdate->strategy = UpdateStrategy::Force;
        } else if ($skip) {
            $this->contentUpdate->strategy = UpdateStrategy::Skip;
        }
        try {
            if (is_dir($contentPath)) {
                $this->contentUpdate->apply($contentPath);
            } else {
                $tempName = uniqid('CMS');
                $tempDir = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
                $tempDir->create($tempName);
                $tempPath = $tempDir->getAbsolutePath($tempName);

                try {
                    $archive = new \ZipArchive;
                    $archive->open($contentPath, \ZipArchive::RDONLY);
                    $archive->extractTo($tempPath);
                    $archive->close();
                    $this->contentUpdate->apply($tempPath);
                } finally {
                    $tempDir->delete($tempName);
                }
            }
        } catch (ContentUpdateError $e) {
            if ($output->isDebug()) {
                throw $e;
            } else {
                $this->contentUpdate->logger->error($e->getMessage());
            }
        }
    }
}
