Repository is the abstract layer of storage details, manages entity and abstract ORM.

Repository defines the storage apis, and all the storage operations are directly called via repository.

## Event && Callback

There are a series of builtin events along with repsitory. We can use them by override these callback methods in storage class if necessary.

### `onEntityQueried`

### `onEntityAdded`

### `onEntityRemoved`

### `onEntityUpdated`

### `onEntityCached`

### `onEntityCacheHit`

### `onEntityCacheMissed`

### `onEntityCacheUpdated`
