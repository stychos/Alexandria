<?php

namespace alexandria\lib;

/**
 * Class for executing shell commands on the remote servers
 */
class ssh
{
    const auth_keys = 0;
    const auth_pass = 1;

    private $host;
    private $port;
    private $user;
    private $pass;
    private $pubkey;
    private $privkey;

    private $method;
    private $connection;

    public function __construct(array $args)
    {
        $this->host    = $args['host'] ?? null;
        $this->port    = $args['port'] ?? 22;
        $this->user    = $args['user'] ?? 'root';
        $this->pass    = $args['pass'] ?? null;
        $this->pubkey  = $args['pubkey'] ?? null;
        $this->privkey = $args['privkey'] ?? null;
        $this->method  = $args['method'] ?? self::auth_pass;
    }

    public function connect()
    {
        $this->connection = ssh2_connect($this->host, $this->port);
        if (!$this->connection)
        {
            throw new \Exception('Can not initialize connection to server');
        }

        if ($this->method == self::auth_pass)
        {
            $res = ssh2_auth_password($this->connection, $this->user, $this->pass);
            if (!$res)
            {
                throw new \Exception('Password autentication rejected by the server');
            }
        }
        else
        {
            $res = ssh2_auth_pubkey_file($this->connection, $this->user, $this->pubkey, $this->privkey, $this->pass);
            if (!$res)
            {
                throw new \Exception('Autentication rejected by the server');
            }
        }
    }

    public function exec($cmd)
    {
        if (!$this->connection)
        {
            $this->connect();
        }

        $stream = ssh2_exec($this->connection, $cmd);
        if (!$stream)
        {
            throw new \Exception('SSH command failed');
        }

        $stderr = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        stream_set_blocking($stream, true);
        stream_set_blocking($stderr, true);
        $data   = rtrim(stream_get_contents($stream));
        $errors = rtrim(stream_get_contents($stderr));
        fclose($stream);
        fclose($stderr);

        return empty($data) ? $errors : $data;
    }

    public function disconnect()
    {
        if ($this->connection)
        {
            $this->exec('exit');
        }

        $this->connection = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
