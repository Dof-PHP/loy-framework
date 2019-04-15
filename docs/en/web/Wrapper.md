Wrappers are the validators for both HTTP request and response. They will be used in port method annotation definitions for different purposes.

There are three type of wrappers in dof:

## wrapperin

Validate request parameters.

## wrapperout

Validate success response parameters.

Typically, response data are php arrays. Four types of format of wrappers in Dof:

### Index Array

Rarely used. Examples:

``` php
$arr = [1, 'a', 3, 'b'];
```

### Associate Array(Object)

Frequently used. Examples:

``` php
$arr = [
    'id'  => 1,
    'name' => 'aaa',
    'email' => 'bbb@ccc.com',
];
```

### Index Array List

Rarely used. List of index arrays. Examples:

``` php
$arr = [
    [1, 'a', 3, 'b'],
    [2, 'c', 4, 'd'],
];
```

### Associate Array List(Object List)

Frequently used. List of associate arrays. Examples:

```php
$arr = [
    [
        'id'  => 1,
        'name' => 'aaa',
        'email' => 'bbb@ccc.com',
    ],
    [
        'id'  => 2,
        'name' => 'ddd',
        'email' => 'eee@fff.com',
    ],
];

```

## wrappererr

Validate failed response parameters.