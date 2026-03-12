-- Database schema for Portfolio Builder

CREATE DATABASE IF NOT EXISTS portfolio_db;
USE portfolio_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    bio TEXT,
    profile_pic VARCHAR(255) DEFAULT 'default_avatar.png',
    hero_bg VARCHAR(255) DEFAULT 'default_bg.jpg',
    job_title VARCHAR(255),
    university_id VARCHAR(50),
    department VARCHAR(255),
    phone VARCHAR(20),
    location VARCHAR(255),
    education TEXT,
    experience TEXT,
    linkedin_url VARCHAR(255),
    github_url VARCHAR(255),
    twitter_url VARCHAR(255),
    instagram_url VARCHAR(255),
    skills TEXT,
    theme_choice ENUM('Professional Dark', 'Minimalist Light', 'Academic Blue', 'Modern Glass') DEFAULT 'Minimalist Light',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Projects table
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    link VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
