<?php

namespace Surface\Contracts\Typography;

/**
 * A font as an application uses it: what it asks for and the size of its em, in drawing units.
 * A source pins the face to one file; the request still decides the fallback faces.
 */
final readonly class Font
{
    /** @throws TypographyException When the size is not a finite number above 0. */
    public function __construct(
        public FontRequest $request,
        public float $size,
        public ?FontSource $source = null,
    ) {
        if (! is_finite($size) || $size <= 0.0) {
            throw new TypographyException("A font's size is a finite number above 0, got {$size}.");
        }
    }

    /** The same font at another size. */
    public function at(float $size): self
    {
        return new self($this->request, $size, $this->source);
    }

    public function key(): string
    {
        return $this->request->key().'|'.$this->size.'|'.($this->source?->key() ?? '');
    }
}
