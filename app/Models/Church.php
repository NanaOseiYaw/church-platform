<?php

namespace App\Models;

use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Church extends Model
{
    use HasUniqueSlug;

    protected $fillable = [
        // Core identity
        'name', 'display_name', 'slug', 'tagline',
        // Extended identity (Phase 2)
        'description', 'mission', 'vision', 'founded_year', 'registration_number',
        // Branding
        'logo', 'primary_color',
        // Contact
        'address', 'phone', 'email',
        // Platform
        'timezone', 'language', 'domain', 'subscription_plan', 'is_active',
        // JSON bags
        'settings', 'socials', 'service_times',
    ];

    protected $casts = [
        'settings'      => 'array',
        'socials'       => 'array',
        'service_times' => 'array',
        'is_active'     => 'boolean',
    ];

    /**
     * Church slugs are globally unique (no tenant scope).
     * The default slugConfig() values are correct here — scope is null.
     */
    protected function slugConfig(): array
    {
        return ['from' => 'name', 'to' => 'slug', 'scope' => null];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function sermons(): HasMany
    {
        return $this->hasMany(Sermon::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
