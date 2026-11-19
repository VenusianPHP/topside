<?php

namespace Surface\Rendering\Concerns;

/**
 * A frame provider's or drawer's settings: the given array when it has the stored config's shape, the stored config
 * where it does not. Keys the store does not have are ignored, keys the given array lacks are filled from the store,
 * and an empty array is the stored config whole. Nothing throws.
 */
trait ReadsBackendConfig
{
    /**
     * @param string $key where the stored config lives, as config() reads it
     */
    protected function backendConfig(array $given, string $key): array
    {
        $stored = config($key, []);
        $stored = is_array($stored) ? $stored : [];

        return $given === [] ? $stored : self::shapedLike($given, $stored);
    }

    private static function shapedLike(array $given, array $stored): array
    {
        $shaped = [];
        foreach ($stored as $key => $value) {
            if (! array_key_exists($key, $given) || is_array($given[$key]) !== is_array($value)) {
                $shaped[$key] = $value;
            } elseif (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $shaped[$key] = self::shapedLike($given[$key], $value);
            } else {
                $shaped[$key] = $given[$key];
            }
        }

        return $shaped;
    }
}
