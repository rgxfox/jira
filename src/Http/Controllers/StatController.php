<?php

namespace Rgxfox\Jira\Http\Controllers;

use Rgxfox\Jira\Services\SprintStatService;

class StatController extends Controller
{
    // get jira statistics
    public function stat()
    {
       return SprintStatService::getTeamsStat();
    }

    // get open sprint issues
    public function issues()
    {
        return SprintStatService::getSprintIssues();
    }
}

