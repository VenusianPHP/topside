<?php

namespace Surface\Toolkits\Views;

use Closure;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\PrimitiveMail;
use Surface\Contracts\Toolkits\Mail\Views\Toggled;
use Surface\Contracts\Toolkits\Mail\Views\ViewScrolled;
use Surface\Contracts\Toolkits\Mail\Views\ViewResized;
use Surface\Contracts\Toolkits\Mail\Views\ButtonClicked;
use Surface\Contracts\Toolkits\Mail\Views\GroupClicked;
use Surface\Contracts\Toolkits\Mail\Views\WebViewFailed;
use Surface\Contracts\Toolkits\Mail\Views\WebViewLoaded;
use Surface\Contracts\Toolkits\Mail\Views\VideoEnded;
use Surface\Contracts\Toolkits\Mail\Views\VideoFailed;
use Surface\Contracts\Toolkits\Mail\Views\VideoPaused;
use Surface\Contracts\Toolkits\Mail\Views\VideoPlaying;
use Surface\Contracts\Toolkits\Mail\Views\DateChanged;
use Surface\Contracts\Toolkits\Mail\Views\TextChanged;
use Surface\Contracts\Toolkits\Mail\Views\TextSubmitted;
use Surface\Contracts\Toolkits\Mail\Views\RowSelected;
use Surface\Contracts\Toolkits\Mail\Views\NodeSelected;
use Surface\Contracts\Toolkits\Mail\Views\NodeExpanded;
use Surface\Contracts\Toolkits\Mail\Views\NodeActivated;
use Surface\Contracts\Toolkits\Mail\Views\ItemActivated;
use Surface\Contracts\Toolkits\Mail\Views\ItemActionClicked;
use Surface\Contracts\Toolkits\Mail\Views\SplitMoved;
use Surface\Contracts\Toolkits\Mail\Views\TabSelected;
use Surface\Contracts\Toolkits\Mail\Views\ValueChanged;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;

/**
 * A component on screen: its primitives under the host, and which handler each one's mail
 * runs. Everything the view made is held here and nowhere else, so unmount() leaves nothing
 * behind: no primitive, no handler.
 */
final class MountedView
{
    private readonly Mounted $host;

    /** @var array<string, array<string, Closure>> by primitive uuid, then by the mail they answer */
    private array $handlers = [];

    private bool $mounted = false;

    /**
     * @param Mount|null $mount what the view is mounted in: the component and every one inside
     *        it get it; a $mounter given here should be made with it too
     */
    public function __construct(
        public readonly Component $component,
        TKPrimitiveGroup $host,
        ?Mount $mount = null,
        private ?Mounter $mounter = null,
    ) {
        $this->host = new Mounted($host);
        $this->mounter ??= new Mounter(mount: $mount);
        $component->mountedIn($mount);
    }

    /**
     * Describe the view again and make the screen match. A view's root fills the host unless
     * it says otherwise, so a centred root is centred in the window.
     */
    public function render(): void
    {
        $root = $this->component->render();
        if (! $root->has('fill')) {
            $root->fill();
        }

        $this->mounter->reconcile($this->host, [$root]);
        $this->handlers = $this->mounter->handlers($this->host);
        $this->mounted = true;
        $this->mounter->settled();
    }

    /**
     * Remove everything the view made.
     */
    public function unmount(): void
    {
        $this->mounter->reconcile($this->host, []);
        $this->handlers = [];
        $this->mounted = false;
    }

    public function isMounted(): bool
    {
        return $this->mounted;
    }

    /**
     * The handler $mail runs, if one of this view's primitives posted it and has one for it.
     */
    public function handlerFor(PrimitiveMail $mail): ?Closure
    {
        $event = self::event($mail);

        return is_null($event) ? null : ($this->handlers[$mail->uuid()][$event] ?? null);
    }

    /**
     * Which handler a piece of mail runs: ButtonClicked and GroupClicked run onClick(), Toggled onToggle(),
     * SelectionChanged, DateChanged, ValueChanged, TabSelected and SplitMoved onChange(), TextChanged onChange(), TextSubmitted
     * onSubmit(); a table's and a tree's selection onSelect(), a tree's node onExpand(), what
     * is activated onActivate(), an item's action onAction(),
     * a web view's WebViewLoaded onLoaded() and WebViewFailed onFailed(), a video view's
     * VideoPlaying onPlaying(), VideoPaused onPaused(), VideoEnded onEnded() and VideoFailed
     * onFailed(), a scroll view's
     * ViewScrolled onScroll(), and any view's ViewResized onResized().
     */
    private static function event(PrimitiveMail $mail): ?string
    {
        return match (true) {
            $mail instanceof ButtonClicked, $mail instanceof GroupClicked => 'click',
            $mail instanceof Toggled => 'toggle',
            $mail instanceof SelectionChanged, $mail instanceof DateChanged, $mail instanceof ValueChanged,
            $mail instanceof TabSelected, $mail instanceof SplitMoved => 'change',
            $mail instanceof TextChanged => 'input',
            $mail instanceof TextSubmitted => 'submit',
            $mail instanceof RowSelected, $mail instanceof NodeSelected => 'select',
            $mail instanceof NodeExpanded => 'expand',
            $mail instanceof ItemActivated, $mail instanceof NodeActivated => 'activate',
            $mail instanceof ItemActionClicked => 'action',
            $mail instanceof WebViewLoaded => 'loaded',
            $mail instanceof WebViewFailed, $mail instanceof VideoFailed => 'failed',
            $mail instanceof VideoPlaying => 'playing',
            $mail instanceof VideoPaused => 'paused',
            $mail instanceof VideoEnded => 'ended',
            $mail instanceof ViewScrolled => 'scroll',
            $mail instanceof ViewResized => 'resized',
            default => null,
        };
    }
}
