<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Model;

use Dof\Framework\DDD\Model;

/**
 * @Title(Common Key-Title model)
 */
class KeyTitle extends Model
{
    /**
     * @Title(Key)
     * @Type(String)
     */
    protected $key;

    /**
     * @Title(Title)
     * @Type(String)
     */
    protected $title;
}
