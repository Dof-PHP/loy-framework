<?php

declare(strict_types=1);

namespace Loy\Framework\OFB\Pipe;

use Loy\Framework\DDD\ApplicationService;
use Loy\Framework\Paginator;
use Loy\Framework\DSL\IFRSN;
use Loy\Framework\Facade\Assembler;

class GraphQL
{
    public function pipein($request, $response, $route)
    {
        $this->findAndSetFeilds($request, $route, true);

        return true;
    }

    public function pipeout($result, $route, $request, $response)
    {
        if (! $route->params->pipe->has(__CLASS__)) {
            $this->findAndSetFeilds($request, $route, false);
        }

        $fields = $route->params->pipe->get(__CLASS__);
        if (! $fields) {
            return null;
        }

        $assembler = $route->get('assembler');
        if ($assembler) {
            $assembler = get_annotation_ns($assembler, $route->get('class'));
            if (! class_exists($assembler)) {
                exception('AssemblerNoExists', compact('assembler'));
            }
        }

        if ($result instanceof ApplicationService) {
            if (! $result->isExecuted()) {
                $result = $result->execute();
            }
        }

        if ($result instanceof Paginator) {
            $meta = $result->getMeta();
            $response->addWrapout('paginator', $meta);
        }

        return Assembler::assemble($result, $fields, $assembler);
    }

    private function findAndSetFeilds($request, $route, bool $exception)
    {
        $fields = $request->get($this->getFieldsParameterName());
        if (! $fields) {
            return $exception ? exception('MissingGraphQLFields') : null;
        }
        $_fields = IFRSN::parse(sprintf('graphql(%s)', $fields));
        if (empty($_fields)) {
            return $exception ? exception('InvalidGraphQLFields', compact('fields')) : null;
        }

        $route->params->pipe->set(__CLASS__, ($_fields['graphql'] ?? null));
    }

    private function getFieldsParameterName() : string
    {
        return '__fields';
    }
}
