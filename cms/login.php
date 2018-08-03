<?php

namespace alexandria\cms;

use alexandria\cms;

/**
 * Class user
 *
 * @package alexandria\cms
 */
class login
{
    protected $logged_in;
    protected $username_field;
    protected $password_field;


    public function __construct(array $args = null)
    {
        session_start();

        if (empty($_SESSION['__a_login_username']))
        {
            $this->username_field = cms::security()->string(16);
            $this->password_field = cms::security()->string(16);

            $_SESSION['__a_login_username'] = $this->username_field;
            $_SESSION['__a_login_password'] = $this->password_field;
        }
        else
        {
            $this->username_field = $_SESSION['__a_login_username'];
            $this->password_field = $_SESSION['__a_login_password'];
        }

        $this->logged_in = $this->autologin();
    }

    public function autologin(): bool
    {
        if (isset($_POST[$this->username_field]))
        {
            $hash = $this->check($_POST[$this->username_field], $_POST[$this->password_field]);
            if (!empty($hash))
            {
                $_SESSION['__a_login_username_ex'] = $_POST[$this->username_field];
                $_SESSION['__a_login_password_ex'] = $hash;
                return true;
            }
        }

        elseif (isset($_SESSION['__a_login_username_ex']))
        {
            $hash = $this->check($_SESSION['__a_login_username_ex'], $_SESSION['__a_login_password_ex']);
            if (!empty($hash))
            {
                return true;
            }
        }

        $this->logout();
        return false;
    }

    public function logout()
    {
        unset($_SESSION['__a_login_username_ex']);
        unset($_SESSION['__a_login_password_ex']);
    }

    public function check(string $username, string $password)
    {
        $info            = password_get_info($password);
        $password_hashed = !empty($info['algo']);

        $user = cms::user()->by_name($username);
        if (empty($user))
        {
            return false;
        }

        // users names must match (case-insensitive)
        if (strtolower($username) !== strtolower($user->login))
        {
            return false;
        }

        // on hash to hash comparison return bool
        if ($password_hashed && $password === $user->password
            || password_verify($password, $user->password))
        {
            cms::user()->_fill($user->_data());
            return $user->password;
        }

        return false;
    }

    public function get_username_field(): string
    {
        return $this->username_field;
    }

    public function get_password_field(): string
    {
        return $this->password_field;
    }

    public function accepted(): bool
    {
        return $this->logged_in;
    }
}
