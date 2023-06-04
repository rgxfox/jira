<?php

namespace Rgxfox\Jira\Services;

use Illuminate\Http\JsonResponse;
use JiraRestApi\Issue\IssueService;

class SprintCheckService extends BaseService
{
    protected static IssueService $issueService;

    /**
     * Get Teams Sprint Status
     *
     * @param array $tasks
     * @param string $sprintName
     * @return \Illuminate\Http\JsonResponse|object
     * @throws \JsonMapper_Exception
     */
    public static function doCheck(string $sprintName = '', array $tasks = []): JsonResponse
    {
        if (empty($sprintName)) {
            return response()->json([
                'error' => 'Sprint is empty'
            ])->setStatusCode(403);
        }

        $tasks = array_filter($tasks);

        if (count($tasks) <= 0) {
            return response()->json([
                'error' => 'Empty task list'
            ])->setStatusCode(403);
        }

        self::setDevelopers(config('foxjira.developers'));
        self::$issueService = new IssueService();

        try {
            $sprintCheck = static::requestSprintStatus($sprintName, $tasks);
        } catch (\Throwable $e) {
            return response()->json($e->getMessage() . ':' . $e->getLine())->setStatusCode(403);
        }

        return response()->json($sprintCheck)->setStatusCode(200);
    }

    /**
     * Получает основные характеристики задач спринта
     *
     * @param string $sprintName
     * @param array $tasks
     * @return array
     * @throws \JiraRestApi\JiraException
     * @throws \JsonMapper_Exception
     */
    protected static function requestSprintStatus(string $sprintName, array $tasks): array
    {
        $result = [];

        $jql = static::getSearchRequest($tasks);

        // Запросим задачи и проверим их параметры
        $issues = self::$issueService->search($jql, 0, 1000)->getIssues();
        // Задачи на реализацию
        $causesToHistory = [];
        foreach ($issues as $issue) {
            // Получим спринт, в котором задача активна в данный момент
            $issueSprints = static::parseIssueSprints($issue);
            $busKey = '';

            $causes = [];
            foreach ($issue->fields->issuelinks as $link) {
                if (isset($link->inwardIssue) && (strpos($link->inwardIssue->key, 'ECMBUS')) !== false) {
                    $busKey = $link->inwardIssue->key;
                    break;
                } elseif (isset($link->outwardIssue) && (strpos($link->outwardIssue->key, 'ECMBUS')) !== false) {
                    $busKey = $link->outwardIssue->key;
                    break;
                }

                // Соберём задачи на реализацию
                if (($link->type->outward == 'causes') && property_exists($link, 'outwardIssue')) {
                    $causes[] = $link->outwardIssue->key;
                    $causesToHistory[$link->outwardIssue->key] = [
                        'history' => $issue->key
                    ];
                }
            }

            $result[$issue->key] = [
                'key' => $issue->key,
                'status' => mb_strtoupper($issue->fields->status->name),
                'inSprint' => static::inCurrentSprint($sprintName, $issueSprints),
                'hasCurator' => static::hasCurator($issue),
                'hasComponents' => static::hasComponents($issue),
                'hasEstimate' => static::hasEstimate($issue),
                'busKey' => $busKey,
                'causes' => $causes
            ];
        }

        // Получим инфу по задачам на реализацию
        // Если они имеются, подменим у Истории значения проверяемых параметров
        if (count($causesToHistory)) {
            $workIssues = self::$issueService->search(
                static::getSearchRequest(array_keys($causesToHistory)),
                0,
                1000
            )->getIssues();

            foreach ($workIssues as $issue) {
                $causesToHistory[$issue->key]['curator'] = static::hasCurator($issue);
                $causesToHistory[$issue->key]['components'] = static::hasComponents($issue);
                $causesToHistory[$issue->key]['estimate'] = static::hasEstimate($issue);
            }

            foreach ($result as &$item) {
                $item['hasCurator'] = $item['hasComponents'] = $item['hasEstimate'] = 1;
                foreach ($item['causes'] as $workKey) {
                    $item['hasCurator'] &= $causesToHistory[$workKey]['curator'];
                    $item['hasComponents'] &= $causesToHistory[$workKey]['components'];
                    $item['hasEstimate'] &= $causesToHistory[$workKey]['estimate'];
                }
            }
        }

        return $result;
    }

    /**
     * Определяет идентичность активного спринта задачи и переданного
     *
     * @param string $sprintName
     * @param array $issueSprints
     * @return int
     */
    protected static function inCurrentSprint(string $sprintName, array $issueSprints): int
    {
        static $curSprint = null;
        if (!$curSprint) {
            preg_match('{(?P<y>\d{4})-(?P<m>\d{1,2})-(?P<N>\d{1,2})}', $sprintName, $matches);
            $curSprint = sprintf('%s-%s-%s', $matches['y'], $matches['m'], $matches['N']);
        }

        // Получим последний спринт в массиве, он будет самый актуальный
        $lastSprint = count($issueSprints) ? array_slice($issueSprints, -1, 1)[0] : [];

        return (int)(strpos($curSprint, $lastSprint['name'] ?? '') === false);
    }

    /**
     * Получает код тимлида по имени спринта (так как спринт содержит имя тимлида)
     *
     * @param string $sprintName
     * @return string
     */
    public static function getSprintLeadName(string $sprintName): string
    {
        foreach (static::$developers as $developer) {
            if (isset(static::$developers[$developer['LEAD']])) {
                $leadName = static::$developers[$developer['LEAD']]['NAME'];
                if (strpos($sprintName, $leadName) !== false) {
                    return $developer['LEAD'];
                }
            }
        }

        return '';
    }

    /**
     * @param array $tasks
     * @return string
     */
    public static function getSearchRequest(array $tasks = []): string
    {
        if (count($tasks) > 0) {
            $request = sprintf(
                'project = %s and key in (%s)',
                static::PROJECT,
                "'" . implode("','", $tasks) . "'"
            );
        } else {
            $request = sprintf(
                'project = %s',
                static::PROJECT,
            );
        }

        return $request;
    }

    /**
     * @param $issue
     * @return bool
     */
    public static function hasCurator($issue): bool
    {
        return (int)(
            isset($issue->fields->customFields[static::CURATOR_FIELD_CODE])
            && is_object($issue->fields->customFields[static::CURATOR_FIELD_CODE])
            && !empty($issue->fields->customFields[static::CURATOR_FIELD_CODE]->name)
        );
    }

    /**
     * @param $issue
     * @return bool
     */
    public static function hasComponents($issue): bool
    {
        return (int)(!empty($issue->fields->components));
    }

    /**
     * @param $issue
     * @return bool
     */
    public static function hasEstimate($issue): bool
    {
        return (int)(
            is_object($issue->fields->timeoriginalestimate)
            && ($issue->fields->timeoriginalestimate->scalar > 0)
        );
    }
}
