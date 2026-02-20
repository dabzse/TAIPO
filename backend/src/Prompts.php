<?php

namespace App;

class Prompts
{
    public static function getLanguagePrompt(string $language): string
    {
        $lang = htmlspecialchars($language);
        return "Plan a project named {{PROJECT_NAME}}! This project should be primarily written in {$lang}. Generate at least 10 tasks for the Kanban board covering basic development steps (setup, core features, testing). Provide each task on a new line without any prefix (e.g. [SPRINT BACKLOG]:) so they all go into the **SPRINT BACKLOG** column. Do not include introductory text.";
    }
}
