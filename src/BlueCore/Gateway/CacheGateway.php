<?php

namespace BlueFission\BlueCore\Gateway;

use BlueFission\Services\Gateway;
use BlueFission\Services\Request;
use BlueFission\Data\Storage\Storage;

class CacheGateway extends Gateway
{
    protected $cache;
    protected $cacheTTL;

    public function __construct(Storage $cache, int $cacheTTL)
    {
        $this->cache = $cache;
        $this->cacheTTL = $cacheTTL;
    }

    public function process(Request $request, &$arguments)
    {
        $cacheKey = $this->generateCacheKey($request);

        if ($this->cache->has($cacheKey)) {
            // Get the cache entry
            $this->cache->hash = $cacheKey;
            $this->cache->read();
            $this->cache->data;

            // Check if the cache entry is still within TTL
            $currentTime = time();
            if (($currentTime - $this->cache->timestamp) < $this->cacheTTL) {
                $arguments = $this->cache->data;
                return;
            }
        }

        // Call the next middleware or controller
        $response = $this->handleRequest($request);

        // Cache the response
        $this->cache->hash = $cacheKey;
        $this->cache->data = $response;
        $this->cache->timestamp = time();
        $this->cache->write();

        $arguments = $response;
    }

    private function generateCacheKey(Request $request)
    {
        return md5($request->uri() . ':' . serialize($request->data()));
    }

    private function handleRequest(Request $request)
    {
        // Handle the request and return the response
        // You need to implement this based on your application logic
        // For example, call the next middleware or the controller action
        return $response;
    }
}
