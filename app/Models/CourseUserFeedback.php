<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseUserFeedback extends Model
{
    protected $table = 'course_user_feedback';

    protected $fillable = ['user_id', 'course_id', 'saved', 'interested', 'completed', 'already_knows', 'dismissed_at'];

    protected function casts(): array
    {
        return ['saved' => 'boolean', 'interested' => 'boolean', 'completed' => 'boolean', 'already_knows' => 'boolean', 'dismissed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }
}
