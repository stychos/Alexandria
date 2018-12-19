<?php

namespace alexandria\lib;

/**
 * Request library
 */
class request
{
    protected $method;
    protected $headers;
    protected $data;

    public function __construct()
    {
        if (stripos(PHP_SAPI, 'CLI') !== false) {
            $this->method = 'CLI';
            $this->data   = $_SERVER['argv'];
        } else {
            $this->method = strtoupper($_SERVER['REQUEST_METHOD']);
            $this->data   = $this->method === 'GET'
                ? $_SERVER['QUERY_STRING']
                : file_get_contents('php://input');
        }
    }

    /**
     * Return used method for the current request.
     */
    public function get_method(): string
    {
        return $this->method;
    }


    /**
     * Return received headers for the current request.
     */
    public function get_headers(): array
    {
        if (empty($this->headers)) {
            $this->headers = $this->parse_headers();
        }

        return $this->headers;
    }


    /**
     * Return header value for the current request (if assigned).
     *
     * @param   string $name Header name to retreive
     *
     * @return  string|false        Returns header value or false.
     */
    public function get_header(string $name)
    {
        if (empty($this->headers)) {
            $this->headers = $this->parse_headers();
        }

        return $this->headers[$name] ?? false;
    }


    /**
     * Return received data for the current request.
     */
    public function get_data(): string
    {
        return $this->data;
    }


    /**
     * Return received data as JSON decoded object.
     */
    public function get_json()
    {
        return json_decode($this->data);
    }


    public function is_cli(): bool
    {
        return $this->method === 'CLI';
    }
    public function is_options(): bool
    {
        return $this->method === 'OPTIONS';
    }
    public function is_get(): bool
    {
        return $this->method === 'GET';
    }
    public function is_head(): bool
    {
        return $this->method === 'HEAD';
    }
    public function is_post(): bool
    {
        return $this->method === 'POST';
    }
    public function is_put(): bool
    {
        return $this->method === 'PUT';
    }
    public function is_delete(): bool
    {
        return $this->method === 'DELETE';
    }
    public function is_trace(): bool
    {
        return $this->method === 'TRACE';
    }
    public function is_connect(): bool
    {
        return $this->method === 'CONNECT';
    }

    /**
     * Call $callback if the request of $method HTTP method.
     *
     * @param $method    string    Method on which callback is called.
     * @param $callback  callable  Callback to call on needed conditions.
     *
     * @return           mixed     Callback execution result.
     */
    public function on(string $method, callable $callback)
    {
        if (strtoupper($method) == $this->method) {
            return $callback($this->data);
        }
    }


    /**
     * Get all HTTP header key/values as an associative array for the current request.
     *
     * @return array [string] The HTTP header key/value pairs.
     */
    protected function parse_headers()
    {
        $headers     = [];
        $copy_server = [
            'CONTENT_TYPE'   => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5'    => 'Content-Md5',
        ];

        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $key = substr($key, 5);
                if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
                    $key = str_replace('_', ' ', $key);
                    $key = strtolower($key);
                    $key = ucwords($key);
                    $key = str_replace(' ', '-', $key);

                    $headers[$key] = $value;
                }
            } elseif (isset($copy_server[$key])) {
                $headers[$copy_server[$key]] = $value;
            }
        }

        if (!isset($headers['Authorization'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
                $basic_pass               = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
                $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass);
            } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }

        return $headers;
    }
}
