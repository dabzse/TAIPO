PRAGMA foreign_keys = off;

BEGIN TRANSACTION;

ALTER TABLE tasks RENAME TO tasks_old;

CREATE TABLE tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_name TEXT NOT NULL,
    description TEXT NOT NULL,
    status TEXT NOT NULL CHECK (
        status IN (
            'SPRINT BACKLOG',
            'IMPLEMENTATION WIP:3',
            'TESTING WIP:2',
            'REVIEW WIP:2',
            'DONE'
        )
    ),
    is_important INTEGER DEFAULT 0,
    is_subtask INTEGER DEFAULT 0,
    po_comments TEXT DEFAULT NULL,
    generated_code TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO
    tasks (
        id,
        project_name,
        description,
        status,
        is_important,
        is_subtask,
        po_comments,
        generated_code,
        created_at
    )
SELECT
    id,
    project_name,
    description,
    CASE
        WHEN status = 'SPRINTBACKLOG' THEN 'SPRINT BACKLOG'
        ELSE status
    END,
    is_important,
    is_subtask,
    po_comments,
    generated_code,
    created_at
FROM tasks_old;

DROP TABLE tasks_old;

COMMIT;
