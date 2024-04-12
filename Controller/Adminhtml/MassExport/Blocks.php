<?php

namespace Irs\CmsSetup\Controller\Adminhtml\MassExport;

use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Irs\CmsSetup\Model\ContentArchiveFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Filesystem;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

class Blocks extends \Irs\CmsSetup\Controller\Adminhtml\MassExport
{
    const ADMIN_RESOURCE = 'Magento_Cms::block';

    public function __construct(
        protected readonly BlockCollectionFactory $blockCollFactory,
        Filter $filter,
        ContentArchiveFactory $archiveFactory,
        LoggerInterface $logger,
        Filesystem $filesystem,
        Context $context,
    ) {
        parent::__construct($filter, $archiveFactory, $logger, $filesystem, $context);
    }

    protected function createCollection(): \Magento\Cms\Model\ResourceModel\AbstractCollection
    {
        return $this->blockCollFactory->create();
    }
}
