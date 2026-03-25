<?php
/**
 * Performance Caching Layer
 * Provides simple caching with automatic timeout management
 * Supports both file-based and Redis (if available)
 */

class PerformanceCache {
    private static $instance = null;
    private $cache_dir = null;
    private $use_redis = false;
    private $redis = null;
    private $default_ttl = 3600; // 1 hour
    
    private function __construct() {
        global $app_config;
        
        $this->cache_dir = $app_config['upload_dir'] . '../cache/';
        
        // Create cache directory if needed
        if (!is_dir($this->cache_dir)) {
            @mkdir($this->cache_dir, 0755, true);
        }
        
        // Try to use Redis if available
        if (extension_loaded('redis') && getenv('REDIS_URL')) {
            try {
                $this->redis = new Redis();
                $redis_url = parse_url(getenv('REDIS_URL'));
                $this->redis->connect($redis_url['host'], $redis_url['port'] ?? 6379);
                if (!empty($redis_url['pass'])) {
                    $this->redis->auth($redis_url['pass']);
                }
                $this->use_redis = true;
            } catch (Exception $e) {
                error_log("Redis connection failed: " . $e->getMessage());
                $this->use_redis = false;
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get cached value
     */
    public function get($key, $default = null) {
        try {
            if ($this->use_redis) {
                $value = $this->redis->get($key);
                return $value !== false ? json_decode($value, true) : $default;
            } else {
                $file = $this->cache_dir . md5($key) . '.cache';
                if (file_exists($file)) {
                    $data = json_decode(file_get_contents($file), true);
                    if ($data['expires'] > time()) {
                        return $data['value'];
                    }
                    @unlink($file);
                }
            }
        } catch (Exception $e) {
            error_log("Cache get error: " . $e->getMessage());
        }
        return $default;
    }
    
    /**
     * Set cached value
     */
    public function set($key, $value, $ttl = null) {
        if ($ttl === null) $ttl = $this->default_ttl;
        
        try {
            if ($this->use_redis) {
                $this->redis->setex($key, $ttl, json_encode($value));
            } else {
                $file = $this->cache_dir . md5($key) . '.cache';
                $data = [
                    'value' => $value,
                    'expires' => time() + $ttl,
                    'created' => time()
                ];
                @file_put_contents($file, json_encode($data), LOCK_EX);
            }
            return true;
        } catch (Exception $e) {
            error_log("Cache set error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete cached value
     */
    public function delete($key) {
        try {
            if ($this->use_redis) {
                $this->redis->del($key);
            } else {
                $file = $this->cache_dir . md5($key) . '.cache';
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("Cache delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear all cached values
     */
    public function clear() {
        try {
            if ($this->use_redis) {
                $this->redis->flushDB();
            } else {
                $files = glob($this->cache_dir . '*.cache');
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("Cache clear error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remember pattern: get or set
     */
    public function remember($key, $ttl, $callback) {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        $value = call_user_func($callback);
        $this->set($key, $value, $ttl);
        return $value;
    }
}

/**
 * Helper function to access cache globally
 */
function cache() {
    return PerformanceCache::getInstance();
}

/**
 * Quick cache helpers
 */
function cache_get($key, $default = null) {
    return cache()->get($key, $default);
}

function cache_set($key, $value, $ttl = 3600) {
    return cache()->set($key, $value, $ttl);
}

function cache_remember($key, $ttl, $callback) {
    return cache()->remember($key, $ttl, $callback);
}

function cache_forget($key) {
    return cache()->delete($key);
}
