<?php

namespace SyntaxPhoenix\Api\BGPView;

use Exception;
use GuzzleHttp\Client;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Caching\Storages\FileStorage;
use SyntaxPhoenix\Api\BGPView\Exceptions\RequestFailedException;

class BGPView
{

    private string $baseUrl;
    private bool $caching;
    private Client $client;
    private Storage $storage;
    private Cache $cache;
    private string $cacheTime;
    private float $lastRequestTimestamp = 0;
    private float $requestTimeout;
    private int $failures;
    private int $maxFailures;

    public function __construct(string $baseUrl, float $requestTimeout = 0.4, bool $caching = false, ?string $cachingUrl = 'web/cache/bgpview', ?string $cacheTime = '10 minutes', int $maxFailures = 3) {
        if ($caching) {
            $this->createPath($cachingUrl);
            $this->storage = new FileStorage($cachingUrl);
            $this->cache = new Cache($this->storage, 'bgpview-requests');
        }
        $this->baseUrl = $baseUrl;
        $this->caching = $caching;
        $this->cacheTime = $cacheTime;
        $this->requestTimeout = $requestTimeout;
        $this->maxFailures = $maxFailures;
        $this->client = new Client([
            'base_uri' => $baseUrl
        ]);
    }

    public function getAsnDetails(int $asNumber): array
    {
        return $this->getDataByApi('asn/' . $asNumber)['data'];
    }

    public function getAsnPrefixes(int $asNumber): array
    {
        return $this->getDataByApi('asn/' . $asNumber . '/prefixes')['data'];
    }

    public function getAsnPeers(int $asNumber): array
    {
        return $this->getDataByApi('asn/' . $asNumber . '/peers')['data'];
    }

    public function getAsnUpstreams(int $asNumber): array
    {
        return $this->getDataByApi('asn/' . $asNumber . '/upstreams')['data'];
    }

    public function getAsnDownstreams(int $asNumber): array
    {
        return $this->getDataByApi('asn/' . $asNumber . '/downstreams')['data'];
    }

    public function getPrefix(string $ipAddress, int $cidr): array
    {
        return $this->getDataByApi('prefix/' . $ipAddress . '/' . $cidr)['data'];
    }

    public function getIPDetails(string $ipAddress): array
    {
        return $this->getDataByApi('ip/' . $ipAddress)['data'];
    }

    public function getIXDetails(int $ixId): array
    {
        return $this->getDataByApi('ix/' . $ixId)['data'];
    }

    private function getDataByApi(string $urlPart): array
    {
        if ($this->caching) {
            $response = $this->cache->load($urlPart);
            if ($response) {
                return $response;
            }
        }
        $time = microtime(true);
        if ($this->lastRequestTimestamp > $time) {
            $restTime = $this->lastRequestTimestamp - $time;
            usleep($restTime * 1000 * 1000);
        }
        try {
            $response = $this->client->request('GET', $urlPart);
        } catch (Exception $exception) {
            $this->failures++;
            if ($this->failures > $this->maxFailures) {
                throw new RequestFailedException();
            }
            $this->lastRequestTimestamp = microtime(true) + ($this->requestTimeout * ($this->failures + 1));
            return $this->getDataByApi($urlPart);
        }
        if ($response->getStatusCode() != 200) {
            throw new RequestFailedException();
        }
        $finalResponse = json_decode($response->getBody(), true);
        if ($this->caching) {
            $this->cache->save($urlPart, $finalResponse, [
                Cache::EXPIRE => $this->cacheTime ?? '10 minutes',
            ]);
        }
        $this->lastRequestTimestamp = microtime(true); + $this->requestTimeout;
        $this->failures = 0;

        return $finalResponse;
    }

    private function createPath($path) {
        if (is_dir($path)) {
            return true;
        }
        $prev_path = substr($path, 0, strrpos($path, '/', -2) + 1 );
        $return = $this->createPath($prev_path);
        return ($return && is_writable($prev_path)) ? mkdir($path) : false;
    }
}