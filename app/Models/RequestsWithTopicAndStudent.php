<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestsWithTopicAndStudent extends Model
{
    protected $table = 'requests_with_topic_and_student';

    public $timestamps = false;

    protected $primaryKey = null;

    public $incrementing = false;

    protected $fillable = [
        'id', 'form_id', 'fields', 'status', 'created_at', 'updated_at', 'created_by_id', 'created_by_type', 'req_note', 'is_selected', 'selected_at',
        'firstname', 'lastname', 'program', 'mail', 'phone', 'is_choosed'
    ];
}
