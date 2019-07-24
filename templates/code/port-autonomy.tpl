<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Http\Port__NAMESPACE__;

// use Dof\Framework\OFB\Pipe\Paginate;
// use Dof\Framework\OFB\Pipe\Sorting;
// use Domain\__DOMAIN__\Http\ERR;
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
 * @_Logging(Domain\Logging\Repository\__NAME__LogRepository)
 */
class __NAME__
{
    /**
     * @Title(Parameter Title)
     * @Type(String)
     * @Need(1)
     */
    private $param1;

    // public function execute(Service $service)
    public function execute()
    {
        // $service->error(ERR::_, 500);

        // extract(pargvs());

        return $service
            ->setParam1(port('argument')->param1)
            ->execute();
    }
}
