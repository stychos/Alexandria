<?php

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
     * @param string $__file Filename of the form file to load.
     * @param mixed  $__vars Variables to pass to the form.
     *
     * @return string Loaded form content.
     */
    public static function load(string $__file, array $__vars = []): string
    {
        if (!file_exists($__file))
        {
            Throw new \RuntimeException("Can not load form: {$__file}");
        }

        // For resolving possible conflicts with form including, all passed variables are stored in the $__ vars.
        extract($__vars, EXTR_SKIP);

        // Including allows to run PHP inside forms.
        ob_start();
        include $__file;
        $__content = ob_get_clean();

        // Replace content mnemonics, in example: {$var} will become to $var value, if found (and scalar) in passed variables.
        // Also, {CONST} will become to CONST constant value, if constant defined.
        preg_match_all('/\{\$?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\}/', $__content, $__to_replace);
        if (is_array($__to_replace[0]) && !empty($__to_replace[0]))
        {
            foreach (array_unique($__to_replace[0]) as $__mnemonic)
            {
                $__var = str_replace(['{', '}', '$'], '', $__mnemonic);
                if (strpos($__mnemonic, '{$') === 0
                    && isset($__vars[$__var])
                    && is_scalar($__vars[$__var]))
                {
                    $__content = str_replace($__mnemonic, $__vars[$__var], $__content);
                }

                // Otherwise, try to find appropriate constant. Constants are always scalar.
                elseif (strpos($__mnemonic, '{$') !== 0
                        && defined($__var))
                {
                    $__content = str_replace($__mnemonic, constant($__var), $__content);
                }

                elseif (strpos($__mnemonic, '{$') === 0)
                {
                    $__content = str_replace($__mnemonic, '', $__content);
                }
            }
        }

        return $__content;
    }

    public static function show(string $file, array $vars = [])
    {
        echo self::load($file, $vars);
    }
}