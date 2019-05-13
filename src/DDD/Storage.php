<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Throwable;
use Dof\Framework\StorageManager;
use Dof\Framework\RepositoryManager;
use Dof\Framework\Paginator;

/**
 * Storage is the persistence layer implementations
 * In Dof, it's also the configuration of ORM
 */
abstract class Storage implements Repository
{
    /**
     * @Column(id)
     * @Type(int)
     * @Length(10)
     * @Primary(1)
     * @Notnull(1)
     */
    protected $id;

    /** @var Storage Instance */
    protected $__storage;

    public function __construct()
    {
        $this->__storage = StorageManager::init(static::class);
    }

    final public function storage()
    {
        return $this->__storage;
    }

    final public function find(int $pk) : ?Entity
    {
        $result = $this->__storage->find($pk);

        return RepositoryManager::convert(static::class, $result);
    }

    final public function convert(array $result = null)
    {
        return RepositoryManager::convert(static::class, $result);
    }

    final public function first(...$params)
    {
        $res = $this->__storage->get(...$params);

        return $res[0] ?? null;
    }

    final public function get(...$params)
    {
        return $this->__storage->get(...$params);
    }

    final public function add(Entity $entity) : ?int
    {
    }

    final public function remove($entity) : ?int
    {
        if ((! is_int($entity)) && (! ($entity instanceof Entity))) {
            return false;
        }

        if (is_int($entity)) {
            if ($entity < 1) {
                return false;
            }
        }

        $pk = is_int($entity) ? $entity : $entity->getId();

        try {
            // Ignore when entity not exists in repository
            return $this->__storage->delete($pk);

            // TODO: Flush repository cache
        } catch (Throwable $e) {
            exception('RemoveEntityFailed', ['pk' => $pk, 'class' => static::class], $e);
        }
    }

    public function paginate(int $page, int $size) : Paginator
    {
        if (method_exists($this->__storage, 'paginate')) {
            $total = $this->__storage->count();
            $list = $this->__storage->paginate($page, $size);
            foreach ($list as &$item) {
                $item = $this->convert($item);
            }

            return paginator($list, [
                'page' => $page,
                'size' => $size,
                'total' => $total,
            ]);
        }

        return [];
    }
}
