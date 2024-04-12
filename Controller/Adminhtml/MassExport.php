<?php

namespace Irs\CmsSetup\Controller\Adminhtml;

use Irs\CmsSetup\Model\ContentArchiveFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

abstract class MassExport extends \Magento\Backend\App\Action implements HttpPostActionInterface
{
    protected WriteInterface $tempDir;

    public function __construct(
        protected readonly Filter $filter,
        protected readonly ContentArchiveFactory $archiveFactory,
        protected readonly LoggerInterface $logger,
        Filesystem $filesystem,
        Context $context,
    ) {
        parent::__construct($context);

        $this->tempDir = $filesystem->getDirectoryWrite(DirectoryList::TMP);
    }

    abstract protected function createCollection(): \Magento\Cms\Model\ResourceModel\AbstractCollection;

    public function execute()
    {
        try {
            $archiveName = uniqid('CMS');
            $archive = $this->archiveFactory->create();
            $archive->open($this->tempDir->getAbsolutePath($archiveName), \ZipArchive::CREATE);
            $collection = $this->filter->getCollection($this->createCollection());

            foreach ($collection as $entity) {
                $archive->add($entity);
            }
            $archive->close();
            $content = file_get_contents($this->tempDir->getAbsolutePath($archiveName));
            $this->tempDir->delete($archiveName);

            return $this->resultFactory->create(ResultFactory::TYPE_RAW)
                    ->setHeader('Pragma', 'public', true)
                    ->setHeader('Content-type', 'application/octet-stream', true)
                    ->setHeader('Content-Length', strlen($content))
                    ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
                    ->setHeader('Content-Disposition', 'attachment; filename="content.zip"')
                    ->setHeader('Last-Modified', date('r'))
                    ->setContents($content);
        } catch (LocalizedException $e) {
            $this->logger->error($e);
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error($e);
            $this->messageManager->addErrorMessage(__('An error has occurred on blocks export.'));
        }

        return $this->resultRedirectFactory->create()
            ->setRefererOrBaseUrl();
    }
}
