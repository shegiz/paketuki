<?php

namespace Paketuki;

/**
 * Simple file-based cache
 */
class Cache
{
    private string $cacheDir;
    private int $defaultTtl;

    public function __construct(string $cacheDir = 'cache', int $defaultTtl = 300)
    {
        $this->cacheDir = $cacheDir;
        $this->defaultTtl = $defaultTtl;
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }

    /**
     * Get cached value
     */
    public function get(string $key): ?string
    {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }

    /**
     * Set cached value
     */
    public function set(string $key, string $value, ?int $ttl = null): bool
    {
        $file = $this->getCacheFile($key);
        $ttl = $ttl ?? $this->defaultTtl;
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
        ];
        
        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    /**
     * Delete cached value
     */
    public function delete(string $key): bool
    {
        $file = $this->getCacheFile($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }

    /**
     * Clear all cache
     */
    public function clear(): void
    {
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Generate cache file path
     */
    private function getCacheFile(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . '/' . $hash . '.cache';
    }
}
