<?php

declare(strict_types=1);

namespace Loy\Framework\Base\DSL;

use Exception;

final class InputFieldsParser
{
    public static function parse(string $str) : array
    {
        return self::parseInputGrammerResult(self::parseInputGrammer($str));
    }

    public static function parseInputGrammer(string $str) : array
    {
        $str = trim($str);
        $arr = explode(';', $str);
        $res = [];
        foreach ($arr as $item) {
            if (! is_string($item)) {
                throw new Exception('InvalidSentenceType');
            }
            if (! trim($item)) {
                continue;
            }

            $res[] = self::parseSentenceGrammer($item);
        }

        return $res;
    }

    public static function parseParameterGrammer(string $parameter) : array
    {
        $parameter = trim($parameter);
        if (! $parameter) {
            return [];
        }

        $arr = str_split($parameter, 1);
        $bracesLeft = $bracesRight = [];
        foreach ($arr as $idx => $char) {
            if ($char === '{') {
                $bracesLeft[] = $idx;
                continue;
            }
            if ($char === '}') {
                $bracesRight[] = $idx;
                continue;
            }
        }
        if (count($bracesLeft) !== count($bracesRight)) {
            throw new Exception('GrammerError: Braces Length Mismatch');
        }

        $braces = self::adjustBraces($bracesLeft, $bracesRight);

        return [
            'braces' => $braces,
            'string' => $sentence,
            'array'  => $arr,
        ];
    }

    public static function parseSentenceGrammer(string $sentence) : array
    {
        $sentence = trim($sentence);
        if (! $sentence) {
            return [];
        }

        $arr = str_split($sentence, 1);
        $parenthesesLeft = $parenthesesRight = $bracesLeft = $bracesRight = [];
        foreach ($arr as $idx => $char) {
            if ($char === '(') {
                $parenthesesLeft[] = $idx;
                continue;
            }
            if ($char === ')') {
                $parenthesesRight[] = $idx;
                continue;
            }
        }
        if (count($parenthesesLeft) !== count($parenthesesRight)) {
            throw new Exception('GrammerError: Parentheses Length Mismatch');
        }

        $parentheses = self::adjustParentheses($parenthesesLeft, $parenthesesRight);

        return [
            'parentheses' => $parentheses,
            'string' => $sentence,
            'array'  => $arr,
        ];
    }

    public static function adjustBraces(array $left, array $right) : array
    {
        return self::adjustBrackets($left, $right, '{', '}');
    }

    public static function adjustParentheses(array $left, array $right) : array
    {
        return self::adjustBrackets($left, $right, '(', ')');
    }

    public static function adjustBrackets(
        array $left,
        array $right,
        string $charLeft,
        string $charRight
    ) : array {
        $_left  = array_flip($left);
        $_right = array_flip($right);
        $all = array_merge($left, $right);
        sort($all);

        $res = [];
        $log = [];
        $cnt = count($all);
        for ($i = 0; $i < $cnt; ++$i) {
            $now = $all[$i];
            if (isset($log[$now])) {
                continue;
            }
            $_now = isset($_left[$now]) ? $charLeft : $charRight;
            $lenNow = $lenCompare = 0;
            for ($j = $i + 1; $j < $cnt; ++$j) {
                $compare  = $all[$j];
                if (isset($log[$compare])) {
                    continue;
                }
                $_compare = isset($_left[$compare]) ? $charLeft : $charRight;

                if ($_now === $_compare) {
                    ++$lenNow;
                    continue;
                }
                ++$lenCompare;
                if ($lenCompare > $lenNow) {
                    $log[$now] = $log[$compare] = true;
                    if ($_now === $charLeft) {
                        $res[$now] = $compare;
                    } else {
                        $res[$compare] = $now;
                    }
                    break;
                }
            }
        }

        return $res;
    }

    public static function parseInputGrammerResult(array $result) : array
    {
        $res = [];

        foreach ($result as $sentence) {
            $senres = self::parseSentenceData($sentence);
            if (! $senres) {
                continue;
            }

            list($name, $children) = $senres;
            $res[$name] = $children;
        }

        return $res;
    }

    public static function parseSentenceContent(string $content) : ?array
    {
        $parentheses = self::parseSentenceGrammer($content)['parentheses'] ?? [];
        if (! $parentheses) {
            return [];
        }

        et($content, $parentheses);

        $len = mb_strlen($content);
        $res = [];
        foreach ($parentheses as $idxStart => $idxEnd) {
            $name = mb_strcut($content, 0, $idxStart);
            if (false !== ($idx = mb_strripos($name, ','))) {
                $name = mb_strcut($name, $idx+1);
            }
            $item = mb_strcut($content, $idxStart+1, $idxEnd-$idxStart-1);
            $lenCut = mb_strlen($item) + mb_strlen($name);

            pt($idxStart, $idxEnd, $name, $item)->die();
            $res[$name] = self::parseSentenceContent($item);
        }

        // pt($res)->die();

        return $res;
    }

    public static function parseSentenceData(array $sentence) : ?array
    {
        $parentheses = $sentence['parentheses'] ?? [];
        $sentenceArr = $sentence['array'] ?? [];

        $res = [];
        foreach ($parentheses as $idxLeft => $idxRight) {
            $name = join('', array_slice($sentenceArr, 0, $idxLeft));
            $item = join('', array_slice($sentenceArr, $idxLeft+1, $idxRight-$idxLeft-1));
            if (false !== ($idx = mb_strripos($name, ','))) {
                $name = mb_strcut($name, $idx+1);
            }

            return [$name, self::parseSentenceContent($item)];
        }
    }
}
