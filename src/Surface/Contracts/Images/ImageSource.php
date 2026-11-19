<?php

namespace Surface\Contracts\Images;

/**
 * The compressed image a decoder reads: a file path, or the file's bytes already in hand. One
 * source is one of those, and its key tells two of them apart. The key is not a pixel value.
 */
final readonly class ImageSource
{
    private function __construct(
        private ?string $path,
        private ?string $contents,
    ) {}

    /**
     * @throws ImageException When the path is empty.
     */
    public static function path(string $path): self
    {
        if ($path === '') {
            throw ImageException::unreadable('');
        }

        return new self($path, null);
    }

    /** The compressed bytes themselves, already in hand. */
    public static function containing(string $contents): self
    {
        return new self(null, $contents);
    }

    /**
     * The compressed file. A path source reads it from disk.
     *
     * @throws ImageException When a path source cannot be read, naming the path.
     */
    public function bytes(): string
    {
        if (! is_null($this->contents)) {
            return $this->contents;
        }

        if (! is_file($this->path) || ! is_readable($this->path)) {
            throw ImageException::unreadable($this->path);
        }

        $read = file_get_contents($this->path);
        if ($read === false) {
            throw ImageException::unreadable($this->path);
        }

        return $read;
    }

    /**
     * Identity of this source. Two paths differ even when the files share a base name. Two byte
     * strings differ even when they have the same length. The same source built again has the same key.
     */
    public function key(): string
    {
        if (! is_null($this->path)) {
            return 'path:'.$this->path;
        }

        return 'bytes:'.hash('sha256', $this->contents);
    }
}