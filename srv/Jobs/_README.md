# Jobs

## Overview

Generic background jobs (plan 12, Phase 2, in heurist-explorer
`docs/development/12 Smarty-Reports-Plan.md`). First users: Smarty report test
runs and generation; later records batch actions and exports.

A job is started by `POST /api/{db}/jobs`. The start request answers with the
queued job, closes the HTTP connection and runs the job in the same PHP process
(`Controller/JobController.php`). The client polls `GET /jobs/{id}` and may stop
the job with `POST /jobs/{id}/cancel`.

State is kept in JSON files, `<filestore>/<db>/scratch/jobs/<id>.json`; Stop is a
separate `<id>.cancel` file; result content (e.g. preview HTML) is `<id>.result`.
Files older than 7 days are removed. A job without a heartbeat for 90 s is "lost".

Statuses: `queued`, `running`, `done`, `failed`, `cancelled`, `timeout`, `lost`.

Rules: logged-in users only; one active job of the same type per user (a type
whose handler `replacesPrevious()`, e.g. `report-preview`, stops the user's
older job instead of refusing the new one); at most
`maxJobs` active jobs per database (Reports setting, default 3); each job type
has a time limit; Stop also runs `KILL QUERY` on the connections a job registered.

Job types are supplied by the caller (`hserv/controller/api.php`):
`report-preview` and `report-generate` (`Reports/Jobs/ReportPreviewJob.php`,
`ReportGenerateJob.php`, built by `Runtime\ServiceFactory::reportJobHandlers()`).

## Key files

- `JobRunner.php` — start (validation, limits), run, cancel, list, access rules.
- `JobStore.php` — JSON state files, cancel flag, result content, cleanup.
- `JobContext.php` — what a running job uses: progress/heartbeat, `check()` for
  Stop and the time limit, connection ids, result content.
- `JobHandlerInterface.php` — one job type: `prepare()` validates, `run()` works.
- `JobInterruptedException.php` — thrown by `JobContext::check()`.
