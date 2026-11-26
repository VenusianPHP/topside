<?php

declare(strict_types=1);

use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\UI\TKWebView;
use Surface\Contracts\Toolkits\Mail\Views\WebViewFailed;
use Surface\Contracts\Toolkits\Mail\Views\WebViewLoaded;
use Venusian\Topside\Tests\Fakes\UI\FakeWebView;

/*
| A web view on every toolkit: an engine loads pages and reports how each load ended; with no
| engine it shows nothing and says so, so a view can offer something else.
*/
beforeEach(function (): void {
    FakeWebView::$engine = true;
    openViewWindow();
    sharpApp();
});
afterEach(function (): void {
    FakeWebView::$engine = true;
    dropSharpApp();
});

/** The arguments of each time $hook was told to $primitive, oldest first. */
function webToldWith(object $primitive, string $hook): array
{
    return array_values(array_map(static fn (array $call): array => $call[1], array_filter($primitive->calls, static fn (array $call): bool => $call[0] === $hook)));
}

describe('the primitive', function (): void {
    it('hands a URL or HTML with its base URL to the engine, and reads the page from it', function (): void {
        $web = screen()->webView('player');
        $web->load('https://example.test/a');
        $web->loadHtml('<p>hi</p>', 'https://example.test/base');
        $web->finish('Hello');

        expect(webToldWith($web, 'applyLoad'))->toBe([['https://example.test/a']])
            ->and(webToldWith($web, 'applyLoadHtml'))->toBe([['<p>hi</p>', 'https://example.test/base']])
            ->and($web->url())->toBe('https://example.test/base')
            ->and($web->title())->toBe('Hello')
            ->and($web->isLoading())->toBeFalse()
            ->and($web->supported())->toBeTrue();
    });

    it('posts WebViewLoaded and WebViewFailed as the engine reports them', function (): void {
        $web = screen()->webView('player');
        $web->load('https://example.test/a');
        $web->finish();
        $web->load('https://example.test/b');
        $web->fail('offline');

        [$loaded, $failed] = test()->loop->posted;

        expect($loaded)->toBeInstanceOf(WebViewLoaded::class)
            ->and($loaded->url)->toBe('https://example.test/a')
            ->and($loaded->path)->toBe('root.player')
            ->and($failed)->toBeInstanceOf(WebViewFailed::class)
            ->and([$failed->url, $failed->reason])->toBe(['https://example.test/b', 'offline']);
    });

    it('refuses a URL or base URL that is not absolute', function (string $call): void {
        $web = screen()->webView('player');

        expect(fn () => $call === 'url' ? $web->load('//www.youtube.com/embed/x') : $web->loadHtml('<p/>', 'apod.nasa.gov'))
            ->toThrow(ToolkitException::class, 'absolute');
    })->with(['url', 'base url']);

    it('with no web engine: shows nothing, tells the toolkit nothing, and posts WebViewFailed', function (): void {
        FakeWebView::$engine = false;
        $web = screen()->webView('player');
        $web->load('https://example.test/a');
        $web->loadHtml('<p/>', 'https://example.test/base');
        $web->reload()->stop();

        expect($web->supported())->toBeFalse()
            ->and(webToldWith($web, 'applyLoad'))->toBe([])
            ->and(webToldWith($web, 'applyLoadHtml'))->toBe([])
            ->and(webToldWith($web, 'applyReload'))->toBe([])
            ->and(array_map(fn ($m) => [$m::class, $m->url, $m->reason], test()->loop->posted))->toBe([
                [WebViewFailed::class, 'https://example.test/a', TKWebView::NO_ENGINE],
                [WebViewFailed::class, 'https://example.test/base', TKWebView::NO_ENGINE],
            ]);
    });
});

describe('in Sharp', function (): void {
    it('loads the page once, again only when it changes, and runs @loaded and @failed', function (): void {
        sharp(<<<'S'
            <Column>
                <WebView :html="$html" :baseUrl="$base" @loaded="ready" @failed="fallBack" />
                <Button @click="next">next</Button>
                <Label>{{ $said }}</Label>
            </Column>
            S, <<<'S'
            public string $html = '<p>one</p>';
            public string $base = 'https://example.test/one';
            public string $said = '';
            public int $n = 0;

            public function next(): void
            {
                $this->html = '<p>two</p>';
                $this->n++;
            }

            public function ready(): void
            {
                $this->said = 'ready';
            }

            public function fallBack(\Surface\Contracts\Toolkits\Mail\Views\WebViewFailed $mail): void
            {
                $this->said = 'failed: '.$mail->reason;
            }
            S);

        $web = at('column1.webView1');
        $web->finish();
        deliverPosted();
        $saidReady = at('column1.label3')->text();

        click(at('column1.button2'));
        $web->fail('blocked');
        deliverPosted();

        expect(webToldWith($web, 'applyLoadHtml'))->toBe([
            ['<p>one</p>', 'https://example.test/one'],
            ['<p>two</p>', 'https://example.test/one'],
        ])
            ->and($saidReady)->toBe('ready')
            ->and(at('column1.label3')->text())->toBe('failed: blocked');
    });

    it('on a toolkit with no engine runs @failed, so the view can show something else', function (): void {
        FakeWebView::$engine = false;
        sharp(<<<'S'
            <Column>
                @if ($playable)
                    <WebView url="https://example.test/video" @failed="cannotPlay" />
                @else
                    <Label>Open in browser</Label>
                @endif
            </Column>
            S, <<<'S'
            public bool $playable = true;

            public function cannotPlay(): void
            {
                $this->playable = false;
            }
            S);
        deliverPosted();

        expect(childNames('column1'))->toBe(['label2'])
            ->and(at('column1.label2')->text())->toBe('Open in browser');
    });
});

describe('button padding', function (): void {
    it('is none until set, and reads one to four values', function (): void {
        $button = screen()->button('go', 'Go');
        $before = $button->padding();
        $button->setPadding('8 16');
        $button->setPadding(Insets::none());

        expect($before->isZero())->toBeTrue()
            ->and(array_map(fn (array $a) => [$a[0]->top, $a[0]->right, $a[0]->bottom, $a[0]->left], webToldWith($button, 'applyPadding')))
            ->toBe([[8, 16, 8, 16], [0, 0, 0, 0]]);
    });

    it('takes padding="0 16" in Sharp, and is told only when it changes', function (): void {
        sharp('<Column><Button padding="0 16">More</Button><Button>Plain</Button></Column>');

        expect(array_map(fn (array $a) => [$a[0]->top, $a[0]->right, $a[0]->bottom, $a[0]->left], webToldWith(at('column1.button1'), 'applyPadding')))
            ->toBe([[0, 16, 0, 16]])
            ->and(webToldWith(at('column1.button2'), 'applyPadding'))->toBe([]);
    });
});
