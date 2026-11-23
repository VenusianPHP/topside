<?php

namespace Venusian\Topside\Tests\Fakes\Views;

use Closure;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\Elements\Element;

/**
 * A view whose render() a test writes: $describe gets the view and returns its elements, so
 * one test can change what the view looks like between renders through $state.
 */
class ScriptedView extends Component
{
    /** @var (Closure(ScriptedView): Element)|null */
    public static ?Closure $describe = null;

    /** @var array<string, mixed> */
    public array $state = [];

    public string $title = '';

    public function render(): Element
    {
        return (self::$describe)($this);
    }
}
