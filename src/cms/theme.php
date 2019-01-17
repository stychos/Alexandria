<?php

/**
 * Alexandria Engine.
 * Theme class stub.
 */

namespace alexandria\cms;

use alexandria\cms;
use alexandria\lib\form;

/**
 * Theme CMS Library
 */
class theme
{
    protected $name;
    protected $root;
    protected $wroot;
    protected $theme;
    protected $themes;
    protected $wtheme;
    protected $wthemes;
    protected $appdir;
    protected $started;
    protected $entry;
    protected $vars;

    public function __construct($args = null)
    {
        if (is_array($args))
        {
            $args = (object) $args;
        }

        $proto = ((@$_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off') || @$_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
        $wroot = (@$_SERVER['HTTP_HOST']) ? $proto.'://'.rtrim($_SERVER['HTTP_HOST'], '/') : '';

        $sub   = preg_replace("#(/index\.php)?{$_SERVER['PATH_INFO']}#", '', $_SERVER['PHP_SELF']);
        $wroot .= $sub;

        $this->root  = $args->root ?? dirname($_SERVER['SCRIPT_FILENAME']);
        $this->wroot = $args->wroot ?? $wroot;

        $this->themes  = $args->themes ?? "{$this->root}/themes";
        $this->wthemes = $args->wthemes ?? "{$this->wroot}/themes";

        $this->name   = $args->name ?? 'default';
        $this->theme  = $args->theme ?? "{$this->themes}/{$this->name}";
        $this->wtheme = $args->wtheme ?? "{$this->wthemes}/{$this->name}";
        $this->entry  = $args->entry ?? 'theme.php';
        $this->appdir = $args->appdir ?? '';

        $this->vars = $args->vars ?? [];
        $this->prepare();
    }

    public function get(): string
    {
        return $this->name;
    }

    public function set(string $theme)
    {
        $this->name   = $theme;
        $this->theme  = "{$this->themes}/{$this->name}";
        $this->wtheme = "{$this->wthemes}/{$this->name}";
    }

    public function prepare()
    {
        if (!$this->started)
        {
            ob_start();
            $this->started = true;
        }
    }

    public function add_vars(array $vars)
    {
        foreach ($vars as $name => $value)
        {
            $this->vars[$name] = $value;
        };
    }

    public function show_form(string $form, array $vars = [])
    {
        echo $this->load_form($form, $vars);
    }

    public function load_form(string $form, array $vars = [])
    {
        foreach ($this->vars as $name => $var)
        {
            if (!isset($vars[$name]))
            {
                $vars[$name] = $var;
            }
        }

        if ($this->appdir && strpos($form, '/') !== false)
        {
            $tform    = preg_replace('~/([^/]+)$~', '/forms/$1', $form);
            $filename = "{$this->appdir}/{$tform}.php";
            if (file_exists($filename))
            {
                return form::load($filename, $vars);
            }
        }

        foreach (
            [
                "{$this->theme}/forms",
                "{$this->root}/forms",
            ] as $dir
        )
        {
            $filename = "{$dir}/{$form}.php";
            if (file_exists($filename))
            {
                return form::load($filename, $vars);
            }
        }

        throw new \RuntimeException("Can not load form: {$form}");
    }

    public function render(): string
    {
        if (!file_exists("{$this->theme}/{$this->entry}"))
        {
            throw new \RuntimeException("Theme file not found: {$this->theme}/{$this->entry}");
        }

        if (!$this->started)
        {
            throw new \RuntimeException("Theme buffer was not started, nothing to render.");
        }

        $buffer        = ob_get_clean();
        $this->started = false;

        ob_start();
        extract($this->vars, EXTR_SKIP);
        require "{$this->theme}/{$this->entry}";
        $render = ob_get_clean();

        if (strpos($render, '{content}') === false)
        {
            throw new \RuntimeException('Theme has no {content} section.');
        }

        $render = str_replace('{content}', $buffer, $render);
        $render = str_replace('{theme}', $this->wtheme, $render);
        $render = str_replace('{root}', $this->wroot, $render);

        // Assigned variables
        $matches = null;
        preg_match_all('/\{\$(?<var>[a-zA-Z_\x7f-\xff][\->\[\]\'"a-zA-Z0-9_\x7f-\xff]*)\}/', $render, $matches);
        if (!empty($matches['var']))
        {
            foreach ($matches['var'] as $index => $name)
            {
                $src = $matches[0][$index];
                $val = $this->vars[$name] ?? null;
                if (!empty($val) && is_scalar($val))
                {
                    $render = str_replace($src, htmlspecialchars($val), $render);
                }
                else
                {
                    $var    = preg_replace('~\{\$(.+)\}~', '$1', $src);
                    $esrc   = addslashes($src);
                    $cmd    = "return \${$var} ?? '{$esrc}';";
                    $val    = eval($cmd);
                    $render = str_replace($src, htmlspecialchars($val), $render);
                }
            }
        }

        // Ret-Evaluations
        preg_match_all('/<=\s*(?<eval>.+)=>/u', $render, $matches);
        if (!empty($matches[0]))
        {
            foreach ($matches['eval'] as $index => $cmd)
            {
                $src    = $matches[0][$index];
                $val    = eval("return {$cmd};");
                $render = str_replace($src, htmlspecialchars($val), $render);
            }
        }

        // Widgets
        $matches = null;
        preg_match_all('/\{\{[a-zA-Z_\x7f-\xff][\\a-zA-Z0-9_\x7f-\xff\/]*\}\}/', $render, $matches);
        if (!empty($matches[0]))
        {
            foreach ($matches[0] as $name)
            {
                $var = preg_replace('/^\{\{(.+)\}\}$/', '\1', $name);
                $var = str_replace('/', '\\', $var);

                foreach ([$var, "{$var}\\controller"] as $controller)
                {
                    if (method_exists($controller, '__widget'))
                    {
                        $to     = $controller::__widget();
                        $to     = str_replace('{theme}', $this->wtheme, $to);
                        $to     = str_replace('{root}', $this->wroot, $to);
                        $render = str_replace($name, $to, $render);
                        break;
                    }
                }
            }
        }

        // Config variables
        $matches = null;
        preg_match_all('/\{\[[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\]\}/', $render, $matches);
        if (!empty($matches[0]))
        {
            foreach ($matches[0] as $name)
            {
                $var    = preg_replace('/^\{\[(.+)\]\}$/', '\1', $name);
                $val    = cms::module('config')->$var;
                $render = !empty($val) && is_scalar($val) ? str_replace($name, $val, $render) : str_replace($name, '', $render);
            }
        }

        return $render;
    }

    public function __destruct()
    {
        if ($this->started)
        {
            ob_end_flush();
        }
    }
}
