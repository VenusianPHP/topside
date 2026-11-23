<?php

declare(strict_types=1);

use Surface\Toolkits\Presentation;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\Views\Elements\Link;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\ComboBox;
use Surface\Toolkits\Views\Elements\TextInput;
use Surface\Toolkits\Views\Elements\Datepicker;
use Surface\Toolkits\Views\Sharp\SharpCompileError;

beforeEach(function (): void {
    openViewWindow();
    sharpApp();
});
afterEach(fn () => dropSharpApp());

function posted(): array
{
    return mailNames(test()->loop->posted);
}

describe('the text input', function (): void {
    it('posts what the user types and submits, and nothing written from code', function (): void {
        $input = screen()->textInput('name');
        test()->loop->posted = [];

        $input->setValue('from code');
        expect(posted())->toBe([])->and($input->value())->toBe('from code');

        $input->nativeTyped('typed');
        $input->nativeTyped('typed');
        $input->nativeSubmitted('typed');
        expect(posted())->toBe(['TextChanged', 'TextSubmitted'])->and($input->value())->toBe('typed');
    });

    it('posts nothing while disabled', function (): void {
        $input = screen()->textInput('name');
        test()->loop->posted = [];
        screen()->disable();

        $input->nativeTyped('x');
        $input->nativeSubmitted('x');
        expect(posted())->toBe([]);
    });

    it('never gives queued older text back over newer', function (): void {
        $view = sharp('<Column><TextInput :value="$draft" @change="fn ($text) => $this->draft = $text" /></Column>', "public string \$draft = '';");
        $input = at('column1.textInput1');
        $input->calls = [];

        // Three keys before the loop delivers the first.
        foreach (['a', 'ab', 'abc'] as $text) {
            $input->nativeTyped($text);
        }
        deliverPosted();

        expect($view->draft)->toBe('abc')
            ->and($input->value())->toBe('abc')
            ->and($input->hooks())->not->toContain('applyValue');
    });

    it('shows what a handler made of the text, and clears with the state', function (): void {
        $view = sharp('<Column><TextInput :value="$draft" @change="upper" @submit="send" /></Column>', <<<'P'
            public string $draft = '';
            public array $sent = [];
            public function upper(string $text): void { $this->draft = strtoupper($text); }
            public function send(string $text): void { $this->sent[] = $text; $this->draft = ''; }
            P);
        $input = at('column1.textInput1');

        $input->nativeTyped('hi');
        deliverPosted();
        expect($input->value())->toBe('HI');

        $input->nativeSubmitted('HI');
        deliverPosted();
        expect($view->sent)->toBe(['HI'])->and($input->value())->toBe('');
    });

    it('is built again only when it becomes secret', function (): void {
        $secret = false;
        $hint = 'a';
        scripted(function () use (&$secret, &$hint) {
            return Column::make()->with(TextInput::make(placeholder: $hint, secret: $secret));
        });
        $input = at('column1.textInput1');

        $hint = 'b';
        rerender();
        expect(at('column1.textInput1'))->toBe($input)->and($input->placeholder())->toBe('b');

        $secret = true;
        rerender();
        expect($input->isRemoved())->toBeTrue();
    });
});

describe('the combo box', function (): void {
    it('posts typing, a choice and a submit', function (): void {
        $box = screen()->comboBox('city', '', ['Lima', 'Oslo']);
        test()->loop->posted = [];

        $box->nativeTyped('Li');
        $box->nativeChoseOption(1);
        $box->nativeSubmitted('Oslo');

        expect(posted())->toBe(['TextChanged', 'SelectionChanged', 'TextSubmitted'])
            ->and(test()->loop->posted[1]->index)->toBe(1)
            ->and($box->value())->toBe('Oslo');
    });

    it('shows completions only with focus, and clears a hidden list', function (): void {
        $box = screen()->comboBox('city');
        $box->setCompletions(['Lima']);
        expect($box->completions())->toBe([]);

        $box->focused = true;
        $box->setCompletions(['Lima']);
        expect($box->completions())->toBe(['Lima']);

        $box->nativeHid();
        expect($box->completions())->toBe([]);

        $box->setCompletions(['Lima'])->setValue('x');
        expect($box->completions())->toBe([]);
    });

    it('hides them when the window sees focus move, with no signal from the toolkit', function (): void {
        $box = screen()->comboBox('city');
        $window = Presentation::driver()->window();
        $box->focused = true;
        $window->settleFocus();
        $box->setCompletions(['Lima']);

        $box->focused = false;
        $window->settleFocus();

        expect($box->completions())->toBe([]);
    });

    it('posts a chosen completion as index -1', function (): void {
        $box = screen()->comboBox('city');
        $box->focused = true;
        $box->setCompletions(['Lima', 'Lisbon']);
        test()->loop->posted = [];

        $box->nativeChoseCompletion(1);

        expect(test()->loop->posted[0]->index)->toBe(-1)
            ->and($box->value())->toBe('Lisbon')
            ->and($box->completions())->toBe([]);
    });

    it('completes from what is typed, and stays closed after a choice', function (): void {
        sharp('<Column><ComboBox :options="self::CITIES" :value="$city" :completions="$this->matches()" @change="fn ($v) => $this->city = $v" /></Column>', <<<'P'
            private const array CITIES = ['Lima', 'Lisbon', 'Oslo'];
            public string $city = '';
            public function matches(): array
            {
                return $this->city === '' || in_array($this->city, self::CITIES, true)
                    ? []
                    : array_values(array_filter(self::CITIES, fn ($c) => str_starts_with($c, $this->city)));
            }
            P);
        $box = at('column1.comboBox1');
        $box->focused = true;

        $box->nativeTyped('Li');
        deliverPosted();
        expect($box->completions())->toBe(['Lima', 'Lisbon']);

        $box->nativeChoseCompletion(1);
        deliverPosted();
        expect(Presentation::driver()->view()->component->city)->toBe('Lisbon')->and($box->completions())->toBe([]);
    });

    it('refuses options that are not a list of strings', function (): void {
        expect(fn () => screen()->comboBox('city', '', [1]))->toThrow(ToolkitException::class, 'list of strings');
    });
});

describe('the date picker', function (): void {
    it('holds a day, posts a pick once and nothing from code', function (): void {
        $picker = screen()->datepicker('day', new DateTimeImmutable('2026-11-21 23:59:00+09:00'));
        test()->loop->posted = [];
        expect($picker->date()->format('Y-m-d H:i'))->toBe('2026-11-21 00:00');

        $picker->setDate(new DateTimeImmutable('2026-01-02'));
        expect(posted())->toBe([]);

        $picker->nativePicked(2026, 3, 4);
        $picker->nativePicked(2026, 3, 4);
        expect(posted())->toBe(['DateChanged'])->and($picker->date()->format('Y-m-d'))->toBe('2026-03-04');
    });

    it('shows the computer\'s today, not the application zone\'s', function (): void {
        $was = [date_default_timezone_get(), getenv('TZ')];
        date_default_timezone_set('Pacific/Kiritimati');    // UTC+14
        putenv('TZ=Pacific/Pago_Pago');                     // UTC-11: never the same day
        try {
            $picker = screen()->datepicker('day');
            $shown = (new ReflectionMethod($picker, 'shownDay'))->invoke($picker);

            expect($picker->date())->toBeNull()
                ->and($shown->format('Y-m-d'))->toBe((new DateTimeImmutable('now', new DateTimeZone('Pacific/Pago_Pago')))->format('Y-m-d'));
        } finally {
            date_default_timezone_set($was[0]);
            putenv($was[1] === false ? 'TZ' : "TZ={$was[1]}");
        }
    });

    it('refuses a year some toolkit cannot show', function (): void {
        expect(fn () => screen()->datepicker('day', new DateTimeImmutable('0050-01-01')))->toThrow(ToolkitException::class, 'years 100 to 9999');
    });

    it('is not set again by a fresh object of the same day', function (): void {
        scripted(fn () => Column::make()->with(Datepicker::make(new DateTimeImmutable('2026-11-21 '.random_int(10, 20).':00'))));
        $picker = at('column1.datepicker1');
        $picker->calls = [];

        rerender();
        expect($picker->hooks())->toBe([]);
    });

    it('gives the picked day to its handler', function (): void {
        $view = sharp('<Column><Datepicker date="2026-11-21" @change="picked" /></Column>', "use DateTimeImmutable;\npublic string \$day = ''; public function picked(DateTimeImmutable \$d): void { \$this->day = \$d->format('Y-m-d'); }");

        at('column1.datepicker1')->nativePicked(2026, 12, 25);
        deliverPosted();
        expect($view->day)->toBe('2026-12-25');
    });

    it('refuses a date that is no day', function (): void {
        expect(fn () => sharp('<Datepicker date="2026-02-30"/>'))->toThrow(SharpCompileError::class, 'not a day');
    });
});

describe('the link', function (): void {
    it('changes in place', function (): void {
        $url = 'https://a.test';
        scripted(function () use (&$url) {
            return Column::make()->with(Link::make('Docs', $url));
        });
        $link = at('column1.link1');
        $link->calls = [];

        $url = 'mailto:a@b.test';
        rerender();
        expect($link->hooks())->toBe(['applyUrl'])->and($link->url())->toBe('mailto:a@b.test');
    });

    it('refuses a URL with no scheme', function (): void {
        expect(fn () => screen()->link('docs', 'Docs', 'example.com'))->toThrow(ToolkitException::class, 'scheme');
    });

    it('takes its text from a tag\'s content', function (): void {
        sharp('<Column><Link url="https://a.test">Read the docs</Link></Column>');
        expect(at('column1.link1')->text())->toBe('Read the docs');
    });
});
