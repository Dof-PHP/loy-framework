<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Web\Route;
use Loy\Framework\Web\Request;
use Loy\Framework\Web\Response;
use Loy\Framework\Core\Exception\ApplicationServiceNotExistsExeception;

class Port
{
    protected $route;
    protected $request;
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
                throw new ApplicationServiceNotExistsExeception((string) $service);
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
