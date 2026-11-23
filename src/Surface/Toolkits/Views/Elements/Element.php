<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\NutsAndBolts\Color;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\Align;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

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

    public function cornerRadius(float $radius): static
    {
        return $this->set('cornerRadius', $radius);
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
            'enabled' => fn (TKPrimitive $p, bool $v) => $p->setEnabled($v),
            'fill' => fn (TKPrimitive $p, array $v) => $p->fill(...$v),
            'align' => fn (TKPrimitive $p, array $v) => $p->align(...$v),
            'minSize' => fn (TKPrimitive $p, array $v) => $p->minSize(...$v),
            'span' => static fn () => null, // the grid reads it, in settle()
            'background' => fn (TKPrimitive $p, ?Color $v) => $p->setBackground($v),
            'opacity' => fn (TKPrimitive $p, float $v) => $p->setOpacity($v),
            'cornerRadius' => fn (TKPrimitive $p, float $v) => $p->setCornerRadius($v),
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
            'enabled' => true,
            'fill' => [false, false],
            'align' => [Align::FILL, Align::FILL],
            'minSize' => [0, 0],
            'span' => 1,
            'background' => null,
            'opacity' => 1.0,
            'cornerRadius' => 0.0,
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
