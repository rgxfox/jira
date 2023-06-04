<?php


namespace Rgxfox\Jira\Services;

use Illuminate\Http\JsonResponse;
use JiraRestApi\Issue\Issue;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\JiraException;

class SprintStatService extends BaseService
{
    protected static IssueService $issueService;

    /**
     * Get Kd Teams Sprint stat
     *
     * @return JsonResponse
     */
    public static function getTeamsStat(): JsonResponse
    {
        self::setDevelopers(config('foxjira.developers'));
        self::$issueService = new IssueService();

        try {
            $activeSprints = static::requestActiveSprints();
            $sprintStats = static::requestWorklog($activeSprints);
        } catch (\Throwable $e) {
            return response()->json($e->getMessage() . ':' . $e->getLine())->setStatusCode(403);
        }

        return response()->json($sprintStats)->setStatusCode(200);
    }

    /**
     * Get Kd Teams Sprint Issues
     *
     * @return JsonResponse
     */
    public static function getSprintIssues(): JsonResponse
    {
        self::setDevelopers(config('foxjira.developers'));
        self::$issueService = new IssueService();

        try {
            $activeSprints = static::requestActiveSprints();
        } catch (\Throwable $e) {
            return response()->json($e->getMessage() . ':' . $e->getLine())->setStatusCode(403);
        }

        return response()->json($activeSprints)->setStatusCode(200);
    }

    /**
     * Запрашивает ворклог по спринтам
     *
     * @param array $sprints
     * @return array
     * @throws JiraException
     * @throws \JsonMapper_Exception
     */
    protected static function requestWorklog(array $sprints = []): array
    {
        if (empty($sprints)) {
            return [];
        }

        /**
         * Получим минимальную дату начала и максимальную дату конца спринтов,
         * чтобы по ним достать ворклог
         */
        $minStartDate = new \DateTime();
        $maxEndDate = new \DateTime();
        foreach ($sprints as $sprint) {
            if ($sprint['startDate']->getTimestamp() < $minStartDate->getTimestamp()) {
                $minStartDate = $sprint['startDate'];
            }

            if ($sprint['endDate']->getTimestamp() > $maxEndDate->getTimestamp()) {
                $maxEndDate = $sprint['endDate'];
            }
        }

        // Получим список сотрудников команд для выборки по ним ворклога
        $leadWards = [];
        foreach ($sprints as $sprint) {
            $leadWards = array_merge($leadWards, static::getLeadWards($sprint['lead']));
        }

        // Сформируем запрос ворклога спринтов для сотрудников команд
        // Фильтр по спринту не нужен, чтобы учесть списания в неспринтовые (напр., административные) задачи
        $jql = sprintf(
            'project = %s and worklogDate >= %s and worklogDate <= %s and worklogAuthor in (%s)',
            static::PROJECT,
            $minStartDate->format('Y-m-d'),
            $maxEndDate->format('Y-m-d'),
            "'" . implode("','", $leadWards) . "'"
        );

        // Запросим все спринтовые задачи за заданный промежуток времени
        $issues = self::$issueService->search($jql, 0, 1000)->getIssues();

        // Спринт, куда попадут задачи не из активных спринтов
        foreach ($issues as $issue) {

            // Получим спринт, в котором задача активна в данный момент
            $issueSprint = static::parseIssueSprints($issue) ? array_slice(static::parseIssueSprints($issue), -1, 1)[0] : null;
            // Если спринт задачи не найден или неактивный, переносим ее ворклог в спринт "Без спринта"
            if ($issueSprint && !isset($sprints[$issueSprint['name']])) {
                $issueSprint = null;
            }

            // Получаем ворклог задачи
            $worklogs = self::$issueService->getWorklog($issue->key)->getWorklogs();

            /**
             * $worklog->started - дата логирования работы
             */
            foreach ($worklogs as $worklog) {

                // Если логирование произведено не сотрудником команд, не учитываем его в расчетах
                if (!isset(static::$developers[$worklog->author['name']])) {
                    continue;
                }

                // Если у задачи нет активного спринта, запишем ворклог в спринт "Без спринта"
                if (empty($issueSprint)) {

                    if (!isset($sprints['EMPTY_SPRINT']['issues'][$worklog->author['name']][$issue->key])) {
                        $sprints['EMPTY_SPRINT']['issues'][$worklog->author['name']][$issue->key] = static::initIssueWorklog($issue);
                    }
                    $sprints['EMPTY_SPRINT']['issues'][$worklog->author['name']][$issue->key]['spentTime'] += $worklog->timeSpentSeconds;

                    continue;
                }

                // К этому моменту мы убедились, что ворклог списан разработчиком команды и что задача
                // в активном спринте

                // Получим дату логирования на начало суток
                $chargeDateTimestamp = static::convertDate($worklog->started)->getTimestamp();

                // Убедимся, что ворклог списан на дату в течение спринта, в котором выполняется задача
                if (
                    ($chargeDateTimestamp >= $issueSprint['startDate']->getTimestamp())
                    &&
                    ($chargeDateTimestamp < $issueSprint['endDate']->getTimestamp())
                ) {

                    if (!isset($sprints[$issueSprint['name']]['issues'][$worklog->author['name']][$issue->key])) {
                        $sprints[$issueSprint['name']]['issues'][$worklog->author['name']][$issue->key] = static::initIssueWorklog($issue);
                    }

                    $sprints[$issueSprint['name']]['issues'][$worklog->author['name']][$issue->key]['spentTime'] += $worklog->timeSpentSeconds;
                }
            }
        }

        return $sprints;
    }

    /**
     * Получает список активных спрнинтов, при необходимости
     * фильтруя по тимлиду
     *
     * @param string $lead
     * @return array
     * @throws JiraException
     * @throws \JsonMapper_Exception
     */
    protected static function requestActiveSprints(string $lead = ''): array
    {
        static $sprints = null;
        if (is_null($sprints)) {
            $jql = sprintf('project = %s and Sprint in openSprints()', static::PROJECT);
            $issues = self::$issueService->search($jql, 0, 1000)->getIssues();
            /** @param Issue $issue */
            foreach ($issues as $issue) {
                $issueSprints = static::parseIssueSprints($issue);
                $initIssue = static::initIssueWorklog($issue);
                // последний спринт задачи всегда активный в соответствии с фильтром выше
                $activeSprint = array_pop($issueSprints);
                // если задан лид, отфильтруем по нему
                if (!empty($lead) && ($activeSprint['lead'] != $lead)) {
                    continue;
                }

                if (!isset($sprints[$activeSprint['name']])) {
                    $sprints[$activeSprint['name']] = $activeSprint;
                }
                $sprints[$activeSprint['name']]['issues'][$initIssue['key']] = $initIssue;
            }

            /** @var array $sprints */
            ksort($sprints);
        }

        return $sprints;
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
     * Получает подчинённых сотрудников тимлида
     *
     * @param string $leadName
     * @return array
     */
    protected static function getLeadWards(string $leadName): array
    {
        $wards = [];
        foreach (static::$developers as $code => $developer) {
            if ($developer['LEAD'] == $leadName) {
                $wards[] = $code;
            }
        }

        return $wards;
    }

    /**
     * Init issue work log array
     *
     * @param Issue $issue
     * @return array
     */
    protected static function initIssueWorklog(Issue $issue): array
    {
        // Используем только исполнителей из участников команд
        if ($issue->fields->assignee && isset(static::$developers[$issue->fields->assignee->name])) {
            $assignee = $issue->fields->assignee->name;
        } else {
            $assignee = '';
        }

        // Не учитываем оценку задач типа Возврат
        // Не рекомендуется оценку брать из поля $issue->fields->progress['total']
        if ($issue->fields->issuetype->id == 10602) {
            $originalEstimate = 0;
        } elseif (!is_object($issue->fields->timeoriginalestimate)) {
            $originalEstimate = 0;
        } else {
            $originalEstimate = (int)$issue->fields->timeoriginalestimate->scalar;
        }

        if (isset($issue->fields->customFields['customfield_10645'])) {
            $curator = $issue->fields->customFields['customfield_10645']->name;
        } else {
            $curator = '';
        }

        $causes = [];
        if (!empty($issuelinks = $issue->fields->issuelinks)) {
            foreach ($issuelinks as $link) {
                if (($link->type->outward == 'causes') && property_exists($link, 'outwardIssue')) {
                    $causes[] = $link->outwardIssue->key;
                }
            }
        }

        return [
            'key' => $issue->key,
            'originalEstimate' => $originalEstimate,
            'spentTime' => 0,
            'assignee' => $assignee,
            'curator' => $curator,
            'isFinished' => intval(in_array($issue->fields->status->name, static::$finishedStatus)),
            'estimate' => (int)$issue->fields->timeestimate,
            'isReturn' => intval($issue->fields->issuetype->id == 10602),
            'title' => $issue->fields->summary,
            'causes' => $causes,
            'type' => $issue->fields->issuetype->name
        ];
    }
}
