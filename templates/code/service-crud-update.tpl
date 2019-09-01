<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Service\CRUD;

use Throwable;
use Dof\Framework\EXCP;
use Dof\Framework\DDD\Service;
use Domain\__DOMAIN__\Repository\__ENTITY__Repository;

class Update__ENTITY__ extends Service
{
    private $data = [];
    private $param1;
    // private $attr1;

    private $id;
    private $repository;
    private $entity;
    private $_entity;

    public function __construct(__ENTITY__Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute()
    {
        if (($this->id < 1) || (! $this->entity) || (! $this->_entity)) {
            $this->exception('Missing__ENTITY__OrIdentity');
        }

        foreach ($this->data as $attr => $val) {
            if ((! is_null($val)) && property_exists($this->entity, $attr)) {
                $this->entity->{$attr} = $val;
            }
        }

        // if ($this->param1) {
        // ...
        // }

        if (! $this->entity->compare($this->_entity)) {
            $this->exception('NothingToUpdate');
            // return $this->entity;
        }

        $this->entity->updatedAt = time();

        try {
            return $this->repository->save($this->entity);
        } catch (Throwable $e) {
            if (EXCP::is($e, EXCP::VIOLATED_UNIQUE_CONSTRAINT)) {
                $this->exception('Duplicate__ENTITY__Attr1', ['attr1' => $this->attr1]);
            }

            $this->exception('Update__ENTITY__Failed', [], $e);
        }
    }

    public function setId(int $id)
    {
        // Check entity existence in setter to fail quickly
        $this->entity = $this->repository->find($id);
        if (! $this->entity) {
            $this->exception('__ENTITY__NotExists', compact('id'));
        }

        $this->id = $id;
        $this->_entity = clone $this->entity;

        return $this;
    }

    public function setParam1(string $param1 = null)
    {
        $this->param1 = $param1;

        return $this;
    }

    public function setAttr1(string $attr1 = null)
    {
        $this->data['attr1'] = $attr1;

        return $this;
    }
}
