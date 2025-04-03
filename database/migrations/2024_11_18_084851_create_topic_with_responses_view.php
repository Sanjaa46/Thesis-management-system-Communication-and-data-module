<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateTopicWithResponsesView extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create the view
        DB::statement("
            CREATE VIEW topic_with_responses AS
            SELECT
                topics.*,
                topic_responses.id AS res_id,
                topic_responses.supervisor_id,
                topic_responses.res_date,
                topic_responses.note
            FROM topics
            INNER JOIN topic_responses ON topics.id = topic_responses.topic_id
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop the view when rolling back the migration
        DB::statement("DROP VIEW IF EXISTS topic_with_responses");
    }
}
