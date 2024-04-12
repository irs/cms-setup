<?php

namespace Irs\CmsSetup\Model;

class BlockFile extends File
{
    protected const HEADER_ID = 'id';
    protected const HEADER_TITLE = 'title';
    protected const HEADER_ACTIVE = 'active';
    protected const HEADER_STORES = 'stores';

    protected const YES = 'yes';
    protected const NO = 'no';

    protected const REQUIRED_HEADERS = [
        self::HEADER_ID,
        self::HEADER_TITLE,
    ];

    public function getId(): string
    {
        return $this->header[self::HEADER_ID];
    }

    public function setId(string $id): self
    {
        $this->header[self::HEADER_ID] = $this->filterHeader($id);

        return $this;
    }

    public function getTitle(): string
    {
        return $this->header[self::HEADER_TITLE];
    }

    public function setTitle(string $title): self
    {
        $this->header[self::HEADER_TITLE] = $this->filterHeader($title);

        return $this;
    }

    public function isActive(): bool
    {
        return self::YES == strtolower($this->header[self::HEADER_ACTIVE] ?? self::YES);
    }

    public function setActive(bool $active): self
    {
        $this->header[self::HEADER_ACTIVE] = $active ? self::YES : self::NO;

        if ($this->header[self::HEADER_ACTIVE] == self::YES) {
            unset($this->header[self::HEADER_ACTIVE]);
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function getStoreCodes(): array
    {
        return isset($this->header[self::HEADER_STORES])
            ? array_map('trim', explode(',', $this->header[self::HEADER_STORES]))
            : [];
    }

    public function setStoreCodes(array $codes): self
    {
        $this->header[self::HEADER_STORES] = implode(', ', $codes);

        if ('admin' == $this->header[self::HEADER_STORES]) {
            unset($this->header[self::HEADER_STORES]);
        }

        return $this;
    }

    protected function filterHeader(string $value): string
    {
        return str_replace("\n", '', $value);
    }
}
