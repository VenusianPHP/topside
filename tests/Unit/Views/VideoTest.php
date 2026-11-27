<?php

declare(strict_types=1);

use Surface\Toolkits\UI\TKVideo;
use Surface\Toolkits\ToolkitAdapters;
use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\Mail\Views\VideoEnded;
use Surface\Contracts\Toolkits\Mail\Views\VideoFailed;
use Surface\Contracts\Toolkits\Mail\Views\VideoPaused;
use Surface\Contracts\Toolkits\Mail\Views\VideoPlaying;
use Surface\Contracts\Toolkits\UI\TKVideo as VideoContract;
use Surface\Contracts\Toolkits\UI\TKWebView as WebViewContract;
use Venusian\Topside\Tests\Fakes\UI\FakeVideo;

/*
| A video view on every toolkit: a player plays a file or a stream and reports what it does;
| with no player it shows nothing and says so, so a view can offer something else.
*/
beforeEach(function (): void {
    FakeVideo::$player = true;
    openViewWindow();
    sharpApp();
});
afterEach(function (): void {
    FakeVideo::$player = true;
    dropSharpApp();
});

/** The arguments of each time $hook was told to $primitive, oldest first. */
function videoToldWith(object $primitive, string $hook): array
{
    return array_values(array_map(static fn (array $call): array => $call[1], array_filter($primitive->calls, static fn (array $call): bool => $call[0] === $hook)));
}

/** A video primitive another package gives a toolkit. */
class RegisteredFakeVideo extends FakeVideo {}

describe('the primitive', function (): void {
    it('hands the player a URL, and an absolute path as its file URL', function (): void {
        $video = screen()->video('player');
        $video->setSource('https://example.test/a%20clip.mp4');
        $video->setSource('/Media/Space Clips/moon.mp4');
        $video->setSource(null);

        expect(videoToldWith($video, 'applySource'))->toBe([
            ['https://example.test/a%20clip.mp4'],
            ['file:///Media/Space%20Clips/moon.mp4'],
            [null],
        ])
            ->and($video->source())->toBeNull()
            ->and($video->supported())->toBeTrue();
    });

    it('refuses a source that is neither an absolute path nor an absolute URL', function (string $source): void {
        expect(fn () => screen()->video('player')->setSource($source))->toThrow(ToolkitException::class, 'absolute path or URL');
    })->with(['clips/moon.mp4', 'example.test/moon.mp4', 'https://example.test/a clip.mp4']);

    it('plays, pauses and seeks only with a source, and refuses a negative seek', function (): void {
        $video = screen()->video('player');
        $video->play()->pause()->seek(3.0);
        $before = $video->calls;
        $video->setSource('https://example.test/a.mp4');
        $video->play()->pause()->seek(4.5);

        expect(array_column($before, 0))->not->toContain('applyPlay')
            ->and(videoToldWith($video, 'applyPlay'))->toBe([[]])
            ->and(videoToldWith($video, 'applyPause'))->toBe([[]])
            ->and(videoToldWith($video, 'applySeek'))->toBe([[4.5]])
            ->and($video->position())->toBe(4.5)
            ->and(fn () => $video->seek(-1.0))->toThrow(ToolkitException::class, '0 seconds or later');
    });

    it('keeps muting, looping and the controls, and tells the player only of a change', function (): void {
        $video = screen()->video('player');
        $video->setMuted(true)->setMuted(true)->setLoop(true)->setControls(true)->setControls(false);

        expect(videoToldWith($video, 'applyMuted'))->toBe([[true]])
            ->and(videoToldWith($video, 'applyLoop'))->toBe([[true]])
            ->and(videoToldWith($video, 'applyControls'))->toBe([[true], [false]])
            ->and([$video->isMuted(), $video->isLooping(), $video->hasControls()])->toBe([true, true, false]);
    });

    it('reads position and duration from the player only while it has a source', function (): void {
        $video = screen()->video('player');
        $video->at = 2.0;
        $video->length = 9.5;
        $none = [$video->position(), $video->duration()];
        $video->setSource('https://example.test/a.mp4');

        expect($none)->toBe([0.0, null])
            ->and([$video->position(), $video->duration()])->toBe([2.0, 9.5]);
    });

    it('posts the player\'s reports, each start and stop once, and is not playing after the end', function (): void {
        $video = screen()->video('player');
        $video->setSource('https://example.test/a.mp4');
        $video->start();
        $video->start();
        $playing = $video->isPlaying();
        $video->stop();
        $video->stop();
        $video->start();
        $video->finish();
        $video->fail('gone');

        expect($playing)->toBeTrue()
            ->and($video->isPlaying())->toBeFalse()
            ->and(array_map(fn ($m) => $m::class, test()->loop->posted))->toBe([
                VideoPlaying::class, VideoPaused::class, VideoPlaying::class, VideoEnded::class, VideoFailed::class,
            ])
            ->and([test()->loop->posted[4]->source, test()->loop->posted[4]->reason, test()->loop->posted[4]->path])
            ->toBe(['https://example.test/a.mp4', 'gone', 'root.player']);
    });

    it('with no player: shows nothing, tells the toolkit nothing, and posts VideoFailed', function (): void {
        FakeVideo::$player = false;
        $video = screen()->video('player');
        $video->setSource('https://example.test/a.mp4');
        $video->setMuted(true)->setControls(true)->play()->seek(1.0);
        $video->setSource(null);

        expect($video->supported())->toBeFalse()
            ->and(array_column($video->calls, 0))->not->toContain('applySource')
            ->and(array_column($video->calls, 0))->not->toContain('applyPlay')
            ->and(array_column($video->calls, 0))->not->toContain('applyMuted')
            ->and(array_map(fn ($m) => [$m::class, $m->source, $m->reason], test()->loop->posted))->toBe([
                [VideoFailed::class, 'https://example.test/a.mp4', TKVideo::NO_PLAYER],
            ]);
    });
});

describe('a package giving a toolkit its primitive', function (): void {
    it('makes the toolkit build that class, whether its driver was made before or after', function (): void {
        $made = ToolkitAdapters::driver();
        ToolkitAdapters::primitive('fake', VideoContract::class, RegisteredFakeVideo::class);

        expect($made->primitiveFor(VideoContract::class))->toBe(RegisteredFakeVideo::class)
            ->and(screen()->video('player'))->toBeInstanceOf(RegisteredFakeVideo::class);

        app('toolkit-adapters')->forgetDrivers();

        expect(ToolkitAdapters::driver()->primitiveFor(VideoContract::class))->toBe(RegisteredFakeVideo::class);
    });

    it('refuses a class that does not implement the contract', function (): void {
        expect(fn () => ToolkitAdapters::primitive('fake', WebViewContract::class, RegisteredFakeVideo::class))
            ->toThrow(ToolkitException::class, 'does not implement')
            ->and(fn () => ToolkitAdapters::driver()->registerPrimitive(WebViewContract::class, RegisteredFakeVideo::class))
            ->toThrow(ToolkitException::class, 'does not implement');
    });
});

describe('in Sharp', function (): void {
    it('loads the source once, again only when it changes, and runs each handler', function (): void {
        sharp(<<<'S'
            <Column>
                <Video :source="$clip" controls muted @playing="said('playing')" @paused="said('paused')" @ended="said('ended')" @failed="fallBack" />
                <Button @click="next">next</Button>
                <Label>{{ implode(',', $heard) }}</Label>
            </Column>
            S, <<<'S'
            public string $clip = 'https://example.test/one.mp4';
            public array $heard = [];
            public int $n = 0;

            public function next(): void
            {
                $this->clip = 'https://example.test/two.mp4';
                $this->n++;
            }

            public function said(string $what): void
            {
                $this->heard[] = $what;
            }

            public function fallBack(\Surface\Contracts\Toolkits\Mail\Views\VideoFailed $mail): void
            {
                $this->heard[] = 'failed: '.$mail->reason;
            }
            S);

        $video = at('column1.video1');
        $video->start();
        $video->stop();
        $video->finish();
        deliverPosted();
        click(at('column1.button2'));
        $video->fail('blocked');
        deliverPosted();

        expect(videoToldWith($video, 'applySource'))->toBe([['https://example.test/one.mp4'], ['https://example.test/two.mp4']])
            ->and(videoToldWith($video, 'applyControls'))->toBe([[true]])
            ->and(videoToldWith($video, 'applyMuted'))->toBe([[true]])
            ->and(at('column1.label3')->text())->toBe('playing,paused,ended,failed: blocked');
    });

    it('on a toolkit with no player runs @failed, so the view can show something else', function (): void {
        FakeVideo::$player = false;
        sharp(<<<'S'
            <Column>
                @if ($playable)
                    <Video source="https://example.test/clip.mp4" controls @failed="cannotPlay" />
                @else
                    <Label>Watch it online</Label>
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
            ->and(at('column1.label2')->text())->toBe('Watch it online');
    });
});
