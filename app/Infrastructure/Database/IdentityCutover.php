<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Forward cutover for a database whose domain primary keys are still uuid.
 *
 * Existing uuid values are copied into the public uuid column and are not
 * regenerated. Internal foreign keys are rewritten to the new bigint id.
 * A schema that already uses bigint id is left unchanged.
 */
final class IdentityCutover
{
    /** @var list<string> */
    private const PUBLIC_UUID_COLUMNS = [
        'audit_events.resource_id',
        'audit_events.request_id',
        'audit_events.correlation_id',
        'credential_access_events.request_id',
    ];

    public static function apply(): void
    {
        $idType = DB::scalar("select udt_name from information_schema.columns where table_schema = 'public' and table_name = 'users' and column_name = 'id'");

        if ($idType === 'int8') {
            return;
        }

        if ($idType !== 'uuid') {
            throw new RuntimeException('users.id is neither uuid nor bigint; identity cutover stopped.');
        }

        $pkTables = self::uuidPrimaryTables();
        $converted = self::uuidColumnsToRewrite($pkTables);
        $counts = self::counts(array_keys($converted));

        self::addSurrogateKeys($pkTables);
        self::addForeignKeySurrogates($converted);
        self::backfillForeignKeys($converted);
        self::assertMapped($converted);

        $foreignKeys = self::foreignKeys();
        $uniques = self::constraintsTouching('u', $converted);
        $checks = self::constraintsTouching('c', $converted);
        $indexes = self::indexesTouching($converted);
        $primaryKeys = self::primaryKeyNames($pkTables);

        foreach ($foreignKeys as $constraint) {
            DB::statement(sprintf('alter table %s drop constraint %s', self::qi($constraint['table']), self::qi($constraint['name'])));
        }

        foreach ($uniques as $constraint) {
            DB::statement(sprintf('alter table %s drop constraint if exists %s', self::qi($constraint['table']), self::qi($constraint['name'])));
        }

        foreach ($indexes as $index) {
            DB::statement(sprintf('drop index if exists %s', self::qi($index['name'])));
        }

        foreach ($primaryKeys as $table => $name) {
            DB::statement(sprintf('alter table %s drop constraint %s', self::qi($table), self::qi($name)));
        }

        foreach ($converted as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(sprintf('alter table %s drop column %s', self::qi($table), self::qi($column['name'])));
                DB::statement(sprintf('alter table %s rename column %s to %s', self::qi($table), self::qi($column['name'].'_new'), self::qi($column['name'])));
            }
        }

        foreach ($pkTables as $table) {
            $sequence = DB::scalar('select pg_get_serial_sequence(?, ?)', [$table, 'id']);

            if (is_string($sequence) && $sequence !== '' && ! str_ends_with($sequence, '.'.$table.'_id_seq') && ! str_ends_with($sequence, $table.'_id_seq')) {
                DB::statement(sprintf('alter sequence %s rename to %s', $sequence, self::qi($table.'_id_seq')));
            }
        }

        foreach ($primaryKeys as $table => $name) {
            DB::statement(sprintf('alter table %s add constraint %s primary key (id)', self::qi($table), self::qi($name)));
            DB::statement(sprintf('alter table %s add constraint %s unique (uuid)', self::qi($table), self::qi($table.'_uuid_unique')));
        }

        foreach ($uniques as $constraint) {
            DB::statement(sprintf('alter table %s add constraint %s %s', self::qi($constraint['table']), self::qi($constraint['name']), $constraint['def']));
        }

        foreach ($checks as $constraint) {
            DB::statement(sprintf('alter table %s add constraint %s %s', self::qi($constraint['table']), self::qi($constraint['name']), $constraint['def']));
        }

        foreach ($indexes as $index) {
            DB::statement($index['def']);
        }

        foreach ($foreignKeys as $constraint) {
            DB::statement(sprintf('alter table %s add constraint %s %s', self::qi($constraint['table']), self::qi($constraint['name']), $constraint['def']));
        }

        self::replaceIdFunctions();

        foreach (self::counts(array_keys($converted)) as $table => $count) {
            if ($count !== ($counts[$table] ?? -1)) {
                throw new RuntimeException("Row count changed on {$table} during identity cutover.");
            }
        }

        $after = DB::scalar("select udt_name from information_schema.columns where table_schema = 'public' and table_name = 'users' and column_name = 'id'");

        if ($after !== 'int8') {
            throw new RuntimeException('users.id was not converted to bigint.');
        }
    }

    /**
     * @return list<string>
     */
    private static function uuidPrimaryTables(): array
    {
        $rows = DB::select("
            select c.relname as table_name
            from pg_class c
            join pg_namespace n on n.oid = c.relnamespace
            join pg_attribute a on a.attrelid = c.oid and a.attname = 'id' and not a.attisdropped
            join pg_type t on t.oid = a.atttypid
            join pg_constraint pk on pk.conrelid = c.oid and pk.contype = 'p' and a.attnum = any(pk.conkey)
            where n.nspname = 'public' and c.relkind = 'r' and t.typname = 'uuid'
            order by c.relname
        ");

        return array_map(static fn (object $row): string => (string) $row->table_name, $rows);
    }

    /**
     * @param  list<string>  $pkTables
     * @return array<string, list<array{name: string, nullable: bool}>>
     */
    private static function uuidColumnsToRewrite(array $pkTables): array
    {
        $rows = DB::select("
            select table_name, column_name, is_nullable
            from information_schema.columns
            where table_schema = 'public' and udt_name = 'uuid'
            order by table_name, ordinal_position
        ");

        $converted = [];

        foreach ($rows as $row) {
            $key = $row->table_name.'.'.$row->column_name;

            if (in_array($key, self::PUBLIC_UUID_COLUMNS, true)) {
                continue;
            }

            if ($row->column_name === 'id' && ! in_array($row->table_name, $pkTables, true)) {
                continue;
            }

            $converted[$row->table_name][] = [
                'name' => (string) $row->column_name,
                'nullable' => $row->is_nullable === 'YES',
            ];
        }

        return $converted;
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private static function counts(array $tables): array
    {
        $counts = [];

        foreach ($tables as $table) {
            self::assertIdentifier($table);
            $counts[$table] = (int) DB::scalar('select count(*) from '.self::qi($table));
        }

        return $counts;
    }

    /**
     * @param  list<string>  $pkTables
     */
    private static function addSurrogateKeys(array $pkTables): void
    {
        foreach ($pkTables as $table) {
            DB::statement(sprintf('alter table %s add column uuid uuid', self::qi($table)));
            DB::statement(sprintf('update %s set uuid = id', self::qi($table)));
            DB::statement(sprintf('alter table %s alter column uuid set not null', self::qi($table)));
            DB::statement(sprintf('alter table %s add column id_new bigint generated by default as identity', self::qi($table)));
        }
    }

    /**
     * @param  array<string, list<array{name: string, nullable: bool}>>  $converted
     */
    private static function addForeignKeySurrogates(array $converted): void
    {
        foreach ($converted as $table => $columns) {
            foreach ($columns as $column) {
                if ($column['name'] === 'id') {
                    continue;
                }

                DB::statement(sprintf(
                    'alter table %s add column %s bigint',
                    self::qi($table),
                    self::qi($column['name'].'_new'),
                ));
            }
        }
    }

    /**
     * @param  array<string, list<array{name: string, nullable: bool}>>  $converted
     */
    private static function backfillForeignKeys(array $converted): void
    {
        DB::statement('create temp table identity_id_map (old_id uuid primary key, new_id bigint not null) on commit drop');

        foreach (array_keys($converted) as $table) {
            $hasNewId = DB::scalar(
                "select 1 from information_schema.columns where table_schema = 'public' and table_name = ? and column_name = 'id_new'",
                [$table],
            );

            if ($hasNewId === null) {
                continue;
            }

            DB::statement(sprintf(
                'insert into identity_id_map (old_id, new_id) select id, id_new from %s on conflict (old_id) do nothing',
                self::qi($table),
            ));
        }

        foreach ($converted as $table => $columns) {
            foreach ($columns as $column) {
                if ($column['name'] === 'id') {
                    continue;
                }

                $parent = self::parentTable($table, $column['name']);
                $newColumn = $column['name'].'_new';

                if ($parent !== null) {
                    DB::statement(sprintf(
                        'update %s as child set %s = parent.id_new from %s as parent where child.%s = parent.id and child.%s is not null',
                        self::qi($table),
                        self::qi($newColumn),
                        self::qi($parent),
                        self::qi($column['name']),
                        self::qi($column['name']),
                    ));
                } else {
                    DB::statement(sprintf(
                        'update %s as child set %s = map.new_id from identity_id_map as map where child.%s = map.old_id and child.%s is not null',
                        self::qi($table),
                        self::qi($newColumn),
                        self::qi($column['name']),
                        self::qi($column['name']),
                    ));
                }

                if (! $column['nullable']) {
                    DB::statement(sprintf(
                        'alter table %s alter column %s set not null',
                        self::qi($table),
                        self::qi($newColumn),
                    ));
                }
            }
        }
    }

    /**
     * @param  array<string, list<array{name: string, nullable: bool}>>  $converted
     */
    private static function assertMapped(array $converted): void
    {
        foreach ($converted as $table => $columns) {
            foreach ($columns as $column) {
                if ($column['name'] === 'id') {
                    continue;
                }

                $missing = (int) DB::scalar(sprintf(
                    'select count(*) from %s where %s is not null and %s is null',
                    self::qi($table),
                    self::qi($column['name']),
                    self::qi($column['name'].'_new'),
                ));

                if ($missing !== 0) {
                    throw new RuntimeException("Unmapped values in {$table}.{$column['name']}.");
                }
            }
        }
    }

    private static function parentTable(string $table, string $column): ?string
    {
        $parent = DB::scalar("
            select ccu.table_name
            from information_schema.table_constraints tc
            join information_schema.key_column_usage kcu
                on tc.constraint_name = kcu.constraint_name
                and tc.table_schema = kcu.table_schema
                and tc.table_name = kcu.table_name
            join information_schema.constraint_column_usage ccu
                on ccu.constraint_name = tc.constraint_name
                and ccu.table_schema = tc.table_schema
            where tc.table_schema = 'public'
                and tc.constraint_type = 'FOREIGN KEY'
                and kcu.table_name = ?
                and kcu.column_name = ?
                and ccu.column_name = 'id'
            order by ccu.table_name
            limit 1
        ", [$table, $column]);

        return is_string($parent) ? $parent : null;
    }

    /**
     * @return list<array{table: string, name: string, def: string}>
     */
    private static function foreignKeys(): array
    {
        return self::constraintRows("c.contype = 'f'");
    }

    /**
     * @param  array<string, list<array{name: string, nullable: bool}>>  $converted
     * @return list<array{table: string, name: string, def: string}>
     */
    private static function constraintsTouching(string $type, array $converted): array
    {
        return array_values(array_filter(
            self::constraintRows('c.contype = ?', [$type]),
            static fn (array $constraint): bool => self::touches($constraint['table'], $constraint['columns'], $converted),
        ));
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<array{table: string, name: string, def: string, columns: list<string>}>
     */
    private static function constraintRows(string $where, array $bindings = []): array
    {
        $rows = DB::select("
            select r.relname as table_name,
                   c.conname as name,
                   pg_get_constraintdef(c.oid) as def,
                   coalesce(json_agg(a.attname order by u.ord) filter (where a.attname is not null), '[]') as columns
            from pg_constraint c
            join pg_class r on r.oid = c.conrelid
            join pg_namespace n on n.oid = r.relnamespace
            left join unnest(c.conkey) with ordinality as u(attnum, ord) on true
            left join pg_attribute a on a.attrelid = c.conrelid and a.attnum = u.attnum
            where n.nspname = 'public' and {$where}
            group by c.oid, r.relname, c.conname
            order by r.relname, c.conname
        ", $bindings);

        return array_map(static function (object $row): array {
            $columns = json_decode((string) $row->columns, true);

            return [
                'table' => (string) $row->table_name,
                'name' => (string) $row->name,
                'def' => (string) $row->def,
                'columns' => is_array($columns) ? array_map(strval(...), $columns) : [],
            ];
        }, $rows);
    }

    /**
     * @param  array<string, list<array{name: string, nullable: bool}>>  $converted
     * @return list<array{name: string, def: string}>
     */
    private static function indexesTouching(array $converted): array
    {
        $rows = DB::select("
            select ic.relname as index_name,
                   t.relname as table_name,
                   pg_get_indexdef(i.indexrelid) as def,
                   coalesce(json_agg(a.attname) filter (where a.attname is not null), '[]') as columns
            from pg_index i
            join pg_class ic on ic.oid = i.indexrelid
            join pg_class t on t.oid = i.indrelid
            join pg_namespace n on n.oid = t.relnamespace
            left join unnest(i.indkey) as k(attnum) on k.attnum > 0
            left join pg_attribute a on a.attrelid = t.oid and a.attnum = k.attnum
            where n.nspname = 'public'
                and not i.indisprimary
                and not exists (select 1 from pg_constraint c where c.conindid = i.indexrelid)
            group by i.indexrelid, ic.relname, t.relname
        ");

        $indexes = [];

        foreach ($rows as $row) {
            $columns = json_decode((string) $row->columns, true);

            if (! is_array($columns) || ! self::touches((string) $row->table_name, array_map(strval(...), $columns), $converted)) {
                continue;
            }

            $indexes[] = [
                'name' => (string) $row->index_name,
                'def' => (string) $row->def,
            ];
        }

        return $indexes;
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, string>
     */
    private static function primaryKeyNames(array $tables): array
    {
        $names = [];

        foreach ($tables as $table) {
            $name = DB::scalar("
                select c.conname
                from pg_constraint c
                join pg_class r on r.oid = c.conrelid
                where r.relname = ? and c.contype = 'p'
            ", [$table]);

            if (! is_string($name) || $name === '') {
                throw new RuntimeException("Missing primary key on {$table}.");
            }

            $names[$table] = $name;
        }

        return $names;
    }

    /**
     * @param  list<string>  $columns
     * @param  array<string, list<array{name: string, nullable: bool}>>  $converted
     */
    private static function touches(string $table, array $columns, array $converted): bool
    {
        $names = array_column($converted[$table] ?? [], 'name');

        return array_intersect($columns, $names) !== [];
    }

    private static function replaceIdFunctions(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function rahandaz_grant_same_workspace() returns trigger
            language plpgsql as $$
            declare
                found bigint;
            begin
                if new.resource_type = 'project' then
                    select workspace_id into found from projects where id = new.resource_id;
                elsif new.resource_type = 'environment' then
                    select workspace_id into found from environments where id = new.resource_id;
                elsif new.resource_type = 'managed_service' then
                    select workspace_id into found from managed_services where id = new.resource_id;
                elsif new.resource_type = 'database_instance' then
                    select workspace_id into found from database_instances where id = new.resource_id;
                elsif new.resource_type = 'git_connection' then
                    select workspace_id into found from git_connections where id = new.resource_id;
                elsif new.resource_type = 'local_host' then
                    select workspace_id into found from local_hosts where id = new.resource_id;
                else
                    raise exception 'unsupported grant resource' using errcode = '23514';
                end if;

                if found is null or found is distinct from new.workspace_id then
                    raise exception 'grant resource is outside the workspace' using errcode = '23514';
                end if;

                return new;
            end $$;

            create or replace function rahandaz_firewall_project_same_host() returns trigger
            language plpgsql as $$
            declare
                project_host bigint;
            begin
                if new.project_id is null then
                    return new;
                end if;
                select local_host_id into project_host from projects where id = new.project_id;
                if project_host is null or project_host is distinct from new.local_host_id then
                    raise exception 'firewall rule project is on another host' using errcode = '23514';
                end if;
                return new;
            end $$;
            SQL);
    }

    private static function qi(string $identifier): string
    {
        self::assertIdentifier($identifier);

        return '"'.$identifier.'"';
    }

    private static function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-z_][a-z0-9_]*$/', $identifier) !== 1) {
            throw new RuntimeException('Unexpected SQL identifier.');
        }
    }
}
