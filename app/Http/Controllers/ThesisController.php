<?php

namespace App\Http\Controllers;

use App\Services\MockupApiService;
use Illuminate\Http\Request;

class ThesisController extends Controller
{
    protected $apiService;
    
    public function __construct(MockupApiService $apiService)
    {
        $this->apiService = $apiService;
    }
    
    /**
     * Get students enrolled in thesis for a department
     */
    public function getStudentsEnrolledInThesis(Request $request, $departmentId)
    {
        $query = <<<'GRAPHQL'
        query ($departmentId: String!) {
          sisi_GetStudentsEnrolledInThesis(
            clientId: "test", 
            departmentId: $departmentId, 
            semesterId: "2025-1", 
            courseCode: "THES400"
          ) {
            sisi_id
            first_name
            last_name
            student_email
            program_name
            has_selected_research
          }
        }
        GRAPHQL;
        
        $variables = [
            'departmentId' => $departmentId
        ];
        
        $result = $this->apiService->query($query, $variables);
        
        if (!$result) {
            return response()->json(['error' => 'Failed to fetch student data'], 500);
        }
        
        return response()->json($result['sisi_GetStudentsEnrolledInThesis']);
    }
    
    /**
     * Get department information with programs
     */
    public function getDepartments()
    {
        $query = <<<'GRAPHQL'
        query {
          hr_GetDepartments(clientId: "test") {
            id
            name
            programs {
              program_id
              program_index
              program_name
            }
          }
        }
        GRAPHQL;
        
        $result = $this->apiService->query($query);
        
        if (!$result) {
            return response()->json(['error' => 'Failed to fetch department data'], 500);
        }
        
        return response()->json($result['hr_GetDepartments']);
    }
}