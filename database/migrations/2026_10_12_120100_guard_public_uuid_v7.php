<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Query-builder inserts skip Eloquent events. This trigger is the database
 * guarantee that a public uuid is present, is UUIDv7, and does not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function rahandaz_guard_public_uuid() returns trigger
            language plpgsql as $$
            begin
                if new.uuid is null then
                    raise exception 'public uuid is required' using errcode = '23502';
                end if;

                if substring(new.uuid::text from 15 for 1) <> '7' then
                    raise exception 'public uuid must be UUIDv7' using errcode = '23514';
                end if;

                if tg_op = 'UPDATE' and new.uuid is distinct from old.uuid then
                    raise exception 'public uuid is immutable' using errcode = '23514';
                end if;

                return new;
            end $$;
            SQL);

        $tables = DB::select("
            select table_name
            from information_schema.columns
            where table_schema = 'public' and column_name = 'uuid' and udt_name = 'uuid'
            order by table_name
        ");

        foreach ($tables as $table) {
            $name = (string) $table->table_name;

            if (preg_match('/^[a-z_][a-z0-9_]*$/', $name) !== 1) {
                continue;
            }

            DB::statement(sprintf('drop trigger if exists %s_public_uuid_guard on %s', $name, $name));
            DB::statement(sprintf(
                'create trigger %s_public_uuid_guard before insert or update on %s for each row execute function rahandaz_guard_public_uuid()',
                $name,
                $name,
            ));
        }
    }

    public function down(): void
    {
        $tables = DB::select("
            select event_object_table as table_name
            from information_schema.triggers
            where trigger_schema = 'public' and trigger_name like '%\\_public\\_uuid\\_guard' escape '\\'
        ");

        foreach ($tables as $table) {
            $name = (string) $table->table_name;
            DB::statement(sprintf('drop trigger if exists %s_public_uuid_guard on %s', $name, $name));
        }

        DB::unprepared('drop function if exists rahandaz_guard_public_uuid()');
    }
};
