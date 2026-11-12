# Decisions

Deviations from `.claude/00-overview.md` / `.claude/plans/01-backend-laravel.md`.

| Date | What | Why |
|---|---|---|
| 2026-11-12 | Tests use **PHPUnit**, not Pest. | Owner preference. Every "Pest" reference in the plans means PHPUnit tests. |
| 2026-11-12 | No repo `docker-compose.yml`; dev uses the shared stack in `F:\Dev-Tools` (MySQL **8.0**, Redis with password, MailHog instead of Mailpit). DBs `kanban` / `kanban_test` and user `kanban` created there by hand. | Reuse the machine's existing services. MySQL 8.0 vs 8.4: no features we rely on differ. |
| 2026-11-12 | **Horizon not installed** (B0 step 5). Queues run with `php artisan queue:work redis`. | Horizon needs `ext-pcntl`/`ext-posix`, which native Windows PHP lacks. Revisit when deploying to Linux. For now queues run with php artisan queue:work redis |
| 2026-11-12 | Tests use `CACHE_STORE=array`, not `redis` (B0 step 6). | Rate-limit counters in Redis leaked between tests and runs (register limit 5/min/IP caused 429s), and the dev Redis is shared with other projects, so tests must not write to or flush it. |
