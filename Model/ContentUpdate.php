<?php

namespace Irs\CmsSetup\Model;

use Irs\CmsSetup\Api\UpdateStrategyConfigInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ContentUpdate
{
    public const ATTR_ALLOW_OVERWRITE = 'allow_overwrite';

    public bool $dryRun = false;
    public UpdateStrategy $strategy;

    public function __construct(
        public LoggerInterface $logger,
        protected readonly BlockRepositoryInterface $blockRepo,
        protected readonly BlockFactory $blockFactory,
        protected readonly BlockCollectionFactory $blockCollFactory,
        protected readonly PageRepositoryInterface $pageRepo,
        protected readonly PageFactory $pageFactory,
        protected readonly PageCollectionFactory $pageCollFactory,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly Filesystem $filesystem,
        UpdateStrategyConfigInterface $config,
    ) {
        $this->strategy = $config->getUpdateStrategy();
    }

    public function apply(string $path): void
    {
        $path = rtrim($path, '/') . '/';
        $this->applyBlocks($path . 'blocks');
        $this->applyPages($path . 'pages');
        $this->applyMedia($path . 'media');
    }

    /**
     * Extension points of the method:
     *   - createBlockFile()
     *   - loadBlock()
     *   - updateBlockWithFile()
     *
     * These methods can be used to update custom block attributes.
     *
     * @throws \Exception
     */
    public function applyBlocks(string $path): void
    {
        try {
            foreach (new AssetIterator($path) as $asset) { /* @var \SplFileInfo $asset */
                try {
                    $file = $this->createBlockFile($asset);
                    $fileStoreIds = $this->getStoreIds($file->getStoreCodes());

                    if (empty($fileStoreIds)) {
                        $fileStoreIds[] = Store::DEFAULT_STORE_ID;
                    }
                    $block = ($this->loadBlock($file->getId(), $fileStoreIds) ?: $this->blockFactory->create());

                    if ($this->maySave($block)) {
                        if (!$this->dryRun) {
                            $block->setStoreId($fileStoreIds);
                            $this->updateBlockWithFile($block, $file);
                            $this->blockRepo->save($block);
                        }
                        $this->logSave($block, $asset, true);
                    } else {
                        $this->logSave($block, $asset, false);
                    }
                } catch (\Throwable $e) {
                    throw new ContentUpdateError($e->getMessage() . ' (file: ' . $asset->getPathname() . ')', previous: $e);
                }
            }
        } catch (\InvalidArgumentException $e) {
            // Directory is not used. Ignore.
        }
    }

    protected function logSave(Block|Page $object, \SplFileInfo $asset, bool $success): void
    {
        $type = $object instanceof Block ? 'Block' : 'Page';
        $action = $object->isObjectNew() ? 'created' : 'updated';

        if ($success) {
            $tense = $this->dryRun ? 'can be' : 'has been';
        } else {
            $tense = 'cannot be';
        }
        $id = $object->getId() ? ' #' . $object->getId() : '';
        $this->logger->info("$type$id ({$object->getTitle()}) $tense $action with {$asset->getPathname()}");
    }

    /**
     * Extension points of the method:
     *   - createPageFile()
     *   - loadPage()
     *   - updatePageWithFile()
     *
     * These methods can be used to update custom block attributes.
     *
     * @throws \Exception
     */
    public function applyPages(string $path): void
    {
        try {
            foreach (new AssetIterator($path) as $asset) {
                try {
                    $file = $this->createPageFile($asset);
                    $fileStoreIds = $this->getStoreIds($file->getStoreCodes());

                    if (empty($fileStoreIds)) {
                        $fileStoreIds[] = Store::DEFAULT_STORE_ID;
                    }
                    $page = ($this->loadPage($file->getId(), $fileStoreIds) ?: $this->pageFactory->create());

                    if ($this->maySave($page)) {
                        if (!$this->dryRun) {
                            $page->setStoreId($fileStoreIds);
                            $this->updatePageWithFile($page, $file);
                            $this->pageRepo->save($page);
                        }
                        $this->logSave($page, $asset, true);
                    } else {
                        $this->logSave($page, $asset, false);
                    }
                } catch (\Throwable $e) {
                    throw new ContentUpdateError($e->getMessage() . ' (file: ' . $asset->getPathname() . ')', previous: $e);
                }
            }
        } catch (\InvalidArgumentException $e) {
            // Page directory does not exist. Ignore.
        }
    }

    /**
     * Maps store codes to IDs
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getStoreIds(array $storeCodes): array
    {
        return array_map(
            fn ($code) => $this->storeManager->getStore($code)->getId(),
            $storeCodes,
        );
    }

    /**
     * Returns CMS block ONLY if it has the same identifier AND the same stores
     */
    protected function loadBlock(string $identifier, array $storeIds): ?Block
    {
        $blocks = $this->blockCollFactory->create()
            ->addFieldToFilter('identifier', $identifier);

        foreach ($blocks as $block) { /* @var Block $block */
            if (!array_diff($block->getStores(), $storeIds)) {
                return $block;
            }
        }

        return null;
    }

    /**
     * This factory method can be used to import additional block attributes
     */
    protected function createBlockFile(\SplFileInfo $asset): BlockFile
    {
        return new BlockFile($asset);
    }

    /**
     * This method can be used to import additional block attributes
     */
    protected function updateBlockWithFile(Block $block, BlockFile $file): void
    {
        $block->setIdentifier($file->getId())
            ->setTitle($file->getTitle())
            ->setIsActive($file->isActive())
            ->setContent($file->getBody());
    }

    /**
     * Deletes CMS block ONLY if it has the same identifier AND the same stores
     */
    public function deleteBlock(string $identifier, array $storeCodes = []): void
    {

        $block = $this->loadBlock(
            $identifier,
            empty($storeCodes) ? [Store::DEFAULT_STORE_ID] : $this->getStoreIds($storeCodes),
        );
        if ($block) {
            $this->blockRepo->delete($block);
        }
    }

    /**
     * Returns CMS page ONLY if it has the same identifier AND the same stores
     */
    protected function loadPage(string $identifier, array $storeIds): ?Page
    {
        $pages = $this->pageCollFactory->create()
            ->addFieldToFilter('identifier', $identifier);

        foreach ($pages as $page) { /* @var Page $page */
            if (!array_diff($page->getStores(), $storeIds)) {
                return $page;
            }
        }

        return null;
    }

    /**
     * This factory method can be used to import additional page attributes
     */
    protected function createPageFile(\SplFileInfo $asset): PageFile
    {
        return new PageFile($asset);
    }

    /**
     * This method can be used to import additional page attributes
     */
    protected function updatePageWithFile(Page $page, PageFile $file): void
    {
        $page->setIdentifier($file->getId())
            ->setTitle($file->getTitle())
            ->setIsActive($file->isActive())
            ->setContent($file->getBody())
            ->setPageLayout($file->getPageLayout())
            ->setMetaTitle($file->getMetaTitle())
            ->setMetaKeywords($file->getMetaKeywords())
            ->setMetaDescription($file->getMetaDescription())
            ->setContentHeading($file->getContentHeading());
    }

    /**
     * Deletes CMS page ONLY if it has the same identifier AND the same stores
     */
    public function deletePage(string $identifier, array $storeCodes = []): void
    {
        $page = $this->loadPage(
            $identifier,
            empty($storeCodes) ? [Store::DEFAULT_STORE_ID] : $this->getStoreIds($storeCodes),
        );
        if ($page) {
            $this->pageRepo->delete($page);
        }
    }

    public function applyMedia(string $path): void
    {
        if (is_readable($path)) {
            $sourceDirPath = realpath($path);
            $targetDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);

            foreach (new AssetIterator($path) as $asset) {
                /* @var \SplFileInfo $asset */
                $sourcePath = $asset->getRealPath();
                $relativePath = substr($sourcePath, strlen($sourceDirPath));
                $targetDir->create(pathinfo($relativePath, PATHINFO_DIRNAME));
                $targetPath = $targetDir->getAbsolutePath($relativePath);

                if ($this->mayCopy($targetPath) && !$this->dryRun) {
                    if (copy($sourcePath, $targetPath)) {
                        $this->logger->info("$sourcePath => $$targetPath");
                    } else {
                        $message = "Unable to copy $sourcePath to $targetPath";

                        if ($this->strategy == UpdateStrategy::Error) {
                            throw new ContentUpdateError($message);
                        } else {
                            $this->logger->error($message);
                        }
                    }
                }
            }
        }
    }

    /**
     * @throws \DomainException
     */
    protected function maySave(Block|Page $object): bool
    {
        if ($object->isObjectNew()) {
            $object->setData(self::ATTR_ALLOW_OVERWRITE, true);
        }
        if ($object->getData(self::ATTR_ALLOW_OVERWRITE)) {
            return true;
        }
        switch ($this->strategy) {
            case UpdateStrategy::Force:
                return true;

            case UpdateStrategy::Skip:
                return (bool)$object->getData(self::ATTR_ALLOW_OVERWRITE);

            default:
                $type = $object instanceof Block ? 'block' : 'page';
                throw new \DomainException(
                    "Strategy {$this->strategy->name} forbids updating CMS $type #{$object->getId()}. "
                        . "Set Allow Overwrite to Yes in Admin Panel to override it",
                );
        }
    }

    /**
     * @throws \DomainException
     */
    protected function mayCopy(string $destPath): bool
    {
        if (!file_exists($destPath)) {
            return true;
        }
        switch ($this->strategy) {
            case UpdateStrategy::Force:
                return true;

            case UpdateStrategy::Skip:
                return false;

            default:
                throw new \DomainException("Unable to overwrite $destPath");
        }
    }
}
