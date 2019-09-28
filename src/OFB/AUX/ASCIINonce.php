<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\AUX;

/**
 * Nonce string generator based on ASCII characters
 *
 * - invisible control characters: 0~31, 127
 * - invisible normal characters: 32 (space)
 * - visible number characters: 48~57
 * - visible uppercase alphabet characters: 65~90
 * - visible lowercase alphabet characters: 97~122
 * - visible punctuation characters: 33~47, 58~64, 91~96, 123~126
 */
class ASCIINonce
{
    const NUM = 1;
    const AUP = 2;
    const ALO = 4;
    const PUN = 8;

    const CHR = 6;
    const STR = 7;
    const ALL = 15;

    const SRC_NUM = '0123456789';
    const SRC_AUP = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const SRC_ALO = 'abcdefghijklmnopqrstuvwxyz';
    const SRC_PUN = <<<PUN
!"#$%&'()*+,-./:;<=>?@[\]^_`{|}~
PUN;
    const EXCLUDE = <<<PUN
'"(){}[]<>+-/\|:;,`^
PUN;

    public static function get(
        int $length,
        int $mode = self::STR,
        ...$excludes
    ) : ?string {
        $str = '';

        switch ($mode) {
            case self::NUM:
                $src = self::SRC_NUM;
                break;
            case self::AUP:
                $src = self::SRC_AUP;
                break;
            case self::ALO:
                $src = self::SRC_ALO;
                break;
            case self::PUN:
                $src = self::SRC_PUN;
                break;
            case self::CHR:
                $src = self::SRC_AUP.self::SRC_ALO;
                break;
            case self::ALL:
                $src = self::SRC_NUM.self::SRC_AUP.self::SRC_ALO.self::SRC_PUN;
                break;
            case self::STR:
                $src = self::SRC_NUM.self::SRC_AUP.self::SRC_ALO;
                break;
            default:
                return null;
        }

        if ($excludes) {
            foreach ($excludes as $exclude) {
                if (! is_string($exclude)) {
                    continue;
                }

                $exclude = str_split($exclude);
                foreach ($exclude as $char) {
                    $src = str_replace($char, '', $src);
                }
            }
        }

        $max = strlen($src) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $src[mt_rand(0, $max)] ?? '';
        }

        return $str;
    }
}
