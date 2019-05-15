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
        StorageInterface $mysql,
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
            // self::syncTable($database, $table, $annotations, $mysql);
        } else {
            self::initTable($database, $table, $annotations, $mysql);
        }

        return true;
    }

    public static function initDatabase(string $name, $mysql)
    {
        $mysql->exec("DROP DATABASE IF EXISTS `{$name}`");
        $mysql->exec("CREATE DATABASE `{$name}` DEFAULT CHARACTER SET utf8mb4");
    }

    public static function syncTable(string $db, string $table, array $annotations, $mysql)
    {
        // TODO: compare columns and decide whether add/drop operations are required
    }

    public static function initTable(string $db, string $table, array $annotations, $mysql)
    {
        $meta = $annotations['meta'] ?? [];
        $columns = $annotations['columns'] ?? [];
        $properties = $annotations['properties'] ?? [];

        $engine = $meta['ENGINE'] ?? self::DEFAULT_ENGINE;
        $charset = $meta['CHARSET'] ?? self::DEFAULT_CHARSET;
        $_comment = $meta['COMMENT'] ?? '';
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
            $nullable = (($attr['NOTNULL'] ?? null) == 1) ? 'NOT NULL' : '';
            $default = '';
            if (array_key_exists('DEFAULT', $attr)) {
                $_default = $attr['DEFAULT'] ?? null;
                $default = "DEFAULT '{$_default}'";
            }
            $comment = '';
            if (array_key_exists('COMMENT', $attr)) {
                $_comment = $attr['COMMENT'] ?? '';
                $comment = "COMMENT '{$_comment}'";
            }

            $fields .= "`{$column}` {$type}({$len}) {$unsigned} {$nullable} {$default} {$comment}, ";
        }

        $mysql->exec("USE `{$db}`");
        $mysql->exec("DROP TABLE IF EXISTS `{$table}`");

        $sql = <<<SQL
CREATE TABLE `{$table}` (
`{$pkName}` {$pkType}({$pkLength}) NOT NULL AUTO_INCREMENT,
{$fields}
{$indexes}
{$uniques}
PRIMARY KEY (`{$pkName}`)
) ENGINE={$engine} DEFAULT CHARSET={$charset} COMMENT='{$_comment}'
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
        $res = $mysql->get("SHOW DATABASES LIKE '{$name}'");

        return count($res[0] ?? []) > 0;
    }
}
