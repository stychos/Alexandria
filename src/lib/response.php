<?php /** @noinspection PhpMethodMayBeStaticInspection */

namespace alexandria\lib;

class response
{
    public function __construct()
    {
    }

    /**
     * @param string $header
     * @param string $value
     *
     * @return bool
     */
    public function set_header(string $header, string $value)
    {
        if (!headers_sent())
        {
            header("{$header}: {$value}", true);
            return true;
        }

        return false;
    }

    /**
     * @param string $message
     *
     * @return string
     */
    public function write(string $message): string
    {
        if (stripos('CLI', PHP_SAPI) !== false)
        {
            $message = preg_replace('~<br\s*/?>~', PHP_EOL, $message);
            $message = strip_tags($message);
        }

        return $message;
    }

    /**
     * @param mixed $object
     * @param int $json_options
     *
     * @return string
     * @throws \Exception
     */
    public function write_json($object, int $json_options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT): string
    {
        $out = json_encode($object, $json_options);
        if ($out === false)
        {
            throw new \Exception("Can not encode output: ".json_last_error_msg());
        }

        $this->set_header('Content-Type', 'application/json');
        return "{$out}\n";
    }
}
