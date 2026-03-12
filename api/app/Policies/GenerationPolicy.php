<?php

namespace App\Policies;

use App\Models\Generation;
use App\Models\User;

class GenerationPolicy
{
    public function view(User $user, Generation $generation): bool
    {
        return $user->id === $generation->user_id;
    }
}
