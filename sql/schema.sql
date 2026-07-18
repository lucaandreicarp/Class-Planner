CREATE DATABASE class_planner IF NOT EXISTS class_planner;

USE class_planner;

DROP TABLE IF EXISTS Oral;
DROP TABLE IF EXISTS Slot;
DROP TABLE IF EXISTS Schedule;
DROP TABLE IF EXISTS Student;
DROP TABLE IF EXISTS Subject;
DROP TABLE IF EXISTS Event;
DROP TABLE IF EXISTS Class;

CREATE TABLE Class (
	idclass INT AUTO_INCREMENT NOT NULL,
	name VARCHAR (20) NOT NULL,
	code VARCHAR (6) NOT NULL UNIQUE,
	PRIMARY KEY (idclass)
);

CREATE TABLE Event (
idevent INT AUTO_INCREMENT NOT NULL,
name VARCHAR(50) NOT NULL,
description TEXT,
start_date DATE NOT NULL,
end_date DATE NOT NULL CHECK (end_date >= start_date),
idclass INT NOT NULL,
PRIMARY KEY (idevent),
FOREIGN KEY (idclass) REFERENCES Class (idclass) ON DELETE CASCADE	
);

CREATE TABLE Subject (
	idsubject INT AUTO_INCREMENT NOT NULL,
	name VARCHAR (50) NOT NULL,
	idclass INT NOT NULL,
	PRIMARY KEY (idsubject),
	FOREIGN KEY (idclass) REFERENCES Class (idclass) ON DELETE CASCADE
);

CREATE TABLE Student (
	idstudent INT AUTO_INCREMENT NOT NULL,
	name VARCHAR(30) NOT NULL,
	idclass INT NOT NULL,
	PRIMARY KEY (idstudent),
	FOREIGN KEY (idclass) REFERENCES Class (idclass) ON DELETE CASCADE
);

CREATE TABLE Schedule (
	idschedule INT AUTO_INCREMENT NOT NULL,
	day_of_week INT NOT NULL CHECK (day_of_week BETWEEN 1 AND 6),
	idsubject INT NOT NULL,
	PRIMARY KEY (idschedule),
		FOREIGN KEY (idsubject) REFERENCES Subject (idsubject) ON DELETE CASCADE
);

CREATE TABLE Slot (
	idslot INT AUTO_INCREMENT NOT NULL,
	date DATE NOT NULL,
	idclass INT NOT NULL,
	idsubject INT NOT NULL,
	PRIMARY KEY (idslot),
	UNIQUE (date, idclass, idsubject), 
	FOREIGN KEY (idclass) REFERENCES Class (idclass) ON DELETE CASCADE,
	FOREIGN KEY (idsubject) REFERENCES Subject (idsubject) ON DELETE CASCADE
);

CREATE TABLE Oral (
	idoral INT AUTO_INCREMENT NOT NULL,
	idstudent INT NOT NULL,
	idslot INT NOT NULL,
	PRIMARY KEY (idoral),
    UNIQUE (idstudent, idslot),
	FOREIGN KEY (idstudent) REFERENCES Student (idstudent) ON DELETE CASCADE,
	FOREIGN KEY (idslot) REFERENCES Slot (idslot) ON DELETE CASCADE
);
