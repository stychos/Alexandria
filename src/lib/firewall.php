<?php

namespace alexandria\lib;

/**
 * Class firewall
 * IPv4-based firewall
 *
 * @package alexandria\lib
 */
class firewall
{
    public function __construct($config)
    {
        // it's don't works on cli
        if (PHP_SAPI === 'cli')
        {
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        if ($ip === '::1')
        {
            $ip = '127.0.0.1';
        }

        foreach (['allow', 'deny'] as $action)
        {
            foreach ($config[$action] as $rule)
            {
                // rewrite aliases
                if ($rule === 'all')
                {
                    $rule = '0.0.0.0/0';
                }
                elseif ($rule === 'localhost')
                {
                    $rule = '127.0.0.1';
                }

                // no ip-related symbols found, get ip by hostname
                if (preg_match('~[^0-9\./]~', $rule))
                {
                    $tmp = gethostbyname($rule);
                    if ($tmp === $rule)
                    {
                        // skip named rule if can't resolve
                        continue;
                    }

                    $rule = $tmp;
                }

                // add netmask to the ip-rules if needed
                if (!preg_match('#/\d+$#', $rule))
                {
                    $rule .= '/32';
                }

                if ($this->_match($ip, $rule))
                {
                    switch ($action)
                    {
                        case 'allow':
                            return;
                        break;
                        case 'deny':
                            http_response_code(403);
                            echo "Access denied for {$_SERVER['REMOTE_ADDR']}.\n";
                            die(1);
                        break;
                    }
                }
            }
        }
    }

    /**
     * ip to mask matching (cidr formats)
     */
    protected function _match($ip, $mask)
    {
        list($subnet, $bits) = @explode('/', $mask);
        $ip     = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask   = -1 << (32 - $bits);
        $subnet &= $mask; // in case the supplied subnet wasn't correctly aligned
        return ($ip & $mask) == $subnet;
    }
}
