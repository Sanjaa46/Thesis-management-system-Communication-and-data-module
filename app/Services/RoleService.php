<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class RoleService
{
    protected $client;
    protected $resourceEndpoint;

    public function __construct()
    {
        $this->client = new Client([
            'verify' => false, // Disable SSL verification for testing purposes
            'timeout' => 30,
        ]);
        
        $this->resourceEndpoint = config('oauth.resource_endpoint', 'https://auth.num.edu.mn/resource/me');
    }

    /**
     * Get user role information from the access token
     *
     * @param string $accessToken The OAuth access token
     * @return array|null The user role information or null on failure
     */
    public function getUserRoleInfo($accessToken)
    {
        try {
            Log::info('Fetching user role information');
            
            $response = $this->client->get($this->resourceEndpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $userData = json_decode($response->getBody(), true);
            
            if (!$userData || !is_array($userData)) {
                Log::error('Invalid user data response', [
                    'userData' => $userData
                ]);
                return null;
            }
            
            // Extract role information
            $roleInfo = [
                'gid' => $this->findValueByType($userData, 'gid'),
                'uid' => $this->findValueByType($userData, 'uid'),
                'username' => $this->findValueByType($userData, 'username'),
                'fname' => $this->findValueByType($userData, 'fname'),
                'lname' => $this->findValueByType($userData, 'lname'),
                'fnamem' => $this->findValueByType($userData, 'fnamem'),
                'lnamem' => $this->findValueByType($userData, 'lnamem'),
                'unitName' => $this->findValueByType($userData, 'un'),
                'pub_hash' => $this->findValueByType($userData, 'pub_hash'),
            ];
            
            // Map GID to role
            $roleName = $this->mapGidToRole($roleInfo['gid']);
            $roleInfo['roleName'] = $roleName;
            
            Log::info('User role information fetched successfully', [
                'roleName' => $roleName,
                'gid' => $roleInfo['gid']
            ]);
            
            return $roleInfo;
        } catch (GuzzleException $e) {
            Log::error('Failed to get user role info: ' . $e->getMessage(), [
                'code' => $e->getCode(),
                'response' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : 'No response',
            ]);
            
            return null;
        }
    }
    
    /**
     * Find a value in the user data array by its type
     *
     * @param array $userData
     * @param string $type
     * @return string|null
     */
    protected function findValueByType($userData, $type)
    {
        foreach ($userData as $item) {
            if (isset($item['Type']) && $item['Type'] === $type && isset($item['Value'])) {
                return $item['Value'];
            }
        }
        
        return null;
    }
    
    /**
     * Map the GID to a role name
     *
     * @param string|null $gid
     * @return string
     */
    protected function mapGidToRole($gid)
    {
        $roles = [
            '78' => 'department',
            '90' => 'supervisor',
            '5' => 'student',
            '70' => 'teacher',
            // Add more role mappings as needed
        ];
        
        return $roles[$gid] ?? 'unknown';
    }
}