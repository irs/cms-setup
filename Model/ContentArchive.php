<?php

namespace Irs\CmsSetup\Model;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;

class ContentArchive extends \ZipArchive
{
    protected Filesystem\Directory\ReadInterface $mediaDir;

    private array $alreadyAddedBlockIds = [];

    public function __construct(
        protected readonly StoreManagerInterface $storeManager,
        protected readonly BlockRepositoryInterface $blockRepo,
        Filesystem $filesystem,
    ) {
        $this->mediaDir = $filesystem->getDirectoryRead(DirectoryList::MEDIA);
    }

    public function add(Block|Page $asset): void
    {
        if ($asset instanceof Block) {
            $this->addBlock($asset);
        } else {
            $this->addPage($asset);
        }
    }

    public function addBlock(Block $block): void
    {
        if (in_array($block->getId(), $this->alreadyAddedBlockIds)) {
            return;
        }
        $file = (new BlockFile)
            ->setId($block->getIdentifier())
            ->setTitle($block->getTitle())
            ->setActive($block->isActive())
            ->setStoreCodes($this->getStoreCodes($block->getStores()))
            ->setBody($block->getContent());

        $this->addBlockFile($file);
        $this->alreadyAddedBlockIds[] = $block->getId();
    }

    public function addPage(Page $page): void
    {
        $file = (new PageFile)
            ->setId($page->getIdentifier())
            ->setTitle($page->getTitle())
            ->setActive($page->isActive())
            ->setStoreCodes($this->getStoreCodes($page->getStores()))
            ->setBody($page->getContent())
            ->setPageLayout($page->getPageLayout())
            ->setMetaTitle($page->getMetaTitle())
            ->setMetaDescription($page->getMetaDescription())
            ->setMetaKeywords($page->getMetaKeywords())
            ->setContentHeading($page->getContentHeading());

        $this->addBlockFile($file);
    }

    public function addBlockFile(BlockFile $file): void
    {
        $directory = $file instanceof PageFile ? 'pages' : 'blocks';
        $name = str_replace('/', '-', trim($file->getTitle()));
        $this->addFromString($this->getTargetPath($directory, $name), $file);

        if ($file->getBody()) {
            $this->addUsedBlocks($file->getBody());
            $this->addUsedMediaAssets($file->getBody());
        }
    }

    protected function addUsedBlocks(string $content): void
    {
        preg_match_all(
            '/\{\{widget\s+type="Magento\\\\Cms\\\\Block\\\\Widget\\\\Block".*block_id="?(\d+)"/U',
            $content,
            $m,
        );
        foreach ($m[1] as $blockId) {
            try {
                $block = $this->blockRepo->getById($blockId);
                $this->addBlock($block);
            } catch (NoSuchEntityException $e) {
                // Ignore.
            }
        }
    }

    protected function addUsedMediaAssets(string $content): void
    {
        preg_match_all('/\{\{media\s+url="?(.+)"?\s*}}/U', $content, $m);

        foreach (array_unique($m[1]) as $path) {
            $path = str_replace('&quot;', '', $path); // Sometimes URLs quoted with &quote; in {{media}}.

            if ($this->mediaDir->isReadable($path)) {
                $this->addFile($this->mediaDir->getAbsolutePath($path), "media/$path");
            }
        }
    }

    protected function getTargetPath(string $directory, string $name): string
    {
        $i = 0;

        do {
            $path = $directory . '/' . $name . ($i ? " ($i)" : '') . '.html';
        } while (false !== $this->locateName($path) && $i++ < 1000);

        return $path;
    }

    protected function getStoreCodes(array $storeIds): array
    {
        return array_map(fn ($id) => $this->storeManager->getStore($id)->getCode(), $storeIds);
    }
}
