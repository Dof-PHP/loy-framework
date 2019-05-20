<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

class MySQLSchema
{
    const DEFAULT_ENGINE = 'InnoDB';
    const DEFAULT_CHARSET = 'utf8mb4';

    public static function sync(
        string $namespace,
        array $annotations,
        MySQL $mysql,
        bool $force = false
    ) {
        $meta = $annotations['meta'] ?? [];
        $database = $meta['DATABASE'] ?? null;
        if (! $database) {
            exception('DatabaseNotSetOfStorage', compact('namespace'));
        }
        $table = $meta['TABLE'] ?? null;
        if (! $table) {
            exception('TableNameNotSetOfStorage', compact('namespace'));
        }
        if (! self::existsDatabase($database, $mysql)) {
            self::initDatabase($database, $mysql);
        }
        if (self::existsTable($database, $table, $mysql)) {
            self::syncTable($database, $table, $annotations, $mysql, $force);
        } else {
            self::initTable($database, $table, $annotations, $mysql);
        }

        return true;
    }

    public static function initDatabase(string $name, $mysql)
    {
        $mysql->setNeeddb(false)->exec("DROP DATABASE IF EXISTS `{$name}`");
        $mysql->setNeeddb(false)->exec("CREATE DATABASE `{$name}` DEFAULT CHARACTER SET utf8mb4");
        $mysql->setNeeddb(true);
    }

    private static function syncTableColumns(string $db, string $table, array $annotations, $mysql, bool $force = false)
    {
        $meta = $annotations['meta'] ?? [];
        $columns = $annotations['columns'] ?? [];
        $properties = $annotations['properties'] ?? [];

        $_columns = $mysql->setNeeddb(false)->rawGet("SHOW FULL COLUMNS FROM `{$table}` FROM `{$db}`");
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
                if (array_key_exists('COMMENT', $property)) {
                    $_comment = $property['COMMENT'] ?? '';
                    $comment = "COMMENT '{$_comment}'";
                }
                $autoinc = $property['AUTOINC'] ?? '';

                $add .= "ADD COLUMN `{$column}` {$type}($length) {$notnull} {$autoinc} {$default} {$comment}";
                if (false !== next($columnsAdd)) {
                    $add .= ', ';
                }
            }

            $mysql->exec($add);
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
            $commentInCode = trim(strval($attrs['COMMENT'] ?? ''));
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

            logger()->debug('default', [$column, $defaultInCode, $defaultInSchema]);

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
                    $default = 'DEFAULT '.$mysql->quote($attr['DEFAULT'] ?? '');
                }
                $comment = '';
                if (array_key_exists('COMMENT', $attrs)) {
                    $comment = 'COMMENT '.$mysql->quote($commentInCode);
                }

                $autoinc = '';
                if (false
                    || $autoincInCode
                    || ci_equal(strval($meta['PRIMARYKEY'] ?? 'id'), $column)
                ) {
                    $autoinc = 'AUTO_INCREMENT';
                }

                $res = $mysql->exec("ALTER TABLE `{$db}`.`{$table}` MODIFY `{$column}` {$typeInCode} {$notnull} {$autoinc} {$default} {$comment}");
            }
        }

        $columnsDrop = array_diff($_columnNames, $columnNames);
        if ($columnsDrop && $force) {
            $drop = "ALTER TABLE `{$table}` ";
            $dropColumns = $drop.join(', ', array_map(function ($column) {
                return "DROP `{$column}`";
            }, $columnsDrop));

            $columnsToDrop = join(',', array_map(function ($column) {
                return "'{$column}'";
            }, $columnsDrop));

            $mysql->exec($dropColumns);
            // !!! MySQL will drop indexes automatically when the columns of that index is dropped
            // !!! So here we MUST NOT drop then again
            // $indexes = $mysql->rawGet("SHOW INDEX FROM `{$table}` WHERE `Column_name` IN({$columnsToDrop})");
            // if ($indexes) {
                // $indexesDrop = array_unique(array_column($indexes, 'Key_name'));
                // $dropIndexes = $drop.join(', ', array_map(function ($index) {
                    // return "DROP INDEX `{$index}`";
                // }, $indexesDrop));
                // $mysql->exec($dropIndexes);
            // }
        }
        $mysql->setNeeddb(true);
    }

    private static function syncTableIndexes(string $db, string $table, array $annotations, $mysql, bool $force = false)
    {
        $meta = $annotations['meta'] ?? [];
        $columns = $annotations['columns'] ?? [];
        $properties = $annotations['properties'] ?? [];

        $_indexes = $mysql->setNeeddb(false)->rawGet("SHOW INDEX FROM `{$table}` FROM `{$db}` WHERE `KEY_NAME` != 'PRIMARY'");
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
                    $addIndexes .= ', ';
                }
            }

            $mysql->exec($addIndexes);
        }

        $indexesUpdate = array_intersect($indexNames, $_indexNames);
        foreach ($indexesUpdate as $index) {
            $fieldsOfIndex = $mysql->setNeeddb(false)->rawGet("SHOW INDEX FROM `{$table}` FROM `{$db}` WHERE `KEY_NAME` = '{$index}'");
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
                $mysql->exec("ALTER TABLE `{$table}` DROP INDEX `{$index}`, ADD {$unique} KEY `{$index}` ({$fields}) ");
                continue;
            }
        }

        $indexesDrop = array_diff($_indexNames, $indexNames);
        if ($indexesDrop && $force) {
            $dropIndexes = "ALTER TABLE `{$table}` ";
            $dropIndexes .= join(', ', array_map(function ($index) {
                return "DROP KEY `{$index}`";
            }, $indexesDrop));

            $mysql->exec($dropIndexes);
        }

        $mysql->setNeeddb(true);
    }

    /**
     * Compare columns from table schema to storage annotations
     * Then decide whether add/drop/modification operations on columns and indexes are required
     */
    public static function syncTable(string $db, string $table, array $annotations, $mysql, bool $force = false)
    {
        self::syncTableColumns($db, $table, $annotations, $mysql, $force);
        self::syncTableIndexes($db, $table, $annotations, $mysql, $force);
    }

    public static function initTable(string $db, string $table, array $annotations, $mysql)
    {
        $meta = $annotations['meta'] ?? [];
        $columns = $annotations['columns'] ?? [];
        $properties = $annotations['properties'] ?? [];

        $engine = $meta['ENGINE'] ?? self::DEFAULT_ENGINE;
        $charset = $meta['CHARSET'] ?? self::DEFAULT_CHARSET;
        $notes = $mysql->quote($meta['COMMENT'] ?? '');
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
            if (array_key_exists('COMMENT', $attr)) {
                $_comment = $attr['COMMENT'] ?? '';
                $comment = 'COMMENT '.$mysql->quote($_comment);
            }

            $fields .= "`{$column}` {$type}({$len}) {$unsigned} {$nullable} {$default} {$comment}, ";
        }

        $mysql->exec("USE `{$db}`");
        $mysql->exec("DROP TABLE IF EXISTS `{$table}`");

        $sql = <<<SQL
CREATE TABLE `{$table}` (
`{$pkName}` {$pkType}({$pkLength}) UNSIGNED NOT NULL AUTO_INCREMENT,
{$fields}
{$indexes}
{$uniques}
PRIMARY KEY (`{$pkName}`)
) ENGINE={$engine} AUTO_INCREMENT=1 DEFAULT CHARSET={$charset} COMMENT={$notes}
SQL;

        $mysql->exec($sql);

        return true;
    }

    public static function existsTable(string $db, string $table, $mysql) : bool
    {
        $mysql->exec("USE `{$db}`");
        $res = $mysql->get("SHOW TABLES LIKE '{$table}'");

        return count($res[0] ?? []) > 0;
    }

    public static function existsDatabase(string $name, $mysql) : bool
    {
        $res = $mysql->setNeeddb(false)->get("SHOW DATABASES LIKE '{$name}'");

        $mysql->setNeeddb(true);

        return count($res[0] ?? []) > 0;
    }
}
