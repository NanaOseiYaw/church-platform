# Configurable Footer Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow church admins to configure the two footer navigation groups (Explore, Connect) via Settings → Website instead of being stuck with hardcoded links.

**Architecture:** `footer_nav` is stored as `{ explore_links: [{label, href}], connect_links: [{label, href}] }` inside the existing `church.settings['website']` JSON bag. It is surfaced as `church.footerNav` in the global Inertia shared prop (HandleInertiaRequests). `AppFooter.vue` reads that prop with hardcoded defaults as fallback. The Website settings page gains an editor UI matching the existing stats/testimonials pattern.

**Tech Stack:** Laravel 11 (PHP 8.2), Vue 3 + Inertia.js, TypeScript, Tailwind CSS, Lucide icons. Tests use Pest-compatible PHPUnit (see existing tests for style).

---

## File Map

| File | Change |
|------|--------|
| `resources/js/types/index.ts` | Add `FooterNavLink` + `footerNav` field to `ChurchBranding` |
| `app/Http/Middleware/HandleInertiaRequests.php` | Add `footerNav` to shared `church` prop |
| `resources/js/Components/Navigation/AppFooter.vue` | Replace hardcoded `links` with computed from `church.footerNav` |
| `app/Http/Controllers/Dashboard/ChurchSettingsController.php` | Update `website()` + `updateWebsite()` |
| `resources/js/Pages/Dashboard/Settings/Website.vue` | Add footer nav editor section |
| `tests/Feature/ChurchSettingsContentTest.php` | Add 4 tests for footer_nav |

---

## Task 1: TypeScript type — FooterNavLink + ChurchBranding.footerNav

**Files:**
- Modify: `resources/js/types/index.ts`

- [ ] **Step 1: Add `FooterNavLink` interface and `footerNav` field**

  Open `resources/js/types/index.ts`. After the closing brace of `ChurchSeo` (line 8) and before `ChurchBranding` (line 10), insert:

  ```typescript
  export interface FooterNavLink {
      label: string
      href:  string
  }

  export interface FooterNav {
      explore_links: FooterNavLink[]
      connect_links: FooterNavLink[]
  }
  ```

  Then in `ChurchBranding`, add one field after `seo: ChurchSeo`:

  ```typescript
  footerNav: FooterNav
  ```

  The full updated `ChurchBranding` interface becomes:

  ```typescript
  export interface ChurchBranding {
      name: string
      tagline: string
      description?: string | null
      logo: string | null
      favicon?: string | null
      primaryColor: string
      secondaryColor?: string | null
      address: string
      phone: string
      email: string
      socials: {
          facebook?:  string | null
          instagram?: string | null
          youtube?:   string | null
          twitter?:   string | null
          tiktok?:    string | null
          linkedin?:  string | null
          spotify?:   string | null
      }
      seo: ChurchSeo
      footerNav: FooterNav
  }
  ```

- [ ] **Step 2: Verify type-check passes (no new errors)**

  Run: `cd "C:\Users\osein\OneDrive\Desktop\Church webstie" && npm run type-check 2>&1 | tail -20`

  The 35 pre-existing errors in VueUse/socket.io/paginator/realtime are acceptable. The edited file (`types/index.ts`) must contribute zero errors.

---

## Task 2: Share footerNav in HandleInertiaRequests

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

- [ ] **Step 1: Add `footerNav` to the church prop (both church-found and fallback branches)**

  The defaults used when `footer_nav` is absent from settings:

  ```php
  private function defaultFooterNav(): array
  {
      return [
          'explore_links' => [
              ['label' => 'About Us',   'href' => '/about'],
              ['label' => 'Ministries', 'href' => '/ministries'],
              ['label' => 'Events',     'href' => '/events'],
              ['label' => 'Sermons',    'href' => '/sermons'],
          ],
          'connect_links' => [
              ['label' => 'Announcements', 'href' => '/announcements'],
              ['label' => 'Contact Us',    'href' => '/contact'],
              ['label' => 'Watch Live',    'href' => '/live'],
              ['label' => 'Give Online',   'href' => '/give'],
          ],
      ];
  }
  ```

  Add this private method at the bottom of the class (before the closing `}`).

  Then in the `share()` method, update the `church` prop array in the `$church ? [...]` branch — add one key after `'seo'`:

  ```php
  'footerNav' => array_merge(
      $this->defaultFooterNav(),
      $church->settings['website']['footer_nav'] ?? []
  ),
  ```

  And in the fallback `[...]` branch (when `$church` is null), add:

  ```php
  'footerNav' => $this->defaultFooterNav(),
  ```

- [ ] **Step 2: Run type-check — no new errors**

  Run: `npm run type-check 2>&1 | Select-String -Pattern "error TS" | Measure-Object -Line`

  Note the count. It should be identical to the pre-existing 35.

---

## Task 3: AppFooter.vue — consume dynamic footerNav

**Files:**
- Modify: `resources/js/Components/Navigation/AppFooter.vue`

- [ ] **Step 1: Replace the hardcoded `links` const with a computed from `useChurch()`**

  Replace the entire `<script setup lang="ts">` block with:

  ```typescript
  <script setup lang="ts">
  import { computed } from 'vue'
  import { Link } from '@inertiajs/vue3'
  import { useChurch } from '@/composables/useChurch'
  import { useTenantStore } from '@/stores/useTenantStore'
  import { MapPin, Phone, Mail, Facebook, Instagram, Youtube, Twitter, Linkedin } from 'lucide-vue-next'

  const { church } = useChurch()
  const tenant = useTenantStore()

  const links = computed(() => ({
      'Explore': church.value.footerNav.explore_links,
      'Connect': church.value.footerNav.connect_links,
  }))
  </script>
  ```

  The `<template>` is unchanged — `v-for="(items, group) in links"` already works for both a plain object and a computed object.

- [ ] **Step 2: Run type-check — no new errors in this file**

  Run: `npm run type-check 2>&1 | Select-String "AppFooter"`

  Expected: no output (zero errors referencing AppFooter.vue).

---

## Task 4: Backend — save & load footer_nav

**Files:**
- Modify: `app/Http/Controllers/Dashboard/ChurchSettingsController.php`

- [ ] **Step 1: Update `website()` to include `footer_nav` in the Inertia prop**

  Find the `website()` method. Change the `'settings'` key from:

  ```php
  'settings' => array_merge(['privacy_mode' => false], $this->getSettings('website')),
  ```

  to:

  ```php
  'settings' => array_merge(
      [
          'privacy_mode'  => false,
          'footer_nav'    => [
              'explore_links' => [
                  ['label' => 'About Us',   'href' => '/about'],
                  ['label' => 'Ministries', 'href' => '/ministries'],
                  ['label' => 'Events',     'href' => '/events'],
                  ['label' => 'Sermons',    'href' => '/sermons'],
              ],
              'connect_links' => [
                  ['label' => 'Announcements', 'href' => '/announcements'],
                  ['label' => 'Contact Us',    'href' => '/contact'],
                  ['label' => 'Watch Live',    'href' => '/live'],
                  ['label' => 'Give Online',   'href' => '/give'],
              ],
          ],
      ],
      $this->getSettings('website')
  ),
  ```

  `array_merge` means any saved `footer_nav` in the database overwrites the default.

- [ ] **Step 2: Update `updateWebsite()` to validate and save `footer_nav`**

  In `updateWebsite()`, add validation rules after `'privacy_mode' => ['boolean'],`:

  ```php
  'footer_nav'                         => ['nullable', 'array'],
  'footer_nav.explore_links'           => ['nullable', 'array', 'max:12'],
  'footer_nav.explore_links.*.label'   => ['required', 'string', 'max:60'],
  'footer_nav.explore_links.*.href'    => ['required', 'string', 'max:255'],
  'footer_nav.connect_links'           => ['nullable', 'array', 'max:12'],
  'footer_nav.connect_links.*.label'   => ['required', 'string', 'max:60'],
  'footer_nav.connect_links.*.href'    => ['required', 'string', 'max:255'],
  ```

  Then update the `$this->saveSettings('website', ...)` call to include `footer_nav`:

  ```php
  $this->saveSettings('website', [
      'privacy_mode' => $validated['privacy_mode'] ?? false,
      'footer_nav'   => [
          'explore_links' => $validated['footer_nav']['explore_links'] ?? [],
          'connect_links' => $validated['footer_nav']['connect_links'] ?? [],
      ],
  ]);
  ```

- [ ] **Step 3: Confirm PHP syntax is valid**

  Run: `php artisan route:list --path=dashboard/settings/website`

  Expected: shows GET and PUT routes for `dashboard/settings/website` — confirms the controller loaded without parse errors.

---

## Task 5: Website.vue — footer nav editor UI

**Files:**
- Modify: `resources/js/Pages/Dashboard/Settings/Website.vue`

- [ ] **Step 1: Add interfaces and expand props/form**

  Replace the entire `<script setup lang="ts">` block:

  ```typescript
  <script setup lang="ts">
  import { useForm } from '@inertiajs/vue3'
  import SettingsLayout from '@/Layouts/SettingsLayout.vue'
  import AppInput from '@/Components/UI/AppInput.vue'
  import AppSelect from '@/Components/UI/AppSelect.vue'
  import AppButton from '@/Components/UI/AppButton.vue'
  import { Plus, Trash2, GripVertical, Link2 } from 'lucide-vue-next'

  interface FooterNavLink {
      label: string
      href:  string
  }

  interface FooterNav {
      explore_links: FooterNavLink[]
      connect_links: FooterNavLink[]
  }

  interface WebsiteConfig {
      domain:   string | null
      timezone: string | null
      language: string | null
  }

  interface Settings {
      privacy_mode: boolean
      footer_nav:   FooterNav
  }

  const props = defineProps<{ websiteConfig: WebsiteConfig; settings: Settings }>()

  const defaultLinks: FooterNav = {
      explore_links: [
          { label: 'About Us',   href: '/about' },
          { label: 'Ministries', href: '/ministries' },
          { label: 'Events',     href: '/events' },
          { label: 'Sermons',    href: '/sermons' },
      ],
      connect_links: [
          { label: 'Announcements', href: '/announcements' },
          { label: 'Contact Us',    href: '/contact' },
          { label: 'Watch Live',    href: '/live' },
          { label: 'Give Online',   href: '/give' },
      ],
  }

  const form = useForm({
      domain:       props.websiteConfig.domain   ?? '',
      timezone:     props.websiteConfig.timezone ?? 'UTC',
      language:     props.websiteConfig.language ?? 'en',
      privacy_mode: props.settings.privacy_mode  ?? false,
      footer_nav: {
          explore_links: (props.settings.footer_nav?.explore_links ?? defaultLinks.explore_links).map(l => ({ ...l })) as FooterNavLink[],
          connect_links: (props.settings.footer_nav?.connect_links ?? defaultLinks.connect_links).map(l => ({ ...l })) as FooterNavLink[],
      },
  })

  function addLink(group: 'explore_links' | 'connect_links') {
      form.footer_nav[group].push({ label: '', href: '' })
  }

  function removeLink(group: 'explore_links' | 'connect_links', i: number) {
      form.footer_nav[group].splice(i, 1)
  }

  function submit() {
      form.put('/dashboard/settings/website')
  }

  const timezones = [
      { value: 'UTC',                 label: 'UTC' },
      { value: 'Africa/Accra',        label: 'Africa / Accra (GMT+0)' },
      { value: 'Africa/Lagos',        label: 'Africa / Lagos (GMT+1)' },
      { value: 'Africa/Nairobi',      label: 'Africa / Nairobi (GMT+3)' },
      { value: 'Europe/London',       label: 'Europe / London' },
      { value: 'Europe/Amsterdam',    label: 'Europe / Amsterdam' },
      { value: 'America/New_York',    label: 'America / New York' },
      { value: 'America/Los_Angeles', label: 'America / Los Angeles' },
      { value: 'Australia/Sydney',    label: 'Australia / Sydney' },
  ]
  const languages = [
      { value: 'en', label: 'English' },
      { value: 'fr', label: 'French' },
      { value: 'de', label: 'German' },
      { value: 'es', label: 'Spanish' },
      { value: 'pt', label: 'Portuguese' },
      { value: 'nl', label: 'Dutch' },
  ]
  </script>
  ```

- [ ] **Step 2: Add the footer nav editor section to the template**

  In the `<template>`, locate the Privacy card (ends at `</div>` before the save row). Insert the footer nav card **between** the Privacy card and the final save row:

  ```html
  <!-- Footer Navigation -->
  <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
      <div class="px-5 py-4 flex items-center gap-2">
          <Link2 class="w-4 h-4 text-neutral-400 shrink-0" />
          <div>
              <h3 class="text-sm font-semibold text-neutral-900">Footer navigation</h3>
              <p class="text-xs text-neutral-500 mt-0.5">
                  Links shown in the two footer columns. Changes appear on the public site immediately after saving.
              </p>
          </div>
      </div>

      <!-- Explore group -->
      <div class="divide-y divide-neutral-100">
          <div class="px-5 py-3 flex items-center justify-between bg-neutral-50">
              <span class="text-xs font-semibold uppercase tracking-widest text-neutral-500">Explore</span>
              <AppButton
                  type="button" variant="outline" size="sm"
                  @click="addLink('explore_links')"
                  :disabled="form.footer_nav.explore_links.length >= 12"
              >
                  <Plus class="w-3.5 h-3.5" />
                  Add link
              </AppButton>
          </div>

          <div v-if="form.footer_nav.explore_links.length === 0" class="px-5 py-6 text-center">
              <p class="text-sm text-neutral-400">No links — add one below.</p>
          </div>

          <div
              v-for="(link, i) in form.footer_nav.explore_links"
              :key="i"
              class="p-5"
          >
              <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center gap-2 text-neutral-400">
                      <GripVertical class="w-4 h-4" />
                      <span class="text-xs font-medium text-neutral-500">Link {{ i + 1 }}</span>
                  </div>
                  <button
                      type="button"
                      @click="removeLink('explore_links', i)"
                      class="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                  >
                      <Trash2 class="w-4 h-4" />
                  </button>
              </div>
              <div class="grid grid-cols-2 gap-4">
                  <AppInput
                      :id="`explore-label-${i}`"
                      v-model="form.footer_nav.explore_links[i].label"
                      label="Label"
                      placeholder="About Us"
                      :error="(form.errors as any)[`footer_nav.explore_links.${i}.label`]"
                  />
                  <AppInput
                      :id="`explore-href-${i}`"
                      v-model="form.footer_nav.explore_links[i].href"
                      label="URL"
                      placeholder="/about"
                      :error="(form.errors as any)[`footer_nav.explore_links.${i}.href`]"
                  />
              </div>
          </div>
      </div>

      <!-- Connect group -->
      <div class="divide-y divide-neutral-100">
          <div class="px-5 py-3 flex items-center justify-between bg-neutral-50">
              <span class="text-xs font-semibold uppercase tracking-widest text-neutral-500">Connect</span>
              <AppButton
                  type="button" variant="outline" size="sm"
                  @click="addLink('connect_links')"
                  :disabled="form.footer_nav.connect_links.length >= 12"
              >
                  <Plus class="w-3.5 h-3.5" />
                  Add link
              </AppButton>
          </div>

          <div v-if="form.footer_nav.connect_links.length === 0" class="px-5 py-6 text-center">
              <p class="text-sm text-neutral-400">No links — add one below.</p>
          </div>

          <div
              v-for="(link, i) in form.footer_nav.connect_links"
              :key="i"
              class="p-5"
          >
              <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center gap-2 text-neutral-400">
                      <GripVertical class="w-4 h-4" />
                      <span class="text-xs font-medium text-neutral-500">Link {{ i + 1 }}</span>
                  </div>
                  <button
                      type="button"
                      @click="removeLink('connect_links', i)"
                      class="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                  >
                      <Trash2 class="w-4 h-4" />
                  </button>
              </div>
              <div class="grid grid-cols-2 gap-4">
                  <AppInput
                      :id="`connect-label-${i}`"
                      v-model="form.footer_nav.connect_links[i].label"
                      label="Label"
                      placeholder="Contact Us"
                      :error="(form.errors as any)[`footer_nav.connect_links.${i}.label`]"
                  />
                  <AppInput
                      :id="`connect-href-${i}`"
                      v-model="form.footer_nav.connect_links[i].href"
                      label="URL"
                      placeholder="/contact"
                      :error="(form.errors as any)[`footer_nav.connect_links.${i}.href`]"
                  />
              </div>
          </div>
      </div>
  </div>
  ```

- [ ] **Step 3: Run type-check — no new errors in Website.vue**

  Run: `npm run type-check 2>&1 | Select-String "Website.vue"`

  Expected: no output.

---

## Task 6: Tests

**Files:**
- Modify: `tests/Feature/ChurchSettingsContentTest.php`

- [ ] **Step 1: Append 4 new test methods**

  Add these methods at the bottom of the class body (before the final `}`):

  ```php
  // ── Footer nav ───────────────────────────────────────────────────────────────

  public function test_footer_nav_is_saved_to_website_settings(): void
  {
      [$church] = $this->makeChurchAdmin();

      $this->put('/dashboard/settings/website', [
          'domain'       => null,
          'timezone'     => 'UTC',
          'language'     => 'en',
          'privacy_mode' => false,
          'footer_nav'   => [
              'explore_links' => [
                  ['label' => 'About',    'href' => '/about'],
                  ['label' => 'Sermons',  'href' => '/sermons'],
              ],
              'connect_links' => [
                  ['label' => 'Give',     'href' => '/give'],
              ],
          ],
      ])->assertRedirect();

      $church->refresh();
      $nav = $church->settings['website']['footer_nav'];

      $this->assertCount(2, $nav['explore_links']);
      $this->assertSame('About',   $nav['explore_links'][0]['label']);
      $this->assertSame('/about',  $nav['explore_links'][0]['href']);
      $this->assertSame('Sermons', $nav['explore_links'][1]['label']);
      $this->assertCount(1, $nav['connect_links']);
      $this->assertSame('Give',    $nav['connect_links'][0]['label']);
      $this->assertSame('/give',   $nav['connect_links'][0]['href']);
  }

  public function test_footer_nav_explore_link_label_is_required(): void
  {
      $this->makeChurchAdmin();

      $this->put('/dashboard/settings/website', [
          'domain'       => null,
          'timezone'     => 'UTC',
          'language'     => 'en',
          'privacy_mode' => false,
          'footer_nav'   => [
              'explore_links' => [
                  ['label' => '', 'href' => '/about'],
              ],
              'connect_links' => [],
          ],
      ])->assertSessionHasErrors(['footer_nav.explore_links.0.label']);
  }

  public function test_footer_nav_href_is_required(): void
  {
      $this->makeChurchAdmin();

      $this->put('/dashboard/settings/website', [
          'domain'       => null,
          'timezone'     => 'UTC',
          'language'     => 'en',
          'privacy_mode' => false,
          'footer_nav'   => [
              'explore_links' => [],
              'connect_links' => [
                  ['label' => 'Contact', 'href' => ''],
              ],
          ],
      ])->assertSessionHasErrors(['footer_nav.connect_links.0.href']);
  }

  public function test_website_get_returns_footer_nav_with_defaults(): void
  {
      $this->makeChurchAdmin();

      $response = $this->get('/dashboard/settings/website');
      $response->assertOk();

      $props = $response->json('props.settings');
      $this->assertArrayHasKey('footer_nav', $props);
      $this->assertArrayHasKey('explore_links', $props['footer_nav']);
      $this->assertArrayHasKey('connect_links', $props['footer_nav']);
      $this->assertNotEmpty($props['footer_nav']['explore_links']);
      $this->assertNotEmpty($props['footer_nav']['connect_links']);
  }
  ```

- [ ] **Step 2: Run the test suite**

  Run: `php artisan test --filter=ChurchSettingsContentTest`

  Expected: all tests green (including the 4 new ones). Any failure indicates a mismatch between test expectations and implementation — fix before moving on.

---

## Task 7: Final verification

- [ ] **Step 1: Full type-check passes with no new errors**

  Run: `npm run type-check 2>&1 | tail -5`

  The final line should be the same error count as before (35 pre-existing).

- [ ] **Step 2: Full test suite passes**

  Run: `php artisan test`

  Expected: no regressions.

---

## Self-Review

**Spec coverage:**
- ✅ `footer_nav` in `settings.website` namespace — Task 4
- ✅ Shared through `HandleInertiaRequests` as `church.footerNav` — Task 2
- ✅ `AppFooter.vue` uses dynamic links with hardcoded defaults as fallback — Task 3
- ✅ Editor UI in `Website.vue` with add/remove/edit rows for each group — Task 5
- ✅ TypeScript types updated — Task 1
- ✅ Tests covering save, validation, and GET defaults — Task 6

**Type consistency check:**
- `FooterNavLink` → `{ label: string, href: string }` — used identically in `types/index.ts`, `Website.vue`, and the PHP controller (array keys `label`/`href`)
- `FooterNav` → `{ explore_links: FooterNavLink[], connect_links: FooterNavLink[] }` — same shape in all three layers
- `church.footerNav` (camelCase) in TS matches the PHP key `'footerNav'` in `HandleInertiaRequests`
- `footer_nav` (snake_case) is the form field name in `Website.vue` and the validation key in the controller
- `form.footer_nav[group]` uses `'explore_links' | 'connect_links'` consistently

**Placeholder scan:** No TBDs, no "implement later", all code blocks complete.
