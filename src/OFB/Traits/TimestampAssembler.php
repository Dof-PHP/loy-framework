<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Traits;

trait TimestampAssembler
{
    public function formatTimestamp(int $ts = null, array $params = [])
    {
        $format = $params['format'] ?? '0';
        $empty = '-';
        switch ($format) {
            case '3':
                $format = 'd/m/Y H:i:s';
                break;
            case '2':
                $format = 'y/m/d H:i:s';
                break;
            case '1':
                $format = 'Y-m-d H:i:s';
                break;
            case '0':
            default:
                $empty = '';
                return $ts;
        }

        return $ts ? fdate($ts, $format) : $empty;
    }
}
