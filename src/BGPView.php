<?php

namespace SyntaxPhoenix\Api\BGPView;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use SyntaxPhoenix\Api\BGPView\Exceptions\RequestFailedException;

class BGPView
{

    private string $baseUrl;
    private Client $client;

    public function __construct(string $baseUrl) {
        $this->baseUrl = $baseUrl;
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => 2.0,
        ]);
    }

    public function getAsnDetails(int $asNumber): array
    {
        return json_decode($this->getDataByApi('asn/' . $asNumber)->getBody(), true)['data'];
    }

    public function getAsnPrefixes(int $asNumber): array
    {
        return json_decode($this->getDataByApi('asn/' . $asNumber . '/prefixes')->getBody(), true)['data'];
    }

    public function getAsnPeers(int $asNumber): array
    {
        return json_decode($this->getDataByApi('asn/' . $asNumber . '/peers')->getBody(), true)['data'];
    }

    public function getAsnUpstreams(int $asNumber): array
    {
        return json_decode($this->getDataByApi('asn/' . $asNumber . '/upstreams')->getBody(), true)['data'];
    }

    public function getAsnDownstreams(int $asNumber): array
    {
        return json_decode($this->getDataByApi('asn/' . $asNumber . '/downstreams')->getBody(), true)['data'];
    }

    public function getPrefix(string $ipAddress, int $cidr): array
    {
        return json_decode($this->getDataByApi('prefix/' . $ipAddress . '/' . $cidr)->getBody(), true)['data'];
    }

    public function getIPDetails(string $ipAddress): array
    {
        return json_decode($this->getDataByApi('prefix/ip/' . $ipAddress)->getBody(), true)['data'];
    }

    public function getIXDetails(int $ixId): array
    {
        return json_decode($this->getDataByApi('ix/' . $ixId)->getBody(), true)['data'];
    }

    private function getDataByApi(string $urlPart): ResponseInterface
    {
        $response = $this->client->request('GET', $urlPart);
        if ($response->getStatusCode() != 200) {
            throw new RequestFailedException();
        }
        return $response;
    }
}
