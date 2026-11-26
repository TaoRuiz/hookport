# HookPort 
### Work in progress
A control tower for events between applications.

Applications send events to HookPort over HTTP. HookPort checks their signature, routes them to the configured targets, keeps a record of every delivery, retries failed ones automatically and lets you replay them by hand. A web dashboard shows what came in, where it went and what needs attention.

Status: work in progress. Ingestion, routing, delivery with retries and replay, the events dashboard and the configuration screens are in place; demo targets and deployment are next.

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
php -S localhost:8000 -t public
```

Then fill the database, either with the dev fixtures or with an empty setup.

Dev fixtures (sources, targets, event types, mappings, an event history, and the accounts `admin` / `admin-password` and `demo` / `demo-password`). Loading them empties the database first:

```sh
php bin/console doctrine:fixtures:load
```

Empty setup, with only an administrator account:

```sh
php bin/console app:create-first-admin <login>
```

Delivery worker, in another terminal (sends queued deliveries to the targets, retries included):

```sh
cd api
php bin/console messenger:consume async -vv
```

Dashboard, in another terminal:

```sh
cd portal
npm install
npm run dev
```

The dashboard runs on http://localhost:5173 and forwards `/api` to the PHP server.

Tests: `php bin/phpunit` in `api/`, `npm run test:unit` in `portal/`.

Checks before a commit, in `api/`: `make before-commit` (container and YAML lint, coding style, tests). `make cs-fix` fixes the coding style.
