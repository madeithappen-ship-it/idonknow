<?php
/**
 * Database Query Optimization & Batch Processing
 */

class QueryOptimizer {
    private static $batch_queries = [];
    private static $query_cache = [];
    
    /**
     * Execute query with caching
     */
    public static function query($sql, $params = [], $cache_ttl = 0, $cache_key = null) {
        global $pdo;
        
        if ($cache_ttl > 0) {
            $key = $cache_key ?? md5($sql . json_encode($params));
            $cached = cache_get($key);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($cache_ttl > 0) {
                cache_set($key ?? md5($sql . json_encode($params)), $result, $cache_ttl);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Query error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get single row with caching
     */
    public static function queryOne($sql, $params = [], $cache_ttl = 0, $cache_key = null) {
        $result = self::query($sql, $params, $cache_ttl, $cache_key);
        return $result[0] ?? null;
    }
    
    /**
     * Get single value with caching
     */
    public static function queryValue($sql, $params = [], $cache_ttl = 0) {
        global $pdo;
        
        if ($cache_ttl > 0) {
            $key = md5($sql . json_encode($params));
            $cached = cache_get($key);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $value = $stmt->fetchColumn();
            
            if ($cache_ttl > 0) {
                cache_set(md5($sql . json_encode($params)), $value, $cache_ttl);
            }
            
            return $value;
        } catch (Exception $e) {
            error_log("Query error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Invalidate cache for a pattern
     */
    public static function invalidateCache($pattern = null) {
        if ($pattern === null) {
            cache()->clear();
        } else {
            // Simple pattern invalidation
            cache()->delete($pattern);
        }
    }
    
    /**
     * Batch query multiple IDs into single query
     * Converts: SELECT * FROM users WHERE id = ? OR id = ? OR id = ?
     * Into: SELECT * FROM users WHERE id IN (?, ?, ?)
     */
    public static function queryBatch($table, $ids, $where = '', $select = '*', $cache_ttl = 300) {
        global $pdo;
        
        if (empty($ids)) {
            return [];
        }
        
        $ids = array_values(array_unique(array_filter($ids, function($id) {
            return !empty($id);
        })));
        
        $cache_key = "batch_{$table}_" . md5(json_encode($ids));
        if ($cache_ttl > 0) {
            $cached = cache_get($cache_key);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT {$select} FROM {$table} WHERE id IN ({$placeholders})";
        if (!empty($where)) {
            $sql .= " AND {$where}";
        }
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($cache_ttl > 0) {
                cache_set($cache_key, $result, $cache_ttl);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Batch query error: " . $e->getMessage());
            return [];
        }
    }
}
