# Madarat Platform

Madarat (مدارات) is an Arabic-first recruitment platform that connects job seekers, employers, and platform administrators. It combines conventional hiring workflows with AI-assisted CV analysis, job-description generation, candidate matching, and a contextual career assistant.

## Contents

- [What the platform does](#what-the-platform-does)
- [Technology stack](#technology-stack)
- [Requirements](#requirements)
- [Quick start](#quick-start)
- [Demo accounts](#demo-accounts)
- [Configuration](#configuration)
- [User roles and workflows](#user-roles-and-workflows)
- [AI features](#ai-features)
- [Architecture](#architecture)
- [Data model](#data-model)
- [Routes](#routes)
- [Development commands](#development-commands)
- [Testing](#testing)
- [Deployment notes](#deployment-notes)
- [Troubleshooting](#troubleshooting)

## What the platform does

### Job seekers

- Register and maintain a professional profile.
- upload a PDF, DOC, or DOCX CV and receive an AI-generated score, extracted skills, missing skills, summaries, strengths, and recommendations.
- Browse and filter published jobs.
- See a personalized match score for each job.
- Apply once per job and track application status.
- Receive interview invitations.
- Build and export a CV from the browser.
- Ask the Arabic career assistant questions based on their profile, CV, applications, and interview invitations.

### Employers

- Create and maintain a company profile, including a logo.
- Request company verification after completing the required profile fields.
- Create draft jobs or submit jobs for publication.
- Generate an Arabic job description and suggested responsibilities/skills.
- Review ranked candidates and submitted applications.
- Shortlist applicants and schedule interview invitations.

### Administrators

- Review jobs submitted by unverified companies.
- Approve jobs for publication or reject/close them.
- Review, approve, and reject company verification requests.
- View platform-level dashboard statistics.

## Technology stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | React 18, TypeScript, Inertia.js 2 |
| Styling | Tailwind CSS 3, Headless UI, Lucide icons |
| Build tools | Vite 7, TypeScript compiler |
| Database | SQLite by default; Laravel also supports MySQL/PostgreSQL with configuration |
| Authentication | Laravel Breeze-style session authentication |
| AI integration | OpenAI Responses API through Laravel's HTTP client |
| Testing | PHPUnit 12 and Laravel feature tests |

## Requirements

- PHP 8.3 or newer
- Composer 2
- Node.js 20 or newer and npm
- PHP extensions required by Laravel, plus `zip` for DOCX CV extraction
- An OpenAI API key if CV analysis or live AI responses are needed

## Quick start

### Automated setup

From the project root:

```bash
composer run setup
php artisan storage:link
composer run dev
```

The setup script installs PHP and JavaScript dependencies, creates `.env` when needed, generates the application key, runs migrations, and builds the frontend. `composer run dev` starts the Laravel server, queue listener, log viewer, and Vite development server together.

Open [http://localhost:8000](http://localhost:8000).

To load representative demo data and accounts, run:

```bash
php artisan migrate:fresh --seed
```

> `migrate:fresh` deletes all existing database tables. Use it only for disposable local data.

### Manual setup

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm install
npm run dev
php artisan serve
```

On macOS/Linux, replace `copy .env.example .env` with `cp .env.example .env`.

## Demo accounts

After `php artisan db:seed` or `php artisan migrate:fresh --seed`, these accounts are available. All use the password `password`.

| Role | Email |
| --- | --- |
| Administrator | `admin@madarat.test` |
| Employer | `employer@madarat.test` |
| Job seeker | `seeker@madarat.test` |

The seeder also creates additional employers, seekers, jobs, an application, and an interview invitation.

## Configuration

The project starts with SQLite. The repository includes `database/database.sqlite`; for a new checkout, create an empty file there if it is missing.

Important `.env` settings:

```dotenv
APP_NAME=مدارات
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite

FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

OPENAI_API_KEY=
OPENAI_MODEL=gpt-5.2
OPENAI_BASE_URL=https://api.openai.com/v1
```

After changing environment values in a cached environment, run:

```bash
php artisan config:clear
```

### Public uploads

CVs are stored under `storage/app/public/cvs`, and company logos under `storage/app/public/company-logos`. The public symlink is required:

```bash
php artisan storage:link
```

Do not commit uploaded files or secrets. CVs can contain sensitive personal data; production deployments should apply appropriate access, retention, backup, and privacy controls.

## User roles and workflows

### Registration and authorization

Public registration accepts `job_seeker` and `employer`. The application creates the corresponding empty profile automatically. Admin users are created through seeding or administrative database operations, not public registration.

Route groups use both authentication and the custom `role` middleware. Resource ownership is additionally checked in controllers/policies for employer-owned jobs and applications.

### Job publication lifecycle

```text
Employer saves draft ───────────────────────────────> draft
Verified employer requests publication ────────────> published
Unverified employer requests publication ──────────> pending_review
Admin approves pending job ─────────────────────────> published
Admin rejects a job ────────────────────────────────> closed
```

A company verification request requires company name, industry, headquarters, description, and logo. Its lifecycle is `unverified` → `pending` → `verified` or `rejected`.

### Applications and interviews

Only job seekers can apply, and only to published jobs. A database unique constraint prevents the same seeker from applying to the same job twice. Applications begin as `submitted`; an employer can move them to `shortlisted` and then `interview_invited`. Scheduling an interview creates or updates the application's single interview invitation.

## AI features

### CV analysis

- Accepts PDF, DOC, and DOCX files up to 5 MB.
- PDFs are sent to the OpenAI Responses API as file input.
- DOCX text is extracted locally through `ZipArchive`; other accepted non-PDF files are read as text.
- Requires `OPENAI_API_KEY`; failure marks the CV analysis as `failed` while preserving the uploaded path.
- Stores normalized analysis results on the job-seeker profile.

### Job-description generation

The employer form sends basic job details to OpenAI and expects structured description, responsibilities, and suggested skills. If OpenAI is not configured or the request fails, a deterministic Arabic template is returned so this feature remains usable.

### Career assistant

The authenticated assistant uses the user's account, job-seeker profile, recent applications, and interview invitations as context. If a stored CV is available, it may also be attached to the OpenAI request. Each user and assistant message is persisted in `assistant_messages` with the context snapshot. Without an API key—or when the API fails—the assistant uses a local fallback response service.

### Candidate matching

Matching is deterministic and does not call OpenAI:

1. Calculate the percentage of required job skills found in the seeker's extracted CV skills.
2. Add 8 points when job location and seeker city match exactly.
3. Add 5 points when the seeker's field appears in the job title.
4. Cap the final score at 100.

When a job has no required skills, the base score is 40. Skills are compared case-insensitively after trimming.

## Architecture

```text
Browser
  └─ React/TypeScript pages and components
       └─ Inertia requests
            └─ Laravel routes and middleware
                 └─ Controllers + Form Requests + Policies
                      ├─ Eloquent models ── SQLite/database
                      ├─ Matching service
                      └─ AI services ────── OpenAI Responses API
```

Key directories:

| Path | Responsibility |
| --- | --- |
| `app/Http/Controllers` | Request handling and page/API responses |
| `app/Http/Requests` | Validation and request authorization |
| `app/Http/Middleware` | Role-based route protection and Inertia middleware |
| `app/Models` | Eloquent entities and relationships |
| `app/Policies` | Resource authorization rules |
| `app/Services` | AI clients, AI workflows, fallbacks, and matching logic |
| `resources/js/Pages` | Inertia page components grouped by role/feature |
| `resources/js/Components` | Shared React UI components |
| `resources/js/Layouts` | Guest and authenticated layouts |
| `routes` | Web, authentication, and console routes |
| `database/migrations` | Database schema history |
| `database/factories` | Test/demo model factories |
| `database/seeders` | Representative local demo data |
| `tests/Feature` | End-to-end HTTP and workflow coverage |

## Data model

| Entity | Main relationships and purpose |
| --- | --- |
| `User` | Has one seeker or company profile; has many applications; stores role and login data |
| `JobSeekerProfile` | Belongs to user; stores profile, CV path, extracted skills, and analysis results |
| `CompanyProfile` | Belongs to user; has many jobs; stores verification state |
| `Job` | Belongs to company; has many applications; stores publication state and requirements |
| `Application` | Belongs to job and seeker; has one interview invitation; stores match snapshot/status |
| `InterviewInvitation` | Belongs to application; stores schedule, message, and invitation status |
| `AssistantMessage` | Optionally belongs to user; stores conversation message and context snapshot |

Deleting a user, company, job, or application cascades through its dependent recruitment records as defined by the migrations. Deleting a user sets their assistant-message user reference to null.

## Routes

### Public

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/` | Landing page |
| GET | `/jobs` | Published job search and filters |
| GET | `/jobs/{slug}` | Published job details |
| GET | `/cv-builder` | Browser CV builder |
| GET/POST | `/login`, `/register` | Authentication |

### Authenticated shared routes

- `/dashboard` redirects to the dashboard for the current role.
- `/profile` manages the account profile and deletion.
- `POST /assistant/message` returns a JSON assistant reply.

### Job seeker routes

- `/seeker/dashboard`
- `/seeker/profile`
- `/seeker/cv-analysis`
- `/seeker/applications`
- `POST /jobs/{job}/apply`

### Employer routes

- `/employer/dashboard`
- `/employer/company`
- `POST /employer/company/request-verification`
- `/employer/jobs/create`
- `/employer/jobs/{job}/edit`
- `/employer/jobs/{job}/matches`
- `POST /employer/jobs/generate-description`
- Application shortlist and interview-invitation actions

### Administrator routes

- `/admin/dashboard`
- `/admin/jobs/pending`
- `/admin/companies/verification`
- Job approval/rejection and company verification actions

Use `php artisan route:list --except-vendor` for the authoritative route table.

## Development commands

| Command | Purpose |
| --- | --- |
| `composer run dev` | Run server, queue listener, logs, and Vite concurrently |
| `php artisan serve` | Run only the Laravel development server |
| `npm run dev` | Run only Vite with hot reload |
| `npm run build` | Type-check and create a production frontend build |
| `composer test` | Clear configuration and run the test suite |
| `php artisan migrate` | Apply pending database migrations |
| `php artisan db:seed` | Insert demo data |
| `php artisan migrate:fresh --seed` | Rebuild and seed the local database destructively |
| `php artisan storage:link` | Expose public uploads |
| `vendor/bin/pint` | Format PHP code |

## Testing

Run the full backend suite:

```bash
composer test
```

Run a single test class or method:

```bash
php artisan test --filter=MadaratPlatformTest
php artisan test --filter=test_verified_employer_jobs_are_published_immediately
```

The feature tests use an isolated in-memory SQLite database through `phpunit.xml`. Current coverage includes authentication, profiles, role redirects, access control, company verification, job publication, applications, matching, and interview workflows.

Before submitting changes, run both:

```bash
composer test
npm run build
```

## Deployment notes

1. Configure production `APP_KEY`, `APP_URL`, database, mail, queue, filesystem, and OpenAI variables.
2. Set `APP_ENV=production` and `APP_DEBUG=false`.
3. Install optimized PHP dependencies and build frontend assets.
4. Run migrations with `php artisan migrate --force`.
5. Ensure `storage` and `bootstrap/cache` are writable.
6. Configure a persistent queue worker when using queued jobs.
7. Serve the application through the `public` directory.

The included `Procfile` starts a Heroku-compatible Apache process and clears Laravel's config, route, and view caches before startup. SQLite is convenient locally but generally requires a persistent disk; use a managed database when deploying to an ephemeral filesystem.

## Troubleshooting

### Uploaded logos or CV links do not work

```bash
php artisan storage:link
```

Also confirm the web-server user can read `storage/app/public`.

### CV analysis fails

- Confirm `OPENAI_API_KEY` is present and then run `php artisan config:clear`.
- Confirm the file is PDF, DOC, or DOCX and no larger than 5 MB.
- Confirm PHP's `zip` extension is enabled for DOCX extraction.
- Inspect `storage/logs/laravel.log` or run `php artisan pail`.

### Database or session table errors

```bash
php artisan migrate
```

For SQLite, confirm `database/database.sqlite` exists and is writable.

### Frontend changes do not appear

```bash
npm install
npm run dev
```

For production-like verification, use `npm run build` and hard-refresh the browser.

### Configuration changes are ignored

```bash
php artisan optimize:clear
```

## License

The project inherits the MIT license declaration from its Laravel project metadata.
