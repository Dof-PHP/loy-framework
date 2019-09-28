<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Http\Port__NAMESPACE__;

// use Dof\Framework\OFB\Pipe\Paginate;
// use Dof\Framework\OFB\Pipe\Sorting;
// use Domain\__DOMAIN__\EXCP;
// use Dof\Framework\OFB\Wrapper\Pagination;
// use Domain\__DOMAIN__\Service\__NAME__ as Service;

/**
 * @Author(name@group.com)
 * @Version(v1)
 * @Auth(0)
 * @Title(Port Title)
 * @_SubTitle(Port SubTitle)
 * @Status(0)
 * @Route(__NAME__)
 * @Verb(GET)
 * @Autonomy(1)
 * @MimeOut(json)
 * @_Model(Domain\__DOMAIN__\Entity\__NAME__)
 * @_Assembler(Domain\__DOMAIN__\Assembler\__NAME__)
 * @_PipeIn()
 * @_WrapOut()
 * @_Group(__NAME__)
 * @_NotRoute(1)
 * @_NoDump(1)
 * @_NoDoc(1)
 * @_Logging(Domain\Logging\Repository\__NAME__LogRepository)
 * @_InfoOK(Success)
 * @_LogMaskKey()
 * @_CodeOK(200)
 */
class __NAME__
{
    /**
     * @Title(Parameter Title)
     * @Type(String)
     * @Need(1)
     * @_Compatible()
     */
    private $param1;

    // public function execute(Service $service)
    public function execute()
    {
        // $service->error(EXCP::_, 500);

        // extract(pargvs());

        return $service
            ->setParam1(port('argument')->param1)
            ->execute();
    }
}
