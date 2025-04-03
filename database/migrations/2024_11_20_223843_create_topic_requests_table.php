<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTopicRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('topic_requests');

        Schema::create('topic_requests', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('topic_id')->unsigned();
            $table->unsignedBigInteger('requested_by_id'); // References student or teacher ID
            $table->string('requested_by_type', 30);       // Stores 'student' or 'teacher'
            $table->text('req_note');
            $table->boolean('is_selected')->default(false);
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->foreign('topic_id')
                ->references('id')->on('topics')
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
        Schema::dropIfExists('topic_requests');
    }
}
