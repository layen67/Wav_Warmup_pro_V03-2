# Fix Report - Phase 1 & Settings Architecture

## Phase 1: Critical Bugs + Settings

### Database & SQL
- **SQL Injection Prevention:**
  - Verified `Database::get_servers` uses a column whitelist for `ORDER BY`.
  - Added `default_sort_column` and `default_sort_order` settings to control defaults.
- **Unbounded Queries:**
  - Added `LIMIT` clause to `get_servers`, `get_logs`, and `get_enriched_activity` queries.
  - Implemented `db_query_limit` setting (default: 500) to cap results.
  - Capped pagination size in `get_logs` against the global limit.

### Logic & Performance
- **Sleep Calls:**
  - Removed `usleep(50000)` from `Logger::cleanup_old_logs` to prevent process blocking.
- **Queue Management:**
  - Implemented **Queue Locking** using WordPress Transients (`pw_queue_lock`).
  - Added settings: `queue_locking_enabled` (default: ON) and `queue_lock_timeout` (default: 60s).
  - Implemented **Retry Logic** in `QueueManager` with configurable strategies:
    - `fixed`: Base delay.
    - `linear`: Base * attempts.
    - `exponential`: Base * (2^attempts).
  - Added `max_retries` setting logic in `QueueManager`.
- **API Reliability:**
  - Added configurable `api_timeout` (default: 15s) to `Sender.php` and `Client.php`.
  - Refactored `Sender::process_queue` to allow external retry handling (preventing duplicate retries when called from `QueueManager`).

### Settings Architecture (Phase 6)
- **New `Settings` Class:**
  - Implemented `src/Admin/Settings.php` handling a single `pw_settings` array option.
  - Support for Tabbed Interface (General, Security, Queue, Performance, Interface, Notifications).
  - Built-in Migration logic to map legacy options (e.g. `pw_global_tag`) to the new array structure.
  - Static `Settings::get($key, $default)` helper for easy access throughout the codebase.
- **UI Updates:**
  - Updated `admin/partials/settings.php` to render the tabbed navigation and dynamic sections.

## Files Modified
- `src/Admin/Settings.php` (Created/Overwritten)
- `admin/partials/settings.php` (Overwritten)
- `src/Models/Database.php`
- `src/Services/Logger.php`
- `src/Services/QueueManager.php`
- `src/API/Sender.php`
- `src/API/Client.php`
