# report_individualized

Individualized student report plugin for Moodle 5.

Generates a detailed per-student report of their activity within a course — exportable as PDF — intended for submission to OPCO training funding organizations.

Developed by Ifrass (2026–2027).

---

## Requirements

| Dependency | Version |
|---|---|
| Moodle | 5.1 or higher (build 2025100600+) |
| PHP | 8.2 or higher |
| Node.js | 22 (runtime) — see [Known limitations](#known-limitations) for AMD compilation |

---

## Installation

1. Copy the `report/individualized/` folder into your Moodle installation under `{moodle_root}/report/individualized/`.
2. Log in as administrator and go to **Site administration → Notifications** to trigger the automatic installation.
3. Confirm the plugin installation.
4. Clear all caches: **Site administration → Development → Purge all caches**.

---

## Custom fields (required setup)

This plugin reads two custom fields that must be created by an administrator before the plugin can display all columns.

Go to **Site administration → Courses → Course custom fields** and create a field category (e.g. "Formation"), then add the following fields:

### `modalite` — Pedagogical modality

| Setting | Value |
|---|---|
| Field type | Dropdown menu |
| Short name | `modalite` |
| Options | One option per line, e.g.: `Recherche personnelle`, `Débat`, `Évaluation par les pairs`, `Travail de groupe`, `Synthèse de document`, `Auto-évaluation` |

This field is set per-activity by the teacher in the activity settings under **Formation → Modalité pédagogique**.

### `duree_estimee` — Estimated duration

| Setting | Value |
|---|---|
| Field type | Short text |
| Short name | `duree_estimee` |

The teacher enters a duration in **minutes** (integer). Example: `30` for 30 minutes.

This field is set per-activity by the teacher in the activity settings under **Formation → Durée estimée**.

### TIME feedback — Student declared duration

To allow students to declare how long they spent on a section, the teacher creates a **Feedback** activity with an **ID number** starting with `TIME` (case-insensitive, leading spaces ignored). The student's first numeric response to any question in that feedback is used as the declared duration in minutes.

---

## Features

- Filter by student, course, category, and date range
- Report organized by course section
- Two tables per section: **Resources** and **Activities**
- Summary badges per section and per course (estimated duration, student declared duration, completion rate, average grade, resources viewed)
- Specialized support for **Workshop** (2 rows: submission + peer assessment, with 10-minute fixed duration for the assessment row)
- Specialized support for **H5P** activities (Flashcards, Interactive Video with quizzes) — see [H5P support](#h5p-support) below
- Specialized feedback per activity type: assign, quiz, workshop, H5P, fallback gradebook
- Dynamic columns: the administrator can show/hide columns from **Site administration → Reports → Individualized report settings**
- PDF export: full report, per-course, per-section, per-table (resources or activities)
- AJAX filters: no page reload when changing student, course, or date range 
- Server-side pagination: loads complete sections at a time, with a "+" button to load more
- Full English and French language support

---

## H5P support

All H5P activities appear in the **Activities** table regardless of their content type.

### What the report can display

| H5P content type | Completion | Closing trace | Notes |
|---|---|---|---|
| Flashcard | ✓ when all cards answered | ✓ | Fully supported |
| Interactive Video with quizzes | ✓ after submission | ✓ | Student must click **Submit answers** at the end |
| Simple video (no quizzes) | ✗ always | — always | See limitation below |

### Opening and closing dates

H5P activities do not have native availability settings. The plugin reads dates from **access restrictions** configured on the activity. If no restriction is configured, the fallback chain applies: grade timestamp → student submission trace.

### Known H5P limitation

Simple H5P videos (YouTube or file with no integrated quizzes) do not send any xAPI statement to Moodle when played. Completion will always show ✗ and the closing trace will always be empty (—), regardless of how much of the video the student watched. This is a limitation of H5P itself.

**Recommendation**: use H5P Interactive Video with at least one integrated quiz, and remind students to click **Submit answers** at the end for their progress to be recorded.

---

## Permissions

| Capability | Default role |
|---|---|
| `report/individualized:view` | Manager, Teacher, Non-editing teacher |

To grant access to other roles, go to **Site administration → Users → Permissions → Define roles**.

---

## Deployment

### Development mode (local)

During development, add the following line to `config.php` to serve AMD modules directly without compilation:

```php
$CFG->cachejs = false;
```

### Production deployment

AMD compilation via Moodle's Gruntfile is currently blocked by a compatibility issue between Moodle 5.1.x and Node.js 22. Build files are pre-compiled using terser as a workaround until Moodle resolves this upstream. See [Known limitations](#known-limitations) for details.

---

## Running tests

PHPUnit tests are included for the core utility classes. To run them locally:

```bash
# Initialize PHPUnit environment (once)
php admin/tool/phpunit/cli/init.php

# Run all plugin tests
cd report/individualized
php /path/to/moodle/vendor/bin/phpunit \
    --bootstrap /path/to/moodle/public/lib/phpunit/bootstrap.php \
    --testdox
```

Tests cover: `duration_util`, `date_util`, `completion_util`, `summary_util` , `view_stats_util`, `feedback_util`, `category_util`, `workshop_util`.

---

## File structure

```
report/individualized/
├── version.php                          Plugin metadata and version
├── settings.php                         Admin settings (visible columns)
├── lib.php                              Moodle hooks + fragment callback shell
├── index.php                            Main report page
├── export_pdf.php                       PDF export
├── styles.css                           Custom CSS
├── phpunit.xml                          PHPUnit test suite configuration
├── amd/
│   ├── src/filters.js                   AMD module (AJAX filters, source)
│   └── build/filters.min.js            AMD module (compiled — required in production)
├── classes/
│   ├── external/
│   │   └── get_filter_options.php       External function (AJAX filter options)
│   ├── output/
│   │   └── report_fragment.php          Fragment renderer (AJAX report content)
│   └── util/
│       ├── date_util.php                Date formatting and retrieval
│       ├── view_stats_util.php          Consultation statistics and activity labels
│       ├── completion_util.php          Completion icons and status
│       ├── duration_util.php            Duration formatting and retrieval
│       ├── feedback_util.php            Feedback retrieval per module type
│       ├── workshop_util.php            Workshop-specific data (2 rows)
│       ├── summary_util.php             Summary metrics (completion rate, avg grade…)
│       └── category_util.php            Category path and filtering
├── db/
│   ├── access.php                       Capability definitions
│   └── services.php                     External function declarations
├── tests/
│   └── util/                            PHPUnit test files
└── lang/
    ├── en/report_individualized.php     English strings
    └── fr/report_individualized.php     French strings
```

---

## Known limitations

- Resource engagement cannot be verified beyond the `viewed` log event. Moodle does not track time spent on files or external URLs.
- Simple H5P videos (no integrated quizzes) do not generate xAPI statements — completion and closing trace are unavailable for this content type.
- AMD compilation via Moodle's Gruntfile is currently blocked by a compatibility issue between Moodle 5.1.x and Node.js 22. Build files are pre-compiled using terser as a workaround until Moodle resolves this upstream.

---

## License

GNU General Public License v3 or later — see [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)