<?php

namespace Irs\CmsSetup\Model;


class File
{
    protected const BODY_DELIMITER = "\n----\n";
    protected const HEADER_DELIMITER = ':';
    protected const REQUIRED_HEADERS = [];

    protected ?string $body = null;
    protected array $header = [];

    public function __construct(\SplFileInfo $file = null)
    {
        if ($file instanceof \SplFileInfo) {
            $this->fromFile($file);
        }
    }

    protected function fromFile(\SplFileInfo $file)
    {
        if (!$file->isFile()) {
            throw new \InvalidArgumentException($file->getPathname() . ' is not a file');
        }
        if (!$file->isReadable()) {
            throw new \InvalidArgumentException('Unable to read ' . $file->getPathname());
        }
        $content = file_get_contents($file->getPathname());

        if (!is_string($content)) {
            throw new \InvalidArgumentException('Unable to read ' . $file->getPathname());
        }
        $this->parse($content);
        $this->validate();
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function __toString(): string
    {
        $header = [];

        foreach ($this->header as $name => $value) {
            if (!empty($value)) {
                $header[] = $name . self::HEADER_DELIMITER . ' ' . $value;
            }
        }
        return implode("\n", $header)
            . self::BODY_DELIMITER
            . $this->body;
    }

    protected function parse(string $content): void
    {
        $parts = array_map('trim', explode(self::BODY_DELIMITER, $content, 2));

        if (2 != count($parts)) {
            throw new \InvalidArgumentException('Unable to find body delimiter ' . self::BODY_DELIMITER);
        }
        list ($header, $this->body) = $parts;
        $lineNum = 1;

        foreach (explode("\n", $header) as $line) {
            $parts = array_map('trim', explode(self::HEADER_DELIMITER, $line, 2));

            if (2 != count($parts)) {
                throw new \InvalidArgumentException('Unable to find header delimiter ' . self::HEADER_DELIMITER . ' on line ' . $lineNum);
            }
            list ($name, $value) = $parts;

            $this->header[strtolower($name)] = $value;
            $lineNum++;
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function validate(): void
    {
        $missing = array_diff_key(array_flip(static::REQUIRED_HEADERS), $this->header);

        if ($missing) {
            throw new \InvalidArgumentException('Missing required headers ' . implode(', ', array_keys($missing)));
        }
        $empty = array_filter(array_map(
            fn ($required) => empty(trim($this->header[$required])) ? $required : null,
            static::REQUIRED_HEADERS,
        ));
        if ($empty) {
            throw new \InvalidArgumentException('Required headers ' . implode(', ', $empty) . ' should not be empty');
        }
    }
}
