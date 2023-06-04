<?php

namespace Rgxfox\Jira\Services;

use JiraRestApi\Issue\Issue;

abstract class BaseService
{
    protected const PROJECT = 'ECMDEV';
    // код поля Куратор
    public const CURATOR_FIELD_CODE = 'customfield_10645';
    // последняя права списка 27.03
    protected static array $developers = [];

    protected static array $finishedStatus = [
        'Реализовано',
        'FINISHED',
        'WF DEPLOY',
        'DEPLOY',
        'Declined',
        'To release',
        'On regress',
        'YT'
    ];

    /**
     * Checks request hash
     *
     * @param string|null $hash
     * @return bool
     */
    public static function checkHash(?string $hash = ''): bool
    {
        if (empty($hash) || ($hash !== config('foxjira.hash'))) {
            return false;
        }

        return true;
    }

    /**
     * Get lead wards by lead name
     *
     * @param string $leadName
     * @return array
     */
    protected static function getLeadWards(string $leadName): array
    {
        $wards = [];
        foreach (static::$developers as $code => $developer) {
            if (!isset(static::$developers[$developer['LEAD']])) {
                continue;
            }
            if (static::$developers[$developer['LEAD']]['NAME'] == $leadName) {
                $wards[] = $code;
            }
        }

        return $wards;
    }

    /**
     * Parse sprints from the issue
     *
     * @param Issue $issue
     * @return array
     */
    protected static function parseIssueSprints(Issue $issue): array
    {
        $sprints = [];
        if (!array_key_exists('customfield_10001', $issue->fields->customFields)) {
            return $sprints;
        }
        $sprintArray = $issue->fields->customFields['customfield_10001'];
        foreach ($sprintArray as $sprint) {
            if (preg_match(
                '{id=(?P<id>\\d+?),(.*?)state=(?P<state>.+?),(.*?)name=(?P<name>.+?),(.*?)startDate=(?P<start>.+?),endDate=(?P<finish>.+?),}',
                $sprint,
                $matches
            )) {
                try {
                    $sprints[$matches['id']] = [
                        'id' => $matches['id'],
                        'name' => $matches['name'],
                        'active' => $matches['state'] == 'ACTIVE',
                        'lead' => static::getSprintLeadName($matches['name']),
                        'startDate' => static::convertDate($matches['start']),
                        'endDate' => static::convertDate($matches['finish'], true),
                        'issues' => [],
                    ];

                    $sprints[$matches['id']]['sprintInterval'] = sprintf(
                        '%s - %s',
                        $sprints[$matches['id']]['startDate']->format('d.m.Y 0:00'),
                        $sprints[$matches['id']]['endDate']->format('d.m.Y 23:59')
                    );
                } catch (\Throwable $e) {

                }
            }
        }

        return $sprints;
    }

    /**
     * Переводит строковую дату в объект даты начала или конца дня
     *
     * @param string $strDate
     * @param bool $endOfDay - признак, что дату нужно привести к концу дня
     * @return \DateTime|null
     */
    public static function convertDate(string $strDate = '', bool $endOfDay = false): ?\DateTime
    {
        if (empty($strDate)) {
            return null;
        }

        try {
            $date = new \DateTime($strDate);
            if ($endOfDay) {
                return new \DateTime($date->format('Y-m-d 23:59'));
            } else {
                return new \DateTime($date->format('Y-m-d 0:00'));
            }
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array $developers
     * @return void
     */
    public static function setDevelopers(array $developers = []): void
    {
        self::$developers = $developers;
    }
}
