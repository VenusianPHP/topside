<?php

namespace Surface\Contracts\Toolkits\Menus;

use Surface\Toolkits\Menus\Shortcut;
use Surface\Toolkits\Menus\AboutPanel;

/**
 * One node of a bar as a toolkit is told it: everything is worked out for this platform, so
 * an adapter reads no definition and keeps no rule of its own.
 */
final readonly class BarNode
{
    /**
     * @param string $uuid what the adapter says when it is chosen
     * @param bool $enabled counts the menus above it, and whether the toolkit can do its kind
     * @param array<string|int, string> $options a choice's, value => label
     * @param Shortcut|null $shortcut when yieldsToFocus(), the focused widget gets the keys first
     * @param bool $replaced a standard item the application answers: the toolkit does none of
     *        its work and reports it chosen
     * @param bool $application the menu macOS shows first, under the application's name
     */
    public function __construct(
        public BarNodeKind $kind,
        public string $uuid = '',
        public string $label = '',
        public bool $enabled = true,
        public ?bool $on = null,
        public ?string $chosen = null,
        public array $options = [],
        public ?Shortcut $shortcut = null,
        public ?StandardKind $standard = null,
        public ?AboutPanel $panel = null,
        public bool $replaced = false,
        public bool $application = false,
    ) {}

    public function withUuid(string $uuid): self
    {
        return new self(...[...get_object_vars($this), 'uuid' => $uuid]);
    }

    /**
     * Whether a native made for this node can be changed into $other with change(). One of
     * another kind, or with other options, is removed and made again.
     */
    public function sameShape(self $other): bool
    {
        return $this->kind === $other->kind
            && $this->options === $other->options
            && $this->standard === $other->standard
            && $this->panel == $other->panel
            && $this->replaced === $other->replaced
            && $this->application === $other->application;
    }

    /**
     * What change() is told to make this node into $next: only what differs.
     *
     * @return array<string, mixed>
     */
    public function changesTo(self $next): array
    {
        $changes = [];
        foreach (['label', 'enabled', 'on', 'chosen'] as $what) {
            if ($this->{$what} !== $next->{$what}) {
                $changes[$what] = $next->{$what};
            }
        }
        if ($this->shortcut != $next->shortcut) {
            $changes['shortcut'] = $next->shortcut;
        }

        return $changes;
    }
}
