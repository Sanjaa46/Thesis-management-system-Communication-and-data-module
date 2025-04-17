<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index()
    {
        // Бүх оюутныг eager load хийж татна
        $students = Student::with([
            'selectedTopicRequest.topic',
            'selectedTopicRequest.approvedBy',
        ])->get();

        // Оюутныг өөр формат руу хөрвүүлж буцаана
        $data = $students->map(function ($student) {
            $req = $student->selectedTopicRequest;
            $topicTitle = '-';

            // topic exists эсэх, fields нь array эсвэл string байж болно
            if ($req && $req->topic) {
                $fields = $req->topic->fields;

                // Хэрвээ string хэлбэртэй JSON байвал array болгож хөрвүүлнэ
                if (is_string($fields)) {
                    $fields = json_decode($fields, true);
                }

                // Array болсон үед "name_mongolian" талбарыг хайна
                if (is_array($fields)) {
                    foreach ($fields as $field) {
                        if (isset($field['field']) && $field['field'] === 'name_mongolian') {
                            $topicTitle = $field['value'] ?? '-';
                            break;
                        }
                    }
                }
            }

            return [
                'id' => $student->id,
                'sisi_id' => $student->sisi_id,
                'firstname' => $student->firstname,
                'lastname' => $student->lastname,
                'program' => $student->program,
                'mail' => $student->mail,
                'phone' => $student->phone,
                'is_choosed' => $req !== null,
                'topic_title' => $topicTitle,
                'teacher_name' => optional($req?->approvedBy)->firstname
                    ? optional($req?->approvedBy)->firstname . ' ' . optional($req?->approvedBy)->lastname
                    : '-',
            ];
        });

        return response()->json($data->values());
    }
}