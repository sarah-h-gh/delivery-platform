# Laravel Delivery Platform

A Laravel 12 delivery-platform assignment focused on relational database design, efficient Eloquent queries, scopes, and service-container usage. Requires PHP 8.2+ and Composer. The implemented feature is a read-only active-orders JSON API; authentication, order creation, and a delivery-management UI are outside its scope.

## Features

- Active orders (`pending`, `accepted`, `picked_up`), oldest first.
- Optional validated driver filter, 20 orders per page, and pagination links preserving filters.
- Suspended stores excluded through a global scope.
- Customer, store, and driver names eager loaded; item counts calculated in SQL.
- An interface-backed query service, a custom order trait, and meaningful transient/singleton container registrations.
- Lazy-loading prevention in `local` and `development`, with production behavior unchanged.
- A repeatable, non-destructive dataset containing 1,000 orders and automated tests using isolated in-memory SQLite.

## Database schema and normalization

| Table | Main columns and relationships |
| --- | --- |
| `users` | `id`, name, unique email, password; has many orders |
| `stores` | `id`, name, `is_suspended`; has many products and orders |
| `products` | `id`, `store_id` → stores, name, decimal price |
| `drivers` | `id`, name; has many orders |
| `orders` | `id`, `user_id` → users, `store_id` → stores, nullable `driver_id` → drivers, status, delivery fee, total price |
| `order_items` | `id`, `order_id` → orders, `product_id` → products, quantity, unit price |

Each domain table has timestamps. Laravel also provides migration, session, password-reset, cache, and queue tables.

The relational design separates customers, stores, products, drivers, orders, and line items. Atomic columns and separate line-item rows avoid repeating groups; foreign keys avoid duplicating entity details in orders. This follows normal-form principles through 3NF for the entity data. `order_items.unit_price` deliberately snapshots the purchase price, while `orders.total_price` stores a derived total including the delivery fee. These monetary snapshots are deliberate denormalization. Factories and the seeder use integer cents for calculations and decimal database columns for storage; tests verify totals and matching product/order stores.

### Indexes

Primary keys already support entity lookups. On MySQL/MariaDB, foreign-key indexes already cover `products.store_id`, `orders.user_id`, `orders.store_id`, `orders.driver_id`, `order_items.order_id`, and `order_items.product_id`. SQLite does not automatically create equivalent child foreign-key indexes. The existing unique email and Laravel session/cache/queue indexes are preserved.

The new migration `database/migrations/2026_10_12_000000_add_active_order_indexes_to_orders_table.php` adds only:

| Index | Reason and column order |
| --- | --- |
| `orders_status_index (status)` | Supports active-status filtering and pagination counts. Kept narrow because a three-value status filter would still require global date sorting with a status-first composite. |
| `orders_driver_id_created_at_index (driver_id, created_at)` | Driver equality first, then oldest-first ordering within that driver's orders. The original driver-only index cannot supply this ordering. |

No duplicate item-count or store lookup indexes are added: `order_items.order_id` supports the correlated count, and the store existence check uses `stores.id`. A separate suspension index is not justified by that lookup. MariaDB may automatically replace the redundant driver-only foreign-key index with the composite; the migration's rollback restores it before dropping the composite. Existing migrations are unchanged.

Local `EXPLAIN` confirmed composite-index use without a filesort for a driver-filtered query and existing index use for item counts. Queries across several statuses without a driver may still need sorting; indexes do not guarantee the same plan for every dataset.

## Scopes and query architecture

`Order` exposes three local scopes using Laravel's `#[Scope]` attribute:

- `active()`: filters the three active statuses through `HasOrderActivity`.
- `forDriver($driverId)`: filters a qualified driver column.
- `oldestFirst()`: orders by qualified `created_at` ascending.

`Store` registers `NotSuspendedScope`, applying `stores.is_suspended = false` globally. The active-orders service uses `whereHas('store')` so orders from suspended stores are excluded before pagination; eager-loaded stores also respect the scope. `withoutGlobalScopes()` is used explicitly when inspecting all stores in tests.

`OrderController` receives `App\Contracts\ActiveOrders` through constructor injection. `EloquentActiveOrders` implements it and owns the original query, leaving the controller responsible for validated filters and `OrderResource` serialization.

`AppServiceProvider::register()` uses:

```php
$this->app->bind(ActiveOrders::class, EloquentActiveOrders::class);
$this->app->singleton(OrderStatusPolicy::class);
```

`bind()` returns a new query-service instance on each resolution. The singleton supplies one immutable active-status policy per application container. `HasOrderActivity`, used by `Order`, resolves that policy for both its `active()` query scope and `isActive()` instance method, keeping SQL filtering and status classification consistent. Tests verify container resolution, different bound instances, identical singleton instances, trait behavior, and controller injection.

## Eager loading and N+1 prevention

The service selects only required order columns, eagerly loads `user:id,name`, `store:id,name`, and `driver:id,name`, and calls `withCount('items')`. The count is a correlated subquery within the order SELECT, rather than one additional query per order. `OrderResource` uses `whenLoaded()` and `whenCounted()` to avoid implicit loading during serialization.

`AppServiceProvider::boot()` calls:

```php
Model::preventLazyLoading($this->app->environment(['local', 'development']));
```

An unintended unloaded relationship access raises `LazyLoadingViolationException` in those environments. Production, staging, and the default testing environment retain normal behavior. The active-orders tests explicitly enable the local guard. Laravel's built-in guard may not catch single-model hydration or newly created models; it is a development aid rather than a guarantee against every extra query.

### N+1 measurement: 55 vs 5 queries for 20 orders

A read-only measurement against the configured local dataset used the same oldest active page, store visibility condition, and SQL item counts, then accessed customer, store, and driver names. Query logging was enabled after opening the connection and reset between runs. The development guard was temporarily disabled only in the measurement process so the lazy baseline could execute.

| Version | Queries |
| --- | --- |
| Lazy relationships | **55** = pagination count + orders + 20 customer lookups + 20 store lookups + 13 assigned-driver lookups |
| Existing eager-loaded service | **5** = pagination count + orders + customers + stores + drivers |

This is 50 fewer queries, approximately a 91% reduction. The page contains 20 orders with 13 assigned drivers. Baseline totals vary with driver assignments and page contents; a supplied `driver_id` also adds a validation lookup outside this service-level measurement. The five-query measurement includes pagination and excludes unrelated request infrastructure.

To reproduce in a local Tinker session, enable `DB::enableQueryLog()` after establishing the connection. Compare the service's `paginate()` with the same query without `with(...)`, access the three relations on each order, and count `DB::getQueryLog()` after each run, using `DB::flushQueryLog()` between runs. Restore the lazy-loading setting after measuring. No database writes are needed.

## Installation and database setup

1. Clone this repository and enter its directory.
2. Run `composer install`.
3. Copy `.env.example` to `.env` (`Copy-Item .env.example .env` in PowerShell, or `cp .env.example .env` on Unix).
4. Set `APP_ENV=local`, configure your local database, and run `php artisan key:generate` for the new installation.

For MySQL/MariaDB, start your local database server, create an empty development database named `delivery_platform`, and configure `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=delivery_platform
DB_USERNAME=your_local_database_user
DB_PASSWORD=your_local_database_password
```

Use your own credentials and keep `.env` private. `.env.example` defaults to SQLite; to use that instead, create `database/database.sqlite` and set `DB_CONNECTION=sqlite` with `DB_DATABASE` pointing to that file's absolute path. Ensure PHP's PDO driver for the chosen database is enabled; tests also require PDO SQLite.

Apply migrations and optionally seed the development dataset:

```sh
php artisan config:clear
php artisan migrate
php artisan db:seed
php artisan serve
```

Migrations apply pending schema changes, and the seeder preserves existing rows. No database reset is required. Never use destructive reset commands against an existing development database.

Node.js is optional for the JSON API. For frontend assets, run `npm install` followed by `npm run build`. The current root page is Laravel's welcome page.

### Seeder

`DatabaseSeeder` invokes `DeliveryPlatformSeeder`, which creates:

- 200 customers, 40 drivers, 30 stores (25 available, 5 suspended), and 450 products.
- **1,000 orders**: 650 delivered, 100 cancelled, 100 pending, 100 accepted, and 50 picked up.
- 1–5 distinct product lines per order, with quantities of 1–4 and matching stores.
- Active orders only at available stores, pending orders without drivers, and realistic timestamps and monetary totals.

The dataset is written in a transaction. A reserved marker user is inserted last; subsequent runs skip the completed dataset instead of duplicating it. Existing records are preserved. `DatabaseSeeder` additionally creates `test@example.com` only if absent. Consequently, 1,000 is the number of seeded orders, not a forced database-wide total when orders already exist. Factories are provided for all domain models.

## API examples

Start the server at `http://127.0.0.1:8000`, then request:

```sh
curl -H "Accept: application/json" "http://127.0.0.1:8000/api/orders/active"
curl -H "Accept: application/json" "http://127.0.0.1:8000/api/orders/active?driver_id=1"
curl -H "Accept: application/json" "http://127.0.0.1:8000/api/orders/active?driver_id=1&page=2"
```

Use an existing driver ID. `driver_id` is optional; when supplied it must be a nonempty integer identifying an existing driver. Invalid values return HTTP 422. Successful responses contain `data`, `links`, and `meta`, with 20 orders per page. Each order has this structure (illustrative values):

```json
{
  "id": 42,
  "status": "accepted",
  "customer_name": "Sample Customer",
  "store_name": "Sample Store",
  "driver_name": "Sample Driver",
  "items_count": 3,
  "created_at": "2026-10-12T10:00:00.000000Z"
}
```

`driver_name` is `null` for unassigned orders. Pagination links preserve query parameters. This assignment endpoint currently has no authentication middleware.

## Tests and formatting

```sh
php artisan test
php vendor/bin/pint --test
```

`composer test` additionally clears configuration before running tests. PHPUnit uses `APP_ENV=testing`, `DB_CONNECTION=sqlite`, and `DB_DATABASE=:memory:`. Database feature tests explicitly configure and migrate their isolated in-memory connection; they do not reset or write to the local MySQL database.

Coverage includes API statuses, sorting, suspended-store exclusion, driver validation, pagination and filter links, eager-loaded relations and item counts, seeder integrity and repeatability, factory totals, interface injection, container lifetimes, trait behavior, environment-specific lazy-loading prevention, violation detection, and unchanged production lazy loading.

Pint can be restricted to changed PHP files by passing their paths. Full-project Pint currently reports pre-existing formatting issues in the original products and orders migrations; those migrations have deliberately remained unchanged.

## Submission

Submit the GitHub repository URL after all assignment files have been reviewed, committed, and pushed. Ensure the reviewer can access the repository. Keep `.env`, credentials, private keys, `vendor`, and `node_modules` out of Git; `.env.example` is a public configuration template. Composer dependencies are reproducible from `composer.lock`. The local `staged-files.txt` listing is not needed for submission.
