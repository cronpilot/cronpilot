# Cron Pilot

"Cron Pilot: Run, Repeat, Relax" is a robust service for managing repeated tasks or cron jobs. Built with Laravel using 
the Filament package, it supports multi-tenant architectures, making it ideal for companies managing multiple projects 
or clients.

## Features

- **Multi-Tenant Support:** Isolated data and resources for each tenant.
- **User-Friendly Interface:** Built with the Filament package for a sleek and intuitive UI.
- **Flexible Task Scheduling:** Easily manage and schedule cron jobs.
- **Notifications:** Get notified about the status of your tasks.
- **Task History:** Track the execution history of your tasks.
- **Role-Based Access Control:** Secure your tasks with fine-grained permissions.

## Installation

1. **Clone the repository:**
    ```bash
    git clone https://github.com/cronpilot/cronpilot.git
    cd cronpilot
    ```

2. **Install dependencies:**
    ```bash
    composer install
    npm install
    ```

3. **Set up environment variables:**
   Copy the `.env.example` file to `.env` and configure your environment variables, including your database settings.

4. **Run the migrations:**
    ```bash
    php artisan migrate
    ```

5. **Seed the database (optional):**
    ```bash
    php artisan db:seed
    ```

6. **Serve the application:**
    Laravel Herd is recommended approach for running locally. It is a fast and easy way to run your Laravel applications
    locally. If you don't have Laravel Herd installed, you can download it from [Laravel Herd](https://herd.laravel.com/).

    Once Laravel Herd is installed and configured, you can simply visit `http://cronpilot.test` in your browser to
    access your application.

    Another alternative is to use artisan
    ```bash
    php artisan serve
    ```

## Usage

Once installed, you can access the application at `http://localhost:8000`. Register a new account or log in with your
credentials. You can then start creating and managing tasks (cron jobs) through the Filament interface.

### Creating a Task

1. Navigate to the Tasks section.
2. Click on "Add Task."
3. Fill in the task details, including the name, schedule, and command.
4. Save the task.

### Managing Tenants

1. Navigate to the Tenants section.
2. Click on "Add Tenant."
3. Fill in the tenant details, including the name and domain.
4. Save the tenant.

### Running The Scheduler in Development Without Cron
You can run the scheduler in development without using cron by running the following command:
```bash
php artisan schedule:run
```

or if you want to keep the scheduler running, you can use the following command:
```bash
php artisan schedule:work
```

### Running the Queue Worker

**Cron Pilot requires a running queue worker.** Due tasks are dispatched to the queue rather
than executed inline by the scheduler, so without a worker no task will ever run:

```bash
php artisan queue:work
```

This applies in development and in production. If you are upgrading an existing installation
that predates this change, adding a worker is required — the scheduler alone is no longer
enough.

By default a task never runs twice at once: if a run comes due while the previous one is still
in progress, it is recorded as **Skipped**. Turn on **Allow overlapping runs** on a task to let
its runs overlap. Run locks are held in the cache, so the cache store must support
[atomic locks](https://laravel.com/docs/cache#atomic-locks) (the default `database` store does).

## Deployment (with Cron)
Be sure to add the following to your server's cron file to run the scheduler every minute:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Run a queue worker alongside it, supervised by Supervisor, systemd, or Laravel Horizon.

## Contributing

Contributions are welcome! Please follow these steps to contribute:

1. Fork the repository.
2. Create a new branch for your feature or bugfix.
3. Make your changes and commit them with clear messages.
4. Push your changes to your fork.
5. Submit a pull request.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for more details.

## Acknowledgements

- [Laravel](https://laravel.com/)
- [Filament](https://filamentphp.com/)

---

Made with ❤️ by some people who may or may not be named after a popular street medication.
