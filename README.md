<p align="center">
  <img src="public/images/cronpilot-logo-with-bg.svg" alt="CronPilot" width="320">
</p>

<h3 align="center">Your scheduled jobs shouldn't be invisible.</h3>

<p align="center">
  CronPilot is an open-source, self-hosted control panel for scheduling, running and monitoring jobs on your servers.
</p>

<p align="center">
  <a href="#quick-start">Quick start</a> ·
  <a href="#features">Features</a> ·
  <a href="#how-it-works">How it works</a> ·
  <a href="#deploying">Deploying</a> ·
  <a href="#roadmap">Roadmap</a>
</p>

---

## Stop debugging cron jobs at 3 AM

Cron works beautifully, right up until it doesn't. Then you're SSHing into production to read a crontab, grepping
logs to work out whether last night's backup ran, and discovering that an important job quietly stopped three days ago.

CronPilot gives your scheduled jobs a home: one place to define them, run them, and see exactly what happened.

| With crontab                        | With CronPilot                                           |
|-------------------------------------|----------------------------------------------------------|
| SSH into each server to edit jobs   | Manage every job from one web UI                         |
| Hand-write `*/15 9-17 * * 1-5`      | Build schedules visually and preview the next runs       |
| `grep` through logs for output      | Every run's output, exit status and duration, kept       |
| "Did that actually run last night?" | ✓ Successful, ✗ Failed or ⏭ Skipped, at a glance          |
| Wait for the schedule to test a job | Run it now                                               |
| Comment out a line to stop a job    | Pause and resume with a toggle                           |

## Features

- **Visual schedule builder.** Schedules from every second to once a year, specific weekdays, days of the month
  ("the second Tuesday"), start and end dates, and a timezone per task. The form previews the next run times as you
  edit, in the task's timezone and in yours.
- **Runs on your servers.** Tasks run over SSH on the servers you register, using the credentials you choose for each
  task. Private keys and passphrases are stored encrypted.
- **Run history.** Every run records its output, exit status and duration, so you can see what went wrong without
  logging into the server.
- **Run now.** Trigger any task on demand, with a warning if it's already running.
- **No accidental overlaps.** By default a task never runs twice at once. A run that comes due while the previous one
  is still going is recorded as *Skipped* instead of piling up. Turn on *Allow overlapping runs* for tasks where that's
  fine.
- **Pause and resume.** Pause a task from the task list. When you resume it, it picks up at its next scheduled time
  rather than running straight away to catch up.
- **Tenants.** Keep teams, clients or projects apart. Each tenant has its own servers, credentials, tasks and run
  history, and people can belong to several tenants.
- **Open source and self-hosted.** Your commands, output and credentials stay on your own infrastructure.

## Quick start

> A one-command Docker install is on the [roadmap](#roadmap). Until then, CronPilot runs like any Laravel app.

**Requirements:** PHP 8.4+, Composer, Node.js 18+, and MySQL or MariaDB.

```bash
git clone https://github.com/cronpilot/cronpilot.git
cd cronpilot

composer install
npm install && npm run build

cp .env.example .env        # then set your DB_* values
php artisan key:generate
php artisan migrate
```

Serve the app with [Laravel Herd](https://herd.laravel.com/) (it will be at `http://cronpilot.test`) or with
`php artisan serve` (at `http://localhost:8000`), then **register an account** and create your first tenant.

For local development you can seed an admin user instead. Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env` and run
`php artisan db:seed`.

### Start the scheduler and a queue worker

CronPilot needs **both** of these running. The scheduler decides which tasks are due, and the queue worker runs them.
Without a worker, no task will ever run.

```bash
php artisan schedule:work   # checks for due tasks every minute
php artisan queue:work      # runs them
```

Then add a server and a credential, create a task, and press **Run** to see it work.

## How it works

```text
 schedule:run (every minute)
        │  finds due tasks, moves each one's next run forward
        ▼
      queue ──────► queue worker ──SSH──► your server
                         │                     │
                         │              runs the command
                         ▼                     │
                   run history ◄── output, exit status, duration
```

- The scheduler only **queues** due tasks, so one slow command never delays the others.
- Each task's next run is claimed before it's queued, so a task waiting for a worker isn't queued twice, even when
  scheduler runs overlap.
- A per-task lock (held in the cache, one hour at most) stops a task overlapping itself, unless you allow it.

## Deploying

Run the scheduler from cron, every minute:

```bash
* * * * * cd /path-to-cronpilot && php artisan schedule:run >> /dev/null 2>&1
```

Keep a queue worker running alongside it, under Supervisor, systemd, or
[Laravel Horizon](https://laravel.com/docs/horizon):

```bash
php artisan queue:work
```

Run locks are held in the cache, so your cache store must support
[atomic locks](https://laravel.com/docs/cache#atomic-locks). The default `database` store does.

**Upgrading from an older version?** Tasks now run on the queue, so you need to add a queue worker. The scheduler
alone is no longer enough.

## Roadmap

CronPilot is being relaunched as a proper open-source project. Next up:

- **Docker install:** `docker compose up -d` and you're running, with no PHP or Node setup.
- **Demo data:** a sample tenant with realistic tasks and runs, so there's something to explore straight away.
- **CronPilot Agent:** a small open-source agent you install on each server. It connects out to CronPilot, so
  CronPilot never has to hold SSH credentials for your servers.

Ideas and feedback are very welcome. [Open an issue](https://github.com/cronpilot/cronpilot/issues).

## Why I built CronPilot

<!-- TODO(Peter): this is a first draft written from our planning notes. Rewrite it in your own words. -->

I've managed scheduled jobs in production for years. Eventually I got tired of SSHing into servers, editing crontabs,
grepping logs, and finding out that an important job had silently stopped running days ago. At one point I was using
Jenkins as a cron manager, just to get the visibility and control that cron doesn't give you.

CronPilot is the tool I wanted instead.

I also gave a talk about the problem:
[Cron Jobs Gone Wrong: The Top Mistakes That Keep Your Tasks From Ticking](https://www.slideshare.net/slideshow/cron-jobs-gone-wrong-the-top-mistakes-that-keep-your-tasks-from-ticking/279478111).

## Contributing

Contributions are welcome. For anything bigger than a small fix, please open an issue first so we can talk it through.

1. Fork the repository and create a branch for your change.
2. Make your change, with tests. The suite uses [Pest](https://pestphp.com/): `./vendor/bin/pest`.
3. Open a pull request describing what changed and why.

## Built with

[Laravel 13](https://laravel.com/), [Filament 3](https://filamentphp.com/), [Livewire](https://livewire.laravel.com/),
[phpseclib](https://phpseclib.com/) for SSH, and [Recurr](https://github.com/simshaun/recurr) for schedules.

## License

<!-- TODO(Peter): confirm the license. The previous README said MIT, but there is no LICENSE file in the repo. -->

CronPilot is open-source software licensed under the [MIT license](LICENSE).
