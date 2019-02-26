<?php

namespace alexandria\lib;

class str
{
    /**
     * Adds 's' suffix for the nouns by the number
     *
     * @param  int   $number [description]
     * @param string $noun
     *
     * @return string [type]                   [description]
     */
    public static function numstr(int $number, string $noun, string $multi = null)
    {
        $postfix = $multi ? $multi : $noun.'s';
        if ($number % 10 != 1 && $number != 11)
        {
            $ret = "{$number} {$postfix}";
        }
        else
        {
            $ret = "{$number} {$noun}";
        }

        return $ret;
    }
}
