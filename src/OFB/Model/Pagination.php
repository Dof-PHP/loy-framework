<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Model;

/**
 * @Title(Pagination field format)
 */
class Pagination
{
    /**
     * @Title(Total count of items in current query conditions)
     * @Type(Uint)
     */
    private $total;

    /**
     * @Title(Count of items in current page)
     * @Type(Uint)
     */
    private $count;

    /**
     * @Title(Current Page Number)
     * @Type(Pint)
     */
    private $page;

    /**
     * @Title(Current Page Size)
     * @Type(Uint)
     */
    private $size;
}
