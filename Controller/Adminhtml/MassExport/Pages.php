<?php

namespace Irs\CmsSetup\Controller\Adminhtml\MassExport;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Irs\CmsSetup\Controller\Adminhtml\MassExport;
use Irs\CmsSetup\Model\ContentArchiveFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Filesystem;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

class Pages extends MassExport
{
    const ADMIN_RESOURCE = 'Magento_Cms::page';

    public function __construct(
        protected readonly PageCollectionFactory $pageCollFactory,
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
        return $this->pageCollFactory->create();
    }
}
