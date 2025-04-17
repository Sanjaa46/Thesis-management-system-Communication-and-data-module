<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('auto_notifications_enabled')->default(true);
            $table->boolean('topic_approval_enabled')->default(true);
            $table->boolean('deadline_reminders_enabled')->default(true);
            $table->json('deadline_reminder_days')->nullable();
            $table->boolean('evaluation_notifications_enabled')->default(true);
            $table->unsignedBigInteger('topic_approval_template_id')->nullable();
            $table->unsignedBigInteger('deadline_reminder_template_id')->nullable();
            $table->unsignedBigInteger('evaluation_template_id')->nullable();
            $table->date('thesis_proposal_deadline')->nullable();
            $table->date('first_draft_deadline')->nullable();
            $table->date('final_submission_deadline')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('topic_approval_template_id')
                ->references('id')
                ->on('thesis_notification_templates')
                ->nullOnDelete();
                
            $table->foreign('deadline_reminder_template_id')
                ->references('id')
                ->on('thesis_notification_templates')
                ->nullOnDelete();
                
            $table->foreign('evaluation_template_id')
                ->references('id')
                ->on('thesis_notification_templates')
                ->nullOnDelete();
        });
        
        // Insert default settings
        DB::table('notification_settings')->insert([
            'auto_notifications_enabled' => true,
            'topic_approval_enabled' => true,
            'deadline_reminders_enabled' => true,
            'deadline_reminder_days' => json_encode([14, 7, 3, 1]),
            'evaluation_notifications_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('notification_settings');
    }
}