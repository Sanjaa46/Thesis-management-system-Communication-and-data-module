<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GraphQLClientService;
use Illuminate\Support\Facades\Log;

class HubProxyController extends Controller
{
    protected $graphqlClient;
    
    public function __construct(GraphQLClientService $graphqlClient)
    {
        $this->graphqlClient = $graphqlClient;
    }
    
    public function proxyRequest(Request $request)
    {
        // Get the token from the request (set by middleware)
        $accessToken = $request->attribute('access_token');
        
        // Validate the request
        $validated = $request->validate([
            'query' => 'required|string',
            'variables' => 'array|nullable',
        ]);
        
        // Get the query and variables from the request
        $query = $validated['query'];
        $variables = $validated['variables'] ?? [];
        
        // Execute the query
        $result = $this->graphqlClient->executeQuery($query, $variables, $accessToken);
        
        // Return the response
        if ($result === null) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to execute GraphQL query'
            ], 500);
        }
        
        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}