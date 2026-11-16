# PromptEdit – Pixelforge image generation

[![CI](https://github.com/rafaelqueiroz/promptedit/actions/workflows/ci.yml/badge.svg)](https://github.com/rafaelqueiroz/promptedit/actions/workflows/ci.yml)

Take-home assessment for the Senior Product Engineer role at PromptEdit. See [docs/assessment.md](docs/assessment.md) for the brief and [docs/plan.md](docs/plan.md) for the implementation plan.

Stack: Laravel 13, Vue 3, Inertia, Tailwind, Postgres. Deployed on Laravel Cloud.

## Local development (Laravel Sail)

Requirements: Docker + Docker Compose. No local PHP or Node needed.

First-time setup:

```bash
cp .env.example .env            # defaults already point at the Sail pgsql/redis services

# vendor/ is needed before Sail can run; bootstrap it with a throwaway Composer container
docker run --rm -v "$PWD:/app" -w /app -u "$(id -u):$(id -g)" composer:2 \
  composer install --ignore-platform-reqs

./vendor/bin/sail up -d --build
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
```

Day-to-day:

```bash
./vendor/bin/sail up -d          # start app + pgsql + redis
./vendor/bin/sail npm run dev    # Vite dev server (http://localhost)
./vendor/bin/sail artisan queue:work   # generations are processed in the queue
./vendor/bin/sail artisan test
./vendor/bin/sail composer test  # pint + phpstan + rector + phpunit
./vendor/bin/sail down
```

Tip: `alias sail='./vendor/bin/sail'`.

Services and host ports: app `80`, Vite `5173`, Postgres `5432`, Redis `6379`. Override with `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT` in `.env`.

## Code quality

Every push to `main` and every pull request runs [the CI workflow](.github/workflows/ci.yml): a PHP job (Postgres service) and a frontend job, in parallel. The same checks run locally through Sail:

| Check                | Tool                                                                                                          | Command                                                   |
| -------------------- | ------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------- |
| Code style           | [Laravel Pint](https://laravel.com/docs/pint) (`laravel` preset)                                              | `sail composer lint` / `sail composer lint:check`         |
| Static analysis      | [PHPStan](https://phpstan.org) + [Larastan](https://github.com/larastan/larastan), level 7, `app` and `tests` | `sail composer types:check`                               |
| Refactoring rules    | [Rector](https://getrector.com) + [rector-laravel](https://github.com/driftingly/rector-laravel)              | `sail composer refactor` / `sail composer refactor:check` |
| Tests                | PHPUnit against the Sail `testing` Postgres database                                                          | `sail artisan test`                                       |
| JS/Vue lint + format | [vite-plus](https://vite.plus) (`oxlint` + `oxfmt`)                                                           | `sail npm run check` / `sail npm run check:fix`           |
| TypeScript           | `vue-tsc`                                                                                                     | `sail npm run types:check`                                |
| Dependencies         | `composer audit`, `npm audit`, [Dependabot](.github/dependabot.yml)                                           | —                                                         |

`sail composer test` runs Pint, PHPStan, Rector and PHPUnit in one go; `sail composer ci:check` adds the frontend checks and mirrors CI exactly.

### Pixelforge webhooks locally

Pixelforge needs a public URL to deliver webhooks. Run a tunnel (Expose, ngrok or Cloudflare Tunnel) against port 80 and set that URL as the webhook URL on your **local** Pixelforge API key. Use a separate key per environment so `GET /v1/usage` matches each deployment exactly.

```bash
PIXELFORGE_API_KEY=...
PIXELFORGE_WEBHOOK_SECRET=...
```
