Port is a set of HTTP endpoints which exposed to clients.

Annotations in both port class and port method define the HTTP apis.

There are a series of simple annotation keywords syntax used in Port. 

> Notes: all keywords are case-insensitive.

## Keyword

### `route`

Part of request uri. If both class annotation and method annotation are defined `route`, then the ultimate route is the combination of them. 

### `notroute`

Ask for ignorance of this port explicitly, thus this class or method will not expose to client.

### `autonomy`

**Applicable only to class annotation**, determines where the whole port class is one single route which does one single thing.

### `verb`

Allowed request methods on this port, support multiple http verbs.

### `pipe`

The pipelines that current requested route will through before excite port method.

### `alias`

The unique alias name of a route definition.

### `mimein`

The request content type alias setting.

### `mimeout`

The response content type alias setting.

### `wrapin`

The request body parameters definition and validator.

### `wrapout`

The success response body wrapper format.

### `wraperr` 

The failed response body wrapper format.

### `suffix` 

The supported url suffix for that route(URL), it's useful for HTTP caching like cdn.

The suffix annotation definition will not occupy the route path definition, one route path can have multiple suffix defnitions.

## Example

``` php
<?php

declare(strict_types=1);

namespace Domain\User\Http\Port;

use Dof\Framework\Web\Port;

/**
 * @route(users)
 * @pipe(api_auth)
 * @pipe(p0,p1)
 * @pipe(p2)
 * @pipe(p3)
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