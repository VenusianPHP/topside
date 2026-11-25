<?php

namespace Surface\Toolkits\UI;

use Surface\NutsAndBolts\Color;
use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\UI\TKLink as PrimitiveContract;

/**
 * Text that opens a URL: the toolkit opens it, so nothing is posted.
 */
abstract class TKLink extends TKPrimitive implements PrimitiveContract
{
    protected TextAlignment $alignment = TextAlignment::LEFT;

    /**
     * @throws ToolkitException When the URL has no scheme; before any native exists.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        private string $text = '',
        private string $url = '',
    ) {
        self::guardUrl($url, $name);

        parent::__construct($name, $window, $parent, $placement);
    }

    public function text(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->guardLive();

        $this->text = $text;
        $this->applyText($text);

        return $this;
    }

    public function url(): string
    {
        return $this->url;
    }

    /**
     * @throws ToolkitException When removed, or the URL has no scheme.
     */
    public function setUrl(string $url): static
    {
        $this->guardLive();
        self::guardUrl($url, $this->path());

        $this->url = $url;
        $this->applyUrl($url);

        return $this;
    }

    public function setAlignment(TextAlignment $alignment): static
    {
        $this->guardLive();

        $this->alignment = $alignment;
        $this->applyAlignment($alignment);

        return $this;
    }

    public function setFont(FontSpec $font): static
    {
        $this->guardLive();
        $this->applyFont($font);

        return $this;
    }

    /**
     * @throws ToolkitException With no scheme, or with a space or control character in it.
     */
    private static function guardUrl(string $url, string $where): void
    {
        if (! preg_match('/^[a-z][a-z0-9+.-]*:[^\s\x00-\x1f]+$/iD', $url)) {
            throw new ToolkitException("A link's URL has a scheme and no spaces (https://..., mailto:...), got '{$url}' for '{$where}'.");
        }
    }

    public function setTextColor(?Color $color): static
    {
        $this->guardLive();
        $this->applyTextColor($color);

        return $this;
    }

    abstract protected function applyText(string $text): void;

    abstract protected function applyUrl(string $url): void;

    abstract protected function applyAlignment(TextAlignment $alignment): void;

    abstract protected function applyFont(FontSpec $font): void;

    /**
     * Null restores the toolkit's own link colour. Nothing until the adapter can do it natively.
     */
    protected function applyTextColor(?Color $color): void {}
}
