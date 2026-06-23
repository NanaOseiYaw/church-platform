<?php

namespace App\Search\Contracts;

use App\Models\User;
use App\Search\SearchResult;

/**
 * Contract for a single searchable resource type.
 *
 * Every module that wants to appear in global search implements this interface.
 * The SearchService discovers providers at boot time via the SearchServiceProvider,
 * which means adding a new module to search is a one-file change.
 *
 * Future swap to Meilisearch/Algolia: replace the body of `search()` in each
 * provider while keeping the same interface — zero changes to the service layer.
 */
interface SearchProvider
{
    /**
     * Execute the search for this resource type.
     *
     * Implementations MUST:
     *   - Scope results to $churchId
     *   - Respect $user's permissions (RBAC + visibility)
     *   - Limit results to $limit rows
     *   - Return SearchResult[] (never throw; return [] on failure)
     *
     * @return SearchResult[]
     */
    public function search(string $query, User $user, int $churchId, int $limit): array;

    /**
     * Machine-readable type key.
     * Used as the group key in API responses and for frontend icon mapping.
     * Example: 'member', 'event', 'task'
     */
    public function getType(): string;

    /**
     * Human-readable group label shown in the search modal.
     * Example: 'Members', 'Upcoming Events'
     */
    public function getLabel(): string;

    /**
     * Whether this provider should run for the given user.
     * Use this for permission gates, feature flags, etc.
     */
    public function isAvailable(User $user): bool;
}
