<?php

namespace Surface\VelvetGE\Raycasting;

use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\Rendering\Raycasting\Raycaster;

/**
 * The casting in PHP. The arithmetic is ext-raycast's, step for step (its src/core.c): IEEE
 * double with + - * /, abs and floor in the same order, so both answer the same bytes.
 */
class PHPLandRaycaster extends Raycaster
{
    /** The tallest wall line a view draws, before it is cut to the view: ext-raycast's RAYCAST_MAX_LINE. */
    private const float MAX_LINE = 16777216.0;

    public function driver(): string
    {
        return 'native';
    }

    protected function ray(float $x, float $y, float $dx, float $dy, float $reach): string
    {
        return self::record($this->march($x, $y, $dx, $dy, $reach));
    }

    protected function rays(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $columns, float $reach): string
    {
        $out = '';
        for ($column = 0; $column < $columns; $column++) {
            $across = (2 * $column + 1) / $columns - 1.0;
            $out .= self::record($this->march($x, $y, $dirX + $planeX * $across, $dirY + $planeY * $across, $reach));
        }

        return $out;
    }

    protected function picture(float $x, float $y, float $dirX, float $dirY, float $planeX, float $planeY, int $width, int $height, array $walls, int $ceiling, int $floor, int $shade, float $reach): string
    {
        $horizon = intdiv($height, 2);

        // The ceiling above the horizon, the floor from it down.
        $out = str_repeat(pack('N', $ceiling), $width * $horizon).str_repeat(pack('N', $floor), $width * ($height - $horizon));

        /** @var array<int, string> $dimmed each texture's bytes with the shade applied, made the first time a shaded face shows it */
        $dimmed = [];

        for ($column = 0; $column < $width; $column++) {
            $across = (2 * $column + 1) / $width - 1.0;
            $hit = $this->march($x, $y, $dirX + $planeX * $across, $dirY + $planeY * $across, $reach);
            if (is_null($hit)) {
                continue;
            }
            [$distance, $along, , , $wall, $face] = $hit;
            $entry = $walls[$wall] ?? throw RaycastException::noWall($wall);

            // The wall's line is as tall as the view at distance 1, centred on the horizon.
            $tall = $distance > 0.0 ? $height / $distance : self::MAX_LINE;
            if (! ($tall < self::MAX_LINE)) {
                $tall = self::MAX_LINE;
            }
            $line = (int) $tall;
            $start = $horizon - intdiv($line, 2);
            $from = max($start, 0);
            $to = min($start + $line, $height);

            // The north and south faces are darkened: what tells one wall from the next at a corner.
            $dim = $face >= 2 ? $shade : 255;

            if (is_int($entry)) {
                $red = chr(intdiv((($entry >> 24) & 0xFF) * $dim, 255));
                $green = chr(intdiv((($entry >> 16) & 0xFF) * $dim, 255));
                $blue = chr(intdiv((($entry >> 8) & 0xFF) * $dim, 255));
                $alpha = chr($entry & 0xFF);
                for ($row = $from; $row < $to; $row++) {
                    $at = ($row * $width + $column) * 4;
                    $out[$at] = $red;
                    $out[$at + 1] = $green;
                    $out[$at + 2] = $blue;
                    $out[$at + 3] = $alpha;
                }

                continue;
            }

            [$pixels, $texture_width, $texture_height] = $entry;
            if ($dim !== 255) {
                $pixels = $dimmed[$wall] ??= self::dim($pixels, $dim);
            }

            // The texture's column under the hit, read left to right as the face is seen from outside.
            $texel_x = (int) ($along * $texture_width);
            if ($texel_x > $texture_width - 1) {
                $texel_x = $texture_width - 1;
            }
            if ($face === 1 || $face === 2) {
                $texel_x = $texture_width - 1 - $texel_x;
            }

            for ($row = $from; $row < $to; $row++) {
                $texel = (intdiv(($row - $start) * $texture_height, $line) * $texture_width + $texel_x) * 4;
                $at = ($row * $width + $column) * 4;
                $out[$at] = $pixels[$texel];
                $out[$at + 1] = $pixels[$texel + 1];
                $out[$at + 2] = $pixels[$texel + 2];
                $out[$at + 3] = $pixels[$texel + 3];
            }
        }

        return $out;
    }

    /**
     * The grid walk (Amanatides and Woo): from the eye's cell, step to whichever cell boundary the
     * ray reaches first, x or y, and look at the cell entered. The cell the ray starts in is never
     * looked at. A tie steps in y.
     *
     * @return array{float, float, int, int, int, int}|null [distance, along, x, y, wall, face]; null for a miss.
     */
    private function march(float $x, float $y, float $dx, float $dy, float $reach): ?array
    {
        $width = $this->grid->width;
        $height = $this->grid->height;
        if (! ($x >= 0.0) || ! ($y >= 0.0) || ! ($x < $width) || ! ($y < $height)) {
            return null;
        }

        // How far along the ray one whole cell is, in x and in y.
        $delta_x = $dx == 0.0 ? INF : abs(1.0 / $dx);
        $delta_y = $dy == 0.0 ? INF : abs(1.0 / $dy);
        if (is_infinite($delta_x) && is_infinite($delta_y)) {
            return null;
        }

        $cell_x = (int) floor($x);
        $cell_y = (int) floor($y);
        $step_x = $dx < 0.0 ? -1 : 1;
        $step_y = $dy < 0.0 ? -1 : 1;

        // How far along the ray the next boundary is, in x and in y.
        $next_x = INF;
        $next_y = INF;
        if (! is_infinite($delta_x)) {
            $next_x = $dx < 0.0 ? ($x - $cell_x) * $delta_x : ($cell_x + 1 - $x) * $delta_x;
        }
        if (! is_infinite($delta_y)) {
            $next_y = $dy < 0.0 ? ($y - $cell_y) * $delta_y : ($cell_y + 1 - $y) * $delta_y;
        }

        $cells = $this->grid->cells;
        for (;;) {
            if ($next_x < $next_y) {
                $distance = $next_x;
                $next_x += $delta_x;
                $cell_x += $step_x;
                $crossed_x = true;
            } else {
                $distance = $next_y;
                $next_y += $delta_y;
                $cell_y += $step_y;
                $crossed_x = false;
            }

            if ($distance > $reach || $cell_x < 0 || $cell_y < 0 || $cell_x >= $width || $cell_y >= $height) {
                return null;
            }

            $wall = ord($cells[$cell_y * $width + $cell_x]);
            if ($wall === 0) {
                continue;
            }

            $where = $crossed_x ? $y + $distance * $dy : $x + $distance * $dx;

            return [
                $distance,
                $where - floor($where),
                $cell_x,
                $cell_y,
                $wall,
                $crossed_x ? ($step_x > 0 ? 0 : 1) : ($step_y > 0 ? 2 : 3),
            ];
        }
    }

    /** A hit as its 22 bytes; null is a miss. */
    private static function record(?array $hit): string
    {
        return is_null($hit) ? str_repeat("\0", 22) : pack('eevvCC', ...$hit);
    }

    /** RGBA8 bytes with red, green and blue multiplied by $dim / 255; alpha as it is. */
    private static function dim(string $pixels, int $dim): string
    {
        for ($i = 0, $n = strlen($pixels); $i < $n; $i += 4) {
            $pixels[$i] = chr(intdiv(ord($pixels[$i]) * $dim, 255));
            $pixels[$i + 1] = chr(intdiv(ord($pixels[$i + 1]) * $dim, 255));
            $pixels[$i + 2] = chr(intdiv(ord($pixels[$i + 2]) * $dim, 255));
        }

        return $pixels;
    }
}
