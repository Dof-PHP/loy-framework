<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Web\Route;
use Loy\Framework\Web\Request;
use Loy\Framework\Web\Response;

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
}
