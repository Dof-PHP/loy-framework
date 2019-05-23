<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

class MySQLSchema
{
    const DEFAULT_ENGINE = 'InnoDB';
    const DEFAULT_CHARSET = 'utf8mb4';

    private $storage;
    private $annotations = [];
    private $driver;
    private $force = false;
    private $dump = false;
    private $sqls = [];

    public function reset()
    {
        $this->storage = null;
        $this->annotations = [];
        $this->driver = null;
        $this->force = false;
        $this->dump = false;
        $this->sqls = [];

        return $this;
    }

    public function exec()
    {
        $this->sqls[] = "-- {$this->storage}";

        $meta = $this->annotations['meta'] ?? [];
        $database = $meta['DATABASE'] ?? null;
        if (! $database) {
            exception('DatabaseNotSetOfStorage', [$this->storage]);
        }
        $table = $meta['TABLE'] ?? null;
        if (! $table) {
            exception('TableNameNotSetOfStorage', [$this->storage]);
        }

        if ($this->existsDatabase($database)) {
            if ($this->existsTable($database, $table)) {
                $this->syncTable($database, $table);
            } else {
                $this->initTable($database, $table);
            }
        } else {
            $this->initDatabase($database);
            $this->initTable($database, $table);
        }

        return $this->dump ? $this->sqls : true;
    }

    public function initDatabase(string $name)
    {
        $dropDB = "DROP DATABASE IF EXISTS `{$name}`;";
        $createDB = "CREATE DATABASE IF NOT EXISTS `{$name}` DEFAULT CHARACTER SET utf8mb4;";

        if ($this->dump) {
            if ($this->force) {
                $this->sqls[] = $dropDB;
            }
            $this->sqls[] = $createDB;
        } else {
            if ($this->force) {
                $this->mysql()->rawExec($dropDB);
            }

            $this->mysql()->rawExec($createDB);
        }
    }

    private function syncTableColumns(string $db, string $table)
    {
        $meta = $this->annotations['meta'] ?? [];
        $columns = $this->annotations['columns'] ?? [];
        $properties = $this->annotations['properties'] ?? [];

        $_columns = $this->mysql()->rawGet("SHOW FULL COLUMNS FROM `{$table}` FROM `{$db}`");
        $_columnNames = array_column($_columns, 'Field');
        $_columns = array_combine($_columnNames, $_columns);
        // sort($_columnNames);
        $columnNames = array_keys($columns);
        // sort($columnNames);

        $columnsAdd = array_diff($columnNames, $_columnNames);
        if ($columnsAdd) {
            $add = "ALTER TABLE `{$db}`.`{$table}` ";
            foreach ($columnsAdd as $column) {
                $property = $columns[$column] ?? null;
                $property = $properties[$property] ?? null;
                if (! $property) {
                    exception('PropertiesOfColumnNotFound', compact('table', 'column'));
                }

                $type = $property['TYPE'] ?? null;
                $length = $property['LENGTH'] ?? null;
                $notnull = 'NOT NULL';
                if (($property['NOTNULL'] ?? null) == '0') {
                    $notnull = '';
                }
                $default = '';
                if (array_key_exists('DEFAULT', $property)) {
                    $_default = $property['DEFAULT'] ?? null;
                    $default = "DEFAULT '{$_default}'";
                }
                $comment = '';
                if ($_comment = (trim(strval($property['COMMENT'] ?? '')) ?: trim(strval($property['TITLE'] ?? '')))) {
                    $comment = 'COMMENT '.$this->mysql()->quote($_comment);
                }
                $autoinc = '';
                if (trim(strval($property['AUTOINC'] ?? '')) === '1') {
                    $autoinc = 'AUTO_INCREMENT';
                }

                $add .= "ADD COLUMN `{$column}` {$type}($length) {$notnull} {$autoinc} {$default} {$comment}";
                if (false !== next($columnsAdd)) {
                    $add .= ",\n";
                }
            }

            $add .= ';';

            if ($this->dump) {
                $this->sqls[] = $add;
            } else {
                $this->mysql()->rawExec($add);
            }
        }

        $columnsUpdate = array_intersect($columnNames, $_columnNames);
        foreach ($columnsUpdate as $column) {
            $attrs = $properties[$columns[$column] ?? null] ?? [];
            if (! $attrs) {
                exception('AttrsOfColumnNotFound', compact('table', 'column'));
            }

            $typeInCode = trim(strval($attrs['TYPE'] ?? null));
            $lengthInCode = trim(strval($attrs['LENGTH'] ?? null));
            if ((! $typeInCode) || (! $lengthInCode)) {
                exception('MissingTypeInColumnAnnotations', compact('table', 'column'));
            }
            $unsignedInCode = (($attrs['UNSIGNED'] ?? null) == 1) ? 'unsigned' : '';
            $typeInCode = trim("{$typeInCode}({$lengthInCode}) {$unsignedInCode}");
            $notnullInCode = ci_equal($attrs['NOTNULL'] ?? '1', '1');
            $defaultInCode = array_key_exists('DEFAULTNULL', $attrs) ? null : ($attrs['DEFAULT'] ?? null);
            $commentInCode = trim(strval($attrs['COMMENT'] ?? '')) ?: trim(strval($attrs['TITLE'] ?? ''));
            $autoincInCode = ci_equal(trim(strval($attrs['AUTOINC'] ?? '')), '1');

            $_column = $_columns[$column] ?? null;
            if (! $_column) {
                exception('ColumnNotFoundInSchema', compact('db', 'table', 'column'));
            }
            $typeInSchema = trim(strval($_column['Type'] ?? ''));
            $notnullInSchema = ci_equal($_column['Null'] ?? 'NO', 'no');
            $defaultInSchema = $_column['Default'] ?? null;
            $commentInSchema = trim(strval($_column['Comment'] ?? ''));
            $autoincInSchema = ci_equal(trim(strval($_column['Extra'] ?? '')), 'auto_increment');

            if (false
                || (! ci_equal($typeInCode, $typeInSchema))
                || ($notnullInCode !== $notnullInSchema)
                || ($defaultInCode !== $defaultInSchema)
                || (! ci_equal($commentInCode, $commentInSchema))
                || ($autoincInCode !== $autoincInSchema)
            ) {
                // update table column with schema in annotations
                $notnull = $notnullInCode ? 'NOT NULL' : '';
                $default = '';
                if (array_key_exists('DEFAULTNULL', $attrs)) {
                    $default = 'DEFAULT NULL';
                } elseif (array_key_exists('DEFAULT', $attrs)) {
                    $default = 'DEFAULT '.$this->mysql()->quote($attrs['DEFAULT'] ?? '');
                }
                $comment = '';
                if ($commentInCode) {
                    $comment = 'COMMENT '.$this->mysql()->quote($commentInCode);
                }

                $autoinc = '';
                if (false
                    || $autoincInCode
                    || ci_equal(strval($meta['PRIMARYKEY'] ?? 'id'), $column)
                ) {
                    $autoinc = 'AUTO_INCREMENT';
                }

                $modify = "ALTER TABLE `{$db}`.`{$table}` MODIFY `{$column}` {$typeInCode} {$notnull} {$autoinc} {$default} {$comment};";
                if ($this->dump) {
                    $this->sqls[] = $modify;
                } else {
                    $this->mysql()->rawExec($modify);
                }
            }
        }

        $columnsDrop = array_diff($_columnNames, $columnNames);
        if ($columnsDrop && $this->force) {
            $drop = "ALTER TABLE `{$table}` \n";
            $dropColumns = $drop.join(",\n", array_map(function ($column) {
                return "DROP `{$column}`";
            }, $columnsDrop));
            $dropColumns .= ';';

            if ($this->dump) {
                $this->sqls[] = $dropColumns;
            } else {
                $this->mysql()->rawExec($dropColumns);
            }

            // !!! MySQL will drop indexes automatically when the columns of that index is dropped
            // !!! So here we MUST NOT drop then again
            // $indexes = $this->mysql()->rawGet("SHOW INDEX FROM `{$table}` WHERE `Column_name` IN({$columnsToDrop})");
            // if ($indexes) {
                // $indexesDrop = array_unique(array_column($indexes, 'Key_name'));
                // $dropIndexes = $drop.join(', ', array_map(function ($index) {
                    // return "DROP INDEX `{$index}`";
                // }, $indexesDrop));
                // $this->mysql()->rawExec($dropIndexes);
            // }
        }
    }

    private function syncTableIndexes(string $db, string $table)
    {
        $meta = $this->annotations['meta'] ?? [];
        $columns = $this->annotations['columns'] ?? [];
        $properties = $this->annotations['properties'] ?? [];

        $_indexes = $this->mysql()->rawGet("SHOW INDEX FROM `{$table}` FROM `{$db}` WHERE `KEY_NAME` != 'PRIMARY'");
        $_indexNames = array_unique(array_column($_indexes, 'Key_name'));
        $indexes = $meta['INDEX'] ?? [];
        $uniques = $meta['UNIQUE'] ?? [];
        $indexNames = array_keys(array_merge($indexes, $uniques));

        $indexesAdd = array_diff($indexNames, $_indexNames);
        if ($indexesAdd) {
            $addIndexes = "ALTER TABLE `{$table}` ";
            foreach ($indexesAdd as $key) {
                $unique = '';
                $fields = $indexes[$key] ?? [];
                if ($_fields = ($uniques[$key] ?? null)) {
                    $unique = 'UNIQUE';
                    $fields = $_fields;
                }
                if (! $fields) {
                    exception('MissingColumnsOfIndexKey', compact('key', 'unique'));
                }
                foreach ($fields as $field) {
                    if (! ($columns[$field] ?? false)) {
                        exception('FieldOfIndexKeyNotExists', compact('field', 'key'));
                    }
                }

                $fields = join(',', array_map(function ($field) {
                    return "`{$field}`";
                }, $fields));

                $addIndexes .= "ADD {$unique} KEY `{$key}`($fields)";
                if (false !== next($indexesAdd)) {
                    $addIndexes .= ",\n";
                }
            }
            $addIndexes .= ';';

            if ($this->dump) {
                $this->sqls[] = $addIndexes;
            } else {
                $this->mysql()->rawExec($addIndexes);
            }
        }

        $indexesUpdate = array_intersect($indexNames, $_indexNames);
        foreach ($indexesUpdate as $index) {
            $fieldsOfIndex = $this->mysql()->rawGet("SHOW INDEX FROM `{$table}` FROM `{$db}` WHERE `KEY_NAME` = '{$index}'");
            $_fieldsOfIndex = array_column($fieldsOfIndex, 'Column_name');
            $columnsOfIndex = $indexes[$index] ?? ($uniques[$index] ?? []);

            // Check index unicity between annotations and db schema
            // Check indexes fields count between annotations and db schema
            $uniqueInCode = boolval($uniques[$index] ?? false);
            $uniqueInSchema = !boolval($fieldsOfIndex[0]['Non_unique'] ?? false);
            $unique = $uniqueInCode ? 'UNIQUE' : '';
            $fields = join(',', array_map(function ($field) {
                return "`{$field}`";
            }, $columnsOfIndex));

            if (($uniqueInCode !== $uniqueInSchema) || ($columnsOfIndex !== $_fieldsOfIndex)) {
                // re-create index and name as $index with unicity
                $createIndex = "ALTER TABLE `{$db}`.`{$table}` DROP INDEX `{$index}`, ADD {$unique} KEY `{$index}` ({$fields});";
                if ($this->dump) {
                    $this->sqls[] = $createIndex;
                } else {
                    $this->mysql()->rawExec($createIndex);
                }
                continue;
            }
        }

        $indexesDrop = array_diff($_indexNames, $indexNames);
        if ($indexesDrop && $this->force) {
            $dropIndexes = "ALTER TABLE `{$table}` ";
            $dropIndexes .= join(', ', array_map(function ($index) {
                return "DROP KEY `{$index}`";
            }, $indexesDrop));
            $dropIndexes .= ';';

            if ($this->dump) {
                $this->sqls[] = $dropIndexes;
            } else {
                $this->mysql()->rawExec($dropIndexes);
            }
        }
    }

    public function syncTableSchema(string $db, string $table)
    {
        $_table = $this->mysql()->rawGet("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE table_schema='{$db}' AND table_name='{$table}'")[0] ?? [];
        $commentInSchema = strval($_table['TABLE_COMMENT'] ?? '');
        $commentInCode = trim(strval($this->annotations['meta']['COMMENT'] ?? ''));
        if (($commentInCode !== $commentInSchema) && $commentInCode) {
            $comment = $this->mysql()->quote($commentInCode);
            $updateComment = "ALTER TABLE `{$db}`.`{$table}` COMMENT = {$comment};";
            if ($this->dump) {
                $this->sqls[] = $updateComment;
            } else {
                $this->mysql()->rawExec($updateComment);
            }
        }

        $engineInSchema = strval($_table['ENGINE'] ?? 'InnoDB');
        $engineInCode = trim(strval($this->annotations['meta']['ENGINE'] ?? 'InnoDB'));
        if ((! ci_equal($engineInCode, $engineInSchema)) && $engineInCode) {
            $updateEngine = "ALTER TABLE `{$db}`.`{$table}` ENGINE {$engineInCode};";
            if ($this->dump) {
                $this->sqls[] = $updateEngine;
            } else {
                $this->mysql()->rawExec($updateEngine);
            }
        }

        $charsetInCode = trim(strval($this->annotations['meta']['CHARSET'] ?? ''));
        if ($charsetInCode) {
            $collateInCode = trim(strval($this->annotations['meta']['COLLATE'] ?? ''));
            $collateInSchema = strval($_table['TABLE_COLLATION'] ?? '');
            if (ci_equal($collateInCode, $collateInSchema)) {
                $charsetInSchema = '';
                $__table = $this->mysql()->rawGet("SHOW CREATE TABLE `{$db}`.`{$table}`");
                $tmp = explode(PHP_EOL, array_values($__table[0] ?? [])[1] ?? '');
                $tmp = $tmp[count($tmp) - 1] ?? '';
                if ($tmp) {
                    $reg = '#CHARSET\=((\w)+)#';
                    $res = [];
                    preg_match($reg, $tmp, $res);
                    $charsetInSchema = $res[1] ?? '';
                }

                if (! ci_equal($charsetInSchema, $charsetInCode)) {
                    $updateCharset = "ALTER TABLE `{$db}`.`{$table}` CONVERT TO CHARACTER SET {$charsetInCode};";
                    if ($this->dump) {
                        $this->sqls[] = $updateCharset;
                    } else {
                        $this->mysql()->rawExec($updateCharset);
                    }
                }
            } elseif ($collateInCode) {
                $updateCollate = "ALTER TABLE `{$db}`.`{$table}` CONVERT TO CHARACTER SET {$charsetInCode} COLLATE {$collateInCode};";
                if ($this->dump) {
                    $this->sqls[] = $updateCollate;
                } else {
                    $this->mysql()->rawExec($updateCollate);
                }
            }
        }
    }

    /**
     * Compare columns from table schema to storage annotations
     * Then decide whether add/drop/modification operations on columns and indexes are required
     */
    public function syncTable(string $db, string $table)
    {
        self::syncTableSchema($db, $table);
        self::syncTableColumns($db, $table);
        self::syncTableIndexes($db, $table);
    }

    public function initTable(string $db, string $table)
    {
        $meta = $this->annotations['meta'] ?? [];
        $columns = $this->annotations['columns'] ?? [];
        $properties = $this->annotations['properties'] ?? [];

        $engine = $meta['ENGINE'] ?? self::DEFAULT_ENGINE;
        $charset = $meta['CHARSET'] ?? self::DEFAULT_CHARSET;
        $notes = $this->mysql()->quote($meta['COMMENT'] ?? '');
        $pkName = $meta['PRIMARYKEY'] ?? 'id';
        $pkType = $meta['PRIMARYTYPE'] ?? 'int';
        $pkLength = $meta['PRIMARYLEN'] ?? 10;
        $indexes = '';
        $uniques = '';
        $fields = '';

        foreach ($meta['INDEX'] ?? [] as $index => $_fields) {
            array_walk($_fields, function (&$field) {
                return "`{$field}`";
            });
            $_fields = join(',', $_fields);
            $indexes .= "KEY `{$index}` ($_fields), ";
        }

        foreach ($meta['UNIQUE'] ?? [] as $index => $_fields) {
            array_walk($_fields, function (&$field) {
                return "`{$field}`";
            });
            $_fields = join(',', $_fields);
            $uniques .= "UNIQUE KEY `{$index}` ($_fields), ";
        }

        foreach ($columns as $column => $property) {
            $attr = $properties[$property] ?? null;
            if (! $attr) {
                continue;
            }
            $type = $attr['TYPE'] ?? null;
            if (! $type) {
                continue;
            }
            $len = $attr['LENGTH'] ?? null;
            if (is_null($len)) {
                continue;
            }
            if ($column === $pkName) {
                $pkType = $type;
                $pkLength = $len;
                continue;
            }

            $unsigned = (($attr['UNSIGNED'] ?? null) == 1) ? 'UNSIGNED' : '';
            $nullable = 'NOT NULL';
            if (($attr['NOTNULL'] ?? null) == '0') {
                $nullable = '';
            }
            $default = '';
            if (array_key_exists('DEFAULT', $attr)) {
                $_default = $attr['DEFAULT'] ?? null;
                $default = "DEFAULT '{$_default}'";
            }
            $comment = '';
            if ($_comment = (trim($attr['COMMENT'] ?? '') ?: trim($attr['TITLE'] ?? ''))) {
                $comment = 'COMMENT '.$this->mysql()->quote($_comment);
            }

            $fields .= "`{$column}` {$type}({$len}) {$unsigned} {$nullable} {$default} {$comment}, \n";
        }

        $useDb = "USE `{$db}`;";
        $dropTable = "DROP TABLE IF EXISTS `{$db}`.`{$table}`;";

        $createTable = <<<SQL
CREATE TABLE IF NOT EXISTS `{$table}` (
`{$pkName}` {$pkType}({$pkLength}) UNSIGNED NOT NULL AUTO_INCREMENT,
{$fields}
{$indexes}
{$uniques}
PRIMARY KEY (`{$pkName}`)
) ENGINE={$engine} AUTO_INCREMENT=1 DEFAULT CHARSET={$charset} COMMENT={$notes};
SQL;

        if ($this->dump) {
            $this->sqls[] = $useDb;
            if ($this->force) {
                $this->sqls[] = $dropTable;
            }
            $this->sqls[] = $createTable;
        } else {
            $this->mysql()->rawExec($useDb);
            if ($this->force) {
                $this->mysql()->rawExec($dropTable);
            }
            $this->mysql()->rawExec($createTable);
        }
    }

    public function existsTable(string $db, string $table) : bool
    {
        $useDb = "USE `{$db}`;";

        $this->mysql()->rawExec($useDb);

        $this->sqls[] = $useDb;

        $res = $this->mysql()->rawGet("SHOW TABLES LIKE '{$table}'");

        return count($res[0] ?? []) > 0;
    }

    public function existsDatabase(string $name) : bool
    {
        $res = $this->mysql()->rawGet("SHOW DATABASES LIKE '{$name}'");

        return count($res[0] ?? []) > 0;
    }

    final public function mysql() : MySQL
    {
        if ((! $this->driver) || (! ($this->driver instanceof MySQL))) {
            exception('MissingOrInvalidMySQLDriver', [$this->driver]);
        }

        return $this->driver;
    }

    /**
     * Setter for storage
     *
     * @param string $storage
     * @return MySQLSchema
     */
    public function setStorage(string $storage)
    {
        $this->storage = $storage;
    
        return $this;
    }

    /**
     * Setter for annotations
     *
     * @param array $annotations
     * @return MySQLSchema
     */
    public function setAnnotations(array $annotations)
    {
        $this->annotations = $annotations;
    
        return $this;
    }

    /**
     * Setter for driver
     *
     * @param MySQL $driver
     * @return MySQLSchema
     */
    public function setDriver(MySQL $driver)
    {
        $this->driver = $driver;
    
        return $this;
    }

    /**
     * Setter for force
     *
     * @param bool $force
     * @return MySQLSchema
     */
    public function setForce(bool $force)
    {
        $this->force = $force;
    
        return $this;
    }

    /**
     * Setter for dump
     *
     * @param bool $dump
     * @return MySQLSchema
     */
    public function setDump(bool $dump)
    {
        $this->dump = $dump;
    
        return $this;
    }
}
