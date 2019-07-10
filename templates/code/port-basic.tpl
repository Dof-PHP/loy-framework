<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Http\Port__NAMESPACE__;

// use Dof\Framework\OFB\Pipe\Paginate;
// use Domain\__DOMAIN__\Http\ERR;
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
 * @_Model(param1)
 * @MimeOut(json)
 */
class __NAME__
{
    /**
     * @Title(Parameter Title)
     * @Type(String)
     */
    private $param1;

    /**
     * @Title(Port Title)
     * @_SubTitle(Port SubTitle)
     * @Route(/)
     * @Verb(POST)
     * @Argument(param1)
     * @_Logging(Domain\__DOMAIN__\Repository\__NAME__LogRepository)
     */
    public function action(Action__NAME__ $service)
    {
        // extract(pargvs());
        // $service->error(ERR::_, 500);

        return $service
            ->setParam1(port('argument')->param1)
            ->execute();
    }
}
