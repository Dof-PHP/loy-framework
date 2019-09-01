<?php

declare(strict_types=1);

namespace Dof\Framework;

use Throwable;

class EXCP
{
    const VIOLATED_UNIQUE_CONSTRAINT = 'ViolatedUniqueConstraint';
    const INPUT_FIELDS_SENTENCE_GRAMMER_ERROR = 'InputFieldsSentenceGrammerError';
    const DOF_SERVICE_EXCEPTION = 'DofServiceException';
    const TIMEOUTED_HMAC_SIGNATURE = 'TimeoutedHMACSignature';

    public static function is(Throwable $e, string $name) : bool
    {
        return is_exception($e, $name);
    }
}
