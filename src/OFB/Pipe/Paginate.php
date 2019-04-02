<?php

declare(strict_types=1);

namespace Loy\Framework\OFB\Pipe;

use Loy\Framework\DSL\IFRSN;

class Paginate
{
    public function pipein($request, $response, $route)
    {
        $paginate = $request->get('__paginate');
        if ($paginate) {
            $paginate = sprintf('paginate(%s)', $paginate);
            $paginate = IFRSN::parse($paginate);
            $paginate = $paginate['paginate']['fields'] ?? false;
            if ($paginate) {
                $size = intval($paginate['size'] ?? $this->getPaginateDefaultSize());
                $size = $size < 0 ? $this->getPaginateDefaultSize() : $size;
                $page = intval($paginate['page'] ?? 1);
                $page = $page < 1 ? 1 : $page;
            }
        } else {
            $paginate = [
                'size' => $this->getPaginateDefaultSize(),
                'page' => 1,
            ];
        }

        $route->params->pipe->set(__CLASS__, collect($paginate));

        return true;
    }

    private function getPaginateDefaultSize() : int
    {
        return 20;
    }

    private function getPaginateParameterName() : string
    {
        return '__paginate';
    }
}
