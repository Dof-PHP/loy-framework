<?php

declare(strict_types=1);

namespace Dof\Framework\Web;

use Throwable;
use Closure;
use Dof\Framework\Kernel as Core;
use Dof\Framework\EXCP;
use Dof\Framework\Container;
use Dof\Framework\PortManager;
use Dof\Framework\WrapinManager;
use Dof\Framework\ConfigManager;
use Dof\Framework\TypeHint;
use Dof\Framework\Validator;
use Dof\Framework\Facade\Log;
use Dof\Framework\Facade\Request;
use Dof\Framework\Facade\Response;

/**
 * Dof Framework Web Kernel
 */
final class Kernel
{
    const PIPEIN_HANDLER  = 'pipein';
    const PIPEOUT_HANDLER = 'pipeout';
    const WRAPOUT_HANDLER = 'wrapout';
    const WRAPERR_HANDLER = 'wraperr';
    const PREFLIGHT_HANDLER = 'preflight';
    const HALT_FLAG = '.LOCK.WEB.DOF';
    const ALLOW_SAPI = [
        'fpm-fcgi' => true,
        'cgi-fcgi' => true,
        'cgi' => true,
        'cli-server' => true,
    ];

    private static $booted = false;

    /**
     * Web kernel handler - The entry of HTTP world
     *
     * Bootstrap framework kernel and then process HTTP request
     *
     * @param string $root
     */
    public static function handle(string $root)
    {
        // Deny all non-cgi calls
        if (! (self::ALLOW_SAPI[PHP_SAPI] ?? false)) {
            exit('RunWebKernelInNonCGI');
        }

        self::$booted = true;

        try {
            Core::register('shutdown', function () {
                $uptime   = $_SERVER['REQUEST_TIME_FLOAT'] ?? Core::getUptime();
                $duration = microtime(true) - $uptime;
                $memcost  = memory_get_usage() - Core::getUpmemory();
                Log::log('http', enjson([
                    $duration,
                    $memcost,
                    memory_get_peak_usage(),
                    count(get_included_files()),
                ]), Kernel::getContext(false));
            });

            Core::boot($root);
        } catch (Throwable $e) {
            Kernel::throw(ERR::KERNEL_BOOT_FAILED, ['root' => $root], 500, $e);
        }

        if (is_file($flag = ospath($root, Kernel::HALT_FLAG))) {
            Kernel::throw(ERR::SERVER_CLOSED, dejson($flag, true, true), 503);
        }

        try {
            self::preflight();
        } catch (Throwable $e) {
            Kernel::throw(ERR::PREFLIGHT_EXCEPTION, [], 500, $e);
        }

        try {
            self::routing();
        } catch (Throwable $e) {
            Kernel::throw(ERR::ROUTING_ERROR, [], 500, $e);
        }

        $class  = Port::get('class');
        $method = Port::get('method');
        if ((! $class) || (! class_exists($class))) {
            Kernel::throw(ERR::PORT_CLASS_NOT_EXIST, compact('class'));
        }
        if ((! $method) || (! method_exists($class, $method))) {
            Kernel::throw(ERR::PORT_METHOD_NOT_EXIST, compact('class', 'method'), 500, null, $class);
        }

        try {
            self::pipingin();
        } catch (Throwable $e) {
            Kernel::throw(ERR::PIPEIN_ERROR, [], 500, $e, $class);
        }

        try {
            self::validate();
        } catch (Throwable $e) {
            Kernel::throw(ERR::REQUEST_VALIDATE_ERROR, [], 500, $e, $class);
        }

        try {
            $params = self::build();
        } catch (Throwable $e) {
            Kernel::throw(ERR::BUILD_PORT_METHOD_PARAMETERS_FAILED, [], 500, $e, $class);
        }

        try {
            $result = (new $class)->{$method}(...$params);
        } catch (Throwable $e) {
            self::throwIfService($e, $class, function () use ($class, $method, $e) {
                Kernel::throw(ERR::RESULTING_RESPONSE_FAILED, compact('class', 'method'), 500, $e, $class);
            });
        }

        try {
            $result = self::pipingout($result);
        } catch (Throwable $e) {
            Kernel::throw(ERR::PIPEOUT_ERROR, [], 500, $e, $class);
        }

        try {
            $result = self::packing($result);
        } catch (Throwable $e) {
            Kernel::throw(ERR::PACKAGE_RESULT_FAILED, [], 500, $e, $class);
        }

        try {
            self::logging();
        } catch (Throwable $e) {
            Log::log('port-logging-failure', join('@', [$class, $method]), parse_throwable($e));
            // Kernel::throw(ERR::LOGGING_REQUEST_FAILED, [], 500, $e, $class);
        }

        try {
            Response::setBody($result)->send();
        } catch (Throwable $e) {
            Kernel::throw(ERR::SENDING_RESPONSE_FAILED, [], 500, $e, $class);
        }
    }

    /**
     * Preflight stuffs before process request
     */
    private static function preflight()
    {
        $preflights = ConfigManager::getDomain('http.preflight', []);
        if ((! $preflights) || (! is_array($preflights))) {
            return;
        }

        foreach ($preflights as $preflight) {
            if (! class_exists($preflight)) {
                Kernel::throw(ERR::PREFLIGHT_NOT_EXISTS, compact('preflight'));
            }
            if (! method_exists($preflight, self::PREFLIGHT_HANDLER)) {
                Kernel::throw(ERR::PREFLIGHT_HANDLER_NOT_EXISTS, [
                    'preflight' => $preflight,
                    'handler'   => self::PREFLIGHT_HANDLER,
                ]);
            }

            if (true !== ($res = call_user_func_array([Container::di($preflight), self::PREFLIGHT_HANDLER], [
                Request::getInstance(),
                Response::getInstance()
            ]))) {
                Kernel::throw(ERR::PREFLIGHT_FAILED, compact('res'));
            }
        }
    }

    /**
     * Routing logics
     *
     * 1. Find route definition by request information
     * 2. Validate request uri against route definition
     */
    private static function routing()
    {
        $verb  = Request::getVerb();
        $uri   = Request::getUri();
        $mimes = Request::getMimeAliases();
        $route = PortManager::find($uri, $verb, $mimes);
        if (! $route) {
            $mime = Request::getMimeShort();
            Response::error(404, ERR::ROUTE_NOT_EXISTS, compact('verb', 'uri', 'mime'));
        }
        $port = PortManager::get($route);
        if (! $port) {
            Kernel::throw(ERR::BAD_ROUTE_WITHOUT_PORT, compact('route'), 500);
        }

        Route::setData($route);
        Port::setData($port);
        Response::setMimeAlias(Response::mimeout());

        if (($mimein = Port::get('mimein')) && (! Request::isMimeAlias($mimein))) {
            Response::error(400, ERR::INVALID_REQUEST_MIME, [
                'current' => Request::getMimeShort(),
                'require' => Request::getMimeByAlias($mimein, '?'),
            ], Route::get('class'));
        }
    }

    /**
     * Validate request body parameters against route definitions
     * - either: wrapin check
     * - or: argument annotations check
     */
    private static function validate()
    {
        $wrapin = Port::get('wrapin');
        $arguments = Port::get('__arguments');
        if ((! $wrapin) && (! $arguments)) {
            return;
        }

        // 1. Check wrapin setting on route annotation first
        // 2. Check arguments annotations from route method and port properties
        try {
            $params = Route::get('params.kv');
            $params = $params->count() > 0 ? $params->toArray() : null;
            $validator = $wrapin
                ? WrapinManager::apply($wrapin, $params)
                : WrapinManager::execute($arguments, Route::get('class'), $params);

            if (($fails = $validator->getFails()) && ($fail = $fails->first())) {
                $context = $fail->toArray();
                $context['__info'] = $context['key'] ?? null;
                $context['__more'] = $context['value'] ?? null;
                unset($context['key'], $context['value']);
                if ($wrapin) {
                    $context['wrapins'][] = $wrapin;
                }

                Response::error(400, ERR::WRAPIN_VALIDATE_FAILED, $context, Route::get('class'));
            }

            Port::getInstance()->argument = collect($validator->getResult(), null, false);
        } catch (Throwable $e) {
            Kernel::throw(ERR::REQEUST_PARAMETER_VALIDATION_ERROR, compact('wrapin'), 500, $e, Route::get('class'));
        }
    }

    /**
     * Request through port pipe-ins defined in current route
     */
    private static function pipingin()
    {
        $pipes = Port::getRaw('pipein');
        $noin  = Port::getRaw('nopipein');
        if (count($pipes) === 0) {
            return;
        }

        $shouldPipeInBeIgnored = function ($pipein, $noin) : bool {
            foreach ($noin as $exclude => $ext) {
                if ((! $exclude) || (! class_exists($exclude))) {
                    Kernel::throw(ERR::NOPIPEIN_CLASS_NOT_EXISTS, [
                        'nopipein' => $exclude,
                        'class'    => Route::get('class'),
                        'method'   => Route::get('method'),
                    ], 500, null, Route::get('class'));
                }
                if ($pipein == $exclude) {
                    return true;
                }
            }

            return false;
        };

        foreach ($pipes as $pipe => $ext) {
            if ($noin && $shouldPipeInBeIgnored($pipe, $noin)) {
                continue;
            }
            if ((! $pipe) || (! class_exists($pipe))) {
                Kernel::throw(ERR::PIPEIN_CLASS_NOT_EXISTS, [
                    'port'   => Route::get('class'),
                    'method' => Route::get('method'),
                    'pipein' => $pipe,
                ], 500, null, Route::get('class'));
            }
            if (! method_exists($pipe, Kernel::PIPEIN_HANDLER)) {
                Kernel::throw(ERR::PIPEIN_HANDLER_NOT_EXISTS, [
                    'pipein'  => $pipe,
                    'hanlder' => Kernel::PIPEIN_HANDLER
                ], 500, null, Route::get('class'));
            }

            try {
                call_user_func_array([Container::di($pipe), Kernel::PIPEIN_HANDLER], [
                    Request::getInstance(),
                    Response::getInstance(),
                    Route::getInstance(),
                    Port::getInstance()
                ]);
            } catch (Throwable $e) {
                self::throwIfService($e, Route::get('class'), function () use ($pipe, $e) {
                    $error = is_anonymous($e) ? 400 : 500;
                    Kernel::throw(ERR::PIPEIN_THROUGH_FAILED, compact('pipe'), $error, $e, Route::get('class'));
                });
            }
        }
    }

    /**
     * Build port parameters from port method definition and route params
     */
    private static function build() : array
    {
        $paramsMethod = Port::get('__parameters');
        $paramsRoute  = Route::get('params.kv');
        if ((count($paramsMethod) < 1) && (count($paramsRoute) < 1)) {
            return [];
        }

        try {
            return Container::complete($paramsMethod, $paramsRoute);
        } catch (Throwable $e) {
            $class  = Route::get('class');
            $method = Route::get('method');
            if (is_exception($e, 'TypeHintConvertFailed')) {
                Response::error(400, ERR::INVALID_ROUTE_PARAMETER, parse_throwable($e), $class);
            }

            Kernel::throw(ERR::BUILD_PORT_METHOD_PARAMETERS_FAILED, compact('class', 'method'), 500, $e, $class);
        }
    }

    /**
     * Response result through port pipe-outs defined in current route
     *
     * @param mixed $result
     * @return mixed Pipeouted response result
     */
    private static function pipingout($result)
    {
        $pipes = Port::getRaw('pipeout');
        $noout = Port::getRaw('nopipeout');
        if (count($pipes) === 0) {
            return $result;
        }

        $shouldPipeOutBeIgnored = function ($pipeout, $noout) : bool {
            foreach ($noout as $exclude => $ext) {
                if ((! $exclude) || (! class_exists($exclude))) {
                    Kernel::throw(ERR::NOPIPEOUT_CLASS_NOT_EXISTS, [
                        'nopipeout' => $exclude,
                        'class'     => Route::get('class'),
                        'method'    => Route::get('method'),
                    ], 500, null, Route::get('class'));
                }
                if ($pipeout == $exclude) {
                    return true;
                }
            }

            return false;
        };

        $_result = $result;
        foreach ($pipes as $pipe => $ext) {
            if ($noout && $shouldPipeOutBeIgnored($pipe, $noout)) {
                continue;
            }
            if ((! $pipe) || (! class_exists($pipe))) {
                Kernel::throw(ERR::PIPEOUT_CLASS_NOT_EXISTS, [
                    'port'    => Route::get('class'),
                    'method'  => Route::get('method'),
                    'pipeout' => $pipe,
                ], 500, null, Route::get('class'));
            }
            if (! method_exists($pipe, Kernel::PIPEOUT_HANDLER)) {
                Kernel::throw(ERR::PIPEOUT_HANDLER_NOT_EXISTS, [
                    'pipeout' => $pipe,
                    'hanlder' => Kernel::PIPEOUT_HANDLER
                ], 500, null, Route::get('class'));
            }

            try {
                $_result = call_user_func_array(
                    [Container::di($pipe), Kernel::PIPEOUT_HANDLER],
                    [$_result, Route::getInstance(), Port::getInstance(), Request::getInstance(), Response::getInstance()]
                );
            } catch (Throwable $e) {
                if (EXCP::is($e, EXCP::INPUT_FIELDS_SENTENCE_GRAMMER_ERROR)) {
                    Kernel::throw(ERR::INPUT_FIELDS_SENTENCE_GRAMMER_ERROR, compact('pipe'), 400, $e, Route::get('class'));
                }

                Kernel::throw(ERR::PIPEOUT_THROUGH_FAILED, compact('pipe'), 500, $e, Route::get('class'));
            }
        }

        return $_result;
    }

    /**
     * Business custom logging logics
     *
     * SHOULD NOT throw exceptions/errors to break off normall http response
     */
    public static function logging()
    {
        $logging = Port::get('logging');
        if (! $logging) {
            return;
        }

        $argvs = Port::argvs();
        if ($argvs['password'] ?? false) {
            $argvs['password'] = '*';
        }
        if ($argvs['secret'] ?? false) {
            $argvs['secret'] = '*';
        }

        call_user_func_array([Container::di($logging), 'logging'], [[
            'at' => Core::getUptime(false),
            'api' => 'http',
            'title' => Port::get('title'),
            'operator_id' => (int) Port::get('__logging_operator_id__'),
            'action_type' => Route::get('verb'),
            'action_value' => Route::get('urlpath'),
            'action_params' => enjson(Route::get('params.kv')),
            'arguments' => enjson($argvs),
            'class'  => Port::get('class'),
            'method' => Port::get('method'),
            'client_ip'  => Request::getClientIP(),
            'client_os'  => Request::getClientOS(),
            'client_name' => Request::getClientName(),
            'client_info' => Request::getClientUA(),
            'client_port' => Request::getClientPort(),
            'server_ip' => Response::getServerIP(),
            'server_os' => Response::getServerOS(),
            'server_name' => Response::getServerName(),
            'server_info' => Response::getServerInfo(),
            'server_port' => Response::getServerPort(),
            'server_status' => Response::getStatus(),
            'server_error'  => (int) Response::getError(),
        ]]);
    }

    /**
     * Package response result with given wrapper (if exists)
     *
     * @param mixed $result: result data to response
     * @return $result: Packaged response result
     */
    private static function packing($result = null)
    {
        $hasError = Response::hasError();
        $wrapout = Port::get('wrapout');
        $wraperr = Port::get('wraperr');
        $wrapper = $hasError ? wrapper($wraperr, 'err') : wrapper($wrapout, 'out');

        if ((! $wrapper) || (! is_array($wrapper))) {
            return $result;
        }

        if ($hasError) {
            return Response::wraperr($result, $wrapper);
        }

        return Response::wrapout($result, $wrapper);
    }

    public static function throwIfService(Throwable $e, string $domain, Closure $ifNot)
    {
        if (is_anonymous($e) && (is_exception($e, EXCP::DOF_SERVICE_EXCEPTION))) {
            $previous = parse_throwable($e)['__previous'] ?? [];
            $message = $previous['message'] ?? null;
            $context = $previous['context'] ?? [];

            $errors = $context['__errors'] ?? [];
            $error  = ERR::DOF_SERVICE_EXCEPTION;
            // Here we treat all service exception as client side error by default
            // Coz if not there should never throw the exception named as this
            $status = 503;
            if ($_error = ($errors[$message] ?? null)) {
                $error  = [($_error[0] ?? -1), $message];
                $status = $_error[1] ?? 503;
                if ($info = ($_error[2] ?? null)) {
                    $context['__info'] = $info;
                }
            } else {
                $context['__info'] = $message;
            }

            unset($context['__errors']);

            if ($status >= 500) {
                Response::exception($status, $error, $context, $domain);
                return;
            }

            Response::error($status, $error, $context, $domain);
        } else {
            $ifNot();
        }
    }

    /**
     * Throw throwables from core kernel or web self in the web way
     *
     * @param string $name: Exception name
     * @param array $context: Exception Context
     * @param int $status: Error Code (compatible with HTTP status code)
     * @param Throwable $previous: Previous exception in chain
     * @return null
     */
    public static function throw(
        array $error,
        array $context = [],
        int $status = 500,
        Throwable $previous = null,
        $domain = null
    ) {
        Response::exception($status, $error, parse_throwable($previous, $context), $domain);
    }

    public static function isBooted() : bool
    {
        return self::$booted;
    }

    public static function getContext(bool $sapi = true) : array
    {
        $request  = Request::getContext($sapi);
        $response = Response::getContext();

        return $sapi ? ['web' => $request] : [$request, $response, Core::getContext()];
    }
}
