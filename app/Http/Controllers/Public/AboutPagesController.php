<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Support\AboutPages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The four About sub-pages shared by every Church of Pentecost website:
 * Leadership, History, Beliefs & Tenets, and Core Values.
 *
 * Two of them are denomination-wide and never vary by assembly, so they are
 * served straight from config/cop.php. The other two are local to this assembly
 * and are read from the church's Settings with a clearly-marked template
 * fallback — the same `$settings[...] ?? $this->default…()` pattern
 * AboutController already uses, so an admin can fill them in without a
 * developer and without this file changing.
 */
class AboutPagesController extends Controller
{
    /** GET /about/leadership */
    public function leadership(Request $request): Response
    {
        $this->authorizePreview($request, 'leadership');

        $about = app('church')?->settings['about'] ?? [];

        return Inertia::render('Public/About/Leadership', [
            'leaders'    => $about['leadership'] ?? $this->templateLeaders(),
            'isTemplate' => empty($about['leadership']),
            'intro'      => $about['leadership_intro'] ?? null,
        ]);
    }

    /** GET /about/history */
    public function history(Request $request): Response
    {
        $this->authorizePreview($request, 'history');

        $church = app('church');
        $about  = $church?->settings['about'] ?? [];

        return Inertia::render('Public/About/History', [
            'milestones'  => $about['history'] ?? $this->templateMilestones(),
            'isTemplate'  => empty($about['history']),
            'intro'       => $about['history_intro'] ?? null,
            'localFounded' => $church?->founded_year,
            'global'      => [
                'founded'         => config('cop.founded_year'),
                'founder'         => config('cop.founder'),
                'founderLifespan' => config('cop.founder_lifespan'),
                'countries'       => config('cop.countries'),
                'membership'      => config('cop.global_membership'),
            ],
        ]);
    }

    /** GET /about/beliefs */
    public function beliefs(): Response
    {
        return Inertia::render('Public/About/Beliefs', [
            'tenets' => $this->withExistingImages(config('cop.tenets', [])),
        ]);
    }

    /**
     * Drop the `image` key for any illustration that has not been added yet.
     *
     * Used by both Beliefs and Core Values. Each page falls back to the item's
     * icon when there is no image, so the artwork can be added one file at a
     * time instead of all at once — and a missing or misnamed file degrades to
     * the icon rather than rendering a broken image.
     */
    private function withExistingImages(array $items): array
    {
        return array_map(function (array $item) {
            if (! empty($item['image']) && ! file_exists(public_path(ltrim($item['image'], '/')))) {
                unset($item['image']);
            }

            return $item;
        }, $items);
    }

    /** GET /about/core-values */
    public function coreValues(): Response
    {
        return Inertia::render('Public/About/CoreValues', [
            'values' => $this->withExistingImages(config('cop.core_values', [])),
        ]);
    }

    /**
     * A sub-page still showing template content is not public.
     *
     * It 404s for visitors rather than 403s, so the URL gives away nothing about
     * a page that exists but is not finished. Admins who can edit the church stay
     * able to open it, so they can preview the layout while filling it in.
     */
    private function authorizePreview(Request $request, string $slug): void
    {
        if (AboutPages::isReady($slug)) {
            return;
        }

        abort_unless(
            $request->user()?->can('update', Church::class) ?? false,
            404
        );
    }

    // ── Template fallbacks ────────────────────────────────────────────────────
    // Shown only until an admin fills these in under Settings → About Page.
    // Deliberately generic: real names and real dates are never invented here.

    private function templateLeaders(): array
    {
        return [
            ['name' => 'Name of Presiding Elder', 'role' => 'Presiding Elder',   'bio' => 'A short introduction to the leader of the assembly.'],
            ['name' => 'Name of Resident Pastor', 'role' => 'Resident Pastor',   'bio' => 'A short introduction to the resident pastor.'],
            ['name' => 'Name of Elder',           'role' => 'Elder',             'bio' => 'A short introduction to this member of the leadership team.'],
            ['name' => 'Name of Deacon',          'role' => 'Deacon',            'bio' => 'A short introduction to this member of the leadership team.'],
            ['name' => 'Name of Deaconess',       'role' => 'Deaconess',         'bio' => 'A short introduction to this member of the leadership team.'],
            ['name' => 'Name of Youth Leader',    'role' => 'Youth Ministry Leader', 'bio' => 'A short introduction to this member of the leadership team.'],
        ];
    }

    private function templateMilestones(): array
    {
        return [
            ['year' => '19XX', 'title' => 'The assembly is founded', 'body' => 'Describe how the Amsterdam assembly began — who gathered, where they met, and what prompted it.'],
            ['year' => '20XX', 'title' => 'A permanent place of worship', 'body' => 'Describe the move to a permanent location, or a significant growth milestone.'],
            ['year' => '20XX', 'title' => 'Ministries take shape', 'body' => 'Describe when the ministries and departments of the assembly were established.'],
            ['year' => '20XX', 'title' => 'Today', 'body' => 'Describe where the assembly stands now — membership, ministries and vision for the years ahead.'],
        ];
    }
}
