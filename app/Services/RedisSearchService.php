<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Service สำหรับการจัดการ Full-Text Search และการเก็บข้อมูลความมั่นคงสูง (Zero-Data-Loss)
 * ผ่าน Dedicated Redis Engine (:6379) พร้อม AOF Persistence
 */
class RedisSearchService
{
    private const CONNECTION = 'search';
    private const PERSISTENT_CONNECTION = 'persistent';

    /**
     * สร้างหรืออัปเดต Search Index สำหรับเอกสารหรือข้อมูลกิจกรรม/งาน
     *
     * @param string $indexName ชื่อ Index เช่น 'idx:activities'
     * @param string $docId รหัสเอกสาร เช่น 'activity:12'
     * @param array<string, mixed> $fields ฟิลด์และเนื้อหาที่ต้องการทำดัชนี
     */
    public function indexDocument(string $indexName, string $docId, array $fields): bool
    {
        try {
            $redis = Redis::connection(self::CONNECTION);
            
            // จัดเก็บข้อมูลเอกสารใน Hash Structure
            $key = "doc:{$indexName}:{$docId}";
            $stringFields = [];
            foreach ($fields as $k => $v) {
                $stringFields[$k] = is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
            }
            $redis->hmset($key, $stringFields);
            
            // สร้าง Inverted Index สำหรับการค้นหาคำ (Full-Text Search Tokens)
            foreach ($fields as $fieldName => $fieldValue) {
                if (is_string($fieldValue) && !empty($fieldValue)) {
                    $words = preg_split('/[\s,\.\-_]+/u', mb_strtolower($fieldValue));
                    if ($words) {
                        foreach (array_unique(array_filter($words)) as $word) {
                            if (mb_strlen($word) >= 2) {
                                $redis->sadd("token:{$indexName}:{$fieldName}:{$word}", [$docId]);
                                $redis->sadd("token:{$indexName}:all:{$word}", [$docId]);
                            }
                        }
                    }
                }
            }

            $redis->sadd("index:{$indexName}:all_docs", [$docId]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * ค้นหาเอกสารแบบ Full-Text Search จาก Index
     *
     * @param string $indexName ชื่อ Index
     * @param string $query คำค้นหา
     * @param int $limit จำนวนผลลัพธ์สูงสุด
     * @return array<int, array<string, string>> รายการเอกสารที่ตรงกับคำค้นหา
     */
    public function search(string $indexName, string $query, int $limit = 20): array
    {
        try {
            $redis = Redis::connection(self::CONNECTION);
            $words = preg_split('/[\s,\.\-_]+/u', mb_strtolower(trim($query)));
            if (!$words) {
                return [];
            }

            $matchedDocIds = [];
            foreach (array_unique(array_filter($words)) as $word) {
                $tokenKey = "token:{$indexName}:all:{$word}";
                $docIds = $redis->smembers($tokenKey);
                if (!empty($docIds)) {
                    $matchedDocIds = empty($matchedDocIds) ? $docIds : array_intersect($matchedDocIds, $docIds);
                }
            }

            if (empty($matchedDocIds)) {
                return [];
            }

            $results = [];
            $count = 0;
            foreach ($matchedDocIds as $docId) {
                if ($count >= $limit) {
                    break;
                }
                $docKey = "doc:{$indexName}:{$docId}";
                $docData = $redis->hgetall($docKey);
                if (!empty($docData)) {
                    $docData['_id'] = $docId;
                    $results[] = $docData;
                    $count++;
                }
            }

            return $results;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * จัดเก็บข้อมูลที่ต้องการความมั่นคงสูง (Zero-Data-Loss) ลง Persistent Engine
     *
     * @param string $key คีย์ที่ต้องการบันทึก
     * @param mixed $value ค่าที่ต้องการจัดเก็บ
     * @param int|null $ttlSeconds เวลาหมดอายุ (วินาที) หรือ null หากไม่ต้องการให้หมดอายุ
     */
    public function storeCriticalData(string $key, mixed $value, ?int $ttlSeconds = null): bool
    {
        try {
            $redis = Redis::connection(self::PERSISTENT_CONNECTION);
            $encoded = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            
            if ($ttlSeconds !== null && $ttlSeconds > 0) {
                $redis->setex($key, $ttlSeconds, $encoded);
            } else {
                $redis->set($key, $encoded);
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * ดึงข้อมูลความมั่นคงสูง (Zero-Data-Loss) จาก Persistent Engine
     *
     * @param string $key
     * @return mixed
     */
    public function getCriticalData(string $key): mixed
    {
        try {
            $redis = Redis::connection(self::PERSISTENT_CONNECTION);
            $val = $redis->get($key);
            if ($val === false || $val === null) {
                return null;
            }

            $decoded = json_decode((string) $val, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
        } catch (Throwable) {
            return null;
        }
    }
}
