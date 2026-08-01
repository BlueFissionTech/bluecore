<?php

namespace BlueFission\BlueCore\Gateway;

use BlueFission\Date;
use BlueFission\Security\Hash;
use BlueFission\Services\Gateway;
use BlueFission\Services\Request;
use BlueFission\Data\Storage\Storage;
use BlueFission\Str;

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
            $currentTime = (int)Date::now()->timestamp();
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
        $this->cache->timestamp = (int)Date::now()->timestamp();
        $this->cache->write();

        $arguments = $response;
    }

    private function generateCacheKey(Request $request): string
    {
        $payload = Str::make($request->uri())
            ->append(':')
            ->append(serialize($request->data()))
            ->val();

        return Hash::value($payload, 'md5');
    }

    private function handleRequest(Request $request): mixed
    {
        return null;
    }
}
