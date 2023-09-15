<?php

/**
 * Helper Class for manipulating and/or checking strings
 */
class StringHelper
{
    /**
     * Make a string camelCase
     *
     * @param string $string
     * @param bool $ucFirst
     * @param array $noStrip
     * @return string
     */
    public static function camelCase(string $string, bool $ucFirst = false, array $noStrip = []): string
    {
        // First we make sure all is lower case
        $string = strtolower(string: $string);

        // non-alpha and non-numeric characters become spaces
        $string = preg_replace(
            pattern: '/[^a-z0-9' . implode(array: $noStrip) . ']+/i',
            replacement: ' ',
            subject: $string
        );
        $string = trim($string);

        // uppercase the first character of each word
        $string = ucwords(string: $string);
        $string = str_replace(search: ' ', replace: '', subject: $string);

        if ($ucFirst === false) {
            $string = lcfirst(string: $string);
        }

        return $string;
    }
}
