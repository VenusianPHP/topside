<?php

declare(strict_types=1);

use Surface\Toolkits\Presentation;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Views\Component;
use Surface\Contracts\Toolkits\Dialogs\Alert;
use Surface\Contracts\Toolkits\Dialogs\SaveRequest;
use Surface\Contracts\Toolkits\Mail\Windows\SaveAnswered;
use Surface\Core\Actions\Bootstrap\LoadSignals;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

/*
| A save panel: the toolkit's own, modal to the window, shown without waiting; its answer (a
| path, or null for a cancel) comes back as mail and runs the handler the view gave with it.
*/
beforeEach(fn () => openViewWindow());

function saveWindow(): Venusian\Topside\Tests\Fakes\FakeWindow
{
    return Presentation::driver()->window();
}

/** @return list<SaveAnswered> the answers the loop holds, taken from it */
function savesPosted(): array
{
    [$posted, test()->loop->posted] = [test()->loop->posted, []];

    return array_values(array_filter($posted, static fn (object $mail): bool => $mail instanceof SaveAnswered));
}

/** A view whose button asks where to save, as a picture's download does. */
function savingView(): ScriptedView
{
    return scripted(fn (ScriptedView $v) => Column::make()->with(
        Label::make($v->state['said'] ?? 'Not saved'),
        Button::make('Download')->onClick(fn () => $v->saveFile(
            new SaveRequest('Nebula.jpg', '/Users/me/Pictures', 'Save Picture'),
            function (SaveAnswered $answer) use ($v): void {
                $v->state['said'] = $answer->path ?? 'Cancelled';
            },
        )),
    ));
}

describe('the window', function (): void {
    it('shows the panel without waiting, and posts nothing until it is answered', function (): void {
        $request = new SaveRequest('Nebula.jpg');
        $id = saveWindow()->saveFile($request);

        expect(saveWindow()->shown_save)->toBe($request)
            ->and(saveWindow()->isSaving())->toBeTrue()
            ->and($id)->toMatch('/^[0-9a-f]{16}$/')
            ->and(savesPosted())->toBe([]);
    });

    it('posts the chosen path under the panel\'s id, once, and null for a cancel', function (): void {
        $id = saveWindow()->saveFile(new SaveRequest('Nebula.jpg'));
        saveWindow()->nativeSaveAnswered('/tmp/Nebula.jpg');
        saveWindow()->nativeSaveAnswered('/tmp/again.jpg');
        [$chosen] = savesPosted();

        $next = saveWindow()->saveFile(new SaveRequest());
        saveWindow()->nativeSaveAnswered(null);
        [$cancelled] = savesPosted();

        expect([$chosen->id, $chosen->path, $chosen->name()])->toBe([$id, '/tmp/Nebula.jpg', 'window.save-answered.'.saveWindow()->name()])
            ->and([$cancelled->id, $cancelled->path])->toBe([$next, null])
            ->and(saveWindow()->isSaving())->toBeFalse();
    });

    it('asks one thing at a time: no panel over an alert, no alert over a panel', function (): void {
        saveWindow()->saveFile(new SaveRequest());

        expect(fn () => saveWindow()->saveFile(new SaveRequest()))->toThrow(ToolkitException::class, 'already shows a save panel: it can show one at a time.')
            ->and(fn () => saveWindow()->alert(new Alert('Sure?')))->toThrow(ToolkitException::class, 'already shows a save panel');

        saveWindow()->nativeSaveAnswered(null);
        saveWindow()->alert(new Alert('Sure?'));

        expect(fn () => saveWindow()->saveFile(new SaveRequest()))->toThrow(ToolkitException::class, 'already shows an alert');
    });

    it('takes its panel down and answers it null when it closes first', function (): void {
        $window = saveWindow();
        $id = $window->saveFile(new SaveRequest());
        $window->close();
        $answers = savesPosted();

        expect($window->steps)->toContain('closeSave')
            ->and(array_map(static fn (SaveAnswered $a): array => [$a->id, $a->path], $answers))->toBe([[$id, null]])
            ->and(fn () => $window->saveFile(new SaveRequest()))->toThrow(ToolkitException::class, 'is closed: it can show no save panel.');
    });
});

describe('a view', function (): void {
    it('runs the handler with the path, then shows the view again', function (): void {
        savingView();
        click(at('column1.button2'));
        $asked = saveWindow()->shown_save;

        saveWindow()->nativeSaveAnswered('/Users/me/Pictures/Nebula.jpg');
        foreach (savesPosted() as $mail) {
            Presentation::driver()->answered($mail);
        }

        expect([$asked?->name, $asked?->directory, $asked?->title])->toBe(['Nebula.jpg', '/Users/me/Pictures', 'Save Picture'])
            ->and(at('column1.label1')->text())->toBe('/Users/me/Pictures/Nebula.jpg');
    });

    it('reaches the view through signals, as the loop dispatches mail', function (): void {
        (new LoadSignals())->bootstrap($this->app);
        savingView();
        click(at('column1.button2'));

        saveWindow()->nativeSaveAnswered(null);
        foreach (savesPosted() as $mail) {
            $this->app['signals']->dispatch($mail);
        }

        expect(at('column1.label1')->text())->toBe('Cancelled');
    });

    it('drops the handler of a view taken off the screen', function (): void {
        $runs = new ArrayObject();
        $view = scripted(fn () => Label::make('Old'));
        $id = $view->saveFile(new SaveRequest(), fn () => $runs->append('old'));

        scripted(fn () => Label::make('New'));
        Presentation::driver()->answered(new SaveAnswered(saveWindow()->name(), $id, '/tmp/x'));

        expect($runs)->toHaveCount(0);
    });

    it('refuses a panel from a component not mounted in a window', function (): void {
        $loose = new class extends Component {
            public function render(): Surface\Toolkits\Views\Elements\Element
            {
                return Label::make('loose');
            }
        };

        expect(fn () => $loose->saveFile(new SaveRequest(), fn () => null))
            ->toThrow(ViewException::class, 'is not mounted in a window, so it can show no save panel.');
    });
});
