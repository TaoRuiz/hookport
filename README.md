# HookPort 
### Work in progress
A control tower for events between applications.

Applications send events to HookPort over HTTP. HookPort checks their signature, routes them to the configured targets, keeps a record of every delivery, retries failed ones automatically and lets you replay them by hand. A web dashboard shows what came in, where it went and what needs attention.

Status: work in progress. Event ingestion and the admin screens for sources and targets are in place; routing, delivery and the events dashboard are not built yet.

## Stack

Symfony 8 (API, PHP 8.5), PostgreSQL, Vue 3 + Vuetify (dashboard).

## Development

Requirements: PHP 8.5, Composer, Node.js, Docker.

API, from the repository root:

```sh
docker compose up -d
cd api
composer install
php bin/console doctrine:migrations:migrate
php bin/console app:create-first-admin <login>
php -S localhost:8000 -t public
```

Dashboard, in another terminal:

```sh
cd portal
npm install
npm run dev
```

The dashboard runs on http://localhost:5173 and forwards `/api` to the PHP server.

Tests: `php bin/phpunit` in `api/`, `npm run test:unit` in `portal/`.
