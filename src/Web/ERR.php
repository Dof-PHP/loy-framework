<?php

declare(strict_types=1);

namespace Dof\Framework\Web;

/**
 * Dof Builtin Response Error Code
 */
class ERR
{
    const KERNEL_BOOT_FAILED = [50000000, 'KernelBootFailed'];
    const SERVER_CLOSED = [50000001, 'ServerClosed'];
    const PREFLIGHT_EXCEPTION= [50000002, 'PreflightException'];
    const ROUTING_ERROR = [50000003, 'RoutingError'];
    const PORT_CLASS_NOT_EXIST = [50000004, 'PortClassNotExist'];
    const PORT_METHOD_NOT_EXIST = [50000005, 'PortMethodNotExist'];
    const PIPEIN_ERROR = [50000006, 'PipeinError'];
    const REQUEST_VALIDATE_ERROR = [50000007, 'RequestValidateError'];
    const BUILD_PORT_METHOD_PARAMETERS_FAILED = [50000008, 'BuildPortMethodParametersFailed'];
    const RESULTING_RESPONSE_FAILED = [50000009, 'ResultingResponseFailed'];
    const PIPEOUT_ERROR = [50000010, 'PipeoutError'];
    const PACKAGE_RESULT_FAILED = [50000011, 'PackageResultFailed'];
    const SENDING_RESPONSE_FAILED = [50000012, 'SendingResponseFailed'];
    const PREFLIGHT_NOT_EXISTS = [50000013, 'PreflightNotExists'];
    const PREFLIGHT_HANDLER_NOT_EXISTS = [50000014, 'PreflightHandlerNotExists'];
    const PREFLIGHT_FAILED = [50000015, 'PreflightFailed'];
    const BAD_ROUTE_WITHOUT_PORT = [50000016, 'BadRouteWithoutPort'];
    const REQEUST_PARAMETER_VALIDATION_ERROR = [50000017, 'ReqeustParameterValidationError'];
    const NOPIPEIN_CLASS_NOT_EXISTS = [50000018, 'NopipeinClassNotExists'];
    const PIPEIN_CLASS_NOT_EXISTS = [50000019, 'PipeinClassNotExists'];
    const PIPEIN_HANDLER_NOT_EXISTS = [50000020, 'PipeinHandlerNotExists'];
    const PIPEIN_THROUGH_FAILED = [50000021, 'PipeinThroughFailed'];
    const NOPIPEOUT_CLASS_NOT_EXISTS = [50000022, 'NopipeoutClassNotExists'];
    const PIPEOUT_CLASS_NOT_EXISTS = [50000023, 'PipeoutClassNotExists'];
    const PIPEOUT_HANDLER_NOT_EXISTS = [50000024, 'PipeoutHandlerNotExists'];
    const PIPEOUT_THROUGH_FAILED = [50000025, 'PipeoutThroughFailed'];
    const TOKEN_SECRET_MISSING = [50000026, 'TokenSecretMissing'];
    const TOKEN_SECRET_ID_MISSING = [50000027, 'TokenSecretIdMissing'];
    const TOKEN_SECRET_KEY_MISSING = [50000027, 'TokenSecretKeyMissing'];

    const DOF_SERVICE_EXCEPTION = [40000000, 'DofServiceException'];
    const INVALID_REQUEST_MIME = [40000001, 'InvalidRequestMime'];
    const WRAPIN_VALIDATE_FAILED = [40000002, 'WrapinValidateFailed'];
    const INVALID_ROUTE_PARAMETER = [40000003, 'InvalidRouteParameter'];
    const INVALID_AUTH_CLIENT_ID = [40000004, 'InvalidAuthClientId'];
    const INVALID_AUTH_CLIENT_REALM = [40000006, 'InvalidAuthClientRealm'];
    const MISSING_TOKEN_HEADER_OR_PARAMETER = [40100000, 'MissingTokenHeaderOrParameter'];
    const INVALID_BEARER_TOKEN = [40100001, 'InvalidBearerToken'];
    const INVALID_HTTP_HMAC_TOKEN = [40100002, 'InvalidHttpHmacToken'];
    const MISSING_TOKEN_IN_HEADER = [40100003, 'MissingTokenInHeader'];
    const INVALID_TOKEN_IN_HEADER = [40100004, 'InvalidtokenInHeader'];
    const MISSING_SIGNATURE_IN_TOKEN = [40100005, 'MissingSignatureInToken'];
    const INVALID_HTTP_HMAC_TOKEN_SIGNATURE = [40100006, 'InvalidHttpHmacTokenSignature'];
    const HTTP_HMAC_TOKEN_VEFIY_FAILED = [40100007, 'HttpHmacTokenVefiyFailed'];
    const JWT_TOKEN_VERIFY_FAILED = [40100008, 'JwtTokenVerifyFailed'];
    const INVALID_USER_CREDENTIALS = [40100009, 'InvalidUserCredentials'];
    const MISSING_HTTP_HMAC_TOKEN_HEADER = [40100010, 'MissingHttpHmacTokenHeader'];
    const ROUTE_NOT_EXISTS = [40400000, 'RouteNotExists'];
}
