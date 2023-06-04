<?php

return [
    'prefix' => 'kd',
    'hash' => env('RGXFOX_JIRA_HASH'),
    'developers' => json_decode(env('RGXFOX_JIRA_DEVELOPERS'), true)
];