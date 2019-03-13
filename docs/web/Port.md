Port is a set of HTTP endpoints which exposed to clients.

Annotations in both port class and port method define the HTTP apis.

There are a series of simple annotation keywords syntax used in Port. 

> Notes: all keywords are case-insensitive.

## Keyword

### `route`

Part of request uri. If both class annotation and method annotation are defined `route`, then the ultimate route is the combination of them. 

### `notroute`

Ask for ignorance of this port explicitly, thus this class or method will not expose to client.

### `verb`

Allowed request methods on this port, support multiple http verbs.

### `alias`

### `alias`

### `mimein` 

### `mimeout` 

### `wrapin` 

### `wrapout` 

### `wraperr` 

### `suffix` 

## Example

``` php
<?php

declare(strict_types=1);

namespace Domain\User\Http\Port;

use Loy\Framework\Web\Port;

/**
 * @route(users)
 * @pipe(api_auth)
 * @wraperr(error.default)
 * @wrapout(output.classic)
 * @suffix(xml,json)
 * @mimeout(json)
 */
class User extends Port
{
    /**
     * @route(/)
     * @verb(get)
     * @mimeout(json)
     * @wrapout()
     */
    public function list()
    {
        // TODO
    }
}
```