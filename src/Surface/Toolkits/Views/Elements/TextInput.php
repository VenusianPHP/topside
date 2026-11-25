<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\NutsAndBolts\Color;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\TKTextInput;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\Mail\Views\TextChanged;
use Surface\Contracts\Toolkits\Mail\Views\TextSubmitted;

/**
 * One line the user types in. Give it the text it shows, and a handler that keeps it:
 *
 *     TextInput::make(value: $this->draft, placeholder: 'Your name')
 *         ->onChange(fn (string $text) => $this->draft = $text)
 *
 * The value is written to the field when the state's value changes, so clearing the state
 * clears the field. search builds the toolkit's search field; it cannot also be secret.
 */
class TextInput extends Element
{
    final public function __construct(
        protected readonly string $value = '',
        protected readonly ?string $placeholder = null,
        protected readonly bool $secret = false,
        protected readonly bool $search = false,
    ) {
        if ($secret && $search) {
            throw new ViewException('A TextInput is secret or search, not both.');
        }
    }

    public static function make(string $value = '', ?string $placeholder = null, bool $secret = false, bool $search = false): static
    {
        return new static($value, $placeholder, $secret, $search);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->textInput($name, $this->value, $this->placeholder, $this->secret, $this->search);
    }

    public function kind(): string
    {
        return 'textInput';
    }

    /**
     * Run $handler as the user types, with the text and the TextChanged mail.
     *
     * @param Closure(string, TextChanged): mixed $handler
     */
    public function onChange(Closure $handler): static
    {
        // The text the field holds now, not the mail's: mail queues, and an older text given
        // back to the field would undo what was typed since.
        return $this->onLive('input', static fn (TextChanged $mail, TKTextInput $input) => $handler($input->value(), $mail));
    }

    /**
     * Run $handler on Return, with the text and the TextSubmitted mail.
     *
     * @param Closure(string, TextSubmitted): mixed $handler
     */
    public function onSubmit(Closure $handler): static
    {
        return $this->on('submit', static fn (TextSubmitted $mail) => $handler($mail->value, $mail));
    }

    /**
     * No frame and no field background of the toolkit's own.
     */
    public function borderless(bool $borderless = true): static
    {
        return $this->set('borderless', $borderless);
    }

    /**
     * The text can be selected and copied, not changed.
     */
    public function readOnly(bool $readOnly = true): static
    {
        return $this->set('readOnly', $readOnly);
    }

    /**
     * The most characters the user can type; null is no limit.
     */
    public function maxLength(?int $length): static
    {
        return $this->set('maxLength', $length);
    }

    public function textAlign(TextAlignment $alignment): static
    {
        return $this->set('textAlign', $alignment);
    }

    public function font(FontSpec $font): static
    {
        return $this->set('font', $font);
    }

    public function color(?Color $color): static
    {
        return $this->set('color', $color);
    }

    protected function shape(): array
    {
        return ['font' => $this->has('font'), 'secret' => $this->secret, 'search' => $this->search];
    }

    protected function tracked(): array
    {
        return ['value', 'placeholder'];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'value' => function (TKTextInput $p, string $v): void {
                // Writing what it holds would move the cursor.
                if ($p->value() !== $v) {
                    $p->setValue($v);
                }
            },
            'placeholder' => fn (TKTextInput $p, ?string $v) => $p->setPlaceholder($v),
            'textAlign' => fn (TKTextInput $p, TextAlignment $v) => $p->setAlignment($v),
            'font' => fn (TKTextInput $p, FontSpec $v) => $p->setFont($v),
            'color' => fn (TKTextInput $p, ?Color $v) => $p->setTextColor($v),
            'borderless' => fn (TKTextInput $p, bool $v) => $p->setBorderless($v),
            'readOnly' => fn (TKTextInput $p, bool $v) => $p->setReadOnly($v),
            'maxLength' => fn (TKTextInput $p, ?int $v) => $p->setMaxLength($v),
        ];
    }

    protected function defaults(): array
    {
        return parent::defaults() + [
            'textAlign' => TextAlignment::LEFT,
            'color' => null,
            'borderless' => false,
            'readOnly' => false,
            'maxLength' => null,
        ];
    }

    protected function valueOf(string $prop): mixed
    {
        return match ($prop) {
            'value' => $this->value,
            'placeholder' => $this->placeholder,
            default => parent::valueOf($prop),
        };
    }

    protected function initial(string $prop): mixed
    {
        return in_array($prop, ['value', 'placeholder'], true) ? $this->valueOf($prop) : parent::initial($prop);
    }
}
