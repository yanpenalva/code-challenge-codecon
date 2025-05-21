# Codecon Challenge - 1S vs 3J

This repository contains my implementation of the **"1S vs 3J"** challenge proposed by Codecon, using **Laravel 12 with Octane and Swoole**.
I chose this stack to focus on high-performance in-memory APIs with a clean and modular architecture.

## 🧠 The Challenge

Build a RESTful API that:

-   Accepts a JSON file with 100,000 users via a command.
-   Stores data entirely in memory (no DB).
-   Exposes analytics endpoints with response times under 1 second.
-   Returns in every response:
    -   Processing time in milliseconds.
    -   Request timestamp.

## 🎯 Objectives

-   Efficient data loading and access in memory.
-   Consistent sub-second response times.
-   Maintainable, modular and typed codebase.
-   Adherence to SOLID, PSR standards, and Laravel 12 best practices.

## 🚀 Tech Stack

-   [Laravel 12](https://laravel.com/docs/12.x)
-   [Laravel Octane](https://laravel.com/docs/12.x/octane)
-   [Swoole Extension](https://www.swoole.co.uk/)
-   [PHP 8.3+](https://www.php.net/)
-   No database, no external cache — memory only.

## ⚙️ Why Laravel Octane + Swoole?

I selected **Swoole** with **Laravel Octane** to leverage:

-   Coroutine-based concurrency (non-blocking I/O).
-   Persistent memory between requests.
-   High throughput and low latency.
-   Stateless design ideal for this type of challenge.

## 📦 Data Load Strategy

Instead of using the HTTP `POST /users` endpoint, which would block the server due to large payload size and 30s timeout limits, the data loading is performed **via CLI command**, which is:

-   More performant and reliable for 100,000 records.
-   Fully compatible with Octane/Swoole memory model.
-   Finishes consistently in under **40 seconds**, using a streaming JSON parser.
-   Preserves system responsiveness and respects the challenge constraints.

### ✅ Command Used

The command to load users is:

```bash
php artisan users:process
```

This command will:

-   Read `users.json` from `storage/app/`
-   Parse it in stream (low memory)
-   Save it in memory (cache) in chunks
-   Log progress and total execution time

This approach is valid and aligned with the challenge goals, delivering the same final result as `POST /users`, but in a more robust and scalable way.

## 🔍 Endpoints Overview

-   `GET /api/v1`: Check API status.
-   `GET /api/v1/check`: Check cache data after loading.
-   `GET /api/v1/superusers`: Users with `score >= 900` and `active = true`.
-   `GET /api/v1/topCountries`: Top 5 countries with most superusers.
-   `GET /api/v1/teamInsights`: Aggregated metrics per team.
-   `GET /api/v1/activeUsersPerDay`: Daily login counts, optional filter via `?min=3000`.
-   `GET /api/v1/evaluation`: Evaluate endpoints (status, latency, valid JSON).

## ▶️ Running the Project

### 1. Clone the repository

```bash
git clone <repository-url>
cd <project-folder>
```

### 2. Build and start the container

```bash
docker-compose up -d --build
```

### 3. Access the container

```bash
docker-compose exec app sh
```

### 4. Run post-install script

```bash
./scripts/post-install.sh
```

This script will:

-   Install Laravel Octane
-   Run `php artisan install:api`
-   Install Octane config for Swoole
-   Generate application key
-   Clear and cache config/routes

### 4.1 Run start-server script

-   Start Octane server on port 8000 using `scripts/start-server.sh`

### 5. Load data

```bash
php artisan users:process
```

### 6. If I had more time, what would I improve?

-   Add tests (unit, integration, functional).
-   Improve error handling and logging.
-   Create a Service or Action class for data processing and Business Logic.
-   Implement a more robust caching strategy.

## 📄 License

MIT
