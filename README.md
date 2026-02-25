# AutoProctor - Moodle Quiz Access Rule Plugin

AutoProctor is a proctoring integration plugin for Moodle quizzes that monitors students during online exams to prevent cheating. It integrates with the external AutoProctor service (autoproctor.co) to track students via their camera, microphone, and screen.

## Features

### Configurable Tracking Options (per quiz)

Teachers can enable/disable these monitoring features:

| Category | Options |
|----------|---------|
| **Activity** | Audio detection, Number of humans detection, Tab switch detection |
| **Camera** | Test taker photo at start, Random photos, Camera preview |
| **Screen** | Capture switched tab, Record session, Detect multiple screens, Force fullscreen |

### Consent & Preflight Check

- Before starting a quiz, students must consent to granting access to screen, microphone, and camera
- A preflight check form is displayed before the quiz attempt begins

### Session Management

- Creates proctoring sessions linked to quiz attempts
- Sessions are stored in `quizaccess_autoproctor_sessions` table
- Each session has a unique `test_attempt_id` (HMAC-SHA256 hashed for authentication)

### iframe-based Quiz Experience

- After proctoring starts, the quiz loads in an iframe while AutoProctor monitors the main window
- Handles navigation between `attempt.php`, `summary.php`, and `review.php`
- Automatically stops proctoring when quiz is submitted

### Reporting

- Teachers/managers can view proctoring reports via a button on the quiz page
- Reports are loaded from AutoProctor's external service
- Capability `quizaccess/autoproctor:viewreport` controls access (teachers, editing teachers, managers)

## File Structure

| File | Purpose |
|------|---------|
| `rule.php` | Main plugin class - settings form, preflight check, validation |
| `amd/src/proctoring.js` | Frontend - initializes AutoProctor SDK, handles iframe navigation, session creation |
| `create_session.php` | API endpoint to create proctoring sessions in DB |
| `loadreport.php` | Loads proctoring report view |
| `settings.php` | Admin settings for client_id, client_secret, default enable |
| `db/install.xml` | Database schema definition |
| `db/upgrade.php` | Database migration scripts |
| `db/access.php` | Capability definitions |
| `lang/en/quizaccess_autoproctor.php` | Language strings |
| `templates/` | Mustache templates for loader and report views |

## Database Tables

### `quizaccess_autoproctor`

Quiz-level settings storage.

| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| quiz_id | int | Foreign key to quiz |
| proctoring_enabled | int | Whether proctoring is enabled (0/1) |
| tracking_options | text | JSON of enabled tracking options |
| timecreated | int | Timestamp |
| timemodified | int | Timestamp |

### `quizaccess_autoproctor_sessions`

Per-attempt proctoring session storage.

| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| quiz_id | int | Foreign key to quiz |
| quiz_attempt_id | int | Foreign key to quiz_attempts |
| test_attempt_id | char | Unique AutoProctor attempt identifier |
| tracking_options | text | JSON of tracking options for this session |
| started_at | int | Session start timestamp |
| timecreated | int | Timestamp |
| timemodified | int | Timestamp |

## Installation

1. Copy the `autoproctor` folder to `/mod/quiz/accessrule/`
2. Visit Site Administration > Notifications to install the plugin
3. Configure the plugin at Site Administration > Plugins > Activity modules > Quiz > AutoProctor Integration

## Configuration

### Admin Settings

Navigate to **Site Administration > Plugins > Activity modules > Quiz > AutoProctor Integration**

- **Client ID**: Your AutoProctor Client ID from the dashboard
- **Client Secret**: Your AutoProctor Client Secret from the dashboard
- **Enable by default**: Whether to enable AutoProctor for all new quizzes by default

### Per-Quiz Settings

When editing a quiz, expand the **AutoProctor Settings** section to:

1. Enable/disable AutoProctor for the quiz
2. Configure individual tracking options

## External Dependencies

- AutoProctor SDK (loaded from CDN)
- CryptoJS for HMAC-SHA256 hashing
- Requires credentials from [autoproctor.co](https://autoproctor.co)

## Requirements

- Moodle 4.1+ (version 2022112800)
- `theme_boost` (or compatible theme)

## License

GNU GPL v3 or later - http://www.gnu.org/copyleft/gpl.html

## Author

AutoProctor (autoproctor.co)
