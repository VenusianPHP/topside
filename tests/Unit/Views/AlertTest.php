<?php

declare(strict_types=1);

use Surface\Toolkits\View;
use Surface\Toolkits\Presentation;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Views\Component;
use Surface\Contracts\Toolkits\Dialogs\Alert;
use Surface\Contracts\Toolkits\Dialogs\AlertStyle;
use Surface\Contracts\Toolkits\Mail\Windows\AlertAnswered;
use Surface\Core\Actions\Bootstrap\LoadSignals;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

beforeEach(fn () => openViewWindow());

/** The fake window the view is on. */
function alertWindow(): Venusian\Topside\Tests\Fakes\FakeWindow
{
    return Presentation::driver()->window();
}

/** Deliver every alert answer the loop holds to the presentation driver. */
function answerPosted(): void
{
    [$posted, test()->loop->posted] = [test()->loop->posted, []];
    foreach ($posted as $mail) {
        if ($mail instanceof AlertAnswered) {
            Presentation::driver()->answered($mail);
        }
    }
}

/** A view whose button asks before it clears, as a settings page asks before it forgets a key. */
function confirmingView(): ScriptedView
{
    return scripted(fn (ScriptedView $v) => Column::make()->with(
        Label::make($v->state['said'] ?? 'Kept'),
        Button::make('Clear…')->onClick(fn () => $v->alert(
            new Alert('Clear it?', 'It goes back to the start.', ['Clear', 'Cancel'], default: 1, cancel: 1, style: AlertStyle::Warning),
            function (AlertAnswered $answer) use ($v): void {
                $v->state['said'] = $answer->button === 0 ? 'Cleared' : 'Kept, asked';
            },
        )),
    ));
}

it('shows the alert on the view\'s window when a handler asks', function (): void {
    confirmingView();

    click(at('column1.button2'));

    expect(alertWindow()->shown_alert?->message)->toBe('Clear it?')
        ->and(alertWindow()->shown_alert?->detail)->toBe('It goes back to the start.')
        ->and(alertWindow()->shown_alert?->style)->toBe(AlertStyle::Warning)
        ->and(at('column1.label1')->text())->toBe('Kept');
});

it('runs the handler with the answer, then shows the view again', function (): void {
    confirmingView();
    click(at('column1.button2'));

    alertWindow()->nativeAlertAnswered(0);
    answerPosted();

    expect(at('column1.label1')->text())->toBe('Cleared');
});

it('gives the handler the cancel index for Esc', function (): void {
    confirmingView();
    click(at('column1.button2'));

    alertWindow()->nativeAlertAnswered(1);
    answerPosted();

    expect(at('column1.label1')->text())->toBe('Kept, asked');
});

it('reaches the view through signals, as the loop dispatches mail', function (): void {
    (new LoadSignals())->bootstrap($this->app);
    confirmingView();
    click(at('column1.button2'));

    alertWindow()->nativeAlertAnswered(0);
    [$posted, $this->loop->posted] = [$this->loop->posted, []];
    foreach ($posted as $mail) {
        $this->app['signals']->dispatch($mail);
    }

    expect(at('column1.label1')->text())->toBe('Cleared');
});

it('runs the handler once, and not for an alert it did not ask for', function (): void {
    $runs = new ArrayObject();
    $view = scripted(fn () => Label::make('Here'));
    $id = $view->alert(new Alert('Once?'), fn (AlertAnswered $a) => $runs->append($a->button));

    Presentation::driver()->answered(new AlertAnswered(alertWindow()->name(), 'someone-else', 0));
    Presentation::driver()->answered(new AlertAnswered('another-window', $id, 0));
    Presentation::driver()->answered(new AlertAnswered(alertWindow()->name(), $id, 0));
    Presentation::driver()->answered(new AlertAnswered(alertWindow()->name(), $id, 0));

    expect($runs->getArrayCopy())->toBe([0]);
});

it('drops the handler of a view taken off the screen', function (): void {
    $runs = new ArrayObject();
    $view = scripted(fn () => Label::make('Old'));
    $id = $view->alert(new Alert('Still there?'), fn () => $runs->append('old'));

    scripted(fn () => Label::make('New'));
    Presentation::driver()->answered(new AlertAnswered(alertWindow()->name(), $id, 0));

    expect($runs)->toHaveCount(0);
});

it('refuses an alert from a component not mounted in a window', function (): void {
    $loose = new class extends Component {
        public function render(): Surface\Toolkits\Views\Elements\Element
        {
            return Label::make('loose');
        }
    };

    expect(fn () => $loose->alert(new Alert('Anyone?'), fn () => null))
        ->toThrow(ViewException::class, 'is not mounted in a window, so it can show no alert.');
});
