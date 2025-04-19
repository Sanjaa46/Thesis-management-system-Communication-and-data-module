<?php

namespace Tests\Feature;

use App\Services\MockupApiService;
use Tests\TestCase;

class MockupApiTest extends TestCase
{
    protected $apiService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->apiService = new MockupApiService();
        // Use reflection to update the private/protected endpoint property
        $reflectionClass = new \ReflectionClass($this->apiService);
        $endpointProperty = $reflectionClass->getProperty('endpoint');
        $endpointProperty->setAccessible(true);
        $endpointProperty->setValue($this->apiService, 'http://localhost:8080/gateway');
    }
    
    public function testGetDepartments()
    {
        $query = <<<'GRAPHQL'
        query {
          hr_GetDepartments(clientId: "test") {
            id
            name
            programs {
              program_id
              program_name
            }
          }
        }
        GRAPHQL;
        
        $result = $this->apiService->query($query);
        
        $this->assertNotNull($result);
        $this->assertArrayHasKey('hr_GetDepartments', $result);
        $this->assertNotEmpty($result['hr_GetDepartments']);
    }
    
    public function testGetTeachers()
    {
        $query = <<<'GRAPHQL'
        query {
          hr_GetTeachers(clientId: "test", departmentId: "1") {
            id
            firstname
            lastname
            position
          }
        }
        GRAPHQL;
        
        $result = $this->apiService->query($query);
        
        $this->assertNotNull($result);
        $this->assertArrayHasKey('hr_GetTeachers', $result);
    }
    
    public function testGetStudentsEnrolledInThesis()
    {
        $query = <<<'GRAPHQL'
        query {
          sisi_GetStudentsEnrolledInThesis(
            clientId: "test", 
            departmentId: "1", 
            semesterId: "2025-1", 
            courseCode: "THES400"
          ) {
            sisi_id
            first_name
            last_name
            student_email
          }
        }
        GRAPHQL;
        
        $result = $this->apiService->query($query);
        
        $this->assertNotNull($result);
        $this->assertArrayHasKey('sisi_GetStudentsEnrolledInThesis', $result);
    }
}