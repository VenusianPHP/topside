<?php

namespace Surface\Contracts\Rendering\Raycasting;

/**
 * What rays hit, as bytes: 22-byte records, little-endian, distance (float64),
 * along (float64), x (uint16), y (uint16), wall (uint8), face (uint8). A ray
 * that hit nothing is 22 zero bytes. Raycaster::fan() answers one record a
 * column, left to right.
 */
class Hits
{
    public const int BYTES = 22;

    private function __construct() {}

    public static function pack(Hit $hit): string
    {
        return pack('eevvCC', $hit->distance, $hit->along, $hit->x, $hit->y, $hit->wall, $hit->face->value);
    }

    public static function count(string $hits): int
    {
        if (strlen($hits) % self::BYTES !== 0) {
            throw new RaycastException('A hit list is a whole number of '.self::BYTES.'-byte records, got '.strlen($hits).' bytes.');
        }

        return intdiv(strlen($hits), self::BYTES);
    }

    /** Record $index of $hits; null when that ray hit nothing. */
    public static function at(string $hits, int $index): ?Hit
    {
        if ($index < 0 || $index >= self::count($hits)) {
            throw new RaycastException("A hit list of ".self::count($hits)." records has no record {$index}.");
        }
        $record = unpack('edistance/ealong/vx/vy/Cwall/Cface', $hits, $index * self::BYTES);

        return $record['wall'] === 0 ? null : new Hit($record['distance'], $record['along'], $record['x'], $record['y'], $record['wall'], Face::from($record['face']));
    }

    /** @return list<Hit|null> One a record, in order; null where the ray hit nothing. */
    public static function unpack(string $hits): array
    {
        $out = [];
        for ($i = 0, $n = self::count($hits); $i < $n; $i++) {
            $out[] = self::at($hits, $i);
        }

        return $out;
    }
}
