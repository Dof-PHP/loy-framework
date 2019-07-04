<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Pipe;

use Dof\Framework\DSL\IFRSN;

class Sorting
{
    public function pipein($request, $response, $route, $port)
    {
        $sort = $request->all('__sort');
        $field = $order = null;
        if ($sort) {
            $sort = sprintf('sort(%s)', $sort);
            $sort = IFRSN::parse($sort);
            $sort = $sort['sort']['fields'] ?? null;
            if ($sort) {
                $field = $sort['field'] ?? null;
                $order = $sort['order'] ?? null;
            }
        }
        if ($sortField = $request->all('__sort_field', null)) {
            $field = $sortField;
        }
        if ($sortOrder = $request->all('__sort_order', null)) {
            $order = 'desc';
        }

        if (! ciin($order, ['asc', 'desc'])) {
            $order = null;
        }

        $route->params->pipe->set(__CLASS__, collect([
            'field' => $field,
            'order' => $order,
        ]));

        return true;
    }
}
