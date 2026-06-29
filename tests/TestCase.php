<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\Database\DB;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (extension_loaded('pdo_sqlite')) {
            $pdo = new PDO('sqlite::memory:', '', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } elseif (($dsn = getenv('TEST_DB_DSN') ?: '') !== '') {
            $pdo = new PDO(
                $dsn,
                (string) (getenv('TEST_DB_USER') ?: ''),
                (string) (getenv('TEST_DB_PASS') ?: ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } else {
            $this->markTestSkipped('Requires pdo_sqlite or TEST_DB_DSN for integration tests.');
        }

        DB::initSingle($pdo);
        $this->runMigrations();
    }

    private function runMigrations(): void
    {
        $driver = DB::get()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $this->runMysqlMigrations();
            return;
        }

        $schemas = [
            'users' => "CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT, first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) NOT NULL UNIQUE,
                email VARCHAR(255) UNIQUE, password VARCHAR(255) NOT NULL, deleted_at TEXT,
                created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now'))
            )",
            'user_roles' => 'CREATE TABLE user_roles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, role VARCHAR(50) NOT NULL, UNIQUE(user_id, role))',
            'course_classes' => "CREATE TABLE course_classes (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(200) NOT NULL, level VARCHAR(10) NOT NULL, created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now')))",
            'class_memberships' => "CREATE TABLE class_memberships (id INTEGER PRIMARY KEY AUTOINCREMENT, course_class_id INTEGER NOT NULL, user_id INTEGER NOT NULL, role VARCHAR(50) NOT NULL, created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now')), UNIQUE(course_class_id, user_id, role))",
            'terms' => "CREATE TABLE terms (id INTEGER PRIMARY KEY AUTOINCREMENT, course_class_id INTEGER NOT NULL, name VARCHAR(200) NOT NULL, start_date TEXT NOT NULL, end_date TEXT, is_active INTEGER NOT NULL DEFAULT 1, created_by_teacher_id INTEGER NOT NULL, ended_by_teacher_id INTEGER, ended_at TEXT, closed_student_ids TEXT, created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now')))",
            'teacher_grades' => "CREATE TABLE teacher_grades (id INTEGER PRIMARY KEY AUTOINCREMENT, course_class_id INTEGER NOT NULL, term_id INTEGER NOT NULL, student_id INTEGER NOT NULL, teacher_id INTEGER NOT NULL, created_by_teacher_id INTEGER NOT NULL, updated_by_teacher_id INTEGER NOT NULL, criteria_scores TEXT, score REAL, total_score REAL, max_total_score REAL, feedback TEXT, created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now')), UNIQUE(term_id, student_id))",
            'exams' => "CREATE TABLE exams (id INTEGER PRIMARY KEY AUTOINCREMENT, course_class_id INTEGER NOT NULL, term_id INTEGER NOT NULL, examiner_id INTEGER NOT NULL, exam_date TEXT NOT NULL, student_scores TEXT, class_strengths TEXT, class_improvements TEXT, class_suggestions TEXT, created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now')), UNIQUE(term_id, course_class_id, examiner_id))",
            'student_messages' => "CREATE TABLE student_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INTEGER NOT NULL, course_class_id INTEGER, type VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, body TEXT NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending', admin_reply TEXT, admin_reply_by INTEGER, reviewed_at TEXT, replied_at TEXT, student_seen_at TEXT, created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now')))",
            'student_message_teachers' => 'CREATE TABLE student_message_teachers (student_message_id INTEGER NOT NULL, teacher_id INTEGER NOT NULL, PRIMARY KEY (student_message_id, teacher_id))',
            'teacher_feedbacks' => "CREATE TABLE teacher_feedbacks (id INTEGER PRIMARY KEY AUTOINCREMENT, teacher_id INTEGER NOT NULL, course_class_id INTEGER NOT NULL, admin_id INTEGER NOT NULL, strengths TEXT, improvements TEXT, gems TEXT, teacher_seen_at TEXT, created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now')))",
            'refresh_tokens' => "CREATE TABLE refresh_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, token_hash VARCHAR(64) NOT NULL UNIQUE, active_role VARCHAR(50) NOT NULL, expires_at TEXT NOT NULL, revoked_at TEXT, created_at TEXT DEFAULT (datetime('now')))",
        ];
        foreach ($schemas as $sql) {
            DB::execute($sql);
        }
    }

    private function runMysqlMigrations(): void
    {
        $tables = [
            'refresh_tokens', 'student_message_teachers', 'teacher_feedbacks', 'student_messages', 'exams',
            'teacher_grades', 'terms', 'class_memberships', 'course_classes', 'user_roles', 'users',
        ];
        DB::execute('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            DB::execute("DROP TABLE IF EXISTS {$table}");
        }
        DB::execute('SET FOREIGN_KEY_CHECKS=1');

        $schemas = [
            "CREATE TABLE users (
                id BIGINT AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) NOT NULL UNIQUE,
                email VARCHAR(255) UNIQUE, password VARCHAR(255) NOT NULL, deleted_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            'CREATE TABLE user_roles (
                id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id BIGINT NOT NULL, role VARCHAR(50) NOT NULL,
                UNIQUE KEY user_role_unique (user_id, role)
            )',
            "CREATE TABLE course_classes (
                id BIGINT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, level VARCHAR(10) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            'CREATE TABLE class_memberships (
                id BIGINT AUTO_INCREMENT PRIMARY KEY, course_class_id BIGINT NOT NULL, user_id BIGINT NOT NULL,
                role VARCHAR(50) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY class_member_unique (course_class_id, user_id, role)
            )',
            "CREATE TABLE terms (
                id BIGINT AUTO_INCREMENT PRIMARY KEY, course_class_id BIGINT NOT NULL, name VARCHAR(200) NOT NULL,
                start_date DATE NOT NULL, end_date DATE NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by_teacher_id BIGINT NOT NULL, ended_by_teacher_id BIGINT NULL, ended_at TIMESTAMP NULL,
                closed_student_ids JSON NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            "CREATE TABLE teacher_grades (
                id BIGINT AUTO_INCREMENT PRIMARY KEY, course_class_id BIGINT NOT NULL, term_id BIGINT NOT NULL,
                student_id BIGINT NOT NULL, teacher_id BIGINT NOT NULL, created_by_teacher_id BIGINT NOT NULL,
                updated_by_teacher_id BIGINT NOT NULL, criteria_scores JSON NULL, score DECIMAL(10,2) NULL,
                total_score DECIMAL(10,2) NULL, max_total_score DECIMAL(10,2) NULL, feedback JSON NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY term_student_unique (term_id, student_id)
            )",
            "CREATE TABLE exams (
                id BIGINT AUTO_INCREMENT PRIMARY KEY, course_class_id BIGINT NOT NULL, term_id BIGINT NOT NULL,
                examiner_id BIGINT NOT NULL, exam_date DATE NOT NULL, student_scores JSON NULL,
                class_strengths TEXT NULL, class_improvements TEXT NULL, class_suggestions TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY exam_unique (term_id, course_class_id, examiner_id)
            )",
            "CREATE TABLE student_messages (
                id BIGINT AUTO_INCREMENT PRIMARY KEY, student_id BIGINT NOT NULL, course_class_id BIGINT NULL,
                type VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, body TEXT NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending', admin_reply TEXT NULL, admin_reply_by BIGINT NULL,
                reviewed_at TIMESTAMP NULL, replied_at TIMESTAMP NULL, student_seen_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            'CREATE TABLE student_message_teachers (
                student_message_id BIGINT NOT NULL, teacher_id BIGINT NOT NULL,
                PRIMARY KEY (student_message_id, teacher_id)
            )',
            "CREATE TABLE teacher_feedbacks (
                id BIGINT AUTO_INCREMENT PRIMARY KEY, teacher_id BIGINT NOT NULL, course_class_id BIGINT NOT NULL,
                admin_id BIGINT NOT NULL, strengths TEXT NULL, improvements TEXT NULL, gems TEXT NULL,
                teacher_seen_at TIMESTAMP NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            "CREATE TABLE refresh_tokens (
                id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id BIGINT NOT NULL, token_hash VARCHAR(64) NOT NULL UNIQUE,
                active_role VARCHAR(50) NOT NULL, expires_at TIMESTAMP NOT NULL, revoked_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_refresh_tokens_user_id (user_id)
            )",
        ];
        foreach ($schemas as $sql) {
            DB::execute($sql);
        }
    }

    protected function createUser(string $role, string $phone, string $firstName = 'Test', string $lastName = 'User'): int
    {
        $users = new \App\Shared\Repositories\UserRepository();
        $id = $users->create($firstName, $lastName, $phone, password_hash('secret123', PASSWORD_BCRYPT));
        $users->addRole($id, $role);
        return $id;
    }
}
