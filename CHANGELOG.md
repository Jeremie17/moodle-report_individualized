# Changelog — report_individualized

All notable changes to this plugin will be documented in this file.
This project adheres to [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [0.4.0] - 2026-05-12

### Added
- AJAX filters (student, course, category, date range) with sticky full-width filter bar
- Two `flexible_table` instances per course section: Resources and Activities
- Section and course title prefixes in the report
- Workshop support: two rows per activity (submission + peer assessment), with a fixed
  10-minute duration for the assessment row
- H5P support: completion via `h5pactivity_attempts.completion`, closing trace via
  `action = 'received'` in `logstore_standard_log`
- Summary badges per section and per course: estimated duration, student declared
  duration, completion rate, average grade /20, resources viewed
- Per-table hover PDF export buttons and a global sticky PDF export button,
  synchronized via `updatePdfUrl()`
- Admin-configurable column visibility via `admin_setting_configcheckbox` in
  `settings.php`
- Category filter with full path display and configurable depth limit (`categorydepth`)
- Multi-student PDF export (`userid=0` loops over all enrolled students)
- Full English and French language files (`lang/en/` and `lang/fr/`)
- PHPUnit test suite: 8 suites covering `duration_util`, `date_util`,
  `completion_util`, `summary_util`, `view_stats_util`, `feedback_util`,
  `category_util`, `workshop_util`
- CI via GitHub Actions: PHP 8.2–8.3 on Moodle 5.1, PHP 8.3–8.4 on Moodle 5.2,
  PostgreSQL 16

### Changed
- Migrated rendering logic to `classes/output/report_fragment.php` for AJAX fragment
  support via `core/fragment`
- Migrated filter option fetching to `classes/external/get_filter_options.php`
  (external function, `core/ajax`)
- Business logic split into dedicated util classes under `classes/util/`

---

## [0.3.0] - 2026-04-17

### Added
- Date range filters (From / To) applied to log queries
- Sticky filter bar (CSS `position: sticky`)
- Per-section summary pills (completion rate, average grade, resources viewed)
- `category_util`: category path display under course title

### Changed
- Replaced raw SQL student list with `get_role_users()` scoped to course context
- `feedback_util` split from `lib.php` into dedicated class

---

## [0.2.0] - 2026-04-13

### Added
- Two tables per course section: Resources and Activities
- Dynamic columns: admin can show/hide each column from settings page
- Workshop two-row rendering (submission + assessment) via `workshop_util`
- Specialized feedback per module: assign, quiz, workshop, H5P, fallback gradebook
- PDF export via TCPDF (Moodle bundled), landscape, per-student

### Changed
- Moved from single table to `flexible_table` for each resource/activity type

---

## [0.1.0] - 2026-04-08

### Added
- Initial plugin scaffold: `version.php`, `settings.php`, `lib.php`, `index.php`,
  `export_pdf.php`, `db/access.php`
- Basic student and course filters (page reload)
- Single activity table per course
- Capability `report/individualized:view`
- English and French language files