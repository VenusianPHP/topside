<?php

namespace Venusian\Topside\Tests\Fakes\Views;

use Closure;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\OnMenu;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Element;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Contracts\Toolkits\Mail\Menus\MenuMail;

/**
 * A view whose menu() a test writes, and which shows its count.
 */
class MenuView extends Component
{
    /** @var (Closure(MenuView, MenuBar): MenuBar)|null */
    public static ?Closure $menus = null;

    /** @var (Closure(MenuView): mixed)|null */
    public static ?Closure $onButton = null;

    public int $count = 0;

    public bool $on = false;

    public int $renders = 0;

    public int $asked = 0;

    /** @var list<string> */
    public array $heard = [];

    public function render(): Element
    {
        $this->renders++;

        return Column::make()->with(
            Label::make("count {$this->count}")->name('count'),
            Button::make('Press')->name('press')->onClick(function (): void {
                $this->count++;
                if (! is_null(static::$onButton)) {
                    (static::$onButton)($this);
                }
            }),
        );
    }

    public function menu(MenuBar $bar): MenuBar
    {
        $this->asked++;

        return is_null(static::$menus) ? $bar : (static::$menus)($this, $bar);
    }

    #[OnMenu('file.save')]
    public function saved(MenuMail $mail): void
    {
        $this->count++;
        $this->heard[] = $mail->name();
    }

    #[OnMenu('file.open')]
    #[OnMenu('file.new')]
    protected function opened(): void
    {
        $this->heard[] = 'opened';
    }
}
