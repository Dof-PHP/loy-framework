<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Http\Port__NAMESPACE__;

// use Dof\Framework\OFB\Pipe\Paginate;
// use Domain\__DOMAIN__\EXCP;
// use Domain\__DOMAIN__\Service\Action__NAME__;
// use Dof\Framework\OFB\Wrapper\Pagination;

/**
 * @Author(name@group.com)
 * @Version(v1)
 * @Auth(0)
 * @Status(0)
 * @Route(__NAME__)
 * @_Group(__NAME__)
 * @Autonomy(0)
 * @MimeOut(json)
 * @_PipeIn()
 */
class __NAME__
{
    /**
     * @Title(Parameter Title)
     * @Type(String)
     * @_Compatible()
     */
    private $param1;

    /**
     * @Title(Port Title)
     * @_SubTitle(Port SubTitle)
     * @Route(/)
     * @Verb(POST)
     * @Argument(param1)
     * @_NotRoute(1)
     * @_NoDump(1)
     * @_NoDoc(1)
     * @_Assembler(Domain\__DOMAIN__\Assembler\__NAME__)
     * @_Model(Domain\__DOMAIN__\Entity\__NAME__)
     * @_Logging(Domain\__DOMAIN__\Repository\__NAME__LogRepository)
     * @_LogMaskKey()
     * @_InfoOK(Success)
     * @_CodeOK(200)
     */
    public function action(Action__NAME__ $service)
    {
        // $service->error(EXCP::_, 500);

        // extract(pargvs());

        return $service
            ->setParam1(port('argument')->param1)
            ->execute();
    }
}
