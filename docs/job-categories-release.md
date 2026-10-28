# Job category release

This branch contains job-category browsing, employer category selection, and
Administrator category management on top of the current main branch. It retains
the current CV builder, CV analysis, advertisements, and account-deletion code.
The local PostgreSQL file-storage implementation is not part of this branch.

## Preserve the current production data

Use the existing production PostgreSQL database and existing environment
bindings. Do not replace it with a preview database, copy a local environment
file, run demo seeders, or use fresh, refresh, reset, rollback, or database drops.

The two incremental category migrations add nullable category fields, category
tables, initial category definitions, indexes, and restrictive foreign keys.
They do not rewrite existing jobs, publication states, users, applications,
CV references, or uploads. Published legacy jobs remain available under Other
Jobs. Do not run JobCategoryBackfillSeeder on production as part of this release.

## Deployment prerequisite

Production uploads were found on the independent local filesystems of both App
Platform instances. Keeping the PostgreSQL database does not preserve those file
bytes across container replacement. Do not deploy, restart, or scale either
instance until both copies and any uploads arriving during cutover have been
durably preserved, verified by SHA-256, and reconciled with database references.
An archive left inside the source container is not a durable backup.

This branch does not solve that storage prerequisite and must not be merged
into a live deployment branch before it is resolved. Production restore testing
also remains a separate, uncompleted decision.

## Category migration execution

The Procfile intentionally does not execute migrations during replica startup.
After the preservation prerequisite, backup checks, and release approval, run
exactly one migration process before starting the new web revision:

```sh
php artisan madarat:migrate-job-categories --force
```

Use the existing direct PostgreSQL connection. This command takes a session
advisory lock and applies only:

1. 2026_10_22_000001_add_category_fields_to_jobs_table.php
2. 2026_10_22_000002_create_job_category_tables.php

Consult the existing migration ledger; do not edit it to conceal a conflict.
Do not use transaction or statement pooling for this session lock. Stop the
release if another runner owns the lock. The composer intl requirement ensures
the buildpack includes the PHP extension required by the current application.

Afterward compare existing record counts, IDs, content, publication states and
relationships, verify old CV downloads and images, and check admin permissions,
employer forms, and uncategorized public jobs before resuming normal writes.

## Local verification

Use the normal isolated SQLite test suite and frontend test/build commands.
phpunit.pgsql.xml additionally targets only the disposable loopback PostgreSQL
database madarat_category_verify on port 55432. It must never point at production
or the preview database; its upgrade tests reset synthetic test data.

Verification of this isolated branch on top of main 635a48f:

- PostgreSQL 18.6 category/upgrade suite: 27 tests, 531 assertions, passed.
- Frontend: 38 tests passed; production build passed, 3022 modules transformed.
- Pint, Composer validation, and git diff whitespace checks passed.
- Full PHP suite: 192 tests, 1474 assertions; 171 passed, 3 errors and 18
  failures in the unchanged CV/OpenAI and advertisement code/tests. For example,
  CvAnalysisService::analyzeUncached uses an undefined extension variable, also
  present in main 635a48f. No failing assertion was removed or weakened.

The full PHP suite is not green. This branch is for review on GitHub and is not
a production go-live declaration; existing failures and upload preservation
must be resolved before deployment.
