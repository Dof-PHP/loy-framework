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
        if (! ($data['id'] ?? null)) {
            exception('MissingEntityIdentityWhenInitializing', compact('data', 'entity'));
        }

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

            $instance->{$property} = TypeHint::convert($val, $type, true);
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
}
