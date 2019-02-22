<?php /** @noinspection PhpIncludeInspection We use dynamic views includes here */

namespace alexandria\app;

use alexandria\lib\form;

/**
 * Theme app Library
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

    /**
     * theme constructor.
     *
     * @param null $args
     *
     * @throws \Exception
     */
    public function __construct($args = null)
    {
        if (is_array($args))
        {
            $args = (object) $args;
        }

        $proto = 'http';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off'
        || !empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        {
            $proto = 'https';
        }

        $wroot = '';
        if (!empty($_SERVER['HTTP_HOST']))
        {
            $wroot = "{$proto}://{$_SERVER['HTTP_HOST']}";
            $wroot = rtrim($wroot, '/');
        }

        if (empty($_SERVER['PATH_INFO']))
        {
            $_SERVER['PATH_INFO'] = '';
        }

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

        if (!file_exists("{$this->theme}/{$this->entry}"))
        {
            throw new \Exception("Theme file not found: {$this->theme}/{$this->entry}");
        }
    }

    /**
     * @return string
     */
    public function get(): string
    {
        return $this->name;
    }

    /**
     * @param string $theme
     */
    public function set(string $theme)
    {
        $this->name   = $theme;
        $this->theme  = "{$this->themes}/{$this->name}";
        $this->wtheme = "{$this->wthemes}/{$this->name}";
    }

    /**
     * @param string $content
     *
     * @return string
     */
    public function render(string $content): string
    {
        ob_start();
        extract($this->vars, EXTR_SKIP);
        require "{$this->theme}/{$this->entry}";
        $buffer = ob_get_clean();

        if (strpos($buffer, '{content}') === false)
        {
            trigger_error('Theme has no {content} section, theme rendering is insufficient', E_USER_NOTICE);
        }

        $buffer = str_replace('{content}', $content, $buffer);
        $buffer = str_replace('{theme}', $this->wtheme, $buffer);
        $buffer = str_replace('{root}', $this->wroot, $buffer);

        // Assigned variables
        $matches = null;
        preg_match_all('/\{\$(?<var>[a-zA-Z_\x7f-\xff][\->\[\]\'"a-zA-Z0-9_\x7f-\xff]*)\}/', $buffer, $matches);
        if (!empty($matches['var']))
        {
            foreach ($matches['var'] as $index => $name)
            {
                $src = $matches[0][$index];
                $val = $this->vars[$name] ?? null;
                if (!empty($val) && is_scalar($val))
                {
                    $buffer = str_replace($src, htmlspecialchars($val), $buffer);
                }
                else
                {
                    $var    = preg_replace('~\{\$(.+)\}~', '$1', $src);
                    $esrc   = addslashes($src);
                    $cmd    = "return \${$var} ?? '{$esrc}';";
                    $val    = eval($cmd);
                    $buffer = str_replace($src, htmlspecialchars($val), $buffer);
                }
            }
        }

        // Ret-Evaluations
        preg_match_all('/<=\s*(?<eval>.+)=>/u', $buffer, $matches);
        if (!empty($matches[0]))
        {
            foreach ($matches['eval'] as $index => $cmd)
            {
                $src    = $matches[0][$index];
                $val    = eval("return {$cmd};");
                $buffer = str_replace($src, htmlspecialchars($val), $buffer);
            }
        }

        // Widgets
        $matches = null;
        preg_match_all('/\{\{[a-zA-Z_\x7f-\xff][\\a-zA-Z0-9_\x7f-\xff\/]*\}\}/', $buffer, $matches);
        if (!empty($matches[0]))
        {
            foreach ($matches[0] as $name)
            {
                $controller = preg_replace('/^\{\{(.+)\}\}$/', '\1', $name);
                $controller = str_replace('/', '\\', $controller);
                $controller .= '\\widget';
                if (class_exists($controller) && method_exists($controller, 'main'))
                {
                    ob_start();
                    $instance = new $controller();
                    $tmp      = ob_get_clean();

                    if (method_exists($instance, 'main'))
                    {
                        $output = $instance->main();
                    }
                    elseif (method_exists($instance, 'index'))
                    {
                        $output = $instance->index();
                    }
                    else
                    {
                        $output = $tmp;
                    }

                    $output = str_replace('{theme}', $this->wtheme, $output);
                    $output = str_replace('{root}', $this->wroot, $output);
                    $buffer = str_replace($name, $output, $buffer);
                }
            }
        }

        // Config variables
        $matches = null;
        preg_match_all('/\{\[[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\]\}/', $buffer, $matches);
        if (!empty($matches[0]))
        {
            foreach ($matches[0] as $name)
            {
                $var    = preg_replace('/^\{\[(.+)\]\}$/', '\1', $name);
                $val    = kernel::load('config')->$var;
                $buffer = !empty($val) && is_scalar($val) ? str_replace($name, $val, $buffer) : str_replace($name, '',
                    $buffer);
            }
        }

        return $buffer;
    }

    /**
     * @param array $vars
     */
    public function vars(array $vars)
    {
        foreach ($vars as $name => $value)
        {
            $this->vars[$name] = $value;
        };
    }

    /**
     * @param string $form
     * @param array  $vars
     *
     * @return string
     */
    public function view(string $form, array $vars = []): string
    {
        $vars['theme'] = $this;
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

        trigger_error("Can not load form: {$form}", E_USER_ERROR);
    }

    /**
     *
     */
    public function __destruct()
    {
        if ($this->started)
        {
            ob_end_flush();
        }
    }
}
