/**
 * Echo service — lazy singleton.
 *
 * Echo is only constructed the first time getEcho() is called, and only in
 * authenticated contexts (it requires a valid session for channel auth).
 * Importing this file does NOT create a WebSocket connection.
 *
 * Using Reverb (Pusher protocol) for WebSocket transport.
 *
 * Future: swap `broadcaster: 'reverb'` for 'pusher' / 'ably' / 'soketi'
 * without touching any composable or component — just this file.
 */

import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Required by Echo's Reverb/Pusher transport
window.Pusher = Pusher

let _echo: Echo<'reverb'> | null = null

export function getEcho(): Echo<'reverb'> {
    if (_echo) return _echo

    _echo = new Echo({
        broadcaster: 'reverb',
        key:         import.meta.env.VITE_REVERB_APP_KEY  as string,
        wsHost:      import.meta.env.VITE_REVERB_HOST     as string ?? 'localhost',
        wsPort:      Number(import.meta.env.VITE_REVERB_PORT  ?? 8080),
        wssPort:     Number(import.meta.env.VITE_REVERB_PORT  ?? 8080),
        forceTLS:    (import.meta.env.VITE_REVERB_SCHEME  as string ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        // Auth endpoint — Laravel handles this via session cookie automatically
        authEndpoint: '/broadcasting/auth',
    })

    return _echo
}

/** Disconnect and reset the singleton (call on logout). */
export function disconnectEcho(): void {
    if (_echo) {
        _echo.disconnect()
        _echo = null
    }
}

export default getEcho
