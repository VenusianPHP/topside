<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use DateTimeImmutable;
use Surface\Contracts\Toolkits\UI\TKDatepicker;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\DateChanged;

/**
 * A day to pick. Only the day counts, so a date built fresh on every render changes nothing.
 *
 *     Datepicker::make($this->born)->onChange(fn (DateTimeImmutable $day) => $this->born = $day)
 */
class Datepicker extends Element
{
    final public function __construct(protected readonly ?DateTimeImmutable $date = null) {}

    public static function make(?DateTimeImmutable $date = null): static
    {
        return new static($date);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->datepicker($name, $this->date);
    }

    public function kind(): string
    {
        return 'datepicker';
    }

    /**
     * Run $handler when the user picks, with the day and the DateChanged mail.
     *
     * @param Closure(DateTimeImmutable, DateChanged): mixed $handler
     */
    public function onChange(Closure $handler): static
    {
        return $this->on('change', static fn (DateChanged $mail) => $handler($mail->date, $mail));
    }

    protected function tracked(): array
    {
        return ['date'];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'date' => function (TKDatepicker $p, ?string $day): void {
                if ($p->date()?->format('Y-m-d') !== $day) {
                    $p->setDate(is_null($day) ? null : new DateTimeImmutable($day));
                }
            },
        ];
    }

    protected function valueOf(string $prop): mixed
    {
        return $prop === 'date' ? $this->date?->format('Y-m-d') : parent::valueOf($prop);
    }

    protected function initial(string $prop): mixed
    {
        return $prop === 'date' ? $this->valueOf($prop) : parent::initial($prop);
    }
}
