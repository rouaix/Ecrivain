<?php

/**
 * Logger — centralised, structured JSON logging.
 *
 * Each entry is a single JSON line appended to src/logs/app.jsonl.
 * Readable with: tail -f app.jsonl | jq .
 *
 * Usage:
 *   Logger::configure($f3->get('ROOT') . '/logs');   // once in index.php
 *   Logger::info('auth', 'User logged in', ['user_id' => 42]);
 *   Logger::error('ai', 'API call failed', ['model' => $model, 'err' => $e->getMessage()]);
 */
class Logger
{
    public const DEBUG = 0;
    public const INFO  = 1;
    public const WARN  = 2;
    public const ERROR = 3;

    private const NAMES = [
        self::DEBUG => 'DEBUG',
        self::INFO  => 'INFO',
        self::WARN  => 'WARN',
        self::ERROR => 'ERROR',
    ];

    private static ?string $logPath = null;
    private static int $minLevel    = self::INFO;

    /** Call once at bootstrap (index.php). */
    public static function configure(string $logDir, int $minLevel = self::INFO): void
    {
        self::$logPath  = rtrim($logDir, '/\\') . '/app.jsonl';
        self::$minLevel = $minLevel;
    }

    public static function debug(string $module, string $msg, array $ctx = []): void
    {
        self::log(self::DEBUG, $module, $msg, $ctx);
    }

    public static function info(string $module, string $msg, array $ctx = []): void
    {
        self::log(self::INFO, $module, $msg, $ctx);
    }

    public static function warn(string $module, string $msg, array $ctx = []): void
    {
        self::log(self::WARN, $module, $msg, $ctx);
    }

    public static function error(string $module, string $msg, array $ctx = []): void
    {
        self::log(self::ERROR, $module, $msg, $ctx);
    }

    public static function log(int $level, string $module, string $msg, array $ctx = []): void
    {
        if (self::$logPath === null || $level < self::$minLevel) {
            return;
        }

        $entry = ['ts' => date('c'), 'level' => self::NAMES[$level] ?? 'UNKNOWN', 'module' => $module, 'msg' => $msg];
        if (!empty($ctx)) {
            $entry['ctx'] = $ctx;
        }

        @file_put_contents(
            self::$logPath,
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
