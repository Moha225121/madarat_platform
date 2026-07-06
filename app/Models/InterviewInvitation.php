<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewInvitation extends Model
{
    /** @use HasFactory<\Database\Factories\InterviewInvitationFactory> */
    use HasFactory;

    protected $fillable = [
        'application_id',
        'scheduled_at',
        'message',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
