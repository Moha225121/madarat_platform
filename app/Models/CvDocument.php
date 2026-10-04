<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvDocument extends Model
{
    protected $fillable = ['user_id', 'template', 'resume_data', 'colors'];

    protected function casts(): array
    {
        return ['resume_data' => 'array', 'colors' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
