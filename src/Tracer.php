<?php

declare(strict_types=1);

namespace DOF;

// Tracer model
final class Tracer
{
    /**
     * @var object: Reference of the root of trace chain
     * @Annotation(0)
     */
    public $root = null;

    /**
     * @var int: Incremental ID of object been created during process lifetime
     * @Annotation(0)
     */
    public $id = 0;

    /**
     * @var int: Tracer ID of creator of instance using Tracker
     * @Annotation(0)
     */
    public $pid = null;

    public function __construct($root = null, int $id = 0, int $pid = null)
    {
        $this->root = $root;
        $this->id = $id;
        $this->pid = $pid;
    }

    public function trace(object $instance)
    {
        $instance->__TRACE_ROOT__ = $this->root;
        $instance->__TRACE_ID__ = $this->id + 1;
    }
}
