<?php

namespace App\Support;

final class Money
{
    public static function format(int $minor): string
    {
        return sprintf('%d.%02d', intdiv($minor, 100), abs($minor % 100));
    }
}
