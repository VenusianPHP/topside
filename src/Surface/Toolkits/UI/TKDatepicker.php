<?php

namespace Surface\Toolkits\UI;

use Throwable;
use DateTimeZone;
use DateTimeImmutable;
use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\DateChanged;
use Surface\Contracts\Toolkits\UI\TKDatepicker as PrimitiveContract;
use Surface\Toolkits\UI\Concerns\IgnoresItsOwnWrites;

/**
 * A calendar day: no time and no zone. The date held is that day's midnight in PHP's default
 * timezone, whatever time or zone it was given in. With none, the toolkit's picker shows
 * the computer's today, and date() stays null until the user picks or code sets one.
 */
abstract class TKDatepicker extends TKPrimitive implements PrimitiveContract
{
    use IgnoresItsOwnWrites;

    /** The years every toolkit can show. */
    public const int FIRST_YEAR = 100;

    public const int LAST_YEAR = 9999;

    private ?DateTimeImmutable $date;

    /**
     * @throws ToolkitException When the year is outside 100 to 9999; before any native exists.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        ?DateTimeImmutable $date = null,
    ) {
        $this->date = self::day($date, $name);

        parent::__construct($name, $window, $parent, $placement);
    }

    public function date(): ?DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * @throws ToolkitException When removed, or the year is outside 100 to 9999.
     */
    public function setDate(?DateTimeImmutable $date): static
    {
        $this->guardLive();

        $this->date = self::day($date, $this->path());
        $this->write(fn () => $this->applyDate($this->date));

        return $this;
    }

    /**
     * The user picked a day. Nothing for the day already held, or one out of range.
     */
    protected function picked(int $year, int $month, int $day): void
    {
        if (! $this->hearsUser() || $year < self::FIRST_YEAR || $year > self::LAST_YEAR || ! checkdate($month, $day, $year)) {
            return;
        }

        $picked = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day));
        if ($this->date?->format('Y-m-d') === $picked->format('Y-m-d')) {
            return;
        }

        $this->date = $picked;
        $this->window()->post(new DateChanged($this->window()->name(), $this->path(), $this->uuid(), $picked));
    }

    /**
     * What the native shows: the date, or today.
     */
    protected function shownDay(): DateTimeImmutable
    {
        if (! is_null($this->date)) {
            return $this->date;
        }

        $today = (new DateTimeImmutable('now', self::computerZone()))->format('Y-m-d');

        return new DateTimeImmutable("{$today} 00:00:00");
    }

    /**
     * Where the computer is. PHP's default zone is the application's, often UTC, where it is
     * tomorrow for an evening in the Americas.
     */
    private static function computerZone(): DateTimeZone
    {
        $link = is_link('/etc/localtime') ? (string) readlink('/etc/localtime') : '';
        $name = getenv('TZ') ?: (preg_match('#zoneinfo[^/]*/(.+)$#', $link, $found) ? $found[1] : '');

        try {
            return new DateTimeZone($name !== '' ? $name : date_default_timezone_get());
        } catch (Throwable) {
            return new DateTimeZone(date_default_timezone_get());
        }
    }

    /**
     * @throws ToolkitException
     */
    private static function day(?DateTimeImmutable $date, string $where): ?DateTimeImmutable
    {
        if (is_null($date)) {
            return null;
        }

        $year = (int) $date->format('Y');
        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
            throw new ToolkitException('A date picker shows years '.self::FIRST_YEAR.' to '.self::LAST_YEAR.", got {$year} for '{$where}'.");
        }

        return new DateTimeImmutable($date->format('Y-m-d').' 00:00:00');
    }

    /**
     * Show $date; null shows today.
     */
    abstract protected function applyDate(?DateTimeImmutable $date): void;
}
