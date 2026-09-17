# Moodle Plugins Directory — English description

Reference text for the "Description" field of the Moodle Plugins Directory
submission form (addresses review issue #14, "Provide an English Marketplace
description"). This file is not part of the installable plugin package; it
exists only as a source of truth in this repository so the wording is
tracked and can be reused/updated. **Nobody has submitted this to the
Moodle Plugins Directory on the user's behalf — copy it into the submission
form manually.**

---

## Short description (summary field, if separate)

Bulk-schedule exam quizzes from a spreadsheet: dates, duration, access
codes and Safe Exam Browser settings for dozens of quizzes in one upload.

## Full description

Soft manager turns exam scheduling from a one-quiz-at-a-time chore into a
single spreadsheet upload. Upload a CSV or Excel planning file, preview and
correct it in an editable table, then apply it in one click to program the
opening/closing dates, duration, access code and Safe Exam Browser exit
code of every quiz listed — across as many courses as the file contains.

**Exam programming**
- Drag-and-drop CSV/XLSX/XLS upload, with a downloadable template.
- Editable preview table with live validation before anything is saved.
- Quiz picker limited to the course's own active quizzes (no duplicates,
  no recycle-bin entries).
- Per-row control over the access code and the Safe Exam Browser exit code:
  generate, keep, or disable.
- Optional code sharing: quizzes in the same course opening at the same
  date/time can share one access code and one SEB code, or each get their
  own.
- A post-programming lock keeps a finished batch read-only until a new
  file is uploaded, preventing accidental re-programming.
- Automatically un-hides the quiz activity and its section if needed.
- CSV, Excel and log exports of every programmed batch.

**Dashboard**
Programmed exams, rooms and supervisors in use, exams scheduled today,
total questions across every scheduled quiz, students enrolled in the
targeted courses, average participants and participation rate per test,
and average attempt duration — with an analytics chart.

**Also included**
- A bulk activity/section availability-restriction planner (by date, grade,
  user profile field, or a combined set), with its own CSV/XLSX bulk mode.
- An interactive calendar of every scheduled exam with room/supervisor
  conflict detection.
- A filterable, paginated scheduling history (access codes are never shown
  in this view).
- A room/supervisor conflict report.
- A quick-access link in Moodle's primary navigation for managers and
  administrators.

The plugin only ever touches the quiz fields it is explicitly asked to
program, keeps its own scheduling records in a dedicated database table,
and implements Moodle's Privacy API as a null provider — it does not store
or process any personal data tied to a Moodle user account.

## Suggested tags / categories

Quiz, Assessment, Scheduling, Bulk actions, Safe Exam Browser, Reporting

## Suggested maturity / support text

Actively maintained. Issues and contributions:
https://github.com/kamaous/moodle-local_exammanager
