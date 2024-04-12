<?php

namespace Irs\CmsSetup\Model;

class AssetIterator extends \FilterIterator
{
    /**
     * @throws \InvalidArgumentException If unable to open path
     */
    public function __construct(string $path)
    {
        try {
            parent::__construct(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)));
        } catch (\UnexpectedValueException|\Error $e) {
            throw new \InvalidArgumentException("Unable to open $path", previous: $e);
        }
    }

    public function accept(): bool
    {
        return $this->current()->isFile();
    }
}
