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

Open <http://localhost:7080> (set `APP_PORT` to use another port).

The first start installs Composer dependencies, creates `.env`, generates the app key and runs migrations, so give it a minute. Later starts are fast (dependencies are reinstalled automatically when `composer.lock` changes).

### Export the current database to the repository

To save the most up-to-date database from the running Docker container to `database.sqlite`:

```powershell
docker compose exec app cp /data/database.sqlite /var/www/html/database.sqlite
```

This command:

- Accesses the running `app` container.
- Copies the Docker-managed database from `/data/database.sqlite` to the repository directory (mounted at `/var/www/html`).

Then verify the export:

```powershell
ls -la database.sqlite
```

The file should exist and show a recent modification time. You can now commit this to version control:

```powershell
git add database.sqlite
git commit -m "Update database snapshot"
```

### Start with existing database

For a clean rebuild using the repository's `database.sqlite`, use this sequence from the project directory.

#### 1. Stop the current Compose project

```powershell
docker compose down
```

#### 2. Delete the existing Docker volumes

This removes the Docker-managed SQLite database, but does not delete `.\database.sqlite` in the repository.

```powershell
docker compose down --volumes --remove-orphans
```

This also removes the Docker-managed `vendor`, `storage`, and `sqlite-data` volumes.

#### 3. Rebuild the application image

```powershell
docker compose build --no-cache
```

The `--no-cache` option forces Docker to rebuild every image layer.

#### 4. Copy the repository database into the new Docker volume

Run this before starting the full application:

```powershell
docker compose run --rm --no-deps --entrypoint sh app -lc "cp /var/www/html/database.sqlite /data/database.sqlite && chown www-data:www-data /data/database.sqlite"
```

This command:

- Starts a temporary `app` container.
- Mounts the repository into `/var/www/html`.
- Creates the new `sqlite-data` volume at `/data`.
- Copies the repository database into `/data/database.sqlite`.
- Assigns the database to the PHP application user.
- Removes the temporary container afterward.

#### 5. Start the application

```powershell
docker compose up --pull always -d
```

The startup script will then:

- Install Composer dependencies.
- Create required Laravel directories.
- Generate `APP_KEY` if necessary.
- Run pending migrations.
- Start PHP-FPM and Nginx.

Because the imported database already contains the migration records, existing migrations should not recreate its tables.

#### 6. Verify the containers

```powershell
docker compose ps
```

Expected state:

```text
to-do-app-1   Up ... (healthy)
to-do-web-1   Up ...
```

The web container should show:

```text
0.0.0.0:7080->80/tcp
```

#### 7. Verify the database

Check migration status:

```powershell
docker compose exec app php artisan migrate:status
```

Check the imported todo records:

```powershell
docker compose exec app php artisan tinker --execute="echo DB::table('todos')->count();"
```

Open the application at <http://localhost:7080>

#### Complete reset command sequence

For future clean rebuilds, this is the full sequence:

```powershell
docker compose down --volumes --remove-orphans
docker compose build --no-cache
docker compose run --rm --no-deps --entrypoint sh app -lc "cp /var/www/html/database.sqlite /data/database.sqlite && chown www-data:www-data /data/database.sqlite"
docker compose up --pull always -d
docker compose ps
```

**Note:** Do not run `docker compose down --volumes` if you want to preserve the database currently stored in Docker. The repository database remains unchanged because it is only copied into the Docker volume.

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
