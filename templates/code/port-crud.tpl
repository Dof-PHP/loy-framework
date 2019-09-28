<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Http\Port__NAMESPACE__;

use Dof\Framework\OFB\Pipe\Paginate;
use Dof\Framework\OFB\Pipe\Sorting;
use Domain\__DOMAIN__\EXCP;
use Domain\__DOMAIN__\Service\CRUD\Create__NAME__;
use Domain\__DOMAIN__\Service\CRUD\Delete__NAME__;
use Domain\__DOMAIN__\Service\CRUD\Update__NAME__;
use Domain\__DOMAIN__\Service\CRUD\Show__NAME__;
use Domain\__DOMAIN__\Service\CRUD\List__NAME__;

/**
 * @Author(name@group.com)
 * @Version(v1)
 * @Auth(0)
 * @_Group(__DOMAIN__/__NAME__)
 * @Route(__NAME__)
 * @Model(Domain\__DOMAIN__\Entity\__NAME__)
 * @Assembler(Domain\__DOMAIN__\Assembler\__NAME__)
 * @MimeOut(json)
 * @Autonomy(0)
 * @_PipeIn()
 */
class __NAME__
{
    /**
     * @Title(__NAME__ ID)
     * @Type(Pint)
     * @_Compatible()
     */
    private $id;

    /**
     * @Title(Parameter Title)
     * @Type(String)
     */
    private $param1;

    /**
     * @Title(Serach Keyword)
     * @Type(String)
     * @Compatible(keyword)
     */
    private $search;

    /**
     * @Title(Create Resource __NAME__)
     * @_SubTitle(Sub Title Create Resource __NAME__)
     * @Route(/)
     * @Verb(POST)
     * @_NotRoute(1)
     * @_NoDump(1)
     * @_NoDoc(1)
     * @Argument(param1)
     * @_HeaderStatus(201){Created Success}
     * @_Logging(Domain\__DOMAIN__\Repository\Create__NAME__LogRepository)
     * @_LogMaskKey()
     * @_InfoOK(Success)
     * @_CodeOK(201)
     */
    public function create(Create__NAME__ $service)
    {
        // $service->error(EXCP::_, 500);
        // extract(pargvs());

        return $service
            ->setParam1(port('argument')->param1)
            ->execute();
    }

    /**
     * @Title(Delete Resource __NAME__)
     * @Route({id})
     * @Verb(DELETE)
     * @_NotRoute(1)
     * @_NoDump(1)
     * @_NoDoc(1)
     * @Argument(id){inroute}
     * @Assembler(_)
     * @Model(_)
     * @_HeaderStatus(204){Deleted Success}
     * @_Logging(Domain\__DOMAIN__\Repository\Delete__NAME__LogRepository)
     * @_InfoOK(Success)
     * @_CodeOK(204)
     */
    public function delete(int $id, Delete__NAME__ $service)
    {
        // $service->error(EXCP::__NAME___NOT_EXISTS, 404);

        $service->setId($id)->execute();

        // response()->setStatus(204);
    }

    /**
     * @_NotRoute(1)
     * @Title(Update Resource __NAME__)
     * @Route({id})
     * @Verb(PUT)
     * @_NotRoute(1)
     * @_NoDump(1)
     * @_NoDoc(1)
     * @Argument(id){inroute}
     * @Argument(param1){need:0}
     * @_Logging(Domain\__DOMAIN__\Repository\Update__NAME__LogRepository)
     * @_LogMaskKey()
     */
    public function update(int $id, Update__NAME__ $service)
    {
        // $service->error(EXCP::__NAME___NOT_EXISTS, 404);
        // $service->error(EXCP::NOTHING_TO_UPDATE, 202);

        // extract(pargvs());

        return $service
            ->setId($id)
            ->setParam1(port('argument')->param1)
            ->execute();
    }

    /**
     * @Title(Show Resource __NAME__ Detail)
     * @Route({id})
     * @Verb(GET)
     * @_NotRoute(1)
     * @_NoDump(1)
     * @_NoDoc(1)
     * @Argument(id){inroute}
     * @_Logging(Domain\__DOMAIN__\Repository\Show__NAME__LogRepository)
     */
    public function show(int $id, Show__NAME__ $service)
    {
        return $service->setId($id)->execute();
    }

    /**
     * @Title(List Resource __NAME__ with Pagination)
     * @Route(/)
     * @Verb(GET)
     * @_NotRoute(1)
     * @_NoDump(1)
     * @_NoDoc(1)
     * @PipeIn(Paginate)
     * @PipeIn(Sorting){fields:id,createdAt,updatedAt}
     * @Argument(search){need:0}
     * @WrapOut(Dof\Framework\OFB\Wrapper\Pagination)
     * @_Logging(Domain\__DOMAIN__\Repository\List__NAME__LogRepository)
     * @_MaxPageSize(200)
     */
    public function list(List__NAME__ $service)
    {
        $paginate = route('params')->pipe->get(Paginate::class);
        $sorting = route('params')->pipe->get(Sorting::class);

        return $service
            ->setPage($paginate->page)
            ->setSize($paginate->size)
            ->setSortField($sorting->field)
            ->setSortOrder($sorting->order)
            ->setFilter(port('argument'))
            ->execute();
    }
}
