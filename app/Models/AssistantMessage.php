<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    /** @use HasFactory<\Database\Factories\AssistantMessageFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'role', 'message', 'context'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
