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

    // fields болон program хоёуланг нь array хэлбэрт хөрвүүлнэ
    protected $casts = [
        'fields' => 'array',
        'program' => 'array',
    ];

    // Сэдвийн харьяалагдах маягт
    public function proposalForm()
    {
        return $this->belongsTo(ProposalForm::class, 'form_id');
    }

    // Нэмэлт мэдээлэл
    public function topicDetails()
    {
        return $this->hasMany(TopicDetail::class, 'topic_id');
    }

    // Сэдэвт илгээсэн хүсэлтүүд
    public function topicRequests()
    {
        return $this->hasMany(TopicRequest::class, 'topic_id');
    }

    // Хариу (approve/reject)
    public function topicResponses()
    {
        return $this->hasMany(TopicResponse::class, 'topic_id');
    }

    // Сэдвийг үүсгэсэн багш эсвэл оюутан
    public function createdBy()
    {
        return $this->morphTo();
    }
}