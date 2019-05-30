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

    public static function init(array $data)
    {
        $entity = static::class;
        $instance = new $entity;
        $annotations = EntityManager::get($entity);

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

    final public function getId()
    {
        return $this->id;
    }
}
