<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTopicsTable extends Migration
{
    public function up()
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->string('form_id', 10);
            $table->foreign('form_id')->references('id')->on('proposal_forms')->onDelete('cascade');
            // $table->string('name_mongolian')->nullable();
            // $table->string('name_english')->nullable();
            // $table->text('description')->nullable();
            $table->json('fields')->nullable();
            $table->json('program')->nullable();
            $table->string('status');
            $table->timestamps();

            // Polymorphic columns for the creator (either teacher or student)
            $table->string('created_by_id', 10);
            $table->string('created_by_type');

            // Optional foreign keys for clarity, but not enforced due to polymorphic structure
            // $table->foreign('created_by_id')->references('id')->on('students')->onDelete('cascade');
            // $table->foreign('created_by_id')->references('id')->on('teachers')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('topics');
    }
}

