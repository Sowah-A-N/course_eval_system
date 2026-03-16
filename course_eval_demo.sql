-- =====================================================
-- COURSE EVALUATION SYSTEM - DEMO DATABASE
-- SAFE FOR PUBLIC VERSION CONTROL
-- =====================================================
-- All personal or sensitive data has been replaced
-- with fictional demo values.
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS course_evaluation_demo;
USE course_evaluation_demo;

-- =====================================================
-- ACADEMIC YEARS
-- =====================================================

CREATE TABLE academic_year (
  academic_year_id INT AUTO_INCREMENT PRIMARY KEY,
  start_year INT NOT NULL,
  end_year INT GENERATED ALWAYS AS (start_year + 1) STORED,
  year_label VARCHAR(9) GENERATED ALWAYS AS (CONCAT(start_year,'/',end_year)) STORED,
  is_active TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO academic_year(start_year,is_active) VALUES
(2024,1),
(2023,0);

-- =====================================================
-- SEMESTERS
-- =====================================================

CREATE TABLE semesters (
  semester_id INT AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT NOT NULL,
  semester_name ENUM('First','Second') NOT NULL,
  semester_value TINYINT NOT NULL,
  is_active TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO semesters(academic_year_id,semester_name,semester_value,is_active)
VALUES
(1,'First',1,0),
(1,'Second',2,1);

-- =====================================================
-- DEPARTMENTS
-- =====================================================

CREATE TABLE department (
  t_id INT AUTO_INCREMENT PRIMARY KEY,
  hod_id INT DEFAULT 0,
  dep_name VARCHAR(100),
  dep_code VARCHAR(50) UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO department(dep_name,dep_code) VALUES
('Information Technology','ICT'),
('Electrical Engineering','EEE'),
('Marine Engineering','MEE'),
('Transport Studies','DOT');

-- =====================================================
-- LEVELS
-- =====================================================

CREATE TABLE level(
  t_id INT AUTO_INCREMENT PRIMARY KEY,
  level_name VARCHAR(50),
  level_number INT UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO level(level_name,level_number) VALUES
('Level 100',100),
('Level 200',200),
('Level 300',300),
('Level 400',400);

-- =====================================================
-- PROGRAMMES
-- =====================================================

CREATE TABLE programme(
  t_id INT AUTO_INCREMENT PRIMARY KEY,
  prog_code VARCHAR(20) UNIQUE,
  prog_name VARCHAR(100),
  department_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO programme(prog_code,prog_name,department_id) VALUES
('BIT','BSc Information Technology',1),
('BCS','BSc Computer Science',1),
('BME','BSc Marine Engineering',3);

-- =====================================================
-- CLASSES
-- =====================================================

CREATE TABLE classes(
  t_id INT AUTO_INCREMENT PRIMARY KEY,
  class_name VARCHAR(50) UNIQUE,
  department_id INT,
  year_of_completion INT,
  programme_id INT,
  level_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO classes(class_name,department_id,year_of_completion,programme_id,level_id)
VALUES
('BIT28',1,2028,1,1),
('BIT27',1,2027,1,2);

-- =====================================================
-- COURSES
-- =====================================================

CREATE TABLE courses(
 id INT AUTO_INCREMENT PRIMARY KEY,
 course_code VARCHAR(50) UNIQUE,
 name VARCHAR(255),
 department_id INT,
 level_id INT,
 semester_id INT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO courses(course_code,name,department_id,level_id,semester_id) VALUES
('BINT108','Programming Fundamentals',1,1,1),
('BINT205','Database Systems',1,2,2),
('BINT210','Web Development',1,2,2);

-- =====================================================
-- ROLES
-- =====================================================

CREATE TABLE roles(
 t_id INT AUTO_INCREMENT PRIMARY KEY,
 role_id INT UNIQUE,
 role_name VARCHAR(100) UNIQUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles(role_id,role_name) VALUES
(1,'admin'),
(2,'hod'),
(3,'secretary'),
(4,'advisor'),
(5,'student');

-- =====================================================
-- USERS (SANITIZED)
-- =====================================================

CREATE TABLE user_details(
 user_id INT AUTO_INCREMENT PRIMARY KEY,
 role_id INT,
 f_name VARCHAR(100),
 l_name VARCHAR(100),
 username VARCHAR(100) UNIQUE,
 email VARCHAR(150) UNIQUE,
 unique_id VARCHAR(20) UNIQUE,
 password VARCHAR(255),
 department_id INT,
 class_id INT,
 level_id INT,
 is_active TINYINT DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- demo password = "password123"
-- bcrypt hash generated for demo only

INSERT INTO user_details
(role_id,f_name,l_name,username,email,unique_id,password,department_id,class_id,level_id)
VALUES

(1,'System','Administrator','admin','admin@example.edu',NULL,
'$2y$10$demoHashExample1234567890demoHashExample1234567890',1,NULL,NULL),

(2,'Alice','Mensah','hod_ict','hod.ict@example.edu',NULL,
'$2y$10$demoHashExample1234567890demoHashExample1234567890',1,NULL,NULL),

(4,'Daniel','Owusu','advisor_l100','advisor@example.edu',NULL,
'$2y$10$demoHashExample1234567890demoHashExample1234567890',1,NULL,NULL),

(5,'Student','One',NULL,'student1@example.edu','STU0001',
'$2y$10$demoHashExample1234567890demoHashExample1234567890',1,1,1),

(5,'Student','Two',NULL,'student2@example.edu','STU0002',
'$2y$10$demoHashExample1234567890demoHashExample1234567890',1,1,1);

-- =====================================================
-- COURSE LECTURERS
-- =====================================================

CREATE TABLE course_lecturers(
 assignment_id INT AUTO_INCREMENT PRIMARY KEY,
 course_id INT,
 lecturer_user_id INT,
 academic_year_id INT,
 semester_id INT,
 assigned_by INT,
 assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 is_active TINYINT DEFAULT 1
);

INSERT INTO course_lecturers
(course_id,lecturer_user_id,academic_year_id,semester_id,assigned_by)
VALUES
(1,2,1,1,1),
(2,2,1,2,1);

-- =====================================================
-- EVALUATION QUESTIONS
-- =====================================================

CREATE TABLE evaluation_questions(
 question_id INT AUTO_INCREMENT PRIMARY KEY,
 question_text VARCHAR(255),
 category VARCHAR(50),
 display_order INT,
 is_active TINYINT DEFAULT 1
);

INSERT INTO evaluation_questions(question_text,category,display_order) VALUES
('Course objectives were clearly explained','Teaching',1),
('Lecturer explained concepts clearly','Teaching',2),
('Course materials were useful','Materials',3),
('Assessment methods were fair','Assessment',4);

-- =====================================================
-- TOKENS
-- =====================================================

CREATE TABLE evaluation_tokens(
 token_id INT AUTO_INCREMENT PRIMARY KEY,
 student_user_id INT,
 course_id INT,
 academic_year_id INT,
 semester_id INT,
 token VARCHAR(64) UNIQUE,
 is_used TINYINT DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO evaluation_tokens
(student_user_id,course_id,academic_year_id,semester_id,token)
VALUES
(4,1,1,1,'demo_token_1'),
(5,1,1,1,'demo_token_2');

-- =====================================================
-- EVALUATIONS
-- =====================================================

CREATE TABLE evaluations(
 evaluation_id INT AUTO_INCREMENT PRIMARY KEY,
 token VARCHAR(64),
 course_id INT,
 academic_year_id INT,
 semester_id INT,
 evaluation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO evaluations(token,course_id,academic_year_id,semester_id)
VALUES
('demo_token_1',1,1,1);

-- =====================================================
-- RESPONSES
-- =====================================================

CREATE TABLE responses(
 id INT AUTO_INCREMENT PRIMARY KEY,
 evaluation_id INT,
 question_id INT,
 response_value TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO responses(evaluation_id,question_id,response_value) VALUES
(1,1,'4'),
(1,2,'5'),
(1,3,'4'),
(1,4,'5');

-- =====================================================
-- AUDIT LOGS (EMPTY FOR DEMO)
-- =====================================================

CREATE TABLE audit_logs(
 log_id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT,
 action_type VARCHAR(50),
 table_name VARCHAR(50),
 record_id INT,
 old_values TEXT,
 new_values TEXT,
 ip_address VARCHAR(45),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMIT;
