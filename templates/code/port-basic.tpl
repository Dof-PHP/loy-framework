<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Http\Port__NAMESPACE__;

// use Dof\Framework\OFB\Pipe\Paginate;
// use Domain\Auth\Http\ERR;
// use Domain\Auth\Service\Action__NAME__;
// use Dof\Framework\OFB\Wrapper\Pagination;

/**
 * @Author(name@group.com)
 * @Version(v1)
 * @Auth(0)
 * @Status(0)
 * @Group(__NAME__)
 * @Route(__NAME__)
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
     * @SubTitle(Port SubTitle)
     * @Route(?)
     * @Verb(post)
     * @Argument(param1)
     */
    public function action(Action__NAME__ $service)
    {
        // $service->error(ERR::_, 500);

        return $service
            ->setParam1(port('argument')->param1)
            ->execute();
    }
}
