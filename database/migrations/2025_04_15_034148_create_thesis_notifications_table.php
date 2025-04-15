<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('thesis_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // Student ID or Teacher ID
            $table->string('title');
            $table->text('content');
            $table->string('url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->datetime('read_at')->nullable();
            $table->boolean('sent')->default(false);
            $table->datetime('sent_at')->nullable();
            $table->datetime('scheduled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('thesis_notifications');
    }
};