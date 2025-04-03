<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'fields',
        'program',
        'status',
        'created_by_id',
        'created_by_type',
    ];

    protected $casts = [
        'program' => 'array',
    ];

    public function proposalForm()
    {
        return $this->belongsTo(ProposalForm::class, 'form_id');
    }

    public function topicDetails()
    {
        return $this->hasMany(TopicDetail::class, 'topic_id');
    }

    public function topicRequests()
    {
        return $this->hasMany(TopicRequest::class, 'topic_id');
    }

    public function topicResponses()
    {
        return $this->hasMany(TopicResponse::class, 'topic_id');
    }

    public function createdBy()
    {
        return $this->morphTo();
    }
}
