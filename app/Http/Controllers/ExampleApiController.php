<?php

namespace App\Http\Controllers;

use App\Services\ApiService;
use Illuminate\Http\Request;

class ExampleApiController extends Controller
{
    protected $apiService;

    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->middleware('oauth'); // Ensure the user is authenticated with OAuth
    }

    /**
     * Fetch and display user data from the API
     */
    public function getUserData()
    {
        // Fetch user data from the API
        $userData = $this->apiService->get('user/profile');
        
        if (!$userData) {
            return redirect()->back()->with('error', 'Failed to fetch user data from the API');
        }
        
        return view('user-data', ['userData' => $userData]);
    }
    
    /**
     * Example of submitting data to the API
     */
    public function submitData(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);
        
        // Submit data to the API
        $response = $this->apiService->post('user/submissions', $validated);
        
        if (!$response) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to submit data to the API');
        }
        
        return redirect()->route('home')
            ->with('success', 'Data submitted successfully');
    }
}