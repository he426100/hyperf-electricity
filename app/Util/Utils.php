<?php

declare(strict_types=1);

namespace App\Util;

class Utils
{
    /**
     *
     * @param string $string
     * @return bool
     */
    public static function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     *
     * @param string $spacedHexString
     * @return string The hexadecimal
     */
    public static function removeHexSpaces(string $spacedHexString): string
    {
        return str_replace(' ', '', $spacedHexString);
    }

    /**
     * 
     * @param string $hexString
     * @return string
     */
    public static function decodeHexString(string $hexString): string
    {
        return hex2bin(self::removeHexSpaces($hexString));
    }

    /**
     *
     * @param string $condensedHexString
     * @return string
     */
    public static function formatHexWithSpaces(string $condensedHexString): string
    {
        return implode(' ', str_split(self::removeHexSpaces($condensedHexString), 2));
    }
}
