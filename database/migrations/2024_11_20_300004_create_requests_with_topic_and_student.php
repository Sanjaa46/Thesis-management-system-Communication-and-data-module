<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP VIEW IF EXISTS requests_with_topic_and_student");

        DB::statement("
            CREATE VIEW requests_with_topic_and_student AS
            SELECT
                topics.id as topic_id,
                topics.fields,
                topics.created_by_id as created_by_id,
                topics.created_by_type as created_by_type,
                topics.status as status,
                topic_requests.id as req_id,
                topic_requests.req_note,
                topic_requests.is_selected,
                topic_requests.selected_at,
                CASE
                    WHEN topic_requests.requested_by_type = 'student' THEN CAST(students.id AS BIGINT)
                    ELSE CAST(teachers.id AS BIGINT)
                END as requester_id,
                CASE
                    WHEN topic_requests.requested_by_type = 'student' THEN students.sisi_id
                    ELSE NULL
                END as sisi_id,
                CASE
                    WHEN topic_requests.requested_by_type = 'student' THEN students.firstname
                    ELSE teachers.firstname
                END as firstname,
                CASE
                    WHEN topic_requests.requested_by_type = 'student' THEN students.lastname
                    ELSE teachers.lastname
                END as lastname,
                CASE
                    WHEN topic_requests.requested_by_type = 'student' THEN students.program
                    ELSE NULL
                END as program,
                CASE
                    WHEN topic_requests.requested_by_type = 'student' THEN students.mail
                    ELSE teachers.mail
                END as mail,
                CASE
                    WHEN topic_requests.requested_by_type = 'student' THEN students.phone
                    ELSE NULL
                END as phone,
                CASE
                    WHEN topic_requests.requested_by_type = 'student' THEN students.is_choosed
                    ELSE NULL
                END as is_choosed
            FROM topics
            INNER JOIN topic_requests ON topics.id = topic_requests.topic_id
            LEFT JOIN students ON topic_requests.requested_by_id = CAST(students.id AS BIGINT)
                AND topic_requests.requested_by_type = 'student'
            LEFT JOIN teachers ON topic_requests.requested_by_id = CAST(teachers.id AS BIGINT)
                AND topic_requests.requested_by_type = 'teacher'
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS requests_with_topic_and_student");
    }
};



