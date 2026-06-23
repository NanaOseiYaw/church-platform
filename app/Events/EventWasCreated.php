<?php

namespace App\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EventWasCreated
{
    use Dispatchable;
    public function __construct(
        public readonly Event $event,
        public readonly User  $actor,
    ) {}
}
