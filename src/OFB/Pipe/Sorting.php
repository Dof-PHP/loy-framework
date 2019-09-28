<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Pipe;

use Dof\Framework\Facade\Response;
use Dof\Framework\DSL\IFRSN;
use Dof\Framework\EXCP;

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
            $order = $sortOrder;
        }

        if (! ciin($order, ['asc', 'desc'])) {
            $order = null;
        }

        if ($field) {
            $allow = $port->pipein->get(static::class)->fields;    // annotation extra parameter named as `fields`
            if ((! is_null($allow))&& (! in_list($field, $allow))) {
                Response::abort(400, EXCP::INVALID_SORTING_FIELD, compact('field', 'allow'), $port->get('class'));
            }
        }

        $route->params->pipe->set(static::class, collect([
            'field' => $field,
            'order' => $order,
        ]));

        return true;
    }
}
