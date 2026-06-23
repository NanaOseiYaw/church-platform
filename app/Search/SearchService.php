<?php

namespace App\Search;

use App\Models\User;
use App\Search\Contracts\SearchProvider;

/**
 * Orchestrates global search across all registered providers.
 *
 * The service is provider-agnostic: it calls each registered SearchProvider,
 * collects results, and returns a grouped structure the API controller serialises.
 *
 * ── Future extensibility ─────────────────────────────────────────────────────
 *
 * To add a new searchable module:
 *   1. Create app/Search/Providers/YourProvider.php implementing SearchProvider
 *   2. Register it in SearchServiceProvider::register()
 *   No changes to this class, controllers, or routes.
 *
 * To swap to Meilisearch/Algolia:
 *   Replace each provider's search() implementation.
 *   This class, the controller, and the frontend remain unchanged.
 *
 * To add global result ranking / post-processing:
 *   Add a step between the provider loop and the return value here.
 */
class SearchService
{
    /** @var SearchProvider[] */
    private array $providers = [];

    public function registerProvider(SearchProvider $provider): static
    {
        $this->providers[] = $provider;
        return $this;
    }

    /**
     * Run the query across all available providers.
     *
     * Returns only non-empty groups. Empty groups are omitted so the
     * frontend never renders a section header with nothing under it.
     *
     * @return array<int, array{type: string, label: string, results: array<int, array>}>
     */
    public function search(
        string $query,
        User   $user,
        int    $churchId,
        int    $limitPerGroup = 5,
    ): array {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $groups = [];

        foreach ($this->providers as $provider) {
            if (! $provider->isAvailable($user)) {
                continue;
            }

            try {
                $results = $provider->search($query, $user, $churchId, $limitPerGroup);
            } catch (\Throwable) {
                // One provider failing should never break the entire search response.
                $results = [];
            }

            if (! empty($results)) {
                $groups[] = [
                    'type'    => $provider->getType(),
                    'label'   => $provider->getLabel(),
                    'results' => array_map(
                        fn (SearchResult $r) => $r->toArray(),
                        $results
                    ),
                ];
            }
        }

        return $groups;
    }

    /** @return SearchProvider[] */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
