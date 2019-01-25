<?php

namespace alexandria\lib;

/**
 * Docker API class stub
 */
class Docker
{
    protected        $api;
    protected static $nodes;

    public function __construct(string $api)
    {
        $this->api = rtrim($api, '/');
    }

    /**
     * Pass the raw HTTP GET query to the Docker API
     *
     * @param  string $query   Query to call
     * @param  array  $filters Additional Docker API filters as an array
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    public function get(string $query, array $filters = [])
    {
        $query = trim($query, '/');
        if (!empty($filters))
        {
            $filters = http_build_query(['filters' => json_encode($filters)]);
            $query   .= strpos($query, '?') === false ? '?' . $filters : '&' . $filters;
        }

        error_clear_last();
        $response = @file_get_contents($this->api . '/' . $query);
        $e        = error_get_last();
        if (!empty($e))
        {
            throw new \Exception("Can't do the [GET] call: {$e['message']}");
        }

        $response = json_decode($response);
        return $response;
    }

    /**
     * Pass the raw HTTP POST query to the Docker API
     *
     * @param  string $query Query to call
     * @param  array  $args  Arguments to pass as an POST body
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    public function post(string $query, array $args = [])
    {
        $query   = trim($query, '/');
        $options = [
            'http' => [
                'method'  => 'POST',
                'content' => json_encode($args, JSON_UNESCAPED_SLASHES),
                'header'  => "Content-Type: application/json\r\n",
            ],
        ];

        error_clear_last();
        $context  = stream_context_create($options);
        $response = @file_get_contents($this->api . '/' . $query, false, $context);
        $e        = error_get_last();
        if (!empty($e))
        {
            throw new \Exception("Can't do the [POST] call: {$e['message']}");
        }

        $response = json_decode($response);
        return $response;
    }

    /**
     * Pass the raw HTTP DELETE query to the Docker API
     *
     * @param  string $query Query to call
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    protected function delete(string $query)
    {
        $query = trim($query, '/');

        error_clear_last();
        $context  = stream_context_create(['http' => ['method' => 'DELETE']]);
        $response = @file_get_contents($this->api . '/' . $query, false, $context);
        $e        = error_get_last();
        if (!empty($e))
        {
            throw new \Exception("Can't do the [DELETE] call: {$e['message']}");
        }

        $response = json_decode($response);
        return $response;
    }

    /**
     * Return Docker info
     *
     * @throws \Exception
     */
    public function info()
    {
        return $this->get('/info');
    }

    /**
     * Return info about service / all services
     *
     * @param  string|null $id Service name to get (if specified)
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    public function services(string $id = null)
    {
        if (!empty($id))
        {
            $id = '/' . $id;
        }

        return $this->get('/services' . $id);
    }

    /**
     * Return tasks for the service
     *
     * @param  string       $serviceId   Service name
     * @param  bool|boolean $out_stopped If true, return stopped tasks too
     *
     * @return array
     * @throws \Exception
     */
    public function serviceTasks(string $serviceId, bool $out_stopped = false)
    {
        $tasks = $this->get('/tasks', [
            'service' => [$serviceId],
        ]);

        $ret = [];
        foreach ($tasks as $task)
        {
            if ($out_stopped || $task->Status->State === 'running')
            {
                $ret [] = $task;
            }
        }

        return $ret;
    }

    /**
     * Create service
     *
     * @param array $data Service specification, must conform to Docker Remote API structure
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    public function serviceCreate(array $data)
    {
        return $this->post('/services/create', $data);
    }

    /**
     * Destroy service
     *
     * @param string $id Id or name of the Service
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    public function serviceDelete($id)
    {
        return $this->delete('/services/' . $id);
    }


    /**
     * Return container "top" command results
     *
     * @param string $containerId Id or name of container
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    public function containerTop(string $containerId)
    {
        return $this->get("/containers/{$containerId}/top?ps_args=aux");
    }

    /**
     * Return running containers list
     *
     * @throws \Exception
     */
    public function containers()
    {
        return $this->get("/containers/json");
    }

    /**
     * Execute simple command inside container
     *
     * @param string $containerId Id or name of container
     * @param string $cmd         Command to execute
     *
     * @return bool
     * @throws \Exception
     */
    public function containerExec(string $containerId, string $cmd)
    {
        $cmd  = preg_split('/\s+/', $cmd);
        $exec = $this->post("/containers/{$containerId}/exec", [
            "AttachStdin"  => false,
            "AttachStdout" => true,
            "AttachStderr" => true,
            'Cmd'          => $cmd,
        ]);

        if (empty($exec->Id))
        {
            return false;
        }

        $this->post('/exec/' . $exec->Id . '/start', [
            'Detach' => false,
            'Tty'    => true,
        ]);

        $status = $this->get('/exec/' . $exec->Id . '/json');
        return $status->ExitCode === 0;
    }

    /**
     * Execute command on all containers (tasks) running service
     *
     * @param string $serviceId Name of service
     * @param string $cmd       Command to execute
     *
     * @return array|bool
     * @throws \Exception
     */
    public function serviceExec(string $serviceId, string $cmd)
    {
        $tasks = $this->serviceTasks($serviceId);
        if (!is_array($tasks))
        {
            return false;
        }

        $ret = [];
        foreach ($tasks as $task)
        {
            try
            {
                $node           = @$this->node($task->NodeID);
                $container      = $task->Status->ContainerStatus->ContainerID;
                $ret[$task->ID] = $node->containerExec($container, $cmd);
            }
            catch (\Throwable $e)
            {
                $ret[$task->ID] = "Can not exec command: {$e->getMessage()}";
            }
        }

        return $ret;
    }

    /**
     * Execute comman on all service running containers for a specified stack
     *
     * @param  string $stackId   Stack Name
     * @param  string $serviceId Service Name in a stack
     * @param  string $cmd       Command to execute
     *
     * @return array|bool
     * @throws \Exception
     */
    public function stackExec(string $stackId, string $serviceId, string $cmd)
    {
        return $this->serviceExec("{$stackId}_{$serviceId}", $cmd);
    }

    /**
     * Removes service
     *
     * @param  string $serviceId Service name to remove
     *
     * @throws \Exception
     */
    public function serviceRemove(string $serviceId)
    {
        $this->delete("/services/{$serviceId}");
    }

    /**
     * Return instance of Docker for the specified node
     *
     * @param string $nodeId Id of the node
     *
     * @return Docker
     * @throws \Exception
     */
    public function node($nodeId)
    {
        $node = $this->get('/nodes/' . $nodeId);
        if (empty($node->Description->Hostname))
        {
            throw new \Exception('Can not found node hostname');
        }

        $host = "http://{$node->Status->Addr}:2375";
        $ret  = new self($host);
        return $ret;
    }

    /**
     * Get list of all cluster nodes
     *
     * @throws \Exception
     */
    public function nodeList()
    {
        return $this->get('/nodes');
    }
}
