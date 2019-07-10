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
 * @Status(0)
 * @Route(__NAME__)
 * @Verb(GET)
 * @Autonomy(0)
 * @MimeOut(json)
 * @_Model()
 * @_PipeIn()
 * @_WrapOut()
 * @_Group(__NAME__)
 */
class __NAME__
{
    /**
     * @Title(Parameter Title)
     * @Type(String)
     * @Need(1)
     */
    private $param1;

    /**
     * @_SubTitle(Port SubTitle)
     * @_Logging(Domain\__DOMAIN__\Repository\__NAME__LogRepository)
     */
    // public function execute(Service $service)
    public function execute()
    {
        // extract(pargvs());
        // $service->error(ERR::_, 500);

        return $service
            ->setParam1(port('argument')->param1)
            ->execute();
    }
}
