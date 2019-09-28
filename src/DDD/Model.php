<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Dof\Framework\TypeHint;
use Dof\Framework\ModelManager;
use Dof\Framework\Container;
use Dof\Framework\OFB\Traits\DI;

/**
 * Data Model
 */
abstract class Model
{
    use DI;

    /**
     * @Title(OnCreated callback status)
     * @Type(Bool)
     * @NoDiff(1)
     */
    private $__onCreated = true;

    /**
     * @Title(OnRemoved callback status)
     * @Type(Bool)
     * @NoDiff(1)
     */
    private $__onRemoved = true;

    /**
     * @Title(OnUpdated callback status)
     * @Type(Bool)
     * @NoDiff(1)
     */
    private $__onUpdated = true;

    /**
     * Compare two data model and get there differences
     *
     * @param Model $model1
     * @param Model $model2
     * @param Array|Null $nodiff: The property names of data model do not need to diff
     * @return null|array: The diff result in order [#0, #1] or null when no differences
     */
    final public static function diff(Model $model1, Model $model2, array $nodiff = null) : ?array
    {
        if (is_null($nodiff)) {
            $nodiff = array_unique_merge($model1->nodiff(), $model2->nodiff());
        }

        $current = $model1->toArray();
        $compare = $__compare = $model2->toArray();

        $diff = [];
        foreach ($current as $attr => $val) {
            if (in_array($attr, $nodiff)) {
                unset($__compare[$attr]);
                continue;
            }

            if (! array_key_exists($attr, $compare)) {
                $diff[] = $attr;
                continue;
            }

            unset($__compare[$attr]);

            $_val = $compare[$attr] ?? null;
            if ($val !== $_val) {
                $diff[] = $attr;
            }
        }

        $diff = array_unique_merge($diff, array_keys($__compare));
        if (! $diff) {
            return null;
        }

        $result = [];
        foreach ($diff as $key) {
            $result[$key] = [$current[$key] ?? null, $compare[$key] ?? null];
        }

        return $result;
    }

    /**
     * Get nodiff property names of data model/entity
     */
    final public function nodiff() : array
    {
        $attrs = $this::attrs();
        if (! $attrs) {
            return [];
        }

        $nodiff = [];
        foreach ($attrs as $name => $attr) {
            if ($attr['NODIFF'] ?? false) {
                $nodiff[] = $name;
            }
        }

        return $nodiff;
    }

    /**
     * Compare given data model object to current model instance
     *
     * @param Model $model: The data model given to compare aginst current model instance
     * @param Array|Null $nodiff: The property names of data model do not need to diff
     * @return null|array: The diff result in order [#0-self, #1-other] or null when no differences
     */
    final public function compare(Model $model, array $nodiff = null) : ?array
    {
        return Model::diff($this, $model, $nodiff);
    }

    public static function attrs()
    {
        return static::annotations()['properties'] ?? [];
    }

    public static function meta()
    {
        return static::annotations()['meta'] ?? [];
    }

    public static function annotations()
    {
        return ModelManager::get(static::class);
    }

    public static function init(array $data)
    {
        $model = static::class;
        $instance = new $model;
        $annotations = $this->annotations();

        foreach ($data as $property => $val) {
            $attr = $annotations['properties'][$property] ?? null;
            if (! $attr) {
                continue;
            }
            $type = $attr['TYPE'] ?? null;
            if (! $type) {
                exception('MissingTypeInModelProperty', compact('property', 'model'));
            }

            $instance->{$property} = TypeHint::convert($val, $type, true);
        }

        return $instance;
    }

    // public function onCreated()
    // {
    // }
    // public function onUpdated($_entity)
    // {
    // }
    // public function onDeleted()
    // {
    // }
    // public function onRemoved()
    // {
    // }
    // public function onRead()
    // {
    // }
    // public function onReadOrigin()
    // {
    // }
    // public function onReadCache()
    // {
    // }

    final public function get(string $attr)
    {
        if (property_exists($this, $attr)) {
            $val = $this->{$attr} ?? null;
            if (! is_null($val)) {
                return $val;
            }
            $getter = 'get'.ucfirst($attr);
            if (method_exists($this, $getter)) {
                $params = Container::build(static::class, $getter);
                return $this->{$getter}(...$params);
            }
        }
    }

    public function set(string $attr, $val)
    {
        $annotation = $this::annotations();
        $type = $annotation['properties'][$attr]['TYPE'] ?? null;
        if ($type) {
            $val = TypeHint::convert($val, $type, true);
        }

        if (property_exists($this, $attr)) {
            $setter = 'set'.ucfirst($attr);
            if (method_exists($this, $setter)) {
                $this->{$setter}($val);
            } else {
                $this->{$attr} = $val;
            }
        } else {
            $this->{$attr} = $val;
        }

        return $this;
    }

    final public function __get(string $attr)
    {
        return $this->get($attr);
    }

    final public function __set(string $attr, $val)
    {
        return $this->set($attr, $val);
    }

    final public function __call(string $method, array $params = [])
    {
        if (0 === strpos($method, 'get')) {
            if ('get' !== $method) {
                $attr = lcfirst(substr($method, 3));
                return $this->get($attr);
            }
        }

        if (0 === strpos($method, 'set')) {
            if ('set' !== $method) {
                $attr = lcfirst(substr($method, 3));

                return $this->set($attr, ($params[0] ?? null));
            }
        }

        $class = static::class;
        exception('MethodNotExists', compact('method', 'class'));
    }

    final public function __toArray()
    {
        $array = get_object_vars($this);

        unset($array['__singleton__']);
        unset($array['__onCreated']);
        unset($array['__onRemoved']);
        unset($array['__onUpdated']);

        return $array;
    }

    final public function toArray()
    {
        return $this->__toArray();
    }

    final public function __toXml()
    {
        return enxml($this->toArray());
    }

    final public function __toJson()
    {
        return enjson($this->toArray());
    }

    // public function __sleep()
    // {
    // return array_keys($this->__toArray());
    // }

    final public function __toString()
    {
        return serialize($this);
    }

    final public function toString()
    {
        return $this->__toString();
    }

    final public function __getOnCreated()
    {
        return $this->__onCreated;
    }

    final public function __setOnCreated(bool $onCreated)
    {
        $this->__onCreated = $onCreated;

        return $this;
    }

    final public function __getOnRemoved()
    {
        return $this->__onRemoved;
    }

    final public function __setOnRemoved(bool $onRemoved)
    {
        $this->__onRemoved = $onRemoved;

        return $this;
    }

    final public function __getOnUpdated()
    {
        return $this->__onUpdated;
    }

    final public function __setOnUpdated(bool $onUpdated)
    {
        $this->__onUpdated = $onUpdated;

        return $this;
    }
}
