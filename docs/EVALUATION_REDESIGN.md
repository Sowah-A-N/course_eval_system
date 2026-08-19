# Evaluation Redesign — Remove Tokenization + Two-Tier Questions

Combined plan for two changes that turn out to be one:

1. **Replace the token system** with a computed-eligibility + completion-record model.
2. **Split questions** into *per-course* and *once-per-semester (administrative)*.

They merge cleanly: once tokens are gone, "administrative = one completion per
student per semester" is just another row shape, not a second token type.

---

## 1. Why tokens are being removed

Students **log in** to evaluate, so a token (possession = permission, meant for
anonymous non-logged-in surveys) is doing almost nothing here. Confirmed from the
current generator (`admin/tokens/generate.php`): a student may evaluate every
course where

```
course.department_id = student.department_id  AND  course.level_id = student.level_id
```

for the active year + semester. The tokens are just a **materialized copy of that
join** (students × their dept/level courses), plus a generate/consume workflow.

The only job tokens genuinely do is **anonymity** (unlink answers from student),
and that is preserved — more strongly — below.

**Assumptions (confirmed):** no need for no-login / emailed evaluation; it is
acceptable that the system still knows *who participated* (for response-rate
reporting), just never *what they answered*.

---

## 2. Target data model

### `evaluations` — anonymous answer container (repurposed)
| column | change | notes |
|---|---|---|
| evaluation_id | keep | PK |
| scope | **add** `ENUM('course','administrative')` | what kind of evaluation |
| course_id | **make NULL-able** | set for course evals, NULL for administrative |
| class_id | **add** `INT NULL` | admin evals only — attributes the advisor rating (→ `classes.advisor_user_id`) |
| department_id | **add** `INT NULL` | admin evals — allows per-department institutional breakdowns |
| academic_year_id, semester_id | keep | period |
| evaluation_date | keep | consider **date-only** to reduce timestamp correlation |
| token | **stop writing** (leave column, drop in cleanup) | no student link stored, ever |

No `student_user_id` — never present on answers.

### `responses` — unchanged
`id, evaluation_id, question_id, response_value` (TINYINT after migration 005).

### `evaluation_completions` — NEW (identity / eligibility, no answers)
| column | notes |
|---|---|
| completion_id | PK |
| student_user_id | who participated |
| scope | 'course' or 'administrative' |
| course_id | course id for course scope; **0 for administrative** (sentinel) |
| academic_year_id, semester_id | period |
| completed_at | timestamp |

**`UNIQUE(student_user_id, course_id, academic_year_id, semester_id)`** — enforces
one submission per course, and one administrative submission (course_id = 0) per
semester. The `0` sentinel is used because MariaDB 5.5 allows multiple NULLs in a
unique index; `scope` disambiguates for readability.

### Retired
- `evaluation_tokens` — left dormant, dropped in the cleanup phase.
- `admin/tokens/generate.php` — removed (eligibility is now automatic).
- `admin/tokens/view.php` — **repurposed** into a completion / response-rate view.

---

## 3. Anonymity (equal-or-better than today)

- Answers (`evaluations` + `responses`) carry **no student reference at all** — there
  is nothing to null, so the audit's "student_user_id never nulled" leak disappears.
- `evaluation_completions` knows *who participated* but holds **no answers**, and
  there is **no column linking a completion row to an evaluation row**.
- Residual (same as today): someone with raw DB access could weakly correlate a
  completion and an evaluation by timestamp — mitigated by storing date-only on
  `evaluations.evaluation_date`.

---

## 4. Submission flow (no token)

**`available_courses.php`**
- Load active period.
- Eligible course evals = `courses WHERE department_id = :dept AND level_id = :level`
  (the student's own), **minus** rows already in `evaluation_completions`.
- Administrative eval = one card, shown as pending unless a completion with
  `course_id = 0` exists for this student + period.

**`submit.php`** (course eval and administrative eval share one page, differing by `scope`)
- GET: student chooses a course (or the administrative evaluation). Server verifies
  eligibility (course is in their dept/level, active period, not already completed) —
  this replaces token validation, and there is no IDOR because eligibility is checked
  against the student's own enrollment. Render only the questions whose `scope`
  matches.
- POST: CSRF + re-verify, then one transaction (with the execute-checked, roll-back
  pattern from fix I1):
  1. `INSERT evaluation_completions (...)` — its UNIQUE key is the double-submit
     guard; a duplicate-key error means "already submitted" → roll back, friendly message.
  2. `INSERT evaluations (scope, course_id|NULL, class_id, department_id, period)`.
  3. `INSERT responses (...)`.
- No token in the URL or session.

**`history.php`** — the student's completed list comes from `evaluation_completions`
(participation). Answers remain anonymous and are not shown back.

---

## 5. Reporting

- **Course reports** — filter `evaluations.scope = 'course'` (the existing
  `view_course_evaluation_stats` already inner-joins evaluations→courses on
  course_id, so administrative rows drop out naturally). Also filter to course-scope
  questions.
- **Institutional report (new)** — aggregate `scope = 'administrative'` by question
  (Registry, Accounts, Library, etc.), university-wide and once-per-student; the
  advisor question grouped by `class_id → classes.advisor_user_id`. Optional
  per-department breakdown via `evaluations.department_id`.
- **Completion / response-rate view** — from `evaluation_completions` vs the eligible
  population; replaces the old token dashboard.

---

## 6. Migrations (fresh start — no historical answer migration)

Order matters; apply on staging first (production is MariaDB 5.5.68):

1. **005** (already written) — `response_value` TINYINT, `UNIQUE(evaluations.token)`,
   token dedupe. *Note:* the `UNIQUE(evaluations.token)` becomes irrelevant once we
   stop writing tokens — safe to skip that one line if 005 hasn't been run yet.
2. **006** (already written) — `evaluation_questions.scope`.
3. **007** (new) —
   - `evaluations`: add `scope`, `class_id`, `department_id`; make `course_id` NULL-able.
   - create `evaluation_completions` with the UNIQUE key above.
   - `evaluation_tokens` left in place but unused.
4. **008 (cleanup, later)** — after the new flow is verified in production: drop
   `evaluation_tokens`, drop `evaluations.token`, remove `admin/tokens/generate.php`.

Existing historical evaluations stay as course-scope rows and keep working in course
reports.

---

## 7. Phasing (revised & combined)

- **Phase 1 — DONE** (commit c85c8f6, migration 006): question `scope` + admin UI toggle.
- **Phase 2 — schema**: migration 007 (evaluations columns + `evaluation_completions`).
- **Phase 3 — submission flow**: rewrite `available_courses.php`, `submit.php`,
  `history.php`; add the administrative evaluation; eligibility from enrollment;
  write completion + anonymous evaluation.
- **Phase 4 — reporting**: course reports filter `scope='course'`; new institutional
  report (services + advisor-by-class); repurpose `admin/tokens/view.php` into a
  response-rate view; remove the "generate tokens" step.
- **Phase 5 — cleanup**: migration 008 drops the token table/column and deletes the
  generator.

Each phase is committed and verified before the next.

---

## 8. Open items / risks

- **Eligibility parity**: the rule above (`dept + level`, active period) matches the
  current generator exactly; verify once more against `available_courses.php` when
  rewriting it.
- **`courses.semester_id`** is a `tinyint` (looks like a 1/2 value, not the
  `semesters` PK) and is *not* used in the current eligibility rule — confirm we
  don't need to filter courses by semester before relying on that.
- **Admin workflow change**: admins lose the manual "generate tokens" step (students
  simply see their eligible evaluations). This is a deliberate simplification — flag
  to stakeholders.
- **Anonymity timestamp correlation**: optional date-only mitigation on
  `evaluation_date`.
- **Migration ordering**: 005 → 006 → 007; none are auto-applied (they are gitignored
  `*.sql`, force-added individually) and must be run per environment.
