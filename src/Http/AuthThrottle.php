<?php
declare(strict_types=1);

namespace App\Http;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;

/**
 * Fixed-window counters per client IP, in PostgreSQL (`auth_throttles`; the IP is stored hashed),
 * so every app container shares them and they survive redeploys.
 * - `login`: failed logins, wrong 2FA codes and failed passkeys (`Security.throttle.loginFailures`
 *   per `loginWindow` seconds). Per-account lockout is a separate concern.
 * - any other bucket (register, password reset, resend): every submission
 *   (`accountActions` per `accountActionsWindow`).
 * Each hit is one atomic upsert (no lost counts under concurrency); expired rows are purged now
 * and then (purgeExpired()).
 */
class AuthThrottle
{
    public const LOGIN = 'login';

    /**
     * One purge every N hits (on average).
     */
    private const PURGE_EVERY = 100;

    /**
     * @param string $ip Client IP.
     * @return void
     */
    public static function recordLoginFailure(string $ip): void
    {
        self::hit(self::LOGIN, $ip);
    }

    /**
     * @param string $bucket Bucket (action name).
     * @param string $ip Client IP.
     * @return void
     */
    public static function recordAction(string $bucket, string $ip): void
    {
        self::hit($bucket, $ip);
    }

    /**
     * @param string $bucket Bucket.
     * @param string $ip Client IP.
     * @return int Seconds to wait (0 = allowed).
     */
    public static function retryAfter(string $bucket, string $ip): int
    {
        $row = self::current($bucket, $ip);
        if ($row === null || $row['hits'] < self::limit($bucket)) {
            return 0;
        }

        return max(1, $row['window_ends'] - time());
    }

    /**
     * @param string $bucket Bucket.
     * @param string $ip Client IP.
     * @return int Hits in the current window.
     */
    public static function hits(string $bucket, string $ip): int
    {
        return self::current($bucket, $ip)['hits'] ?? 0;
    }

    /**
     * Deletes the rows whose window has ended.
     *
     * @return int Deleted rows.
     */
    public static function purgeExpired(): int
    {
        return self::connection()->execute(
            'DELETE FROM auth_throttles WHERE window_ends <= :now',
            ['now' => time()],
            ['now' => 'integer'],
        )->rowCount();
    }

    /**
     * Counts one hit: starts a window or increments the current one (restarting it if it ended).
     *
     * @param string $bucket Bucket.
     * @param string $ip Client IP.
     * @return void
     */
    private static function hit(string $bucket, string $ip): void
    {
        $now = time();
        self::connection()->execute(
            'INSERT INTO auth_throttles (bucket, ip_hash, hits, window_ends) VALUES (:bucket, :hash, 1, :ends)
             ON CONFLICT (bucket, ip_hash) DO UPDATE SET
               hits = CASE WHEN auth_throttles.window_ends <= :now THEN 1 ELSE auth_throttles.hits + 1 END,
               window_ends = CASE WHEN auth_throttles.window_ends <= :now
                 THEN :ends ELSE auth_throttles.window_ends END',
            ['bucket' => $bucket, 'hash' => self::hash($ip), 'ends' => $now + self::window($bucket), 'now' => $now],
            ['ends' => 'integer', 'now' => 'integer'],
        );
        if (random_int(1, self::PURGE_EVERY) === 1) {
            self::purgeExpired();
        }
    }

    /**
     * @param string $bucket Bucket.
     * @param string $ip Client IP.
     * @return array{hits: int, window_ends: int}|null Current window, or null when expired/absent.
     */
    private static function current(string $bucket, string $ip): ?array
    {
        $row = self::connection()->execute(
            'SELECT hits, window_ends FROM auth_throttles
             WHERE bucket = :bucket AND ip_hash = :hash AND window_ends > :now',
            ['bucket' => $bucket, 'hash' => self::hash($ip), 'now' => time()],
            ['now' => 'integer'],
        )->fetch('assoc');

        return is_array($row) ? ['hits' => (int)$row['hits'], 'window_ends' => (int)$row['window_ends']] : null;
    }

    /**
     * @param string $ip Client IP.
     * @return string
     */
    private static function hash(string $ip): string
    {
        return hash('sha256', $ip);
    }

    /**
     * @return \Cake\Database\Connection
     */
    private static function connection(): Connection
    {
        $connection = ConnectionManager::get('default');
        assert($connection instanceof Connection);

        return $connection;
    }

    /**
     * @param string $bucket Bucket.
     * @return int
     */
    private static function limit(string $bucket): int
    {
        return $bucket === self::LOGIN
            ? (int)Configure::read('Security.throttle.loginFailures', 20)
            : (int)Configure::read('Security.throttle.accountActions', 5);
    }

    /**
     * @param string $bucket Bucket.
     * @return int Seconds.
     */
    private static function window(string $bucket): int
    {
        return $bucket === self::LOGIN
            ? (int)Configure::read('Security.throttle.loginWindow', 900)
            : (int)Configure::read('Security.throttle.accountActionsWindow', 3600);
    }
}
