<?php

declare(strict_types=1);

use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\Views\Elements\Image;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Slider;
use Surface\Toolkits\Views\Elements\Spinner;
use Surface\Toolkits\Views\Elements\Separator;
use Surface\Toolkits\Views\Elements\ProgressBar;
use Surface\Toolkits\Views\Elements\ToggleButton;
use Surface\Toolkits\Views\Elements\SegmentedControl;
use Surface\Toolkits\Views\Sharp\SharpCompileError;
use Surface\Contracts\Toolkits\UI\ImageScaling;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

/*
| Separator, progress bar, spinner, slider, toggle button, segmented control and image: what
| the primitives hold and post, and the tags that put them in a view. The fakes signal what
| code writes to them, as GTK and Qt do.
*/
beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});

afterEach(fn () => dropSharpApp());

/** The first child of the view's root. */
function simple(int $at = 0): mixed
{
    return screen()->children()[0]->children()[$at];
}

describe('the primitives', function (): void {
    it('posts a slider move by the user, and none of its own rounded echoes', function (): void {
        $slider = screen()->slider('volume', 0.0, 10.0, 2.0);

        $slider->setValue(3.14);
        $slider->setRange(1.0, 5.0);
        flushDrags();
        expect(test()->loop->posted)->toBe([])->and($slider->value())->toBe(3.14);

        $slider->nativeSlid(9.0);
        flushDrags();
        expect(test()->loop->posted)->toHaveCount(1)
            ->and(test()->loop->posted[0]->value)->toBe(5.0)
            ->and($slider->value())->toBe(5.0);
    });

    it('makes one piece of mail of a drag, the latest', function (): void {
        $slider = screen()->slider('volume', 0.0, 10.0, 0.0);

        foreach ([1.0, 2.0, 3.0, 4.0] as $value) {
            $slider->nativeSlid($value);
        }
        flushDrags();

        expect(test()->loop->posted)->toHaveCount(1)->and(test()->loop->posted[0]->value)->toBe(4.0);
    });

    it('keeps a segment in range and posts none of a rebuild', function (): void {
        $control = screen()->segmentedControl('span', ['Day', 'Week', 'Month'], 2);

        $control->setSegments(['A', 'B', 'C', 'D']);
        expect($control->selected())->toBe(2);

        $control->setSegments(['A']);
        expect($control->selected())->toBe(0);

        $control->setSegments([]);
        expect($control->selected())->toBe(-1)->and(test()->loop->posted)->toBe([]);
    });

    it('posts nothing from a disabled toggle button, or for a change from code', function (): void {
        $button = screen()->toggleButton('bold', 'Bold');

        $button->setPressed(true);
        $button->disable();
        $button->nativeToggled(false);

        expect(test()->loop->posted)->toBe([])->and($button->isPressed())->toBeTrue();
    });

    it('refuses before a native exists', function (Closure $make): void {
        $before = built();

        expect(fn () => $make(screen()))->toThrow(ToolkitException::class)
            ->and(built())->toBe($before);
    })->with([
        'progress over 1' => [fn ($in) => $in->progressBar('bad', 1.5)],
        'progress NAN' => [fn ($in) => $in->progressBar('bad', NAN)],
        'slider backwards' => [fn ($in) => $in->slider('bad', 5.0, 1.0, 2.0)],
        'slider INF' => [fn ($in) => $in->slider('bad', 0.0, INF, 2.0)],
        'segment out of range' => [fn ($in) => $in->segmentedControl('bad', ['A'], 3)],
        'segments not strings' => [fn ($in) => $in->segmentedControl('bad', [1, 2])],
        'image not there' => [fn ($in) => $in->image('bad', '/no/such/picture.png')],
    ]);
});

describe('in a view', function (): void {
    it('is the same from Sharp as from composition', function (): void {
        scripted(fn () => Column::make()->with(
            Separator::make(),
            Separator::make(vertical: true),
            ProgressBar::make(),
            ProgressBar::make(0.25),
            Spinner::make(),
            Slider::make(value: 30.0, max: 100.0),
            ToggleButton::make('Bold', pressed: true),
            SegmentedControl::make(['Day', 'Week'], selected: 1),
            Image::make()->scaling(ImageScaling::FILL),
        ));
        $composed = screenShape();

        sharp(<<<'SHARP'
            <Column>
                <Separator />
                <Separator vertical />
                <ProgressBar />
                <ProgressBar value="0.25" />
                <Spinner />
                <Slider value="30" max="100" />
                <ToggleButton pressed>Bold</ToggleButton>
                <SegmentedControl :options="['Day', 'Week']" selected="1" />
                <Image scaling="fill" />
            </Column>
            SHARP);

        expect(screenShape())->toBe($composed);
    });

    it('makes the bare tags do the obvious thing', function (): void {
        sharp('<Column><Separator /><ProgressBar /><Spinner /><Slider /></Column>');

        expect(simple(0)->isHorizontal())->toBeTrue()
            ->and(simple(1)->fraction())->toBeNull()
            ->and(simple(2)->isSpinning())->toBeTrue()
            ->and([simple(3)->min(), simple(3)->max()])->toBe([0.0, 1.0]);
    });

    it('runs @change and @toggle with the value, and changes in place', function (): void {
        $view = sharp(<<<'SHARP'
            <Column>
                <Slider :value="$volume" :max="100" @change="fn ($v) => $this->volume = $v" />
                <ProgressBar :value="$volume / 100" />
                <ToggleButton :pressed="$busy" @toggle="fn ($on) => $this->busy = $on">Busy</ToggleButton>
                <Spinner :spinning="$busy" />
                <SegmentedControl :options="['Day', 'Week']" :selected="$span" @change="pick" />
            </Column>
            SHARP, <<<'PHP'
            public float $volume = 0.0;
            public bool $busy = false;
            public int $span = 0;

            public function pick(int $index): void
            {
                $this->span = $index;
            }
            PHP);
        $before = built();

        simple(0)->nativeSlid(40.0);
        deliverPosted();
        simple(2)->nativeToggled(true);
        deliverPosted();
        simple(4)->nativeChosen(1);
        deliverPosted();

        expect([$view->volume, $view->busy, $view->span])->toBe([40.0, true, 1])
            ->and(simple(1)->fraction())->toBe(0.4)
            ->and(simple(3)->isSpinning())->toBeTrue()
            ->and(built())->toBe($before);
    });

    it('sets a slider value against its new range', function (): void {
        $view = scripted(
            fn (ScriptedView $v) => Column::make()->with(Slider::make($v->state['at'], max: $v->state['max'])),
            ['state' => ['at' => 1.0, 'max' => 1.0]],
        );

        $view->state = ['at' => 80.0, 'max' => 100.0];
        rerender();

        expect(simple()->value())->toBe(80.0)->and(test()->loop->posted)->toBe([]);
    });

    it('finds an image under resources/, or by its whole path', function (): void {
        $file = test()->root.'/resources/logo.png';
        file_put_contents($file, 'png');

        sharp('<Column><Image :src="$file" /></Column>', 'public string $file = \'\';', ['file' => $file]);
        expect(simple()->file())->toBe($file);

        expect(fn () => sharp('<Column><Image src="images/none.png" /></Column>'))
            ->toThrow(ToolkitException::class, app()->basePath('resources/images/none.png'));
    });

    it('says what the value should be, at compile time', function (): void {
        expect(fn () => sharp('<Column><Image scaling="huge" /></Column>'))
            ->toThrow(SharpCompileError::class, "'huge' is none of fit, fill, center, stretch");
    });
});
