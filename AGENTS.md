# Repository Guidelines

## Project Structure & Module Organization
This repository combines a Laravel backend/web app with a separate Expo mobile app.
- `app/`, `routes/`, `config/`, `database/`: Laravel domain logic, HTTP/API routes, app config, migrations/seeders.
- `resources/js/`: Inertia React frontend (`Pages/`, `Layouts/`, `app.tsx`).
- `resources/css/`, `public/`: web styling and static assets.
- `tests/Feature` and `tests/Unit`: PHPUnit coverage for API, flow, and unit behavior.
- `mobile/`: Expo Router app (`app/` routes, `components/`, `services/`, `stores/`, `assets/`).
- `plans/` and `specs/`: product and technical reference docs.

## Build, Test, and Development Commands
- `composer setup`: install PHP/Node deps, create `.env`, generate key, migrate DB, and build assets.
- `composer dev`: run Laravel server, queue worker, logs (`pail`), and Vite concurrently.
- `npm run dev` / `npm run build`: start Vite dev server or build web assets.
- `composer test`: clear config and run PHPUnit suite.
- `php artisan migrate --seed`: apply schema and seed local data.
- `cd mobile && npm start`: start Expo dev tools.
- `cd mobile && npm run ios|android|web`: run mobile targets locally.

## Coding Style & Naming Conventions
- Follow `.editorconfig`: UTF-8, LF, 4-space indentation (2 spaces for `*.yml`/`*.yaml`).
- PHP follows PSR-4 autoloading (`App\\...`), with class/file names in `StudlyCase`.
- React/Expo components use `PascalCase` filenames; hooks use `useXxx` camelCase.
- Keep controllers/services/models focused; place shared UI primitives under `mobile/components/ui`.
- Run `./vendor/bin/pint` before opening a PR for PHP formatting consistency.

## Testing Guidelines
- Framework: PHPUnit (Laravel test runner via `php artisan test`).
- Place integration and HTTP tests in `tests/Feature`; pure logic tests in `tests/Unit`.
- Name test files `*Test.php` and use descriptive method names for behavior.
- Run targeted tests with `php artisan test --filter=AssessmentFlowTest` during iteration.

## Commit & Pull Request Guidelines
- Use Conventional Commit-style prefixes seen in history: `feat:`, `fix:`, `refactor:`, `chore:`.
- Keep commits scoped; include migrations and related tests in the same PR.
- PRs should include: purpose, key changes, test commands run, config/env impacts, and linked issue.
- Add screenshots or recordings for UI changes in web (`resources/js`) or mobile (`mobile/app`).

## Security & Configuration Tips
- Start from `.env.example`; never commit secrets from `.env`.
- Validate billing/auth webhook settings before enabling Stripe-related flows.
- Prefer least-privilege API keys and rotate credentials when sharing environments.
