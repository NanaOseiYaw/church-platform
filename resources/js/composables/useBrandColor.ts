import { watchEffect } from 'vue'
import { useTenantStore } from '@/stores/useTenantStore'

/**
 * Watches the church's saved primary_color and injects it—and a full
 * derived colour scale—as CSS custom properties on <html>.
 *
 * Because Tailwind 4 utility classes compile to `background-color: var(--color-brand-500)`
 * etc., overriding those variables at runtime makes every `bg-brand-*`,
 * `text-brand-*`, `ring-brand-*` class reflect the church's chosen colour
 * immediately—no build step or page reload required.
 *
 * Call once per layout root (DashboardLayout, PublicLayout).
 */
export function useBrandColor(): void {
    const tenant = useTenantStore()

    watchEffect(() => {
        applyBrandColor(tenant.brandColor)
        if (tenant.secondaryColor) {
            applySecondaryColor(tenant.secondaryColor)
        }
    })
}

// ── Colour math ───────────────────────────────────────────────────────────────

/** Parse a #rrggbb hex string → [h°, s%, l%] (HSL). */
function hexToHsl(hex: string): [number, number, number] {
    const r = parseInt(hex.slice(1, 3), 16) / 255
    const g = parseInt(hex.slice(3, 5), 16) / 255
    const b = parseInt(hex.slice(5, 7), 16) / 255

    const max   = Math.max(r, g, b)
    const min   = Math.min(r, g, b)
    const delta = max - min

    let h = 0
    const l = (max + min) / 2
    const s = delta === 0 ? 0 : delta / (1 - Math.abs(2 * l - 1))

    if (delta !== 0) {
        if (max === r)      h = ((g - b) / delta) % 6
        else if (max === g) h = (b - r) / delta + 2
        else                h = (r - g) / delta + 4
        h = Math.round(h * 60)
        if (h < 0) h += 360
    }

    return [h, Math.round(s * 100), Math.round(l * 100)]
}

/** Clamp a number between lo and hi. */
function clamp(n: number, lo: number, hi: number): number {
    return Math.min(hi, Math.max(lo, n))
}

/** Emit a CSS hsl() string, clamping all components to valid ranges. */
function hsl(h: number, s: number, l: number): string {
    return `hsl(${h} ${clamp(s, 0, 100)}% ${clamp(l, 0, 100)}%)`
}

/**
 * Inject a full colour palette derived from a single hex colour under a given
 * CSS variable prefix (e.g. "brand" → --color-brand-*).
 * The palette follows the same tone-curve as Tailwind's built-in palettes.
 */
function applyColorScale(hex: string, prefix: string): void {
    if (!hex || !/^#[0-9a-fA-F]{6}$/i.test(hex)) return

    const [h, s, l] = hexToHsl(hex)
    const root       = document.documentElement

    root.style.setProperty(`--color-${prefix}-50`,  hsl(h, clamp(s - 20, 20, 60),  clamp(l + 42, 93, 99)))
    root.style.setProperty(`--color-${prefix}-100`, hsl(h, clamp(s - 15, 25, 65),  clamp(l + 35, 88, 97)))
    root.style.setProperty(`--color-${prefix}-200`, hsl(h, clamp(s - 10, 30, 75),  clamp(l + 26, 82, 94)))
    root.style.setProperty(`--color-${prefix}-300`, hsl(h, clamp(s -  5, 40, 85),  clamp(l + 16, 72, 88)))
    root.style.setProperty(`--color-${prefix}-400`, hsl(h, s,                       clamp(l +  8, 60, 82)))
    root.style.setProperty(`--color-${prefix}-500`, hex)
    root.style.setProperty(`--color-${prefix}-600`, hsl(h, clamp(s +  3, 0, 100), clamp(l -  8, 10, 50)))
    root.style.setProperty(`--color-${prefix}-700`, hsl(h, clamp(s +  6, 0, 100), clamp(l - 16, 10, 45)))
    root.style.setProperty(`--color-${prefix}-800`, hsl(h, clamp(s +  8, 0, 100), clamp(l - 24, 10, 40)))
    root.style.setProperty(`--color-${prefix}-900`, hsl(h, clamp(s + 10, 0, 100), clamp(l - 32, 10, 35)))
}

function applyBrandColor(hex: string): void     { applyColorScale(hex, 'brand') }
function applySecondaryColor(hex: string): void  { applyColorScale(hex, 'secondary') }
