<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use Dof\Framework\Paginator;

class MySQLBuilder
{
    private $origin;

    private $select = [];
    private $alias = [];
    private $aliasRaw = [];
    private $where = [];
    private $whereRaw = [];
    private $or = [];
    private $orRaw = [];

    private $offset;
    private $limit;

    public function reset()
    {
        $this->origin = null;
        $this->alias = [];
        $this->aliasRaw = [];
        $this->where = [];
        $this->whereRaw = [];
        $this->or = [];
        $this->orRaw = [];
        $this->offset = null;
        $this->limit = null;

        return $this;
    }

    public function setOrigin(StorageInterface $origin)
    {
        $this->origin = $origin;

        return $this;
    }

    public function where(string $column, $value, string $operator = '=')
    {
        $this->where[$column] = [$operator, $value];

        return $this;
    }

    public function whereRaw(string $raw, $value, string $operator = '=')
    {
        $this->whereRaw[$raw] = [$operator, $value];

        return $this;
    }

    public function or(string $column, $value, string $operator = '=')
    {
        $this->or[$column] = [$operator, $value];

        return $this;
    }

    public function orRaw(string $raw, $value, string $operator = '=')
    {
        $this->orRaw[$raw] = [$operator, $value];

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
        $columns = '#{COLUMNS}';
        if ($this->select) {
            $columns = '';
            foreach ($this->select as $column) {
                $_column = $this->alias[$column] ?? null;
                if ($_column) {
                    $columns .= "`{$_column}` AS `{$column}`";
                } else {
                    $expression = $this->aliasRaw[$column] ?? null;
                    if ($expression) {
                        $columns .= "{$expression} AS `{$column}`";
                    } else {
                        $columns .= "`{$column}`";
                    }
                }

                if (false !== next($this->select)) {
                    $columns .= ', ';
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

        $sql = 'SELECT %s FROM #{TABLE} %s %s';
        $sql = sprintf($sql, $columns, $where, $limit);

        return $this->origin->get($sql, $params);
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

        $sql = 'UPDATE #{TABLE} SET `%s` = ? %s';
        $sql = sprintf($sql, $column, $where);

        return $this->origin->exec($sql, $params);
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

        $columns = join(', ', $columns);

        $sql = 'UPDATE #{TABLE} SET %s %s ';
        $sql = sprintf($sql, $columns, $where);

        return $this->origin->exec($sql, $params);
    }

    public function delete()
    {
        list($where, $params) = $this->buildWhere();

        $sql = 'DELETE FROM #{TABLE} %s';
        $sql = sprintf($sql, $where);

        return $this->origin->exec($sql, $params);
    }

    public function add(array $data) : int
    {
        $columns = array_keys($data);
        $values = array_values($data);
        $count = count($values);
        $_values = join(',', array_fill(0, $count, '?'));

        $columns = join(',', array_map(function ($column) {
            return "`{$column}`";
        }, $columns));

        $sql = "INSERT INTO #{TABLE} (%s) VALUES (%s)";
        $sql = sprintf($sql, $columns, $_values);

        return $this->origin->insert($sql, $values);
    }

    public function insert(array $list)
    {
        if (! $list) {
            return 0;
        }

        $first = array_keys($list[0] ?? []);
        sort($first);
        $columns = join(',', array_map(function ($column) {
            return "`{$column}`";
        }, $first));
        $values = join(',', array_fill(0, count($first), '?'));
        $_values = '';
        $params = [];

        foreach ($list as $idx => $item) {
            $_params = array_values($item);
            foreach ($_params as $param) {
                array_push($params, $param);
            }

            $item = array_keys($item);
            sort($item);
            if ($item !== $first) {
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

        $sql = "INSERT INTO #{TABLE} (%s) VALUES %s";
        $sql = sprintf($sql, $columns, $_values);

        return $this->origin->exec($sql, $params);
    }

    private function buildWhere() : array
    {
        $where = '';
        $params = [];

        $buildWhere = function (string $column, string $operator, $val, &$params, bool $expression = false) : string {
            $placeholder = '?';
            if (ciin_array(trim($operator), ['in', 'not in'])) {
                $placeholder = '(?)';
                if (is_array($val)) {
                    $placeholder = '('.join(',', array_fill(0, count($val), '?')).')';
                    foreach ($val as $v) {
                        array_push($params, $v);
                    }
                } else {
                    $params[] = $val;
                }
            } else {
                $params[] = $val;
            }

            if (! $expression) {
                $column = "`{$column}`";
            }

            return "{$column} {$operator} {$placeholder}";
        };

        if ($this->where || $this->whereRaw) {
            $where .= ' WHERE ';
            foreach ($this->where as $column => list($operator, $val)) {
                $where .= $buildWhere($column, $operator, $val, $params, false);
                if (false !== next($this->where)) {
                    $where .= ' AND ';
                }
            }
            if ($this->where && $this->whereRaw) {
                $where .= ' AND ';
            }

            foreach ($this->whereRaw as $expression => list($operator, $val)) {
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

            foreach ($this->or as $column => list($operator, $val)) {
                $where .= $buildWhere($column, $operator, $val, $params, false);
                if (false !== next($this->or)) {
                    $where .= ' OR ';
                }
            }
            if ($this->orRaw) {
                $where .= ' OR ';
            }
            foreach ($this->orRaw as $expression => list($operator, $val)) {
                $where .= $buildWhere($expression, $operator, $val, $params, true);
                if (false !== next($this->orRaw)) {
                    $where .= ' OR ';
                }
            }
        }

        return [$where, $params];
    }
}
