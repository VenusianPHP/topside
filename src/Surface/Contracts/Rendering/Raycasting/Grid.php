<?php

namespace Surface\Contracts\Rendering\Raycasting;

/**
 * The world rays are cast through: width x height cells, one byte each, rows
 * top first. 0 is open; 1..255 is a wall and the value is its id, which a
 * Scenery gives a colour or a texture. x grows east and y grows south, in
 * cells: (0.5, 0.5) is the middle of the first cell.
 */
readonly class Grid
{
    /** The most cells a side holds: a hit names its cell in 16 bits. */
    public const int MAX_SIDE = 65535;

    /**
     * @param string $cells width * height bytes, rows top first.
     * @throws RaycastException When a side is outside 1..MAX_SIDE, or $cells is another length.
     */
    public function __construct(
        public int $width,
        public int $height,
        public string $cells,
    ) {
        if ($width < 1 || $width > self::MAX_SIDE) {
            throw RaycastException::side("A grid's width", $width, self::MAX_SIDE);
        }
        if ($height < 1 || $height > self::MAX_SIDE) {
            throw RaycastException::side("A grid's height", $height, self::MAX_SIDE);
        }
        if (strlen($cells) !== $width * $height) {
            throw new RaycastException("A grid {$width}x{$height} holds ".($width * $height).' cells, one byte each; got '.strlen($cells).' bytes.');
        }
    }

    /**
     * A grid from its rows, top first, each a list of cell values 0..255:
     *
     *     Grid::of([
     *         [1, 1, 1],
     *         [1, 0, 1],
     *         [1, 1, 1],
     *     ]);
     *
     * @param list<list<int>> $rows
     * @throws RaycastException When there is no row, the rows differ in length, or a value is outside 0..255.
     */
    public static function of(array $rows): self
    {
        if ($rows === [] || ! array_is_list($rows)) {
            throw new RaycastException('A grid is a list of rows, at least one.');
        }
        $width = is_array($rows[0]) ? count($rows[0]) : 0;
        $cells = '';
        foreach ($rows as $y => $row) {
            if (! is_array($row) || ! array_is_list($row) || count($row) !== $width) {
                throw new RaycastException("Row {$y} of a grid is not a list of {$width} cells, as row 0 is.");
            }
            foreach ($row as $x => $value) {
                if (! is_int($value) || $value < 0 || $value > 255) {
                    throw new RaycastException("Cell ({$x}, {$y}) of a grid is not a whole number 0..255.");
                }
                $cells .= chr($value);
            }
        }

        return new self($width, count($rows), $cells);
    }

    /** The cell's value: 0 for open, and for anywhere outside the grid. */
    public function wall(int $x, int $y): int
    {
        return $x < 0 || $y < 0 || $x >= $this->width || $y >= $this->height ? 0 : ord($this->cells[$y * $this->width + $x]);
    }
}
