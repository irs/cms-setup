<?php

namespace Irs\CmsSetup\Model;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;

class DisallowOverwritePlugin
{
    public function beforeSave(BlockRepositoryInterface|PageRepositoryInterface $subject, Page|Block $object)
    {
        if ($object->getData(ContentUpdate::ATTR_ALLOW_OVERWRITE) == $object->getOrigData(ContentUpdate::ATTR_ALLOW_OVERWRITE)) {
            $object->setData(ContentUpdate::ATTR_ALLOW_OVERWRITE, false);
        }
    }
}
