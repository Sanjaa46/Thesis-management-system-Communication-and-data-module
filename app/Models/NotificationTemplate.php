<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;
    
    protected $table = 'thesis_notification_templates';
    
    protected $fillable = [
        'name',
        'subject',
        'body',
        'event_type',
        'created_by_id',
        'is_active',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    public function creator()
    {
        return $this->belongsTo(Supervisor::class, 'created_by_id');
    }
}