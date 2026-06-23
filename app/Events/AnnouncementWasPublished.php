<?php

namespace App\Events;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class AnnouncementWasPublished
{
    use Dispatchable;
    public function __construct(
        public readonly Announcement $announcement,
        public readonly User         $actor,
    ) {}
}
