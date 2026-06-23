<?php

namespace App\Providers;

use App\Search\SearchService;
use App\Search\Providers\AnnouncementSearchProvider;
use App\Search\Providers\AttendanceSearchProvider;
use App\Search\Providers\DepartmentSearchProvider;
use App\Search\Providers\EventSearchProvider;
use App\Search\Providers\MediaSearchProvider;
use App\Search\Providers\MemberSearchProvider;
use App\Search\Providers\TaskSearchProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the SearchService as a singleton and wires up all search providers.
 *
 * To add a new searchable module:
 *   1. Create app/Search/Providers/YourProvider.php
 *   2. Add ->registerProvider(new YourProvider()) below
 *   Done — zero other changes required.
 */
class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SearchService::class, function () {
            return (new SearchService())
                ->registerProvider(new MemberSearchProvider())
                ->registerProvider(new DepartmentSearchProvider())
                ->registerProvider(new AnnouncementSearchProvider())
                ->registerProvider(new EventSearchProvider())
                ->registerProvider(new TaskSearchProvider())
                ->registerProvider(new MediaSearchProvider())
                ->registerProvider(new AttendanceSearchProvider());
        });
    }
}
