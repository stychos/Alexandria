<?php

namespace alexandria\lib;

class response
{
    public function __construct()
    {
    }

    public function set_header(string $header, string $value)
    {
        if (!headers_sent()) {
            header("{$header}: {$value}", true);
            return true;
        }

        return false;
    }

    public function write(string $message): string
    {
        if (stripos('CLI', PHP_SAPI) !== false) {
            $message = preg_replace('#<br\s*/?>#', "\n", $message);
            $message = strip_tags($message);
        }

        return $message;
    }

    public function write_json($object = null, $pretty = true): string
    {
        $out = json_encode($object, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($out === false) {
            throw new \Exception("Can not encode output: ".json_last_error_msg());
        }

        $this->set_header('Content-Type', 'application/json');
        return "{$out}\n";
    }
}
