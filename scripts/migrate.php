<?php

declare(strict_types=1);

/**
 * Migration runner (CLI, no Swoole required).
 *
 *   php scripts/migrate.php
 */

use App\Framework\Bootstrap\EnvironmentManager;
use App\Infrastructure\Database\DB;

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

EnvironmentManager::initialize();

$dsn = (string) EnvironmentManager::get('DB_DSN', '');
if ($dsn === '') {
    fwrite(STDERR, "DB_DSN is not set. Copy .env.example to .env first.\n");
    exit(1);
}

if (str_starts_with($dsn, 'sqlite:')) {
    $path = substr($dsn, strlen('sqlite:'));
    if ($path !== '' && $path !== ':memory:') {
        $absolute = preg_match('#^([A-Za-z]:|/)#', $path) ? $path : $base . '/' . $path;
        @mkdir(dirname($absolute), 0777, true);
    }
}

$driver = strtok($dsn, ':') ?: 'sqlite';

$pdo = new PDO(
    $dsn,
    (string) EnvironmentManager::get('DB_USERNAME', ''),
    (string) EnvironmentManager::get('DB_PASSWORD', ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
DB::initSingle($pdo);

$schemas = [
    'sqlite' => getSqliteSchemas(),
    'mysql'  => getMysqlSchemas(),
    'pgsql'  => getPgsqlSchemas(),
];

if (!isset($schemas[$driver])) {
    fwrite(STDERR, "Unsupported driver: {$driver}\n");
    exit(1);
}

foreach ($schemas[$driver] as $name => $sql) {
    DB::execute($sql);
    echo "Applied: {$name}\n";
}

echo "Migration complete (driver: {$driver}).\n";

function getSqliteSchemas(): array
{
    return [
        'notes' => <<<SQL
            CREATE TABLE IF NOT EXISTS notes (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                title      VARCHAR(200) NOT NULL,
                body       TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL,
        'users' => <<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                first_name VARCHAR(100) NOT NULL,
                last_name  VARCHAR(100) NOT NULL,
                phone      VARCHAR(20) NOT NULL UNIQUE,
                email      VARCHAR(255) UNIQUE,
                password   VARCHAR(255) NOT NULL,
                deleted_at TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL,
        'user_roles' => <<<SQL
            CREATE TABLE IF NOT EXISTS user_roles (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                role    VARCHAR(50) NOT NULL,
                UNIQUE(user_id, role)
            )
        SQL,
        'course_classes' => <<<SQL
            CREATE TABLE IF NOT EXISTS course_classes (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       VARCHAR(200) NOT NULL,
                level      VARCHAR(10) NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL,
        'class_memberships' => <<<SQL
            CREATE TABLE IF NOT EXISTS class_memberships (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                course_class_id INTEGER NOT NULL,
                user_id         INTEGER NOT NULL,
                role            VARCHAR(50) NOT NULL,
                created_at      TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE(course_class_id, user_id, role)
            )
        SQL,
        'terms' => <<<SQL
            CREATE TABLE IF NOT EXISTS terms (
                id                   INTEGER PRIMARY KEY AUTOINCREMENT,
                course_class_id      INTEGER NOT NULL,
                name                 VARCHAR(200) NOT NULL,
                start_date           TEXT NOT NULL,
                end_date             TEXT,
                is_active            INTEGER NOT NULL DEFAULT 1,
                created_by_teacher_id INTEGER NOT NULL,
                ended_by_teacher_id  INTEGER,
                ended_at             TEXT,
                closed_student_ids   TEXT,
                created_at           TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at           TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL,
        'teacher_grades' => <<<SQL
            CREATE TABLE IF NOT EXISTS teacher_grades (
                id                    INTEGER PRIMARY KEY AUTOINCREMENT,
                course_class_id       INTEGER NOT NULL,
                term_id               INTEGER NOT NULL,
                student_id            INTEGER NOT NULL,
                teacher_id            INTEGER NOT NULL,
                created_by_teacher_id INTEGER NOT NULL,
                updated_by_teacher_id INTEGER NOT NULL,
                criteria_scores       TEXT,
                score                 REAL,
                total_score           REAL,
                max_total_score       REAL,
                feedback              TEXT,
                created_at            TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at            TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE(term_id, student_id)
            )
        SQL,
        'exams' => <<<SQL
            CREATE TABLE IF NOT EXISTS exams (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                course_class_id   INTEGER NOT NULL,
                term_id           INTEGER NOT NULL,
                examiner_id       INTEGER NOT NULL,
                exam_date         TEXT NOT NULL,
                student_scores    TEXT,
                class_strengths   TEXT,
                class_improvements TEXT,
                class_suggestions TEXT,
                created_at        TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at        TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE(term_id, course_class_id, examiner_id)
            )
        SQL,
        'student_messages' => <<<SQL
            CREATE TABLE IF NOT EXISTS student_messages (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id      INTEGER NOT NULL,
                course_class_id INTEGER,
                type            VARCHAR(50) NOT NULL,
                title           VARCHAR(255) NOT NULL,
                body            TEXT NOT NULL,
                status          VARCHAR(50) NOT NULL DEFAULT 'pending',
                admin_reply     TEXT,
                admin_reply_by  INTEGER,
                reviewed_at     TEXT,
                replied_at      TEXT,
                student_seen_at TEXT,
                created_at      TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL,
        'student_message_teachers' => <<<SQL
            CREATE TABLE IF NOT EXISTS student_message_teachers (
                student_message_id INTEGER NOT NULL,
                teacher_id         INTEGER NOT NULL,
                PRIMARY KEY (student_message_id, teacher_id)
            )
        SQL,
        'teacher_feedbacks' => <<<SQL
            CREATE TABLE IF NOT EXISTS teacher_feedbacks (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                teacher_id      INTEGER NOT NULL,
                course_class_id INTEGER NOT NULL,
                admin_id        INTEGER NOT NULL,
                strengths       TEXT,
                improvements    TEXT,
                gems            TEXT,
                teacher_seen_at TEXT,
                created_at      TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL,
    ];
}

function getMysqlSchemas(): array
{
    return [
        'notes' => <<<SQL
            CREATE TABLE IF NOT EXISTS notes (
                id         BIGINT AUTO_INCREMENT PRIMARY KEY,
                title      VARCHAR(200) NOT NULL,
                body       TEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL,
        'users' => <<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id         BIGINT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name  VARCHAR(100) NOT NULL,
                phone      VARCHAR(20) NOT NULL UNIQUE,
                email      VARCHAR(255) UNIQUE,
                password   VARCHAR(255) NOT NULL,
                deleted_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        SQL,
        'user_roles' => <<<SQL
            CREATE TABLE IF NOT EXISTS user_roles (
                id      BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                role    VARCHAR(50) NOT NULL,
                UNIQUE KEY user_role_unique (user_id, role)
            )
        SQL,
        'course_classes' => <<<SQL
            CREATE TABLE IF NOT EXISTS course_classes (
                id         BIGINT AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(200) NOT NULL,
                level      VARCHAR(10) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        SQL,
        'class_memberships' => <<<SQL
            CREATE TABLE IF NOT EXISTS class_memberships (
                id              BIGINT AUTO_INCREMENT PRIMARY KEY,
                course_class_id BIGINT NOT NULL,
                user_id         BIGINT NOT NULL,
                role            VARCHAR(50) NOT NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY class_member_unique (course_class_id, user_id, role)
            )
        SQL,
        'terms' => <<<SQL
            CREATE TABLE IF NOT EXISTS terms (
                id                    BIGINT AUTO_INCREMENT PRIMARY KEY,
                course_class_id       BIGINT NOT NULL,
                name                  VARCHAR(200) NOT NULL,
                start_date            DATE NOT NULL,
                end_date              DATE NULL,
                is_active             TINYINT(1) NOT NULL DEFAULT 1,
                created_by_teacher_id BIGINT NOT NULL,
                ended_by_teacher_id   BIGINT NULL,
                ended_at              TIMESTAMP NULL,
                closed_student_ids    JSON NULL,
                created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        SQL,
        'teacher_grades' => <<<SQL
            CREATE TABLE IF NOT EXISTS teacher_grades (
                id                    BIGINT AUTO_INCREMENT PRIMARY KEY,
                course_class_id       BIGINT NOT NULL,
                term_id               BIGINT NOT NULL,
                student_id            BIGINT NOT NULL,
                teacher_id            BIGINT NOT NULL,
                created_by_teacher_id BIGINT NOT NULL,
                updated_by_teacher_id BIGINT NOT NULL,
                criteria_scores       JSON NULL,
                score                 DECIMAL(10,2) NULL,
                total_score           DECIMAL(10,2) NULL,
                max_total_score       DECIMAL(10,2) NULL,
                feedback              JSON NULL,
                created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY term_student_unique (term_id, student_id)
            )
        SQL,
        'exams' => <<<SQL
            CREATE TABLE IF NOT EXISTS exams (
                id                 BIGINT AUTO_INCREMENT PRIMARY KEY,
                course_class_id    BIGINT NOT NULL,
                term_id            BIGINT NOT NULL,
                examiner_id        BIGINT NOT NULL,
                exam_date          DATE NOT NULL,
                student_scores     JSON NULL,
                class_strengths    TEXT NULL,
                class_improvements TEXT NULL,
                class_suggestions  TEXT NULL,
                created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY exam_unique (term_id, course_class_id, examiner_id)
            )
        SQL,
        'student_messages' => <<<SQL
            CREATE TABLE IF NOT EXISTS student_messages (
                id              BIGINT AUTO_INCREMENT PRIMARY KEY,
                student_id      BIGINT NOT NULL,
                course_class_id BIGINT NULL,
                type            VARCHAR(50) NOT NULL,
                title           VARCHAR(255) NOT NULL,
                body            TEXT NOT NULL,
                status          VARCHAR(50) NOT NULL DEFAULT 'pending',
                admin_reply     TEXT NULL,
                admin_reply_by  BIGINT NULL,
                reviewed_at     TIMESTAMP NULL,
                replied_at      TIMESTAMP NULL,
                student_seen_at TIMESTAMP NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        SQL,
        'student_message_teachers' => <<<SQL
            CREATE TABLE IF NOT EXISTS student_message_teachers (
                student_message_id BIGINT NOT NULL,
                teacher_id         BIGINT NOT NULL,
                PRIMARY KEY (student_message_id, teacher_id)
            )
        SQL,
        'teacher_feedbacks' => <<<SQL
            CREATE TABLE IF NOT EXISTS teacher_feedbacks (
                id              BIGINT AUTO_INCREMENT PRIMARY KEY,
                teacher_id      BIGINT NOT NULL,
                course_class_id BIGINT NOT NULL,
                admin_id        BIGINT NOT NULL,
                strengths       TEXT NULL,
                improvements    TEXT NULL,
                gems            TEXT NULL,
                teacher_seen_at TIMESTAMP NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        SQL,
    ];
}

function getPgsqlSchemas(): array
{
    return [
        'notes' => <<<SQL
            CREATE TABLE IF NOT EXISTS notes (
                id         BIGSERIAL PRIMARY KEY,
                title      VARCHAR(200) NOT NULL,
                body       TEXT NOT NULL DEFAULT '',
                created_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL,
        'users' => <<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id         BIGSERIAL PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name  VARCHAR(100) NOT NULL,
                phone      VARCHAR(20) NOT NULL UNIQUE,
                email      VARCHAR(255) UNIQUE,
                password   VARCHAR(255) NOT NULL,
                deleted_at TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL,
        'user_roles' => <<<SQL
            CREATE TABLE IF NOT EXISTS user_roles (
                id      BIGSERIAL PRIMARY KEY,
                user_id BIGINT NOT NULL,
                role    VARCHAR(50) NOT NULL,
                UNIQUE (user_id, role)
            )
        SQL,
        'course_classes' => <<<SQL
            CREATE TABLE IF NOT EXISTS course_classes (
                id         BIGSERIAL PRIMARY KEY,
                name       VARCHAR(200) NOT NULL,
                level      VARCHAR(10) NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL,
        'class_memberships' => <<<SQL
            CREATE TABLE IF NOT EXISTS class_memberships (
                id              BIGSERIAL PRIMARY KEY,
                course_class_id BIGINT NOT NULL,
                user_id         BIGINT NOT NULL,
                role            VARCHAR(50) NOT NULL,
                created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (course_class_id, user_id, role)
            )
        SQL,
        'terms' => <<<SQL
            CREATE TABLE IF NOT EXISTS terms (
                id                    BIGSERIAL PRIMARY KEY,
                course_class_id       BIGINT NOT NULL,
                name                  VARCHAR(200) NOT NULL,
                start_date            DATE NOT NULL,
                end_date              DATE NULL,
                is_active             BOOLEAN NOT NULL DEFAULT TRUE,
                created_by_teacher_id BIGINT NOT NULL,
                ended_by_teacher_id   BIGINT NULL,
                ended_at              TIMESTAMPTZ NULL,
                closed_student_ids    JSONB NULL,
                created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at            TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL,
        'teacher_grades' => <<<SQL
            CREATE TABLE IF NOT EXISTS teacher_grades (
                id                    BIGSERIAL PRIMARY KEY,
                course_class_id       BIGINT NOT NULL,
                term_id               BIGINT NOT NULL,
                student_id            BIGINT NOT NULL,
                teacher_id            BIGINT NOT NULL,
                created_by_teacher_id BIGINT NOT NULL,
                updated_by_teacher_id BIGINT NOT NULL,
                criteria_scores       JSONB NULL,
                score                 DECIMAL(10,2) NULL,
                total_score           DECIMAL(10,2) NULL,
                max_total_score       DECIMAL(10,2) NULL,
                feedback              JSONB NULL,
                created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (term_id, student_id)
            )
        SQL,
        'exams' => <<<SQL
            CREATE TABLE IF NOT EXISTS exams (
                id                 BIGSERIAL PRIMARY KEY,
                course_class_id    BIGINT NOT NULL,
                term_id            BIGINT NOT NULL,
                examiner_id        BIGINT NOT NULL,
                exam_date          DATE NOT NULL,
                student_scores     JSONB NULL,
                class_strengths    TEXT NULL,
                class_improvements TEXT NULL,
                class_suggestions  TEXT NULL,
                created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (term_id, course_class_id, examiner_id)
            )
        SQL,
        'student_messages' => <<<SQL
            CREATE TABLE IF NOT EXISTS student_messages (
                id              BIGSERIAL PRIMARY KEY,
                student_id      BIGINT NOT NULL,
                course_class_id BIGINT NULL,
                type            VARCHAR(50) NOT NULL,
                title           VARCHAR(255) NOT NULL,
                body            TEXT NOT NULL,
                status          VARCHAR(50) NOT NULL DEFAULT 'pending',
                admin_reply     TEXT NULL,
                admin_reply_by  BIGINT NULL,
                reviewed_at     TIMESTAMPTZ NULL,
                replied_at      TIMESTAMPTZ NULL,
                student_seen_at TIMESTAMPTZ NULL,
                created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL,
        'student_message_teachers' => <<<SQL
            CREATE TABLE IF NOT EXISTS student_message_teachers (
                student_message_id BIGINT NOT NULL,
                teacher_id         BIGINT NOT NULL,
                PRIMARY KEY (student_message_id, teacher_id)
            )
        SQL,
        'teacher_feedbacks' => <<<SQL
            CREATE TABLE IF NOT EXISTS teacher_feedbacks (
                id              BIGSERIAL PRIMARY KEY,
                teacher_id      BIGINT NOT NULL,
                course_class_id BIGINT NOT NULL,
                admin_id        BIGINT NOT NULL,
                strengths       TEXT NULL,
                improvements    TEXT NULL,
                gems            TEXT NULL,
                teacher_seen_at TIMESTAMPTZ NULL,
                created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL,
    ];
}
