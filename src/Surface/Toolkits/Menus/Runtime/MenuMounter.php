<?php

namespace Surface\Toolkits\Menus\Runtime;

use Throwable;
use Surface\Contracts\Toolkits\Menus\BarNode;
use Surface\Contracts\Toolkits\Menus\TKMenuBar;

/**
 * Makes a native bar match a plan with as few operations as it can, as the Mounter does for
 * views: a node is matched by key among its siblings, changed where it differs, moved when it
 * is out of place, and made again only when its shape differs. An unchanged bar costs nothing.
 *
 * A toolkit with no bar class is told nothing; the record is kept all the same.
 *
 * @internal
 */
final class MenuMounter
{
    private MountedNode $root;

    /** @var array<string, MountedNode> */
    private array $byUuid = [];

    /** @var list<PlannedNode> the last bar that was shown whole */
    private array $good = [];

    public function __construct(
        public readonly ?TKMenuBar $bar,
    ) {
        $this->root = new MountedNode();
    }

    /**
     * Show $plan. When the toolkit fails partway the last good bar is put back, and the
     * failure is thrown.
     *
     * @param list<PlannedNode> $plan
     * @throws Throwable What the toolkit threw.
     */
    public function show(array $plan): void
    {
        try {
            $this->sync($this->root, $plan);
            $this->good = $plan;
        } catch (Throwable $failure) {
            try {
                $this->sync($this->root, $this->good);
            } catch (Throwable) {
                // The toolkit refuses that too. The record holds what it did take.
            }

            throw $failure;
        }
    }

    public function find(string $uuid): ?MountedNode
    {
        return $this->byUuid[$uuid] ?? null;
    }

    public function release(): void
    {
        [$this->root, $this->byUuid, $this->good] = [new MountedNode(), [], []];
        $this->bar?->release();
    }

    /**
     * @param list<PlannedNode> $plan
     */
    private function sync(MountedNode $parent, array $plan): void
    {
        $wanted = [];
        foreach ($plan as $planned) {
            $wanted[$planned->key] = $planned->node;
        }

        // Removed first, from the end, so the indexes of what stays are right.
        for ($index = count($parent->children) - 1; $index >= 0; $index--) {
            $child = $parent->children[$index];
            /** @var BarNode|null $next */
            $next = $wanted[$child->planned->key] ?? null;
            if (is_null($next) || ! $child->node->sameShape($next)) {
                $this->bar?->remove($child->node->uuid);
                array_splice($parent->children, $index, 1);
                $this->forget($child);
            }
        }

        $above = $parent->node?->uuid;
        foreach ($plan as $index => $planned) {
            $from = array_find_key($parent->children, static fn (MountedNode $child): bool => $child->planned->key === $planned->key);

            if (is_null($from)) {
                $node = $planned->node->withUuid(bin2hex(random_bytes(16)));
                $this->bar?->insert($above, $index, $node);
                $mounted = new MountedNode($planned, $node);
                array_splice($parent->children, $index, 0, [$mounted]);
                $this->byUuid[$node->uuid] = $mounted;
            } else {
                $mounted = $parent->children[$from];
                if ($from !== $index) {
                    $this->bar?->move($mounted->node->uuid, $above, $index);
                    array_splice($parent->children, $from, 1);
                    array_splice($parent->children, $index, 0, [$mounted]);
                }

                $node = $planned->node->withUuid($mounted->node->uuid);
                if ($changes = $mounted->node->changesTo($node)) {
                    $this->bar?->change($node->uuid, $changes);
                }
                [$mounted->planned, $mounted->node] = [$planned, $node];
            }

            $this->sync($mounted, $planned->children);
        }
    }

    private function forget(MountedNode $node): void
    {
        unset($this->byUuid[$node->node->uuid]);
        foreach ($node->children as $child) {
            $this->forget($child);
        }
    }
}
