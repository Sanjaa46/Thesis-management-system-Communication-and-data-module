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
        Schema::create('thesis_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // Student ID or Teacher ID
            $table->text('endpoint');
            $table->string('p256dh');
            $table->string('auth');
            $table->datetime('expires_at')->nullable();
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
        Schema::dropIfExists('thesis_push_subscriptions');
    }
};