CREATE DATABASE IF NOT EXISTS class_planner;

USE class_planner;

DROP TABLE IF EXISTS oral;
DROP TABLE IF EXISTS slot;
DROP TABLE IF EXISTS schedule;
DROP TABLE IF EXISTS student;
DROP TABLE IF EXISTS subject;
DROP TABLE IF EXISTS event;
DROP TABLE IF EXISTS class;

CREATE TABLE class (
	idclass INT AUTO_INCREMENT NOT NULL,
	name VARCHAR (20) NOT NULL,
	code VARCHAR (6) NOT NULL UNIQUE,
	PRIMARY KEY (idclass)
);

CREATE TABLE event (
idevent INT AUTO_INCREMENT NOT NULL,
name VARCHAR(50) NOT NULL,
description TEXT,
start_date DATE NOT NULL,
end_date DATE NOT NULL CHECK (end_date >= start_date),
idclass INT NOT NULL,
PRIMARY KEY (idevent),
FOREIGN KEY (idclass) REFERENCES Class (idclass) ON DELETE CASCADE	
);

CREATE TABLE subject (
	idsubject INT AUTO_INCREMENT NOT NULL,
	name VARCHAR (50) NOT NULL,
	idclass INT NOT NULL,
	PRIMARY KEY (idsubject),
	FOREIGN KEY (idclass) REFERENCES Class (idclass) ON DELETE CASCADE
);

CREATE TABLE student (
	idstudent INT AUTO_INCREMENT NOT NULL,
	name VARCHAR(30) NOT NULL,
	idclass INT NOT NULL,
	PRIMARY KEY (idstudent),
	FOREIGN KEY (idclass) REFERENCES Class (idclass) ON DELETE CASCADE
);

CREATE TABLE schedule (
	idschedule INT AUTO_INCREMENT NOT NULL,
	day_of_week INT NOT NULL CHECK (day_of_week BETWEEN 1 AND 6),
	idsubject INT NOT NULL,
	PRIMARY KEY (idschedule),
		FOREIGN KEY (idsubject) REFERENCES Subject (idsubject) ON DELETE CASCADE
);

CREATE TABLE slot (
	idslot INT AUTO_INCREMENT NOT NULL,
	date DATE NOT NULL,
	capacity INT NOT NULL CHECK (capacity > 0),
	idclass INT NOT NULL,
	idsubject INT NOT NULL,
	PRIMARY KEY (idslot),
	UNIQUE (date, idclass, idsubject), 
	FOREIGN KEY (idclass) REFERENCES Class (idclass) ON DELETE CASCADE,
	FOREIGN KEY (idsubject) REFERENCES Subject (idsubject) ON DELETE CASCADE
);

CREATE TABLE oral (
	idoral INT AUTO_INCREMENT NOT NULL,
	idstudent INT NOT NULL,
	idslot INT NOT NULL,
	PRIMARY KEY (idoral),
    UNIQUE (idstudent, idslot),
	FOREIGN KEY (idstudent) REFERENCES Student (idstudent) ON DELETE CASCADE,
	FOREIGN KEY (idslot) REFERENCES Slot (idslot) ON DELETE CASCADE
);
