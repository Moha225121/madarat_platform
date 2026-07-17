<?php

namespace App\Policies;

use App\Models\TrainingProviderProfile;
use App\Models\User;

class TrainingProviderProfilePolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function view(User $user, TrainingProviderProfile $profile): bool
    {
        return $profile->verification_status === 'verified' || $profile->user_id === $user->id;
    }

    public function update(User $user, TrainingProviderProfile $profile): bool
    {
        return $user->role === 'training_provider' && $profile->user_id === $user->id;
    }
}
