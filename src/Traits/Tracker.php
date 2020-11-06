<?php

declare(strict_types=1);

namespace DOF\Traits;

use Closure;
use DOF\Container;
use DOF\Tracer;
use DOF\Surrogate\Log;
use DOF\Util\Rand;

trait Tracker
{
    /**
     * @var string: UUID of this kernel instance, serial number of process lifetime
     * @Annotation(0)
     */
    public $__TRACE_SN__ = null;

    /**
     * @var int: Incremental ID of object been created during kernel lifetime
     * @Annotation(0)
     */
    public $__TRACE_ID__ = 0;

    /**
     * @var object: Reference of the root of trace chain
     * @Annotation(0)
     */
    public $__TRACE_ROOT__ = null;

    /**
     * @var array: Context appended into this tracker
     * @Annotation(0)
     */
    public $__CONTEXT__ = [];

    /**
     * @var array: Callbacks registered on kernel
     * @Annotation(0)
     */
    public $__CALLBACK__ = [];

    /**
     * @var array: Loggingable status of components loaded into this tracker
     * @Annotation(0)
     */
    public $__LOGGING__ = [];

    /**
     * @var string: Serial number of kernel lifetime
     * @Annotation(0)
     */
    public $__LOGGER__ = null;

    /**
     * Unregister a event on trace root
     *
     * @Annotation(0)
     */
    final public function unregister(string $event, string $origin = null)
    {
        $event = \trim(\strtolower($event));

        if ($this->__TRACE_ROOT__) {
            if (\is_null($origin)) {
                unset($this->__TRACE_ROOT__->__CALLBACK__[$event]);
                return;
            }
            $list = $_list = $this->__TRACE_ROOT__->__CALLBACK__[$event] ?? [];
            foreach ($_list as $idx => $item) {
                foreach ($item as $origin => $callback) {
                    unset($list[$idx]);
                }
            }
            $this->__TRACE_ROOT__->__CALLBACK__[$event] = $list;
            return;
        }

        if (\is_null($origin)) {
            unset($this->__CALLBACK__[$event]);
            return;
        }
        $list = $_list = $this->__CALLBACK__[$event] ?? [];
        foreach ($_list as $idx => $item) {
            foreach ($item as $origin => $callback) {
                unset($list[$idx]);
            }
        }
        $this->__CALLBACK__[$event] = $list;
    }

    /**
     * Register a event on trace root
     *
     * @Annotation(0)
     */
    final public function register(string $event, string $origin, Closure $callback)
    {
        $event = \trim(\strtolower($event));

        if ($this->__TRACE_ROOT__) {
            $list = $this->__TRACE_ROOT__->__CALLBACK__[$event] ?? [];
            // \array_unshift($list, [$origin => $callback]);
            \array_push($list, [$origin => $callback]);
            $this->__TRACE_ROOT__->__CALLBACK__[$event] = $list;
            return;
        }

        $list = $this->__CALLBACK__[$event] ?? [];
        // \array_unshift($list, [$origin => $callback]);
        \array_push($list, [$origin => $callback]);
        $this->__CALLBACK__[$event] = $list;
    }
  
    /**
     * Append a group of context by key on trace root
     *
     * @Annotation(0)
     */
    final public function context(string $key, array $context, string $_key = null)
    {
        if ($this->__TRACE_ROOT__) {
            if (\is_null($_key)) {
                $this->__TRACE_ROOT__->__CONTEXT__[$key] = $context;
                return;
            }
            $this->__TRACE_ROOT__->__CONTEXT__[$key][$_key] = $context;
            return;
        }

        if (\is_null($_key)) {
            $this->__CONTEXT__[$key] = $context;
            return;
        }
        $this->__CONTEXT__[$key][$_key] = $context;
    }

    /**
     * @Annotation(0)
     */
    final public function tracer()
    {
        return new Tracer($this->__TRACE_ROOT__ ?? $this, $this->__TRACE_ID__ ?? 0);
    }

    /**
     * @Annotation(0)
     */
    final public function method(string $class, string $method, array $values = [])
    {
        return Container::build($class, $method, $values, $this->tracer());
    }

    /**
     * @Annotation(0)
     */
    final public function completion(array $parameters, array $values = [])
    {
        return Container::complete($parameters, $values, $this->tracer());
    }

    /**
     * @Annotation(0)
     */
    final public function new(string $class, ...$parameters)
    {
        $instance = new $class(...$parameters);
        $instance->__TRACE_ROOT__ = $this->__TRACE_ROOT__ ?? $this;
        $instance->__TRACE_ID__ = ++$this->__TRACE_ID__;

        return $instance;
    }

    /**
     * @Annotation(0)
     */
    final public function di(string $class)
    {
        return Container::di($class, new Tracer($this->__TRACE_ROOT__ ?? $this, ++$this->__TRACE_ID__));
    }

    /**
     * @Annotation(0)
     */
    final public function uuid()
    {
        if ($this->__TRACE_SN__) {
            return $this->__TRACE_SN__;
        }
        if ($this->__TRACE_ROOT__ && ($this->__TRACE_ROOT__->__TRACE_SN__ ?? null)) {
            return $this->__TRACE_ROOT__->__TRACE_SN__;
        }

        return $this->__TRACE_SN__ = Rand::uuid4();
    }

    /**
     * @Annotation(0)
     */
    final public function logging(string $component, bool $logging = null) : bool
    {
        $component = \strtolower($component);

        if (\is_null($logging)) {
            return $this->__TRACE_ROOT__ ? ($this->__TRACE_ROOT__->__LOGGING__[$component] ?? true) : ($this->__LOGGING__[$component] ?? true);
        }

        return $this->__TRACE_ROOT__ ? ($this->__TRACE_ROOT__->__LOGGING__[$component] = $logging) : ($this->__LOGGING__[$component] = $logging);
    }

    /**
     * @Annotation(0)
     */
    final public function logable(string $component) : bool
    {
        return $this->__TRACE_ROOT__ ? $this->__TRACE_ROOT__->logging($component) : $this->logging($component);
    }

    /**
     * Get a general logger for tracer instance
     *
     * @param Closure $callback: The callback to set logger status
     * @param bool $reset: Reset logger status after callback or not
     * @Annotation(0)
     */
    final public function logger(Closure $callback = null, bool $reset = true)
    {
        if (! $this->__LOGGER__) {
            $this->__LOGGER__ = new class(static::class, $this->uuid(), $this->__TRACE_ID__) {
                public function __construct(string $origin, string $traceSN = null, int $traceID = null)
                {
                    $this->origin = $origin;
                    $this->traceSN = $traceSN;
                    $this->traceID = $traceID;
                }
                public function logger($logger)
                {
                    $this->logger = $logger;

                    return $this;
                }
                public function __call(string $method, array $params)
                {
                    switch ($method) {
                        case 'emergency':
                        case 'alert':
                        case 'critical':
                        case 'error':
                        case 'warning':
                        case 'notice':
                        case 'info':
                        case 'debug':
                        case 'exception':
                        case 'trace':
                        case 'exceptor':
                            $params[0] = [$params[0], $this->origin, $this->traceSN, $this->traceID];
                            break;
                        case 'log':
                            $params[1] = [$params[1], $this->origin, $this->traceSN, $this->traceID];
                            break;
                         default:
                            break;
                    }

                    return \call_user_func_array([$this->logger, $method], $params);
                }
            };
        }

        $logger = $reset ? Log::new() : Log::instance();
        if ($callback) {
            $callback($logger);
            if (! $reset) {
                // Permanently update logger status if there's no reset requirement
                Log::instance($logger);
            }
        }

        return $this->__LOGGER__->logger($logger);
    }
}
