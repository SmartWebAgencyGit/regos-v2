/**
 * Portal URLs (kept identical to the legacy portal's paths).
 */
export const routes = {
    entry: '/',
    dashboard: '/dashboard',
    players: '/players',
    rules: '/rules',
    register: '/register',
    editRegistration: '/register?edit=1',
    verify: '/verify',
    boardingPass: '/confirmation',
    extraPlayers: '/extraplayers',
    extraPlayersCheckout: '/extraplayers/checkout',
    thanks: '/thanks',
    groups: '/groups',
    joinGroup: (bookingId: number) => `/groups/${bookingId}`,
    logout: '/logout',
} as const;
