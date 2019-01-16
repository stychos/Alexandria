<?php

namespace alexandria\lib;

class str
{
    /**
     * Adds 's' suffix for the nouns by the number
     *
     * @param  int    $number         [description]
     * @param  string $single_variant [description]
     * @param  [type] $multiple_variant [description]
     * @return [type]                   [description]
     */
    public static function numstr(int $number, string $noun)
    {
        $ret = "{$number} {$noun}";
        if ($number % 10 != 1)
        {
            $ret .= 's';
        }

        return $ret;
    }
}
