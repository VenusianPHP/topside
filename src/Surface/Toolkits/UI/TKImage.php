<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\UI\ImageScaling;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\UI\TKImage as PrimitiveContract;

/**
 * A picture from a file. A file that is not there is refused here, before the toolkit sees
 * it: some throw for one and some show nothing.
 */
abstract class TKImage extends TKPrimitive implements PrimitiveContract
{
    private ?string $file;

    /**
     * @param string|null $file null: no picture
     * @throws ToolkitException When the file cannot be read.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        ?string $file = null,
        private ImageScaling $scaling = ImageScaling::FIT,
    ) {
        $this->file = self::guardFile($file, $name);

        parent::__construct($name, $window, $parent, $placement);
    }

    public function file(): ?string
    {
        return $this->file;
    }

    /**
     * @throws ToolkitException When removed, or the file cannot be read; the picture shown stays.
     */
    public function setFile(?string $file): static
    {
        $this->guardLive();
        $file = self::guardFile($file, $this->path());

        $this->applyFile($file);
        // Kept only once the toolkit took it: it is the one that knows whether it is a picture.
        $this->file = $file;

        return $this;
    }

    public function setScaling(ImageScaling $scaling): static
    {
        $this->guardLive();

        $this->scaling = $scaling;
        $this->applyScaling($scaling);

        return $this;
    }

    public function scaling(): ImageScaling
    {
        return $this->scaling;
    }

    /**
     * @throws ToolkitException
     */
    private static function guardFile(?string $file, string $where): ?string
    {
        if (! is_null($file) && (! is_file($file) || ! is_readable($file))) {
            throw new ToolkitException("There is no image to read at '{$file}', for '{$where}'.");
        }

        return $file;
    }

    /**
     * @param string|null $file null clears the picture
     * @throws ToolkitException When the toolkit cannot show the file.
     */
    abstract protected function applyFile(?string $file): void;

    abstract protected function applyScaling(ImageScaling $scaling): void;
}
