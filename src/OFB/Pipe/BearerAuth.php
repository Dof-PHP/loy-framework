<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Pipe;

use Throwable;
use Dof\Framework\ConfigManager;
use Dof\Framework\Facade\JWT;
use Dof\Framework\Facade\Response;
use Dof\Framework\Web\ERR;

/**
 * AUTHORIZATION: Bearer {token}
 */
class BearerAuth
{
    /** @var string: The bearer auth token found in request */
    protected $token;

    /** @var string: The token secret used for signature, can be overwrite by subclass */
    protected $secret = 'bear-auth-token-secret';

    /** @var string: The parameter key to store authenticated user id */
    protected $authid = 'uid';

    /** @var array: The parameter names will be checked in request when AUTHORIZATION header not found */
    protected $allowTokenParameters = ['token', 'bearer_token', 'auth_token'];

    public function pipein($request, $response, $route, $port)
    {
        $header = trim((string) $request->getHeader('AUTHORIZATION'));
        if ($header) {
            if (! ci_equal(mb_substr($header, 0, 7), 'Bearer ')) {
                Response::abort(401, ERR::INVALID_BEARER_TOKEN, [], $port->get('class'));
            }

            $token = mb_substr($header, 7);
        } elseif ($this->allowTokenParameters) {
            $key   = null;
            $token = $request->match($this->allowTokenParameters, $key);
        } else {
            $token = '';
        }

        $token = trim($token);
        if (! $token) {
            Response::abort(401, ERR::MISSING_TOKEN_HEADER_OR_PARAMETER, [], $port->get('class'));
        }

        $secret = ConfigManager::getDomainFinalEnvByNamespace($route->get('class'), $this->secret);
        if (! $secret) {
            Response::abort(500, ERR::TOKEN_SECRET_MISSING, [
                'key' => $this->secret,
                'ns'  => static::class,
            ], $port->get('class'));
        }
        $id = $secret[0] ?? null;
        if (is_null($id)) {
            Response::abort(500, ERR::TOKEN_SECRET_ID_MISSING, [], $port->get('class'));
        }
        $key = $secret[1] ?? null;
        if (is_null($key) || (! $key)) {
            Response::abort(500, ERR::TOKEN_SECRET_KEY_MISSING, [], $port->get('class'));
        }

        try {
            $uid = JWT::setSecretId($id)->setSecretKey($key)->verify($token);

            $route->params->pipe->set(static::class, collect([
                $this->authid => $uid,
                'token' => $token,
            ]));
        } catch (Throwable $e) {
            $message = method_exists($e, 'getName') ? $e->getName() : $e->getMessage();
            $context = [];
            $context = parse_throwable($e, $context);
            $context['__error'] = $message;

            Response::abort(400, ERR::JWT_TOKEN_VERIFY_FAILED, $context, $port->get('class'));
        }

        $this->token = $token;

        return true;
    }
}
