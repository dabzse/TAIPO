<?php

namespace App\Configuration;

class DatabaseConfig
{
    public static function get(): array
    {
        return [
            'pragma' => [
                'encoding' => "'UTF-8'",
                'foreign_keys' => 'ON',
                'journal_mode' => 'WAL',
                'synchronous' => 'NORMAL'
            ],
            'schema' => [
                'settings' => "CREATE TABLE IF NOT EXISTS settings (
                    `key` VARCHAR(191) PRIMARY KEY,
                    `value` TEXT NOT NULL,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
                'roles' => "CREATE TABLE IF NOT EXISTS roles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(191) NOT NULL UNIQUE,
                    description TEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
                'teams' => "CREATE TABLE IF NOT EXISTS teams (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(191) NOT NULL UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
                'users' => "CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username VARCHAR(191) NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    is_instructor INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
                'projects' => "CREATE TABLE IF NOT EXISTS projects (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(191) NOT NULL UNIQUE,
                    user_id INTEGER DEFAULT NULL,
                    team_id INTEGER DEFAULT NULL,
                    is_archived INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(team_id) REFERENCES teams(id) ON DELETE RESTRICT
                )",
                'tasks' => "CREATE TABLE IF NOT EXISTS tasks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    project_name VARCHAR(191) NOT NULL,
                    title TEXT DEFAULT NULL,
                    description TEXT NOT NULL,
                    status TEXT NOT NULL CHECK (status IN ('SPRINT BACKLOG','IMPLEMENTATION WIP:3', 'TESTING WIP:2', 'REVIEW WIP:2', 'DONE')),
                    is_important INTEGER DEFAULT 0,
                    is_subtask INTEGER DEFAULT 0,
                    parent_id INTEGER DEFAULT NULL,
                    po_comments TEXT DEFAULT NULL,
                    generated_code TEXT DEFAULT NULL,
                    position INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(project_name) REFERENCES projects(name) ON DELETE RESTRICT
                )",
                'requirements' => "CREATE TABLE IF NOT EXISTS requirements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    project_name VARCHAR(191) NOT NULL,
                    content TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(project_name) REFERENCES projects(name) ON DELETE RESTRICT
                )",
                'api_usage' => "CREATE TABLE IF NOT EXISTS api_usage (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    endpoint TEXT NOT NULL,
                    prompt_tokens INTEGER DEFAULT 0,
                    candidate_tokens INTEGER DEFAULT 0,
                    total_tokens INTEGER DEFAULT 0,
                    user_id INTEGER DEFAULT NULL,
                    team_id INTEGER DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
                'team_users' => "CREATE TABLE IF NOT EXISTS team_users (
                    team_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    role_id INTEGER NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (team_id, user_id),
                    FOREIGN KEY(team_id) REFERENCES teams(id) ON DELETE RESTRICT,
                    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
                    FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE RESTRICT
                )"
            ]
        ];
    }
}
