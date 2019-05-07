<?php

declare(strict_types=1);

namespace Dof\Framework\Web;

/**
 * Dof Builtin Response Error Code
 */
class ERR
{
    const KERNEL_BOOT_FAILED = [50000000, 'KERNEL_BOOT_FAILED'];
    const SERVER_CLOSED = [50000001, 'SERVER_CLOSED'];
    const PREFLIGHT_EXCEPTION= [50000002, 'PREFLIGHT_EXCEPTION'];
    const ROUTING_ERROR = [50000003, 'ROUTING_ERROR'];
    const PORT_CLASS_NOT_EXIST = [50000004, 'PORT_CLASS_NOT_EXIST'];
    const PORT_METHOD_NOT_EXIST = [50000005, 'PORT_METHOD_NOT_EXIST'];
    const PIPEIN_ERROR = [50000006, 'PIPEIN_ERROR'];
    const REQUEST_VALIDATE_ERROR = [50000007, 'REQUEST_VALIDATE_ERROR'];
    const BUILD_PORT_METHOD_PARAMETERS_FAILED = [50000008, 'BUILD_PORT_METHOD_PARAMETERS_FAILED'];
    const RESULTING_RESPONSE_FAILED = [50000009, 'RESULTING_RESPONSE_FAILED'];
    const PIPEOUT_ERROR = [50000010, 'PIPEOUT_ERROR'];
    const PACKAGE_RESULT_FAILED = [50000011, 'PACKAGE_RESULT_FAILED'];
    const SENDING_RESPONSE_FAILED = [50000012, 'SENDING_RESPONSE_FAILED'];
    const PREFLIGHT_NOT_EXISTS = [50000013, 'PREFLIGHT_NOT_EXISTS'];
    const PREFLIGHT_HANDLER_NOT_EXISTS = [50000014, 'PREFLIGHT_HANDLER_NOT_EXISTS'];
    const PREFLIGHT_FAILED = [50000015, 'PREFLIGHT_FAILED'];
    const BAD_ROUTE_WITHOUT_PORT = [50000016, 'BAD_ROUTE_WITHOUT_PORT'];
    const REQEUST_PARAMETER_VALIDATION_ERROR = [50000017, 'REQEUST_PARAMETER_VALIDATION_ERROR'];
    const NOPIPEIN_CLASS_NOT_EXISTS = [50000018, 'NOPIPEIN_CLASS_NOT_EXISTS'];
    const PIPEIN_CLASS_NOT_EXISTS = [50000019, 'PIPEIN_CLASS_NOT_EXISTS'];
    const PIPEIN_HANDLER_NOT_EXISTS = [50000020, 'PIPEIN_HANDLER_NOT_EXISTS'];
    const PIPEIN_THROUGH_FAILED = [50000021, 'PIPEIN_THROUGH_FAILED'];
    const NOPIPEOUT_CLASS_NOT_EXISTS = [50000022, 'NOPIPEOUT_CLASS_NOT_EXISTS'];
    const PIPEOUT_CLASS_NOT_EXISTS = [50000023, 'PIPEOUT_CLASS_NOT_EXISTS'];
    const PIPEOUT_HANDLER_NOT_EXISTS = [50000024, 'PIPEOUT_HANDLER_NOT_EXISTS'];
    const PIPEOUT_THROUGH_FAILED = [50000025, 'PIPEOUT_THROUGH_FAILED'];
    const TOKEN_SECRET_MISSING = [50000026, 'TOKEN_SECRET_MISSING'];
    const TOKEN_SECRET_ID_MISSING = [50000027, 'TOKEN_SECRET_ID_MISSING'];
    const TOKEN_SECRET_KEY_MISSING = [50000027, 'TOKEN_SECRET_KEY_MISSING'];

    const DOF_SERVICE_EXCEPTION = [40000000, 'DOF_SERVICE_EXCEPTION'];
    const INVALID_REQUEST_MIME = [40000001, 'INVALID_REQUEST_MIME'];
    const WRAPIN_FAILED = [40000002, 'WRAPIN_FAILED'];
    const INVALID_ROUTE_PARAMETER = [40000003, 'INVALID_ROUTE_PARAMETER'];
    const JWT_TOKEN_VERIFY_FAEILD = [40000005, 'JWT_TOKEN_VERIFY_FAEILD'];
    const MISSING_TOKEN_HEADER_OR_PARAMETER = [40100000, 'MISSING_TOKEN_HEADER_OR_PARAMETER'];
    const MISSING_AUTH_TOKEN_HEADER = [40100000, 'MISSING_AUTH_TOKEN_HEADER'];
    const INVALID_BEARER_TOKEN = [40100001, 'INVALID_BEARER_TOKEN'];
    const INVALID_HTTP_HMAC_TOKEN = [40100002, 'INVALID_HTTP_HMAC_TOKEN'];
    const MISSING_TOKEN_IN_HEADER = [40100003, 'MISSING_TOKEN_IN_HEADER'];
    const ROUTE_NOT_EXISTS = [40400000, 'ROUTE_NOT_EXISTS'];
}