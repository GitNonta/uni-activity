<?php

declare(strict_types=1);

/**
 * migrate_redis_to_valkey.php — Copy all keys from a Redis source to a Valkey target.
 *
 * Why key-by-key instead of copying dump.rdb?
 *   Valkey 9.x ไม่สามารถโหลด RDB ของ Redis 8.8 (format v14) ได้ ("Can't handle RDB format version 14")
 *   — ต้อง migrate แบบ key-by-key ด้วยคำสั่ง RESP ธรรมดา (ไม่มี RDB version เข้ามาเกี่ยวข้อง)
 *
 * Usage (run on Phone 1 ระหว่างที่ Redis ยังรันที่ :6379 และ Valkey รันที่ :6380):
 *   RPW="$(grep '^REDIS_PASSWORD=' .env | cut -d= -f2- | tr -d '\"\\r')" \
 *     php scripts/migrate_redis_to_valkey.php
 *
 * เปลี่ยน host/port ด้านล่างตามสถานการณ์จริง
 */

require __DIR__ . '/../vendor/autoload.php';

$srcHost = '127.0.0.1';
$srcPort = 6379;
$dstHost = '127.0.0.1';
$dstPort = 6380;
$pw = trim((string) (getenv('RPW') ?: ''), "\" \t\r\n");

$src = new Predis\Client(['host' => $srcHost, 'port' => $srcPort, 'password' => $pw, 'timeout' => 10]);
$dst = new Predis\Client(['host' => $dstHost, 'port' => $dstPort, 'password' => $pw, 'timeout' => 10]);
$src->connect();
$dst->connect();

$dst->flushdb();

$count = 0;
$errors = 0;
$cursor = '0';
$types = [];

do {
    $res = $src->scan($cursor, ['count' => 200]);
    $cursor = $res[0];

    foreach ($res[1] as $key) {
        $count++;
        try {
            $type = $src->type($key);
            $types[$type] = ($types[$type] ?? 0) + 1;
            $ttl = $src->ttl($key); // -1 = no expire, -2 = gone, >=0 = seconds

            switch ($type) {
                case 'string':
                    $dst->set($key, (string) $src->get($key));
                    break;
                case 'hash':
                    $dst->hmset($key, $src->hgetall($key));
                    break;
                case 'list':
                    $val = $src->lrange($key, 0, -1);
                    if ($val) {
                        $dst->rpush($key, $val);
                    }
                    break;
                case 'set':
                    $val = $src->smembers($key);
                    if ($val) {
                        $dst->sadd($key, $val);
                    }
                    break;
                case 'zset':
                    $assoc = $src->zrange($key, 0, -1, ['withscores' => true]);
                    $pairs = [];
                    foreach ($assoc as $member => $score) {
                        $pairs[$member] = (float) $score;
                    }
                    if ($pairs) {
                        $dst->zadd($key, $pairs);
                    }
                    break;
                default:
                    echo "SKIP type=$type key=$key\n";
                    $errors++;
                    continue 2;
            }

            if ($ttl > 0) {
                $dst->expire($key, $ttl);
            }
        } catch (Throwable $e) {
            $errors++;
            echo "ERR key=$key => " . $e->getMessage() . "\n";
        }
    }
} while ($cursor !== '0');

echo "DONE total=$count errors=$errors\n";
foreach ($types as $t => $n) {
    echo "  type $t: $n\n";
}
