<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DepartmentDataService;
use App\Services\TeacherDataService;
use App\Services\StudentDataService;
use Illuminate\Support\Facades\Log;

class SyncHubApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hubapi:sync 
                            {--departments : Sync departments}
                            {--teachers : Sync teachers}
                            {--students : Sync students}
                            {--all : Sync all entities}
                            {--department= : Department ID filter}
                            {--semester= : Semester code filter}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize data from HUB API to the local database';

    /**
     * Execute the console command.
     *
     * @param DepartmentDataService $departmentService
     * @param TeacherDataService $teacherService
     * @param StudentDataService $studentService
     * @return int
     */
    public function handle(DepartmentDataService $departmentService, TeacherDataService $teacherService, StudentDataService $studentService)
    {
        // Get options
        $syncDepartments = $this->option('departments') || $this->option('all');
        $syncTeachers = $this->option('teachers') || $this->option('all');
        $syncStudents = $this->option('students') || $this->option('all');
        
        $departmentId = $this->option('department');
        $semester = $this->option('semester');
        
        // If no entities specified, assume all
        if (!$syncDepartments && !$syncTeachers && !$syncStudents) {
            $syncDepartments = $syncTeachers = $syncStudents = true;
        }
        
        // Start with departments (they are referenced by other entities)
        if ($syncDepartments) {
            $this->info('Syncing departments...');
            
            try {
                $result = $departmentService->syncDepartmentsFromHubApi();
                
                $this->table(
                    ['Total', 'Created', 'Updated', 'Failed'], 
                    [[
                        $result['total'], 
                        $result['created'], 
                        $result['updated'], 
                        $result['failed']
                    ]]
                );
                
                if ($result['failed'] > 0) {
                    $this->warn("{$result['failed']} departments failed to sync. Check logs for details.");
                }
            } catch (\Exception $e) {
                $this->error('Failed to sync departments: ' . $e->getMessage());
                Log::error('Department sync command error: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                ]);
            }
        }
        
        // Sync teachers
        if ($syncTeachers) {
            $this->info('Syncing teachers...');
            
            try {
                $result = $teacherService->syncTeachersFromHubApi($departmentId);
                
                $this->table(
                    ['Total', 'Created', 'Updated', 'Failed'], 
                    [[
                        $result['total'], 
                        $result['created'], 
                        $result['updated'], 
                        $result['failed']
                    ]]
                );
                
                if ($result['failed'] > 0) {
                    $this->warn("{$result['failed']} teachers failed to sync. Check logs for details.");
                }
            } catch (\Exception $e) {
                $this->error('Failed to sync teachers: ' . $e->getMessage());
                Log::error('Teacher sync command error: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                ]);
            }
        }
        
        // Sync students
        if ($syncStudents) {
            $this->info('Syncing students...');
            
            try {
                $result = $studentService->syncStudentsFromHubApi($departmentId, $semester);
                
                $this->table(
                    ['Total', 'Created', 'Updated', 'Failed'], 
                    [[
                        $result['total'], 
                        $result['created'], 
                        $result['updated'], 
                        $result['failed']
                    ]]
                );
                
                if ($result['failed'] > 0) {
                    $this->warn("{$result['failed']} students failed to sync. Check logs for details.");
                }
            } catch (\Exception $e) {
                $this->error('Failed to sync students: ' . $e->getMessage());
                Log::error('Student sync command error: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                ]);
            }
        }
        
        $this->info('Synchronization completed!');
        return 0;
    }
}