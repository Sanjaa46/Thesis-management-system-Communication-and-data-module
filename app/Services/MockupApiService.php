<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class MockupApiService
{
    protected $client;
    protected $endpoint;
    
    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false, // For local development only
        ]);
        
        $this->endpoint = 'http://localhost:8080/gateway';
    }
    
    /**
     * Send a GraphQL query to the mockup server
     *
     * @param string $query The GraphQL query
     * @param array $variables Variables for the query
     * @return array|null The response data or null on failure
     */
    public function query($query, $variables = [])
    {
        try {
            $response = $this->client->post($this->endpoint, [
                'json' => [
                    'query' => $query,
                    'variables' => $variables
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['errors'])) {
                Log::error('GraphQL errors:', $data['errors']);
                return null;
            }
            
            return $data['data'] ?? null;
        } catch (\Exception $e) {
            Log::error('GraphQL request failed: ' . $e->getMessage());
            return null;
        }
    }
}