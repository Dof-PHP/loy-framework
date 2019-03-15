<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Facade\Request;
use Loy\Framework\Facade\Response;

class Port
{
    /**
     * The route collection instance related to current request
     */
    protected $route;

    /**
     * The request instance related to current http session
     */
    protected $request;

    /**
     * The response instance related to current http session
     */
    protected $response;

    public function __construct()
    {
        $this->route    = Route::getInstance();
        $this->request  = Request::getInstance();
        $this->response = Response::getInstance();
    }

    public function __get(string $key)
    {
        return $this->{$key} ?? null;
    }

    public function call($service, array $params = [])
    {
        $object = $service;
        if (! is_object($service)) {
            if (! class_exists($service)) {
                exception('ApplicationServiceNotExists', ['service' => string_literal($service)]);
            }
            $object = new $service;
        }

        foreach ($params as $key => $val) {
            $setter = 'set'.ucfirst((string) $key);
            if (method_exists($service, $setter)) {
                $object->{$setter}($val);
            }
        }

        return $object->execute();
    }
}
