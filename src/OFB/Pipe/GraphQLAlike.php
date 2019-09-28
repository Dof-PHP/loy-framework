<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Pipe;

use Dof\Framework\DSL\IFRSN;
use Dof\Framework\Facade\Assembler;

class GraphQLAlike
{
    public function pipein($request, $response, $route, $port)
    {
        $this->findAndSetFeilds($request, $route, true);

        return true;
    }

    public function pipeout($result, $route, $port, $request, $response)
    {
        if (! $route->params->pipe->has(static::class)) {
            $this->findAndSetFeilds($request, $route, false);
        }

        $fields = $route->params->pipe->get(static::class);
        if (! $fields) {
            return null;
        }

        $assembler = $port->get('assembler');
        if ($assembler) {
            $assembler = get_annotation_ns($assembler, $route->get('class'));
            if (! class_exists($assembler)) {
                exception('AssemblerNoExists', compact('assembler'));
            }
        }

        return Assembler::assemble($result, $fields, $assembler);
    }

    private function findAndSetFeilds($request, $route, bool $exception)
    {
        $fields = $request->all($this->getFieldsParameterName());
        if (! $fields) {
            return $exception ? exception('MissingGraphQLFields') : null;
        }
        $_fields = IFRSN::parse(sprintf('graphql(%s)', $fields));
        if (empty($_fields)) {
            return $exception ? exception('InvalidGraphQLFields', compact('fields')) : null;
        }

        $route->params->pipe->set(static::class, ($_fields['graphql'] ?? null));
    }

    private function getFieldsParameterName() : string
    {
        return '__fields';
    }
}
