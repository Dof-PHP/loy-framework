<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use Closure;
use Dof\Framework\Paginator;

class MySQLBuilder
{
    private $origin;

    private $sql = false;
    private $db;
    private $table;
    private $select = [];
    private $alias = [];
    private $aliasRaw = [];
    private $where = [];
    private $whereRaw = [];
    private $or = [];
    private $orRaw = [];

    private $order = [];
    private $offset;
    private $limit;

    public function reset()
    {
        $this->origin = null;
        $this->sql = false;
        $this->db = null;
        $this->table = null;
        $this->select = [];
        $this->alias = [];
        $this->aliasRaw = [];
        $this->where = [];
        $this->whereRaw = [];
        $this->or = [];
        $this->orRaw = [];
        $this->order = [];
        $this->offset = null;
        $this->limit = null;

        return $this;
    }

    public function setOrigin(StorageInterface $origin)
    {
        $this->origin = $origin;

        return $this;
    }

    public function zero(string $column)
    {
        $this->where[] = [$column, '!=', ''];
        $this->where[] = [$column, '=', 0];

        return $this;
    }

    public function empty(string $column)
    {
        $this->where[] = [$column, '=', ''];

        return $this;
    }

    public function notnull(string $column)
    {
        $this->where[] = [$column, 'IS NOT NULL', null];

        return $this;
    }

    public function null(string $column)
    {
        $this->where[] = [$column, 'IS NULL', null];

        return $this;
    }

    public function not(string $column, $value)
    {
        $this->where[] = [$column, '!=', $value];

        return $this;
    }

    public function inRaw(string $column, $value)
    {
        $this->whereRaw[] = [$column, 'INRAW', $value];

        return $this;
    }

    public function in(string $column, $value)
    {
        $this->where[] = [$column, 'IN', $value];

        return $this;
    }

    public function notin(string $column, $value)
    {
        $this->where[] = [$column, 'NOT IN', $value];

        return $this;
    }

    public function rlike(string $column, $value)
    {
        $value = trim(strval($value));

        $this->where[] = [$column, 'LIKE', "{$value}%"];

        return $this;
    }

    public function llike(string $column, $value)
    {
        $value = trim(strval($value));

        $this->where[] = [$column, 'LIKE', "%{$value}"];

        return $this;
    }

    public function like(string $column, $value)
    {
        $value = trim(strval($value));

        $this->where[] = [$column, 'LIKE', "%{$value}%"];

        return $this;
    }

    public function lt(string $column, $value, bool $equal = true)
    {
        $operator = $equal ? '<=' : '<';

        $this->where[] = [$column, $operator, $value];

        return $this;
    }

    public function gt(string $column, $value, bool $equal = true)
    {
        $operator = $equal ? '>=' : '>';

        $this->where[] = [$column, $operator, $value];

        return $this;
    }

    public function where(string $column, $value, string $operator = '=')
    {
        $this->where[] = [$column, $operator, $value];

        return $this;
    }

    public function whereRaw(string $raw, $value, string $operator = '=')
    {
        $this->whereRaw[] = [$raw, $operator, $value];

        return $this;
    }

    public function or(string $column, $value, string $operator = '=')
    {
        $this->or[] = [$column, $operator, $value];

        return $this;
    }

    public function orRaw(string $raw, $value, string $operator = '=')
    {
        $this->orRaw[] = [$raw, $operator, $value];

        return $this;
    }

    /**
     * Alias a timestamp column with custom date format
     *
     * @param string $column: Timestamp column
     * @param string $alias: Alias used to name the expression
     * @param string $format: Format used to convert timestamp to date string
     */
    public function date(string $column, string $alias = null, string $format = null)
    {
        $_format = $format ? ','.$this->origin->quote($format) : '';

        $expression = "FROM_UNIXTIME(`$column`{$_format})";

        $_alias = $alias ?: $column;

        $this->aliasRaw[$_alias] = $expression;

        return $this;
    }

    public function aliasRaw(string $expression, string $alias)
    {
        $this->aliasRaw[$alias] = $expression;

        return $this;
    }

    public function alias(string $column, string $alias)
    {
        $this->alias[$alias] = $column;

        return $this;
    }

    public function asc(string $column)
    {
        $this->order[$column] = 'ASC';

        return $this;
    }

    public function desc(string $column)
    {
        $this->order[$column] = 'DESC';

        return $this;
    }

    public function order(string $column, string $sort)
    {
        $this->order[$column] = $sort;

        return $this;
    }

    public function db(string $db)
    {
        $this->db = $db;

        return $this;
    }

    public function table(string $table)
    {
        $this->table = $table;

        return $this;
    }

    public function select(...$columns)
    {
        $this->select = $columns;

        return $this;
    }

    public function count() : int
    {
        $this->alias = [];
        $this->aliasRaw = ['total' => 'count(*)'];
        $this->select = ['total'];

        $this->limit = $this->offset = null;

        $res = $this->get();

        return intval($res[0]['total'] ?? 0);
    }

    public function limit(int $limit, int $offset = null)
    {
        if ($offset > 0) {
            $this->offset = $limit;
            $this->limit = $offset;
        } else {
            $this->limit = $limit;
        }

        return $this;
    }

    public function paginate(int $page, int $size) : Paginator
    {
        $alias = $this->alias;
        $aliasRaw = $this->aliasRaw;
        $select = $this->select;

        $total = $this->count();

        $this->alias = $alias;
        $this->aliasRaw = $aliasRaw;
        $this->select = $select;

        $this->offset = ($page - 1) * $size;
        $this->limit = $size;

        $list = $this->get();

        return new Paginator($list, [
            'page' => $page,
            'size' => $size,
            'total' => $total,
        ]);
    }

    public function first()
    {
        $this->offset = 0;
        $this->limit = 1;

        $res = $this->get();

        return $res[0] ?? null;
    }

    public function get()
    {
        $params = [];

        $sql = $this->buildSql($params);

        return $this->sql ? $this->generate($sql, $params) : $this->origin->get($sql, $params);
    }

    private function generate(string $sql, array $params) : string
    {
        $sql = $this->origin ? $this->origin->generate($sql) : $sql;

        $placeholders = array_fill(0, count($params), '/\?/');

        array_walk($params, function (&$val) {
            $val = "'{$val}'";    // TODO&FIXME
        });

        return $params ? preg_replace($placeholders, $params, $sql) : $sql;
    }

    public function sql(bool $sql)
    {
        $this->sql = $sql;

        return $this;
    }

    public function buildSql(array &$params) : string
    {
        $selects = '#{COLUMNS}';
        if ($this->select) {
            $selects = '';
            foreach ($this->select as $column) {
                $_column = $this->alias[$column] ?? null;
                if ($_column) {
                    $selects .= "`{$_column}` AS `{$column}`";
                } else {
                    $expression = $this->aliasRaw[$column] ?? null;
                    if ($expression) {
                        $selects .= "{$expression} AS `{$column}`";
                    } else {
                        $selects .= "`{$column}`";
                    }
                }

                if (false !== next($this->select)) {
                    $selects .= ', ';
                }
            }
        } else {
            $selects = '';
            $columns = array_keys($this->origin->annotations()->columns->toArray());
            foreach ($columns as $column) {
                $selects .= "`{$column}`";
                if (next($columns) !== false) {
                    $selects .= ',';
                }
            }
            if ($this->alias) {
                $selects .= ',';
                foreach ($this->alias as $alias => $column) {
                    $selects .= "`{$column}` AS `{$alias}`";
                    if (next($this->alias) !== false) {
                        $selects .= ',';
                    }
                }
            }
            if ($this->aliasRaw) {
                $selects .= ',';
                foreach ($this->aliasRaw as $alias => $expression) {
                    $selects .= "{$expression} AS `{$alias}`";
                }
                if (next($this->aliasRaw) !== false) {
                    $selects .= ',';
                }
            }
        }

        $order = '';
        if ($this->order) {
            $order = 'ORDER BY ';
            foreach ($this->order as $by => $sort) {
                $order .= "`{$by}` {$sort}";
                if (next($this->order) !== false) {
                    $order .= ',';
                }
            }
        }

        $limit = '';
        if (is_int($this->limit)) {
            if (is_int($this->offset)) {
                $limit = "LIMIT {$this->offset}, {$this->limit}";
            } else {
                $limit = "LIMIT {$this->limit}";
            }
        }

        list($where, $params) = $this->buildWhere();

        $table = $this->table ?: '#{TABLE}';
        if ($this->db) {
            $table = "`{$this->db}`.{$table}";
        }

        $sql = 'SELECT %s FROM %s %s %s %s';

        return sprintf($sql, $selects, $table, $where, $order, $limit);
    }

    /**
     * Update a single column on a table
     *
     * @param string $column: The column to be updated
     * @param mixed $value: The value to be set in that column
     */
    public function set(string $column, $value)
    {
        list($where, $params) = $this->buildWhere();

        array_unshift($params, $value);

        $table = $this->table ?: '#{TABLE}';

        $sql = 'UPDATE %s SET `%s` = ? %s';
        $sql = sprintf($sql, $table, $column, $where);

        return $this->sql ? $this->generate($sql, $params) : $this->origin->exec($sql, $params);
    }

    /**
     * Update multiple columns at once
     *
     * @param array $data: The assoc array to be updated
     */
    public function update(array $data) : int
    {
        if (! $data) {
            return 0;
        }

        list($where, $_params) = $this->buildWhere();

        $columns = [];
        foreach ($data as $key => $val) {
            $columns[] = "`{$key}` = ?";
            $params[] = $val;
        }

        foreach ($_params as $param) {
            array_push($params, $param);
        }

        $table = $this->table ?: '#{TABLE}';

        $columns = join(', ', $columns);

        $sql = 'UPDATE %s SET %s %s ';
        $sql = sprintf($sql, $table, $columns, $where);

        return $this->sql ? $this->generate($sql, $params) : $this->origin->exec($sql, $params);
    }

    public function delete()
    {
        list($where, $params) = $this->buildWhere();

        $table = $this->table ?: '#{TABLE}';

        $sql = 'DELETE FROM %s %s';
        $sql = sprintf($sql, $table, $where);

        return $this->sql ? $this->generate($sql, $params) : $this->origin->exec($sql, $params);
    }

    /**
     * Add on record from storage class annotations
     */
    public function add(array $data) : int
    {
        $annotations= $this->origin->annotations();
        $columns = array_keys($data);
        $values = array_values($data);
        $count = count($values);
        $_values = join(',', array_fill(0, $count, '?'));

        $columns = join(',', array_map(function ($column) {
            return "`{$column}`";
        }, $columns));

        $table = $this->table ?: '#{TABLE}';

        $sql = "INSERT INTO %s (%s) VALUES (%s)";
        $sql = sprintf($sql, $table, $columns, $_values);

        return $this->sql ? $this->generate($sql, $values) : $this->origin->insert($sql, $values);
    }

    public function insert(array $list)
    {
        if (! $list) {
            return 0;
        }

        if (! is_index_array($list)) {
            exception('InvalidInsertValues', ['Non-Index Array']);
        }

        $first = $list[0] ?? [];
        ksort($first);
        $first = array_keys($first);
        $columns = join(',', array_map(function ($column) {
            return "`{$column}`";
        }, $first));
        $values = join(',', array_fill(0, count($first), '?'));
        $_values = '';
        $params = [];

        foreach ($list as $idx => $item) {
            ksort($item);
            $_params = array_values($item);
            foreach ($_params as $param) {
                array_push($params, $param);
            }

            if (array_keys($item) !== $first) {
                exception('InvalidInsertRows', [
                    'err' => 'Insert columns not match against the first one',
                    'num' => $idx,
                    'first' => $first,
                    'invalid' => $item,
                ]);
            }

            $_values .= "({$values})";
            if (false !== next($list)) {
                $_values .= ',';
            }
        }

        $sql = "INSERT INTO %s (%s) VALUES %s";
        $sql = sprintf($sql, $columns, $_values);

        return $this->sql ? $this->generate($sql, $params) : $this->origin->exec($sql, $params);
    }

    private function buildWhere() : array
    {
        $where = '';
        $params = [];

        $buildWhere = function (string $column, string $operator, $val, &$params, bool $expression = false) : string {
            $operator = trim($operator);
            $placeholder = '?';
            if (! $expression) {
                $column = "`{$column}`";
            }

            if (ciin_array($operator, ['in', 'not in'])) {
                $placeholder = '(?)';
                if (is_array($val) || is_string($val)) {
                    $val = is_string($val) ? array_trim_from_string($val, ',') : $val;
                    $placeholder = '('.join(',', array_fill(0, count($val), '?')).')';
                    foreach ($val as $v) {
                        array_push($params, $v);
                    }
                } elseif (is_closure($val)) {
                    $builder = new self;
                    $_params = [];
                    $val($builder);
                    $sql = $builder->buildSql($_params);
                    $placeholder = "({$sql})";
                    foreach ($_params as $param) {
                        array_push($params, $param);
                    }
                } else {
                    $params[] = (array) $val;
                }
            } elseif (ciin_array($operator, ['is not null', 'is null'])) {
                $placeholder = '';
            // No params need when null conditions
            } elseif (ci_equal($operator, 'inraw')) {
                $column = "`{$column}`";
                $operator = 'IN';
                $placeholder = "({$val})";
            } else {
                $params[] = $val;
            }

            return "{$column} {$operator} {$placeholder}";
        };

        if ($this->where || $this->whereRaw) {
            $where .= ' WHERE ';
            foreach ($this->where as list($column, $operator, $val)) {
                $where .= $buildWhere($column, $operator, $val, $params, false);
                if (false !== next($this->where)) {
                    $where .= ' AND ';
                }
            }
            if ($this->where && $this->whereRaw) {
                $where .= ' AND ';
            }

            foreach ($this->whereRaw as list($expression, $operator, $val)) {
                $where .= $buildWhere($expression, $operator, $val, $params, true);
                if (false !== next($this->whereRaw)) {
                    $where .= ' AND ';
                }
            }
        }
        if ($this->or || $this->orRaw) {
            if ($this->where || $this->whereRaw) {
                $where .= ' OR ';
            } else {
                $where .= ' WHRER ';
            }

            foreach ($this->or as list($column, $operator, $val)) {
                $where .= $buildWhere($column, $operator, $val, $params, false);
                if (false !== next($this->or)) {
                    $where .= ' OR ';
                }
            }
            if ($this->orRaw) {
                $where .= ' OR ';
            }
            foreach ($this->orRaw as list($expression, $operator, $val)) {
                $where .= $buildWhere($expression, $operator, $val, $params, true);
                if (false !== next($this->orRaw)) {
                    $where .= ' OR ';
                }
            }
        }

        return [$where, $params];
    }
}
