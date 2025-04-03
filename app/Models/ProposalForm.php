<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalForm extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'dep_id', 'fields', 'created_date', 'created_by'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'fields' => 'array',
        'default_fields' => 'array',
    ];

    // Disable the automatic timestamps
    public $timestamps = false;

    public function department()
    {
        return $this->belongsTo(Department::class, 'dep_id');
    }

    public function topics()
    {
        return $this->hasMany(Topic::class, 'form_id');
    }
}
