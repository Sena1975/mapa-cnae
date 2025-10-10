<?php

namespace App\Support;


class GeoBounds
{
    public static function contains(array $bounds, float $lat, float $lng): bool
    {
        // bounds: [ 'n'=>..., 's'=>..., 'e'=>..., 'w'=>... ]
        return $lat <= $bounds['n'] && $lat >= $bounds['s'] && $lng <= $bounds['e'] && $lng >= $bounds['w'];
    }
}
