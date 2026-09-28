-- ACMS — قاعدة بيانات الاختبارات (مرة واحدة لكل مطوّر)
-- نفّذ في phpMyAdmin أو: mysql -u root < scripts/setup-test-database.sql

CREATE DATABASE IF NOT EXISTS acms_testing
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
