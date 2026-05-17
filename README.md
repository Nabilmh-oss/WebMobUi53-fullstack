## Installation
1. `composer install`
2. `cp .env.example .env` puis `php artisan key:generate`
3. `php artisan migrate`
4. `npm install`

## Lancement
- `php artisan serve`
- `npm run dev`

## Choix techniques
- Auth SPA via Sanctum (session cookie)
- Deux apps Vue : dashboard et page de vote
- Store partagé `usePollStore` pour la réactivité
- Polling toutes les 5s via `usePolling` pour les résultats en direct
