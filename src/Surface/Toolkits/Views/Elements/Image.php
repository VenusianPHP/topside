<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\TKImage;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\ImageScaling;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * A picture from a file: an absolute path, or one under resources/.
 *
 *     <Image src="images/logo.png" scaling="fill" />
 */
class Image extends Element
{
    final public function __construct(
        protected readonly ?string $src = null,
    ) {}

    /**
     * @param string|null $src a path, absolute or under resources/; null or '': no picture
     */
    public static function make(?string $src = null): static
    {
        return new static($src);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->image($name, self::path($this->src));
    }

    public function kind(): string
    {
        return 'image';
    }

    /**
     * How it meets its place: fit (unless told), fill, center or stretch.
     */
    public function scaling(ImageScaling $scaling): static
    {
        return $this->set('scaling', $scaling);
    }

    protected function made(): array
    {
        return ['src' => $this->src];
    }

    protected function defaults(): array
    {
        return parent::defaults() + ['scaling' => ImageScaling::FIT];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'src' => fn (TKImage $p, ?string $v) => $p->setFile(self::path($v)),
            'scaling' => fn (TKImage $p, ImageScaling $v) => $p->setScaling($v),
        ];
    }

    /**
     * @internal An image's path as the lists take it too.
     */
    public static function path(?string $src): ?string
    {
        if (is_null($src) || $src === '') {
            return null;
        }

        return preg_match('~^(/|[A-Za-z]:[\\\\/])~', $src) === 1 ? $src : app()->basePath('resources/'.$src);
    }
}
