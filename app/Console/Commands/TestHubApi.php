<?php

namespace App\Console\Commands;

use App\Services\HubApiService;
use Illuminate\Console\Command;

class TestHubApi extends Command
{
    protected $signature = 'test:hub-api';
    protected $description = 'Test connection to HUB API mockup';

    public function handle(HubApiService $hubApiService)
    {
        $this->info('Testing HUB API connection...');
        
        // Test departments
        $departments = $hubApiService->getDepartments();
        if ($departments) {
            $this->info('Successfully retrieved ' . count($departments) . ' departments');
            $this->table(
                ['ID', 'Name', 'Programs'],
                collect($departments)->map(function ($dept) {
                    return [
                        $dept['id'],
                        $dept['name'],
                        count($dept['programs'] ?? []) . ' programs'
                    ];
                })
            );
        } else {
            $this->error('Failed to retrieve departments');
        }
        
        // Test teachers
        $teachers = $hubApiService->getTeachers();
        if ($teachers) {
            $this->info('Successfully retrieved ' . count($teachers) . ' teachers');
            $this->table(
                ['ID', 'Name', 'Department'],
                collect($teachers)->map(function ($teacher) {
                    return [
                        $teacher['id'],
                        $teacher['first_name'] . ' ' . $teacher['last_name'],
                        $teacher['department_name']
                    ];
                })
            );
        } else {
            $this->error('Failed to retrieve teachers');
        }
        
        // Test students
        $students = $hubApiService->getStudentsInfo();
        if ($students) {
            $this->info('Successfully retrieved ' . count($students) . ' students');
            $this->table(
                ['SISI ID', 'Name', 'Program'],
                collect($students)->map(function ($student) {
                    return [
                        $student['sisi_id'],
                        $student['first_name'] . ' ' . $student['last_name'],
                        $student['program_name']
                    ];
                })
            );
        } else {
            $this->error('Failed to retrieve students');
        }
        
        return 0;
    }
}