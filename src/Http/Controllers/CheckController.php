<?php

namespace Rgxfox\Jira\Http\Controllers;

use Rgxfox\Jira\Services\SprintCheckService;
use Illuminate\Http\Request;

class CheckController extends Controller
{
    // get jira sprint status
    public function __invoke(Request $request)
    {
        $taskList = $request->query('tasks');
        $sprintName = $request->query('sprint');

        return SprintCheckService::doCheck($sprintName, explode(',', $taskList) ?: []);
    }
}

