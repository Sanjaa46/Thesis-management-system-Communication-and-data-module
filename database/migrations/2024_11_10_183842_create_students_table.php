<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Ensure the students table doesn't already exist
        Schema::dropIfExists('students');

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('dep_id', 10);
            $table->string('sisi_id', 12)->unique(); // Make sisi_id unique
            $table->string('firstname', 40);
            $table->string('lastname', 40);
            $table->string('program', 100);
            $table->string('mail', 100);
            $table->string('phone', 12);
            $table->boolean('is_choosed');
            $table->smallInteger('proposed_number');

            $table->foreign('dep_id')
                ->references('id')->on('departments')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Ensure topic_requests table exists before modifying it
        if (Schema::hasTable('topic_requests')) {
            Schema::table('topic_requests', function (Blueprint $table) {
                $table->dropForeign(['student_id']);
            });
        }

        Schema::dropIfExists('students');
    }
}
