<?php

declare(strict_types=1);

namespace App\Domain\Databases\Enums;

use App\Support\HasEnumLabel;

enum DatabaseEngine: string
{
    use HasEnumLabel;

    case Postgresql = 'postgresql';
    case Mysql = 'mysql';
    case Mariadb = 'mariadb';
    case Mongodb = 'mongodb';
    case Mssql = 'mssql';
    case Redis = 'redis';

    public function label(): string
    {
        return match ($this) {
            self::Postgresql => 'PostgreSQL',
            self::Mysql => 'MySQL',
            self::Mariadb => 'MariaDB',
            self::Mongodb => 'MongoDB',
            self::Mssql => 'SQL Server',
            self::Redis => 'Redis',
        };
    }
}
