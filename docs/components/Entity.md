Entity is a complete domain object, it contains data and logics, and identity.

Entities used ORM, which just makes it easy to access the db tables in an OOP friendly way.

## Event && Callback

There are a series of builtin events along with entity. We can use them by override these callback methods in entity class if necessary.

### `onSelfQueried`

### `onSelfAdded`

### `onSelfRemoved`

### `onSelfUpdated`

### `onSelfCached`

### `onSelfCacheHit`

### `onSelfCacheMissed`

### `onSelfCacheUpdated`
