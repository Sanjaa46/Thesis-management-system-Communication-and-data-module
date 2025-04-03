<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supervisor extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'dep_id', 'firstname', 'lastname', 'mail', 'phone'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function department()
    {
        return $this->belongsTo(Department::class, 'dep_id');
    }

    public function topicResponses()
    {
        return $this->hasMany(TopicResponse::class, 'supervisor_id');
    }
}
