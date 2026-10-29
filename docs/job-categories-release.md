# Job categories and persistent uploads: release review

This local branch combines category browsing and management with PostgreSQL
upload storage. It preserves the current CV builder, AI usage policy and result
cache, advertisements, and account deletion. This is a review branch; publishing
its Git commits does not authorize production cutover. A green local test run is
not production cutover approval.

## Preserve the current production data

Use the existing production PostgreSQL database and existing environment
bindings. Do not replace it with a preview database, copy a local environment
file, run demo seeders, or use fresh, refresh, reset, rollback, or database drops.

The two incremental category migrations add nullable category fields, category
tables, initial category definitions, indexes, and restrictive foreign keys.
They do not rewrite existing jobs, publication states, users, applications,
CV references, or uploads. Published legacy jobs remain available under Other
Jobs. Do not run JobCategoryBackfillSeeder on production as part of this release.

## Verified preservation and remaining prerequisites

Production uploads were found on the independent local filesystems of both App
Platform instances. Keeping the PostgreSQL database does not preserve those file
bytes across container replacement. Do not deploy, restart, or scale either
instance until both copies and any uploads arriving during cutover have been
durably preserved, verified by SHA-256, and reconciled with database references.
An archive left inside the source container is not a durable backup.

The operator's read-only results verified 33 files / 20,372,433 bytes on one
instance and 29 files / 6,353,115 bytes on the other. Their private PostgreSQL
copies passed independent SHA-256 readback. They represent 60 distinct disk/path
keys, two identical paths, and no same-path content conflict. These are initial
snapshots, not a freeze or coverage of later uploads.

Of 300 current unique reference paths, 58 matched those snapshots. The 242
unmatched references comprise 238 CVs, one company logo, two course covers and
one advertisement image. Read-only diagnosis found standard relative paths and
no prefix/basename candidates. Current and captured missing path sets matched.
This establishes the observed baseline, not why or when the files disappeared.
Never clear those references, manufacture empty replacements, or describe them
as recovered. The recovery task stays open.

The current production readiness check is TCP; no liveness check is configured.
Build and run command overrides are absent. AutoDeploy is enabled for main.
Do not push main to stage this work: doing so would trigger a deployment.

The user explicitly approved continuing preparation while retaining all 242
documented missing references unchanged and protecting existing files. This
acceptance does not mark missing files as recovered or close their recovery task.
The exact final reference/owner baseline still needs verification by the guarded
proposal before mapping. Do not ask for the same existing-baseline acceptance
again unless its exact scope changes.

The preparation branch has been pushed. The user subsequently explicitly
authorized publishing the update; do not ask for publication approval again.
Keep main unchanged until the file-preserving cutover prerequisites are met,
because its AutoDeploy is enabled. The publish instruction does not establish
drain proof, complete or waive a restore test, or authorize a new paid cluster
or moving production data to another device. A write freeze had been reserved
for separate approval and has not been executed.

A production restore drill has not been completed or waived. The existing restore UI alone is not proof of a
tested backup, and a pre-import database backup does not contain former
container-only upload bytes.

Before cutover, finish a real two-instance mutation freeze and drain procedure.
A local Apache method guard kept GET/HEAD health requests working while blocking
mutations, but it does not drain requests already in flight. Confirm actual
server-side active requests or enforced bounds and all file-writing workers.
Do not restart or scale the old instances to implement the freeze. No maximum
freeze duration is promised before this is measured.

After separate approval, capture final deltas from both instances, verify every
copied byte again, and use fresh references and owners. Available CVs map to
private documents with authenticated, role-checked access. Media are restricted
to allowlisted image paths and types; legacy direct storage serving is blocked.
All independent preserved copies, versions and unreferenced files remain stored.

## Exact missing-reference proposal

Missing references block reconciliation by default. There is no generic
ignore-missing option. The reviewed optional proposal binds two explicitly
selected original runs, their complete manifests, exact missing reference tuples
and all current reference owners into a SHA-256 digest. It validates actual
preserved bytes and public table resolution and rejects omitted references.

The read-only planning action prints no filenames or personal records and does
not record approval:

```sh
php /tmp/REVIEWED_BUNDLE/tools/uploads/database-rescue.php --source=/workspace --action=plan-baseline --baseline-runs=ORIGINAL_A,ORIGINAL_B --instances=INSTANCE_A,INSTANCE_B
```

Resolve run IDs directly from PostgreSQL; do not transcribe identifiers from
screenshots. A proposal digest is not consent. Only an explicitly reviewed digest
may accompany a later separately approved finalize operation. Any changed
missing tuple, new missing reference, owner drift, conflict or unsupported file
blocks mapping. Legitimate uploads can invalidate an earlier digest, so regenerate
and review the final proposal after the last such changes.

Apply mode checks the public namespace, takes a bounded advisory lock and SHARE
locks on all five reference tables before reading target owners. A real competing
writer test verifies that owner updates block until commit. These database locks
do not stop filesystem writes made by an already running upload request.
The CLI's writers-frozen flag is an acknowledgement, not a freeze mechanism.

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

The storage migration 2026_10_28_000001_create_stored_files_table was already
prepared and recorded by the approved initial preservation operation. Verify its
schema/ledger instead of rerunning table creation. Do not run all pending
migrations or a category backfill as a shortcut. The migration process must use
the existing environment and run once, outside replica startup.

Afterward compare existing record counts, IDs, content, publication states and
relationships, verify old CV downloads and images, and check admin permissions,
employer forms, and uncategorized public jobs before resuming normal writes.

## Local verification

Use the normal isolated SQLite test suite and frontend test/build commands.
phpunit.pgsql.xml additionally targets only the disposable loopback PostgreSQL
database madarat_category_verify on port 55432. It must never point at production
or the preview database; its upgrade tests reset synthetic test data.

The PostgreSQL upload suite additionally uses the disposable loopback database
madarat_upload_verify. The missing-baseline probe creates a new synthetic
madarat_baseline_review_* database and never resets an existing database. Its
15 cases include 43 meaningful checks of retained bytes/manifests, strict default
refusal, exact consent, new loss, owner drift, conflicts, idempotency and a real
competing writer. The wrapper is one PHPUnit test, not 15 PHPUnit tests.

The integration repairs the existing undefined CV extension in the uncached
analysis path without removing current AI controls. Positive provider fixtures
explicitly enable their mocked service. Legacy plain-text API coverage is retained
with a separate exact assertion of the configured output-token default.

Combined revision validation:

- Full PHP suite: 208 tests, 1,727 assertions, passed without warnings.
- PostgreSQL 18.6 upload suite: 143 tests, 1,207 assertions, passed without notices.
- PostgreSQL 18.6 category/upgrade suite: 27 tests, 531 assertions, passed.
- Frontend: 40 tests across six files, passed.
- Production frontend build: passed, 3,022 modules transformed.
- Pint dirty/new-file checks and git diff whitespace checks passed.
- Route listing passed and contains 104 routes.
- Real local Apache 2.4.66 / PHP 8.3.30 / PostgreSQL 18.6 smoke: 35 assertions
  passed with synthetic accounts and files. A physical legacy CV was blocked;
  owner, admin and related employer downloads preserved the exact bytes;
  unrelated actors were denied; real multipart upload wrote only to the database.

The HTTP server and database were isolated from production and the existing
preview. The temporary HTTP server was stopped; the preview remains available.
No package lock updates were required. No test was skipped or weakened to hide
an implementation failure. Initial test-run environment errors were corrected
with a synthetic local test key and the PostgreSQL extension in the actual test
runner; they were not production findings.

Existing-missing-baseline acceptance and publication approval are recorded above.
Remaining production gates are effective healthy mutation freeze/drain, its
previously reserved approval, final deltas and guarded mapping, and production
restore verification with an approved target. These are not established by local
checks or the review-branch push. Proceed with authorized preparation; never
request the same already granted publication approval again.
