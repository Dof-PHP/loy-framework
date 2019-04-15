<?php

declare(strict_types=1);

namespace Dof\Framework\DSL;

/**
 * Command line interface arguments parser
 */
class CLIA
{
    public static function build(array $argvs) : array
    {
        $entry = $argvs[0] ?? null;
        $cmd   = $argvs[1] ?? null;

        list($options, $params) = CLIA::parse(array_slice($argvs, 2));

        return [$entry, $cmd, $options, $params];
    }

    public static function parse(array $argvs) : array
    {
        $options = $params = [];
        $hasParamSeparator = false;
        foreach ($argvs as $idx => $argv) {
            if (! is_string($argv)) {
                continue;
            }
            if ($argv === '--') {
                $hasParamSeparator = $idx;
                break;
            }
            if (mb_strcut($argv, 0, 2) === '--') {
                $option = CLIA::parseOption($argv);
                if ($option) {
                    list($name, $_params) = $option;
                    $options[$name] = $_params;
                }
                continue;
            }
            $params[] = $argv;
        }

        if ($hasParamSeparator) {
            $params = array_merge($params, array_slice($argvs, $hasParamSeparator+1));
        }

        return [$options, $params];
    }

    public static function parseOption(string $option) : ?array
    {
        $option = mb_strcut($option, 2);
        if (! $option) {
            return null;
        }

        $sidx = mb_strpos($option, '=');     // separator index
        if (false === $sidx) {
            return [$option, []];
        }

        $name    = mb_substr($option, 0, $sidx);
        $_params = mb_strcut($option, $sidx+1);
        $params  = [];
        parse_str($_params, $params);
        if ((count($params) === 1) && (array_values($params)[0] === '')) {
            $params = $_params;
        }

        return [$name, $params];
    }

    public static function compile(string $cli) : array
    {
        return CLIA::assemble(array_filter(explode(' ', $cli)));
    }
}
