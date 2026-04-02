<?php

namespace App\Exception;

use Exception;

class ProjectUnauthorizedException extends Exception
{
    public function __construct(string $projectName)
    {
        parent::__construct("Unauthorized access to project '{$projectName}'.", 403);
    }
}
