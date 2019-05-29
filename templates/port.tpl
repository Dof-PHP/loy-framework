<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Http\Port__NAMESPACE__;

use Dof\Framework\OFB\Pipe\Paginate;
use Domain\Auth\Http\ERR;
use Domain\Auth\Service\Create__NAME__;
use Domain\Auth\Service\Show__NAME__;
use Domain\Auth\Service\Delete__NAME__;
use Domain\Auth\Service\List__NAME__;
use Domain\Auth\Service\Update__NAME__;

/**
 * @Author(name@group.com)
 * @Version(v1)
 * @Auth(0)
 * @Group(?/?){?/?}
 * @Route(__NAME__)
 * @Model(Domain\__DOMAIN__\Entity\__NAME__)
 * @MimeOut(json)
 */
class __NAME__
{
    /**
     * @Title(__NAME__ ID)
     * @Type(Pint)
     */
    private $id;

    /**
     * @Title(ARGV_TITLE)
     * @Type(String)
     */
    private $argv2;

    /**
     * @Title(Create Resource __NAME__)
     * @Route(/)
     * @Verb(post)
     * @Argument(argv2)
     * @HeaderStatus(201){Created Success}
     */
    public function create(Create__NAME__ $service)
    {
        // $service->error(ERR::_, 500);

        return $service
            ->setArgv2(strval(port()->argument->argv2))
            ->execute();
    }

    /**
     * @Title(Delete Resource __NAME__)
     * @Route({id})
     * @Verb(delete)
     * @HeaderStatus(204){Deleted Success}
     */
    public function delete(int $id, Delete__NAME__ $service)
    {
        // $service->error(ERR::_, 404);

        $service->setId($id)->execute();

        response()->setStatus(204);
    }

    /**
     * @Title(Update Resource __NAME__)
     * @Route({id})
     * @Verb(put)
     * @Argument(argv2){need:0}
     */
    public function update(int $id, Update__NAME__ $service)
    {
        // $service->error(ERR::_, 500);

        return $service->setId($id)->execute();
    }

    /**
     * @Title(Show Resource __NAME__ Detail)
     * @Route({id})
     * @Verb(get)
     */
    public function show(int $id, Show__NAME__ $service)
    {
        return $service->setId($id)->execute();
    }

    /**
     * @Title(List Resource __NAME__ with Pagination)
     * @Route(/)
     * @Verb(get)
     * @PipeIn(Paginate)
     * @WrapOut(Dof\Framework\OFB\Wrapper\Pagination)
     */
    public function list(List__NAME__ $service)
    {
        $params = route('params')->pipe->get(Paginate::class);

        return $service->setPage($params->page)->setSize($params->size)->execute();
    }
}
