<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TYPES = "'laravel', 'php', 'static', 'static-react', 'static-vue', 'node', 'nuxt', 'next', 'python', 'django', 'flask', 'fastapi', 'express', 'vue', 'react', 'nest', 'adonis', 'wordpress'";

    private const LEGACY_TYPES = "'laravel', 'php', 'static', 'node', 'nuxt', 'next', 'python', 'django', 'flask', 'fastapi', 'express', 'vue', 'react', 'nest', 'adonis'";

    public function up(): void
    {
        DB::unprepared($this->constraint(self::TYPES));
    }

    public function down(): void
    {
        $blocked = DB::table('projects')
            ->whereNotIn('declared_type', ['laravel', 'php', 'static', 'node', 'nuxt', 'next', 'python', 'django', 'flask', 'fastapi', 'express', 'vue', 'react', 'nest', 'adonis'])
            ->orWhere(function ($query): void {
                $query->whereNotNull('detected_type')
                    ->whereNotIn('detected_type', ['laravel', 'php', 'static', 'node', 'nuxt', 'next', 'python', 'django', 'flask', 'fastapi', 'express', 'vue', 'react', 'nest', 'adonis']);
            })
            ->exists();

        if ($blocked) {
            throw new RuntimeException('Remove projects that use the newer application types before reverting this migration.');
        }

        DB::unprepared($this->constraint(self::LEGACY_TYPES));
    }

    private function constraint(string $types): string
    {
        return <<<SQL
            alter table projects drop constraint if exists projects_type_chk;
            alter table projects add constraint projects_type_chk check (
                declared_type in ({$types})
                and (detected_type is null or detected_type in ({$types}))
            );
            SQL;
    }
};
