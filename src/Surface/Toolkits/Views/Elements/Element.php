<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use InvalidArgumentException;
use Surface\NutsAndBolts\Color;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\Align;
use Surface\Contracts\Toolkits\UI\Cursor;
use Surface\Contracts\Toolkits\UI\Corners;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\UI\TKTransition;

/**
 * A description of one primitive: what a view's render() returns, built fresh each time.
 * It holds no native and touches no toolkit; the Mounter reads it to build a primitive, and
 * reads the next one to change that primitive where the two differ.
 *
 * A property set here is applied with the primitive's own setter. One left unset is the
 * primitive's default, so taking a setting away between renders restores the default.
 */
abstract class Element
{
    /**
     * What was set, by property name.
     *
     * @var array<string, mixed>
     */
    protected array $props = [];

    /**
     * Handlers by the mail they answer: 'click' => Closure.
     *
     * @var array<string, Closure>
     */
    protected array $handlers = [];

    /** @var list<Closure> the handlers that are given the primitive too */
    private array $live = [];

    protected string|int|null $key = null;

    protected ?string $name = null;

    /**
     * Build the primitive inside $parent, named $name, from what it is built with.
     */
    abstract public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive;

    /**
     * The start of a generated primitive name: 'label' gives label1, label2, ...
     */
    abstract public function kind(): string;

    /**
     * Who this element is among its siblings across renders: the same key is the same
     * primitive, wherever it moves. Siblings with no key are matched in order.
     */
    public function key(string|int $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function keyed(): string|int|null
    {
        return $this->key;
    }

    /**
     * The primitive's name, for a readable path in its mail ("root.home.save"). Unset, the
     * Mounter names it. Unique among its siblings, with no dot.
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function named(): ?string
    {
        return $this->name;
    }

    public function visible(bool $visible = true): static
    {
        return $this->set('visible', $visible);
    }

    public function hidden(bool $hidden = true): static
    {
        return $this->set('visible', ! $hidden);
    }

    /**
     * Play $transition each time it is shown: appear="move-right", or appear="move-right 0.4"
     * with its time in seconds. Null shows it at once.
     */
    public function appear(?TKTransition $transition, float $seconds = TKTransition::DEFAULT_SECONDS): static
    {
        return $this->set('appear', [$transition, $seconds]);
    }

    /**
     * Play $transition backwards each time it is hidden, then hide it. Null hides it at once.
     */
    public function disappear(?TKTransition $transition, float $seconds = TKTransition::DEFAULT_SECONDS): static
    {
        return $this->set('disappear', [$transition, $seconds]);
    }

    public function enabled(bool $enabled = true): static
    {
        return $this->set('enabled', $enabled);
    }

    public function disabled(bool $disabled = true): static
    {
        return $this->set('enabled', ! $disabled);
    }

    /**
     * Take the spare space of the column or row it is in, along each axis asked for.
     */
    public function fill(bool $horizontal = true, bool $vertical = true): static
    {
        return $this->set('fill', [$horizontal, $vertical]);
    }

    /**
     * Where it sits inside a slot larger than it needs.
     */
    public function align(Align $horizontal, Align $vertical = Align::FILL): static
    {
        return $this->set('align', [$horizontal, $vertical]);
    }

    public function minSize(int $width, int $height): static
    {
        return $this->set('minSize', [$width, $height]);
    }

    /**
     * The most it grows to, 0 for no limit that way: fill() and align(Align::CENTER) together
     * give a column no wider than this, centred in a wider room.
     */
    public function maxSize(int $width, int $height): static
    {
        return $this->set('maxSize', [$width, $height]);
    }

    /**
     * How many of a Grid's columns it takes. Elsewhere it changes nothing.
     */
    public function span(int $columns): static
    {
        return $this->set('span', $columns);
    }

    public function background(?Color $color): static
    {
        return $this->set('background', $color);
    }

    public function opacity(float $opacity): static
    {
        return $this->set('opacity', $opacity);
    }

    /**
     * How round its corners are: one radius for all four, or one to four as CSS writes them,
     * '0 0 14 14' being square on top and round at the bottom.
     *
     * @param Corners|float|int|string|list<int|float> $radius
     * @throws ViewException When a radius is negative or the value unreadable.
     */
    public function cornerRadius(Corners|float|int|string|array $radius): static
    {
        try {
            return $this->set('cornerRadius', Corners::of($radius));
        } catch (InvalidArgumentException $e) {
            throw new ViewException("A corner radius: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Cut what is inside at its edge and its corner radius.
     */
    public function clip(bool $clip = true): static
    {
        return $this->set('clip', $clip);
    }

    /**
     * Space kept outside it, as CSS writes it: '12', '12 0', '12 0 8', '12 0 8 4'.
     *
     * @param Insets|int|string|list<int> $margin
     * @throws ViewException When a side is negative or the value unreadable.
     */
    public function margin(Insets|int|string|array $margin): static
    {
        return $this->set('margin', self::insetsOf($margin, 'margin'));
    }

    /**
     * The pointer's shape over it; null is the toolkit's own.
     */
    public function cursor(?Cursor $cursor): static
    {
        return $this->set('cursor', $cursor);
    }

    /**
     * Text shown when the pointer rests on it.
     */
    public function tooltip(?string $tooltip): static
    {
        return $this->set('tooltip', $tooltip === '' ? null : $tooltip);
    }

    /**
     * A line round its edge: border="1 #33333380". A null colour is the toolkit's own.
     */
    public function border(int $width, ?Color $color = null): static
    {
        return $this->set('border', [$width, $color]);
    }

    public function has(string $prop): bool
    {
        return array_key_exists($prop, $this->props);
    }

    /**
     * @return array<string, Closure>
     */
    public function handlers(): array
    {
        return $this->handlers;
    }

    /**
     * The handlers as mail runs them on $primitive. An element's own wrapper (onChange()) may
     * take the primitive second, to read what it holds now; what a view gave is given only
     * the mail.
     *
     * @return array<string, Closure>
     */
    public function handlersFor(TKPrimitive $primitive): array
    {
        return array_map(
            fn (Closure $handler): Closure => in_array($handler, $this->live, true)
                ? static fn ($mail) => $handler($mail, $primitive)
                : $handler,
            $this->handlers,
        );
    }

    /**
     * What goes inside the primitive, in order; null holds the place of a child left out.
     * Nothing for a leaf.
     *
     * @return list<Element|null>
     */
    public function children(): array
    {
        return [];
    }

    /**
     * Put $slot where this element's content says Children::slot(). Nothing for a leaf.
     *
     * @param list<Element|null> $slot
     */
    public function fillSlot(array $slot): void {}

    /**
     * The primitive and everything in it match this element now: what could only be set with
     * the children there (a tab view's selection, a grid's cells) is set here.
     *
     * @param Element|null $before the element it matched before, null when just built
     * @param list<\Surface\Toolkits\Views\Mounted> $children
     */
    public function settle(TKPrimitive $primitive, ?Element $before, array $children): void {}

    /**
     * Take the settings $from made (fill, align, hidden, ...), over this element's own: a
     * component's root takes those made on the component where it is used.
     */
    public function inherit(Element $from): static
    {
        $this->props = $from->props + $this->props;

        return $this;
    }

    /**
     * Whether a primitive built from $other can be changed into this one with setters. A
     * different kind, or a different value of something only the constructor takes, cannot.
     */
    public function sameShape(Element $other): bool
    {
        return $other::class === static::class && self::same($this->shape(), $other->shape());
    }

    /**
     * Set on $primitive what differs from $before: the element it was built or last changed
     * from, or null when it was just built from this one.
     */
    public function apply(TKPrimitive $primitive, ?Element $before): void
    {
        $setters = $this->setters();

        $props = array_unique([...array_keys($this->props + ($before?->props ?? [])), ...$this->tracked()]);

        // A primitive just built takes its visibility before its transitions, so starting
        // hidden plays nothing; one already there takes them first, so a change of both plays
        // the new one.
        $transitions = array_values(array_intersect(['appear', 'disappear'], $props));
        $rest = array_values(array_diff($props, $transitions));
        $props = is_null($before) ? [...$rest, ...$transitions] : [...$transitions, ...$rest];

        foreach ($props as $prop) {
            $now = $this->valueOf($prop);
            $was = is_null($before) ? $this->initial($prop) : $before->valueOf($prop);

            if (! self::same($now, $was)) {
                ($setters[$prop] ?? throw new ViewException("No setter for '{$prop}' on ".static::class.'.'))($primitive, $now);
            }
        }
    }

    /**
     * Properties compared on every render though nothing set them: those make() takes, whose
     * value is the element's own rather than an entry of $props.
     *
     * @return list<string>
     */
    protected function tracked(): array
    {
        return array_keys($this->made());
    }

    /**
     * What make() took that a setter can change, by property name: build() passes it to the
     * constructor, and it is compared on every render.
     *
     * @return array<string, mixed>
     */
    protected function made(): array
    {
        return [];
    }

    /**
     * What only the constructor takes, or a setting the primitive cannot take back once set:
     * a change there builds the primitive again.
     *
     * @return array<string, mixed>
     */
    protected function shape(): array
    {
        return [];
    }

    /**
     * Each property's setter on the primitive.
     *
     * @return array<string, Closure(TKPrimitive, mixed): void>
     */
    protected function setters(): array
    {
        return [
            'visible' => fn (TKPrimitive $p, bool $v) => $p->setVisible($v),
            'appear' => fn (TKPrimitive $p, array $v) => $p->appear(...$v),
            'disappear' => fn (TKPrimitive $p, array $v) => $p->disappear(...$v),
            'enabled' => fn (TKPrimitive $p, bool $v) => $p->setEnabled($v),
            'fill' => fn (TKPrimitive $p, array $v) => $p->fill(...$v),
            'align' => fn (TKPrimitive $p, array $v) => $p->align(...$v),
            'minSize' => fn (TKPrimitive $p, array $v) => $p->minSize(...$v),
            'maxSize' => fn (TKPrimitive $p, array $v) => $p->maxSize(...$v),
            'span' => static fn () => null, // the grid reads it, in settle()
            'background' => fn (TKPrimitive $p, ?Color $v) => $p->setBackground($v),
            'opacity' => fn (TKPrimitive $p, float $v) => $p->setOpacity($v),
            'cornerRadius' => fn (TKPrimitive $p, Corners $v) => $p->setCornerRadius($v),
            'clip' => fn (TKPrimitive $p, bool $v) => $p->setClip($v),
            'margin' => fn (TKPrimitive $p, Insets $v) => $p->setMargin($v),
            'cursor' => fn (TKPrimitive $p, ?Cursor $v) => $p->setCursor($v),
            'tooltip' => fn (TKPrimitive $p, ?string $v) => $p->setTooltip($v),
            'border' => fn (TKPrimitive $p, array $v) => $p->setBorder(...$v),
        ];
    }

    /**
     * What each property is on a primitive nobody set it on.
     *
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'visible' => true,
            'appear' => [null, TKTransition::DEFAULT_SECONDS],
            'disappear' => [null, TKTransition::DEFAULT_SECONDS],
            'enabled' => true,
            'fill' => [false, false],
            'align' => [Align::FILL, Align::FILL],
            'minSize' => [0, 0],
            'maxSize' => [0, 0],
            'span' => 1,
            'background' => null,
            'opacity' => 1.0,
            'cornerRadius' => Corners::none(),
            'clip' => false,
            'margin' => Insets::none(),
            'cursor' => null,
            'tooltip' => null,
            'border' => [0, null],
        ];
    }

    /**
     * What $prop is on the primitive just after build(): its default, unless build() passed it
     * to the constructor.
     */
    protected function initial(string $prop): mixed
    {
        $made = $this->made();

        return array_key_exists($prop, $made) ? $made[$prop] : ($this->defaults()[$prop] ?? null);
    }

    protected function valueOf(string $prop): mixed
    {
        return array_key_exists($prop, $this->props) ? $this->props[$prop] : $this->initial($prop);
    }

    protected function set(string $prop, mixed $value): static
    {
        $this->props[$prop] = $value;

        return $this;
    }

    protected function on(string $mail, Closure $handler): static
    {
        $this->handlers[$mail] = $handler;

        return $this;
    }

    /**
     * As on(), for a wrapper that takes the primitive after the mail.
     */
    protected function onLive(string $mail, Closure $handler): static
    {
        $this->live[] = $handler;

        return $this->on($mail, $handler);
    }

    /**
     * $children with each Children::slot() replaced by $slot, and the slot filled inside the
     * rest.
     *
     * @param list<Element|null> $children
     * @param list<Element|null> $slot
     * @return list<Element|null>
     */
    protected static function splice(array $children, array $slot): array
    {
        $spliced = [];
        foreach ($children as $child) {
            if ($child instanceof Children) {
                array_push($spliced, ...$slot);
                continue;
            }
            $child?->fillSlot($slot);
            $spliced[] = $child;
        }

        return $spliced;
    }

    /**
     * One to four values read as CSS reads them.
     *
     * @throws ViewException When a side is negative or the value unreadable.
     */
    protected static function insetsOf(Insets|int|string|array $value, string $what): Insets
    {
        try {
            return Insets::of($value);
        } catch (InvalidArgumentException $e) {
            throw new ViewException("A {$what}: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Equal values: objects (a colour, a font) by their contents, the rest exactly.
     */
    protected static function same(mixed $a, mixed $b): bool
    {
        if (is_object($a) && is_object($b) && ! $a instanceof Closure) {
            return $a::class === $b::class && $a == $b;
        }

        if (is_array($a) && is_array($b)) {
            if (array_keys($a) !== array_keys($b)) {
                return false;
            }
            foreach ($a as $k => $v) {
                if (! self::same($v, $b[$k])) {
                    return false;
                }
            }

            return true;
        }

        return $a === $b;
    }
}
