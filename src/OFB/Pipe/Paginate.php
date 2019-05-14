<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Pipe;

use Dof\Framework\DSL\IFRSN;

class Paginate
{
    public function pipein($request, $response, $route, $port)
    {
        $size = $this->getPaginateDefaultSize();
        $page = 1;
        $paginate = $request->get('__paginate');
        if ($paginate) {
            $paginate = sprintf('paginate(%s)', $paginate);
            $paginate = IFRSN::parse($paginate);
            $paginate = $paginate['paginate']['fields'] ?? null;
            if ($paginate) {
                $size = $paginate['size'] ?? $this->getPaginateDefaultSize();
                $page = $paginate['page'] ?? 1;
            }
        }

        if ($paginateSize = $request->get('__paginate_size', null)) {
            $size = $paginateSize;
        }
        if ($paginatePage = $request->get('__paginate_page', null)) {
            $page = $paginatePage;
        }

        $route->params->pipe->set(__CLASS__, collect([
            'size' => $this->validateSize($size),
            'page' => $this->validatePage($page),
        ]));

        return true;
    }

    protected function validateSize($size) : int
    {
        $size = intval($size);
        $size = $size < 0 ? $this->getPaginateDefaultSize() : $size;
        $size = $size > $this->getPaginateMaxSize() ? $this->getPaginateMaxSize() : $size;

        return $size;
    }

    protected function validatePage($page) : int
    {
        $page = intval($page);
        $page = $page < 1 ? 1 : $page;

        return $page;
    }

    protected function getPaginateMaxSize() : int
    {
        return 50;
    }

    protected function getPaginateDefaultSize() : int
    {
        return 10;
    }

    protected function getPaginateParameterName() : string
    {
        return '__paginate';
    }
}
