<?php

/**
 * Alexandria Engine.
 * Theme class stub.
 */

namespace alexandria\cms;

use alexandria\cms;

/**
 * Theme CMS Library
 */
class theme extends cms
{
    protected $name;
    protected $root;
    protected $wroot;
    protected $theme;
    protected $themes;
    protected $wtheme;
    protected $wthemes;
    protected $appdir;
    protected $formsdirs;
    protected $started;
    protected $entry;

    public function __construct($args = null)
    {
        if (is_array($args)) {
            $args = (object) $args;
        }

        $proto = @$_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off' || @$_SERVER['SERVER_PORT'] == 443
            ? 'https'
            : 'http';

        $wroot = @$_SERVER['HTTP_HOST']
            ? $proto.'://'.rtrim($_SERVER['HTTP_HOST'], '/')
            : '';

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

        $this->appdir    = $args->appdir ?? '';
        $this->formsdirs = $args->formsdirs ?? [
                "{$this->theme}/forms",
                "{$this->root}/forms",
            ];

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
        if (!$this->started) {
            ob_start();
            $this->started = true;
        }
    }

    public function add_forms_path(string $path)
    {
        $this->appdirs [] = $path;
    }

    public function show_form(string $form, array $vars = [])
    {
        echo $this->load_form($form, $vars);
    }

    public function load_form(string $form, array $vars = [])
    {
        if ($this->appdir) {
            $form = preg_replace('~/([^/]+)$~', '/forms/$1', $form);
            $filename = "{$this->appdir}/{$form}.php";
            if (file_exists($filename)) {
                return \alexandria\lib\form::load($filename, $vars);
            }
        }

        foreach ($this->formsdirs as $dir) {
            $filename = "{$dir}/{$form}.php";
            if (file_exists($filename)) {
                return \alexandria\lib\form::load($filename, $vars);
            }
        }

        throw new \RuntimeException("Can not load form: {$form}");
    }

    public function render(): string
    {
        if (!file_exists("{$this->theme}/{$this->entry}")) {
            throw new \RuntimeException("Theme file not found: {$this->theme}/{$this->entry}");
        }

        if (!$this->started) {
            throw new \RuntimeException("Theme buffer was not started, nothing to render.");
        }

        $buffer        = ob_get_clean();
        $this->started = false;

        try {
            $user = cms::module('user\au1th')::login();
        }
        catch (\throwable $e) {
            $user = false;
        }

        ob_start();
        require "{$this->theme}/{$this->entry}";
        $render = ob_get_clean();

        if (strpos($render, '{content}') === false) {
            throw new \RuntimeException('Theme has no {content} section.');
        }

        $render = str_replace('{content}', $buffer, $render);
        $render = str_replace('{theme}', $this->wtheme, $render);
        $render = str_replace('{root}', $this->wroot, $render);

        // Widgets
        $matches = null;
        preg_match_all('/\{\{[a-zA-Z_\x7f-\xff][\\a-zA-Z0-9_\x7f-\xff\/]*\}\}/', $render, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $name) {
                $var = preg_replace('/^\{\{(.+)\}\}$/', '\1', $name);
                $var = str_replace('/', '\\', $var);

                foreach ([$var, "{$var}\\controller"] as $controller) {
                    if (method_exists($controller, '__widget')) {
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
        if (!empty($matches[0])) {
            foreach ($matches[0] as $name) {
                $var    = preg_replace('/^\{\[(.+)\]\}$/', '\1', $name);
                $val    = self::config()->$var;
                $render = !empty($val) && is_scalar($val)
                    ?
                    str_replace($name, $val, $render)
                    :
                    str_replace($name, '', $render);
            }
        }

        return $render;
    }

    public function __destruct()
    {
        if ($this->started) {
            ob_end_flush();
        }
    }
}
