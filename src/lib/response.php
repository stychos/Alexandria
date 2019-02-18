<?php

namespace alexandria\lib;

class response
{
    protected $_code;
    protected $_code_msg;
    protected $_buffer;
    protected $_headers;

    public function __construct()
    {
        $this->clear();
    }

    public function clear()
    {
        $this->_code     = 200;
        $this->_code_msg = null;
        $this->_headers  = [];
        $this->_buffer   = '';
    }

    /**
     * @param string $text
     *
     * @return self
     */
    public function append(string $text = null): self
    {
        $this->_buffer .= $text;
        return $this;
    }

    /**
     * @param int         $code
     * @param string|null $message
     *
     * @return self
     */
    public function code(int $code, string $message = null): self
    {
        $this->_code     = $code;
        $this->_code_msg = $message;
        return $this;
    }

    /**
     * @param string $header
     * @param string $value
     *
     * @return self
     */
    public function header(string $header, string $value): self
    {
        $this->_headers[$header] = $value;
        return $this;
    }

    /**
     * @param bool $send_headers
     * @param bool $cli_transform
     *
     * @return string
     */
    public function flush(bool $send_headers = true, bool $cli_transform = true): string
    {
        if ($send_headers && !headers_sent())
        {
            foreach ($this->_headers as $header => $value)
            {
                header("{$header}: {$value}", true);
            }
        }

        $output = $this->_buffer;
        if ($cli_transform && stripos(PHP_SAPI, 'CLI') !== false)
        {
            $output = preg_replace('~<br\s*/?>~', PHP_EOL, $output);
            $output = strip_tags($output);
        }

        $this->_buffer = '';
        return $output;
    }

    /**
     * @param mixed $object
     * @param int   $options
     *
     * @return string
     */
    public function json($object, int $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT): string
    {
        $this->_buffer = json_encode($object, $options) . PHP_EOL;
        $this->header('Content-Type', 'application/json');

        return $this->flush();
    }
}
