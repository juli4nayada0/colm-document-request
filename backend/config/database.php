<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Database Connection Manager (PDO Singleton)
 */

declare(strict_types=1);

namespace Colm\Config;

use PDO;
use PDOException;
use RuntimeException;

class Database {
    private static ?PDO $instance = null;

    /**
     * Get active PDO database instance
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $config = require __DIR__ . '/config.php';
            $db = $config['db'];

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['database'],
                $db['charset']
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$db['charset']} COLLATE {$db['charset']}_unicode_ci, time_zone = '+08:00'"
            ];

            try {
                self::$instance = new PDO($dsn, $db['username'], $db['password'], $options);
            } catch (PDOException $e) {
                // Log technical error securely and do not expose credentials
                error_log('[COLM DB ERROR] Connection failed: ' . $e->getMessage());
                throw new RuntimeException('Database connection failure. Please check database server and credentials.', 500);
            }
        }

        return self::$instance;
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool {
        return self::getConnection()->beginTransaction();
    }

    /**
     * Commit active transaction
     */
    public static function commit(): bool {
        return self::getConnection()->commit();
    }

    /**
     * Rollback active transaction
     */
    public static function rollBack(): bool {
        if (self::getConnection()->inTransaction()) {
            return self::getConnection()->rollBack();
        }
        return false;
    }
}
