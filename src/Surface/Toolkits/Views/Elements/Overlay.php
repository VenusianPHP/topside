<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\UI\TKOverlay as OverlayContract;

/**
 * Children on top of each other: the first at the back, each later one above. Each is placed
 * by its own align() and margin(); one that fills takes the whole area.
 *
 * A child that fills both ways takes the overlay's size and never gives it one, so a picture
 * can cover a card without making the card the picture's size:
 *
 *     <Overlay minSize="0 320" cornerRadius="0 0 14 14" clip>
 *         <Image src="hero.jpg" scaling="fill" fill />
 *         <Column padding="28 32" align="start start"> ... </Column>
 *     </Overlay>
 */
class Overlay extends Container
{
    protected readonly Insets $padding;

    /**
     * @throws ViewException When the padding has a negative side or cannot be read.
     */
    final public function __construct(Insets|int|string|array $padding = 0)
    {
        $this->padding = self::insetsOf($padding, 'padding');
    }

    /**
     * @param Insets|int|string|list<int> $padding points between the edge and the children, as
     *        CSS writes it: 16, '40 16', '40 16 8' or '40 16 8 0'
     */
    public static function make(Insets|int|string|array $padding = 0): static
    {
        return new static($padding);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->overlay($name, $this->padding);
    }

    public function kind(): string
    {
        return 'overlay';
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'padding' => fn (OverlayContract $p, Insets $v) => $p->setPadding($v),
        ];
    }

    /**
     * The constructor's padding is a property like any other, so a change becomes
     * setPadding(), not a new primitive.
     */
    protected function valueOf(string $prop): mixed
    {
        return $prop === 'padding' ? $this->padding : parent::valueOf($prop);
    }

    protected function initial(string $prop): mixed
    {
        return $prop === 'padding' ? $this->padding : parent::initial($prop);
    }

    protected function tracked(): array
    {
        return ['padding'];
    }
}
