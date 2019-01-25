<?php /** @noinspection PhpIncludeInspection */

/**
 * Alexandria Engine.
 * It's all for You, my Endless Love.
 *
 * @version   0.8
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright Hello Carrot, inc., 2014. http://hello-carrot.com
 * @author    iloveYou <i@blackrabbit.info>
 */

namespace alexandria\lib;

/**
 * Forms Library.
 *
 * @todo refactor for proper constructor!
 */
class form
{
    /**
     * Load form and return its parsed content
     * Note: if form variables and passed variables have similar names, form variables will overwrite passed variables
     * Also, it is VERY STRONGLY recommended to NOT USE "__form" as variable name in the forms
     *
     * {$var} -> escaped $var content or unchanged if $var not found
     * {?var} -> escaped $var content or empty if $var not found
     * {!var} -> unescaped $var content or empty if $var not found
     *
     * @param string $__file Filename of the form file to load.
     * @param mixed  $__vars Variables to pass to the form.
     * @return string Loaded form content.
     */
    public static function load(string $__file, array $__vars = []): string
    {
        if (!file_exists($__file))
        {
            throw new \RuntimeException("Can not load form: {$__file}");
        }

        // For resolving possible conflicts with form including, all passed variables are stored in the $__ vars.
        extract($__vars, EXTR_SKIP);

        // Including allows to run PHP inside forms.
        ob_start();
        include $__file;
        $__content = ob_get_clean();

        // Replace content mnemonics, in example: {$var} will become to $var value, if found (and scalar) in passed variables.
        // Also, {CONST} will become to CONST constant value, if constant defined.
        preg_match_all('/\{(?<operation>[\$\?\!])(?<name>[a-zA-Z_\x7f-\xff][\->\(\)\[\]\'"a-zA-Z0-9_\x7f-\xff]*)\}/', $__content, $__to_replace);
        foreach (array_unique($__to_replace[0]) as $__index => $__src)
        {
            $__ret = null;
            $__cmd = $__to_replace['operation'][$__index];
            $__var = $__to_replace['name'][$__index];

            // Check in extracted scalar variables
            if (isset($__vars[$__var]))
            {
                $__tmp = $__vars[$__var];
                if (is_scalar($__tmp))
                {
                    $__ret = $__tmp;
                }

                // Check in objects contains __toString()
                elseif (is_object($__tmp) && method_exists($__tmp, '__toString'))
                {
                    $__ret = (string) $__tmp;
                }
            }

            // check in constants
            elseif (defined($__var))
            {
                $__ret = $__var;
            }

            // check in object methods and arrays
            else
            {
                try
                {
                    $__tmp = @eval("return \${$__var};");
                }
                catch (\Throwable $e)
                {
                    $__tmp = null;
                }
                $__ret = $__tmp;
            }

            if ($__cmd != '!')
            {
                if (is_null($__ret) && $__cmd == '$')
                {
                    $__ret = $__src;
                }

                $__ret = htmlspecialchars($__ret);
            }

            $__content = str_replace($__src, $__ret, $__content);
        }

        // Ret-Evaluations
        preg_match_all('/<=\s*(?<eval>.+)=>/u', $__content, $__to_replace);
        if (is_array($__to_replace[0]) && !empty($__to_replace[0]))
        {
            foreach (array_unique($__to_replace['eval']) as $__index => $__cmd)
            {
                $__src     = $__to_replace[0][$__index];
                $__val     = eval("return {$__cmd};");
                $__content = str_replace($__src, htmlspecialchars($__val), $__content);
            }
        }

        return $__content;
    }

    public static function show(string $file, array $vars = [])
    {
        echo self::load($file, $vars);
    }
}
