<?php

namespace App\Config;

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
                'tasks' => "CREATE TABLE IF NOT EXISTS tasks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    project_name TEXT NOT NULL,
                    title TEXT DEFAULT NULL,
                    description TEXT NOT NULL,
                    status TEXT NOT NULL CHECK (status IN ('SPRINT BACKLOG','IMPLEMENTATION WIP:3', 'TESTING WIP:2', 'REVIEW WIP:2', 'DONE')),
                    is_important INTEGER DEFAULT 0,
                    is_subtask INTEGER DEFAULT 0,
                    po_comments TEXT DEFAULT NULL,
                    generated_code TEXT DEFAULT NULL,
                    position INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
                'projects' => "CREATE TABLE IF NOT EXISTS projects (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
                'settings' => "CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT NOT NULL,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )"
            ]
        ];
    }
}
