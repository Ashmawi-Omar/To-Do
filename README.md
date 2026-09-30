# To-Do

A classic to-do app built with **Laravel 13**, **SQLite**, **Blade + Alpine.js** and **Docker** (nginx + PHP-FPM).

## Features

- Add a to-do with Enter or the **Add** button
- Mark a to-do as completed (and back to active)
- Edit a to-do (**Edit** button, or double-click the title; Enter saves, Esc cancels)
- Delete a to-do
- **Calendar**: every to-do belongs to a day (defaults to today; pick another in the date field). Click a day to see what's planned and what's done. Dots show open (blue) and done (green) to-dos, *Today* / *All days* jump back, and the arrows browse months.
- Filter by **All / Active / Completed**, combined with the selected day
- Move a to-do to another day with **Edit** and the date field
- Creation date shown on every to-do (in the viewer's local time)
- Data persists in a SQLite database stored on a Docker volume

## Run with Docker

```sh
docker compose up -d --build
```

Open <http://localhost:8080> (set `APP_PORT` to use another port).

The first start installs Composer dependencies, creates `.env`, generates the app key and runs migrations, so give it a minute. Later starts are fast (dependencies are reinstalled automatically when `composer.lock` changes).

| Command | What it does |
|---|---|
| `docker compose exec -u www-data app php artisan test` | Run the test suite |
| `docker compose down` | Stop the app (data is kept) |
| `docker compose down -v` | Stop the app **and delete the database** (and the `vendor`/`storage` volumes, so the next start reinstalls dependencies) |
| `docker compose logs -f app` | Follow the Laravel and PHP-FPM logs |

The project directory is bind-mounted into the container, so code changes show up on refresh.

### Why `vendor/` and `storage/` are Docker volumes

On Windows/macOS, bind mounts are slow for file checks. With `vendor/` (thousands of PHP files) on the bind mount, every request after a short pause took about 1.5s because OPcache re-validated every file. Keeping `vendor/` and `storage/` on native volumes, plus the OPcache and realpath-cache settings in `docker/php/app.ini`, brings requests down to roughly 0.1s. Because of this, `vendor/` on your host is **not** what the container uses; run Composer commands inside the container (`docker compose exec app composer ...`).

## Run without Docker

Requires PHP 8.3+ with `pdo_sqlite`, and Composer.

```sh
composer setup
touch database/database.sqlite   # if it doesn't exist yet
php artisan serve
```

## Structure

- `app/Http/Controllers/TodoController.php`: list/filter, create, edit, toggle, delete
- `app/Models/Todo.php`: model with `active` / `completed` / `onDate` scopes
- `resources/views/todos/_calendar.blade.php`: the month calendar
- `resources/views/`: Blade layout and the to-do page
- `public/css/app.css`, `public/js/alpine.min.js`: no frontend build step needed
- `tests/Feature/TodoTest.php`: feature tests
- `Dockerfile`, `docker-compose.yml`, `docker/`: container setup

## Calendar notes

- The selected day lives in the URL (`?date=2026-10-15`, or `?date=all`), so days can be bookmarked. `?month=2026-11` browses months without changing the selected day.
- "Today" follows the browser's timezone: a small script in the layout stores it in a `tz` cookie (one extra reload on the very first visit). Without the cookie the server falls back to `APP_TIMEZONE`.
- To-dos that existed before the calendar were assigned the day they were created on.
