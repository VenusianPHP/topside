<?php

declare(strict_types=1);

use Surface\Rendering\CommandRecorders\CCommandRecorder;
use Surface\Rendering\CommandRecorders\PHPCommandRecorder;
use Surface\Rendering\Managers\DrawCommandManager;
use Voyager\Contracts\Core\FrameworkCore;

/*
 * An application names its recorder in rendering.recorder.default and gets it from 'rendering.recorders'.
 * The wrong driver behind a name is a frame recorded seven times slower, or an extension that is never used.
 * These tests need ext-comrec: without it they fail, they do not skip.
 */

it('is registered as rendering.recorders, a singleton, with the manager class as its alias', function (): void {
    $app = renderingApp($this->createStub(FrameworkCore::class));

    expect($app->make('rendering.recorders'))->toBeInstanceOf(DrawCommandManager::class)
        ->and($app->make('rendering.recorders'))->toBe($app->make('rendering.recorders'))
        ->and($app->make(DrawCommandManager::class))->toBe($app->make('rendering.recorders'));
});

it('answers the recorder the config names', function (array $config, string $class): void {
    expect(renderingApp($this->createStub(FrameworkCore::class), $config)->make('rendering.recorders')->driver())->toBeInstanceOf($class);
})->with([
    'php' => [['rendering.recorder.default' => 'php'], PHPCommandRecorder::class],
    'c' => [['rendering.recorder.default' => 'c'], CCommandRecorder::class],
    'auto, with ext-comrec loaded' => [['rendering.recorder.default' => 'auto'], CCommandRecorder::class],
    'nothing set is auto' => [[], CCommandRecorder::class],
]);

it('answers a recorder by name, whatever the config names', function (): void {
    $recorders = renderingApp($this->createStub(FrameworkCore::class), ['rendering.recorder.default' => 'c'])->make('rendering.recorders');

    expect($recorders->driver('php'))->toBeInstanceOf(PHPCommandRecorder::class)
        ->and($recorders->driver('c'))->toBeInstanceOf(CCommandRecorder::class);
});

it('answers the same recorder each time: it holds the last frame', function (): void {
    $recorders = renderingApp($this->createStub(FrameworkCore::class))->make('rendering.recorders');

    expect($recorders->driver())->toBe($recorders->driver());
});

it('refuses a recorder nobody registered, by its name', function (): void {
    expect(fn () => renderingApp($this->createStub(FrameworkCore::class), ['rendering.recorder.default' => 'gpu'])->make('rendering.recorders')->driver())
        ->toThrow(InvalidArgumentException::class, 'gpu');
});

it('without ext-comrec, makes auto the PHP recorder and refuses c by name', function (): void {
    // A second PHP with no ini file loads no extension: the one way to take ext-comrec away.
    $autoload = (new ReflectionClass(Composer\Autoload\ClassLoader::class))->getFileName();
    $code = 'require '.var_export(dirname($autoload, 2).'/autoload.php', true).';'
        .'echo extension_loaded("comrec") ? "loaded" : "absent", "|";'
        .'echo (new Surface\Rendering\DrawCommandManager(new class extends Voyager\Vessel\ControlPanel {'
        .' public function make($abstract, array $parameters = []): mixed { return new class { public function get($k, $d = null) { return $d; } }; } }))->driver()::class, "|";'
        .'try { new Surface\Rendering\CommandRecorders\CCommandRecorder(); echo "made"; } catch (RuntimeException $e) { echo $e->getMessage(); }';

    $output = shell_exec(escapeshellarg(PHP_BINARY).' -n -r '.escapeshellarg($code).' 2>&1');

    expect($output)->toStartWith('absent|'.PHPCommandRecorder::class.'|ext-comrec is not loaded');
});
