<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextRun;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\UI\TKLabel as PrimitiveContract;

/**
 * Text the user reads. It takes no input and posts nothing.
 */
abstract class TKLabel extends TKPrimitive implements PrimitiveContract
{
    /**
     * Left until set. The toolkits do not agree on their own (GTK centres a label; Qt,
     * AppKit and Raven put it on the left), so the native is built with this one: read it
     * in bootstrapNative(), as the text is. A button's text is not a label: the toolkit's
     * own button centres it.
     */
    protected TextAlignment $alignment = TextAlignment::LEFT;

    /** @var list<TextRun> what setRuns() set; empty while the text is one plain run */
    private array $runs = [];

    /**
     * The text is known before the native is built, so it is built with it.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        private string $text = '',
    ) {
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
        $this->runs = [];
        $this->applyText($text);

        return $this;
    }

    public function setRuns(array $runs): static
    {
        $this->guardLive();

        foreach ($runs as $run) {
            if (! $run instanceof TextRun) {
                throw new ToolkitException("A label's runs are TextRuns, got ".get_debug_type($run)." for '{$this->path()}'.");
            }
        }

        $runs = array_values($runs);
        $this->text = TextRun::join($runs);
        $this->runs = $runs;
        $this->applyRuns($runs);

        return $this;
    }

    public function runs(): array
    {
        return $this->runs === [] ? [new TextRun($this->text)] : $this->runs;
    }

    public function setWrap(bool $wrap): static
    {
        $this->guardLive();
        $this->applyWrap($wrap);

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

    public function setTextColor(?Color $color): static
    {
        $this->guardLive();
        $this->applyTextColor($color);

        return $this;
    }

    abstract protected function applyText(string $text): void;

    /**
     * Show the runs, each with its own look over the label's font and colour. Until an
     * adapter overrides it, the label shows their text plainly.
     *
     * @param list<TextRun> $runs
     */
    protected function applyRuns(array $runs): void
    {
        $this->applyText(TextRun::join($runs));
    }

    /**
     * True wraps at the allocated width instead of growing.
     */
    abstract protected function applyWrap(bool $wrap): void;

    abstract protected function applyAlignment(TextAlignment $alignment): void;

    abstract protected function applyFont(FontSpec $font): void;

    /**
     * Null restores the toolkit's own text colour.
     */
    abstract protected function applyTextColor(?Color $color): void;
}
