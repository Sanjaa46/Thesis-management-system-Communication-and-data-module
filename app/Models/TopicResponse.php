<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopicResponse extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'topic_id', 'supervisor_id', 'res', 'note', 'res_date'];
    public $incrementing = false;
    protected $keyType = 'string';

    // Disable the automatic timestamps
    public $timestamps = false;

    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(Supervisor::class, 'supervisor_id');
    }
}
