<?php

declare(strict_types=1);

namespace Loy\Framework\OFB\Pipe;

use Loy\Framework\DSL\IFRSN;

class GraphQL
{
    public function pipein($request, $response, $route)
    {
        $fields = $request->get('__fields');
        if (! $fields) {
            exception('MissingGraphQLFields');
        }
        $_fields = IFRSN::parse($fields);
        if (empty($_fields)) {
            exception('InvalidGraphQLFields', compact('fields'));
        }

        $route->params->pipe->set(__CLASS__, [
            'fields' => $_fields,
        ]);

        return true;
    }

    public function pipeout($result, $route)
    {
        $params = $route->params->pipe->get(__CLASS__);
        $fields = $params['fields'] ?? false;
        if (false === $fields) {
            exception('NoGraphSQLFieldsFound');
        }

        // TODO
        pd($fields);

        return $result;
    }
}
