import axios from 'axios'

// ── Axios baseline headers ────────────────────────────────────────────────────
// Tells Laravel this is an XHR request (needed for redirectResponse detection).
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// ── CSRF strategy ─────────────────────────────────────────────────────────────
// Axios natively reads the `XSRF-TOKEN` cookie (set fresh by Laravel's
// VerifyCsrfToken middleware on every response) and sends it as the
// `X-XSRF-TOKEN` request header. Laravel decrypts that header server-side.
//
// This is the DYNAMIC path — the cookie is updated after every page visit, so
// subsequent axios.patch/post calls always use the current session's token even
// if the session was regenerated in between (e.g. after a long idle period).
//
// Inertia's own router uses this same cookie mechanism independently, so no
// extra work is needed for router.post/patch/delete calls.
//
// Axios defaults are already:
//   xsrfCookieName: 'XSRF-TOKEN'
//   xsrfHeaderName: 'X-XSRF-TOKEN'
// — but we spell them out explicitly for clarity.
axios.defaults.xsrfCookieName = 'XSRF-TOKEN'
axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN'

// ── Graceful 419 handling ─────────────────────────────────────────────────────
// A 419 (Page Expired) means the CSRF token in the request doesn't match the
// one in the server session. This happens when:
//   • The session expired (idle > SESSION_LIFETIME minutes)
//   • The user opened the page in multiple tabs and one tab regenerated the
//     session token
//
// The safest recovery is a full page reload, which fetches a fresh session
// cookie + fresh XSRF-TOKEN cookie from the server.
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            // Give the browser one chance to load a fresh page.
            // The reload will start a new session (or resume the existing one)
            // and the next axios call will pick up the correct fresh token.
            window.location.reload()
        }
        return Promise.reject(error)
    },
)

window.axios = axios
