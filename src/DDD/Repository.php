<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

/**
 * The repository abstract persistence access, whatever storage it is. That is its purpose.
 * THe fact that you're using a db or xml files or an ORM doesn't matter.
 * The Repository allows the rest of the application to ignore persistence details.
 * This way, you can easily test the app via mocking or stubbing and you can change storages if it's needed.
 *
 * Repositories deal with Domain/Business objects (from the app point of view), an ORM handles db objects.
 * A business objects IS NOT a db object, first has behaviour, the second is a glorified DTO, it only holds data.
 */
interface Repository
{
}
