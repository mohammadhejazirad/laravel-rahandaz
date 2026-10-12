<?php

declare(strict_types=1);

namespace App\Domain\Databases\Enums;

enum DatabaseEngine: string
{
    case Postgresql = 'postgresql';
    case Mysql = 'mysql';
    case Mariadb = 'mariadb';
    case Mongodb = 'mongodb';
    case Mssql = 'mssql';
    case Redis = 'redis';
}
