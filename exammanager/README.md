# Soft manager by KAMA (`local_exammanager`)

A Moodle plugin for **bulk exam scheduling**: upload a planning file
(CSV/XLSX), preview and correct it, then program the opening/closing dates,
duration, access code and Safe Exam Browser exit code of dozens of quizzes
in one click.

- **Component**: `local_exammanager`
- **Compatibility**: Moodle 3.9+ (developed and tested up to Moodle 5.0)
- **Type**: local plugin — installs into `local/exammanager`
- **Licence**: GPL v3 or later (see `LICENSE`)

## Why this plugin

Institutions that run large exam sessions (dozens of courses, hundreds of
quizzes, room/supervisor allocation) typically have to open every Moodle
quiz by hand to set its schedule, password and Safe Exam Browser settings.
Soft manager turns that into a single spreadsheet upload: one row per quiz,
one click to apply it to every quiz at once, with a preview step and
validation before anything is written to the database.

## Features

### Exam programming (`/local/exammanager/index.php`)
- Drag-and-drop upload of a CSV / XLSX / XLS planning file, with a
  downloadable Excel template.
- Editable preview: correct the course, quiz, dates and duration directly
  in the table, with live client-side validation before submission.
- Quiz picker limited to the active quizzes of the matched course (deleted/
  recycle-bin quizzes excluded, no duplicates).
- Access code (the quiz password) and **Safe Exam Browser** exit code, set
  per row to generate, keep, or disable.
- **Optional shared codes**: when enabled, quizzes in the same course that
  open at the same date/time share the same access and SEB codes; when
  disabled, every quiz gets its own unique codes.
- **Post-programming lock**: once a batch is programmed, the results become
  read-only and no further edits or re-programming are possible (enforced
  both in the UI and on the server) until a new file is uploaded.
- Automatically reveals the quiz activity and its section if they were
  hidden.
- CSV, Excel and execution log exports of every programmed batch.

### Dashboard (`/local/exammanager/dashboard.php`)
- Programmed exams, rooms used, supervisors involved, exams scheduled today.
- Total number of questions across every quiz scheduled by the plugin.
- Students enrolled (student role only) in the courses targeted by the
  plugin.
- Average number of participants per scheduled test.
- Average participation rate per course.
- Average attempt duration.
- An analytics chart (Chart.js).

### Other screens
- **Activity planner**: bulk availability-restriction planning for other
  course activities and sections (by date, grade, user profile field, or a
  combined restriction set), including a CSV/XLSX bulk-upload mode.
- **Calendar**: an interactive FullCalendar view of every scheduled exam,
  with room and supervisor conflict detection.
- **History**: a filterable, paginated history of every scheduled exam
  (statuses: scheduled, running, finished, hidden, error), with CSV export.
  Access codes and SEB exit codes are never shown in this view.
- **Reports**: room and supervisor scheduling conflicts.
- **Sessions**: exam session records.

### Quick access
A "Soft manager" link is added to Moodle's primary navigation bar, visible
only to users who hold the `local/exammanager:manage` capability (managers
and administrators by default).

## Installation

1. Install the plugin as a zip via *Site administration → Plugins → Install
   plugins*, or copy the `exammanager` folder into `local/` on your Moodle
   server.
2. Visit *Site administration → Notifications* to complete the installation.
3. The `local/exammanager:manage` capability is granted to the Manager role
   by default; assign it to any other role that should access the plugin.

## Planning file format

Required columns: `open_time`, `close_time`, `time_limit` (minutes).

Optional columns: `course_shortname`, `quiz_name`, `teacher`, `room`,
`session`, `access_code_action`, `seb_action` (`keep` / `generate` /
`disable`), `generate_access_code`, `generate_seb_exit_code`,
`force_new_codes`.

Accepted date formats: `YYYY-MM-DD HH:MM`, `DD/MM/YYYY HH:MM`, and native
Excel date values.

## Data and privacy

The plugin records its scheduling operations in the `local_exammanager_codes`
table (one row per quiz: course, quiz, room, supervisor, dates, codes) and
only ever modifies the quiz fields it is explicitly asked to program (dates,
duration, password, Safe Exam Browser settings). It implements Moodle's
Privacy API as a null provider: it does not store, export or process any
personal data tied to an identifiable Moodle user account. See
`classes/privacy/provider.php`.

## Support

Issues and contributions: <https://github.com/kamaous/moodle-local_exammanager>

---

© KAMA — distributed under the GPL v3 licence, like Moodle itself.
