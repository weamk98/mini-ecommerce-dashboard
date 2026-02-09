CREATE DATABASE production_monitoring;
USE production_monitoring;

CREATE TABLE  machines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  ideal_cycle_time_sec DECIMAL(10,2) NOT NULL DEFAULT 2.50,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE  production_minute (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  machine_id INT NOT NULL,
  ts_minute DATETIME NOT NULL,
  status ENUM('RUN','DOWN','IDLE') NOT NULL,
  good_count INT NOT NULL DEFAULT 0,
  scrap_count INT NOT NULL DEFAULT 0,
  downtime_sec INT NOT NULL DEFAULT 0,
  planned_sec INT NOT NULL DEFAULT 60,
  UNIQUE KEY uq_machine_minute (machine_id, ts_minute),
  FOREIGN KEY (machine_id) REFERENCES machines(id)
);
