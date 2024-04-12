<?php

namespace Irs\CmsSetup\Model;

class PageFile extends BlockFile
{
    protected const HEADER_PAGE_LAYOUT = 'layout';
    protected const HEADER_META_TITLE = 'meta title';
    protected const HEADER_META_KEYWORDS = 'meta keywords';
    protected const HEADER_META_DESCRIPTION = 'meta description';
    protected const HEADER_CONTENT_HEADING = 'content heading';

    protected const REQUIRED_HEADERS = [
        self::HEADER_ID,
        self::HEADER_TITLE,
        self::HEADER_PAGE_LAYOUT,
    ];

    public function getPageLayout(): ?string
    {
        return $this->header[self::HEADER_PAGE_LAYOUT] ?? null;
    }

    public function setPageLayout(?string $layout): self
    {
        $this->header[self::HEADER_PAGE_LAYOUT] = $layout;

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->header[self::HEADER_META_TITLE] ?? null;
    }

    public function setMetaTitle(?string $title): self
    {
        if ($title) {
            $title = $this->filterHeader($title);
        }
        $this->header[self::HEADER_META_TITLE] = $title;

        return $this;
    }

    public function getMetaKeywords(): ?string
    {
        return $this->header[self::HEADER_META_KEYWORDS] ?? null;
    }

    public function setMetaKeywords(?string $keywords): self
    {
        if ($keywords) {
            $keywords = $this->filterHeader($keywords);
        }
        $this->header[self::HEADER_META_KEYWORDS] = $keywords;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->header[self::HEADER_META_DESCRIPTION] ?? null;
    }

    public function setMetaDescription(?string $description): self
    {
        if ($description) {
            $description = $this->filterHeader($description);
        }
        $this->header[self::HEADER_META_DESCRIPTION] = $description;

        return $this;
    }

    public function getContentHeading(): ?string
    {
        return $this->header[self::HEADER_CONTENT_HEADING] ?? null;
    }

    public function setContentHeading(?string $heading): self
    {
        if ($heading) {
            $heading = $this->filterHeader($heading);
        }
        $this->header[self::HEADER_CONTENT_HEADING] = $heading;

        return $this;
    }
}
