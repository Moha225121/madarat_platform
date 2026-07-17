<?php

namespace App\Policies;

use App\Models\TrainingCourse;
use App\Models\User;

class TrainingCoursePolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function view(User $user, TrainingCourse $course): bool
    {
        return $course->status === 'published' || $this->owns($user, $course);
    }

    public function create(User $user): bool
    {
        return $user->role === 'training_provider';
    }

    public function update(User $user, TrainingCourse $course): bool
    {
        return $this->owns($user, $course) && in_array($course->status, ['draft', 'rejected'], true);
    }

    public function submit(User $user, TrainingCourse $course): bool
    {
        return $this->update($user, $course);
    }

    public function close(User $user, TrainingCourse $course): bool
    {
        return $this->owns($user, $course) && $course->status === 'published';
    }

    public function archive(User $user, TrainingCourse $course): bool
    {
        return $this->owns($user, $course) && in_array($course->status, ['draft', 'rejected', 'closed'], true);
    }

    private function owns(User $user, TrainingCourse $course): bool
    {
        return $user->role === 'training_provider' && $course->provider?->user_id === $user->id;
    }
}
