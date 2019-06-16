<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Dof\Framework\EntityManager;
use Dof\Framework\TypeHint;

/**
 * A special model which has identity
 */
abstract class Entity extends Model
{
    /**
     * @Title(Entity Identity)
     * @Type(Uint)
     */
    protected $id;

    public static function annotations()
    {
        return EntityManager::get(static::class);
    }

    public static function init(array $data)
    {
        $entity = static::class;
        $instance = new $entity;
        $annotations = $this->annotations();

        foreach ($data as $property => $val) {
            $attr = $annotations['properties'][$property] ?? null;
            if (! $attr) {
                continue;
            }
            $type = $attr['TYPE'] ?? null;
            if (! $type) {
                exception('MissingTypeInEntityProperty', compact('property', 'entity'));
            }

            $setter = 'set'.ucfirst($property);
            $instance->{$setter}(TypeHint::convert($val, $type));
        }

        if (is_null($instance->getId())) {
            exception('MissingEntityIdentity', compact('data', 'entity'));
        }

        return $instance;
    }

    final public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    final public function getPk()
    {
        return $this->id;
    }

    final public function getId()
    {
        return $this->id;
    }

    public function set(string $attr, $val)
    {
        $annotation = EntityManager::get(static::class);
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
}
