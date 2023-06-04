# Требования
- PHP >= 8.0
- Laravel >= 10

# Requirements
Библиотека имеет следующие настройки, которые необходимо разместить в вашем файле окружения:

1. JIRA_HOST - адрес сервера, на котором располагается Jira
``` sh
  JIRA_HOST='https://jira.yoursite.com'
```

2. JIRA_USER - ваше имя пользователя в Jira. От этого пользователя будут производиться rest-запросы
``` sh
  JIRA_USER='surname.n'
```

3. JIRA_PASS - ваш пароль от Jira
``` sh
  JIRA_PASS='12345'
```

4. TOKEN_BASED_AUTH - флаг, уведомляющий о том, что авторизация происходит не по токену. Должен быть установлен
``` sh
  TOKEN_BASED_AUTH='false'
```

5. RGXFOX_JIRA_DEVELOPERS - json-кодированный массив разработчиков, по которым будет считаться статистика. В значение нужно подставить сам json
``` sh
  RGXFOX_JIRA_DEVELOPERS='json_encode([
    developer1.name => [
      'NAME' => 'Developer 1',
      'LEAD' => 'lead1.name'
    ],
    developer2.name => [
      'NAME' => 'Developer 2',
      'LEAD' => 'lead1.name'
    ],
    lead1.name => [
      'NAME' => 'Lead 1',
      'LEAD' => 'lead1.name'
    ],
    ...
  ], true)'
```

6. RGXFOX_JIRA_HASH - хеш, который передается в виде GET-параметра для авторизации запроса
``` sh
  RGXFOX_JIRA_HASH='ofq847yoqiu4fhoa87e4yf'
```

# Использование
Библиотека создает несколько маршрутов, которыми можно воспользоваться ддя получения статистики по задачам или разработчикам.

1. `/kd/sprint/stat` - получает статистику (в виде json) по активным спринтам
``` php
  #http://localhost/kd/sprint/stat?hash=ofq847yoqiu4fhoa87e4yf
  
  return [
    'ActiveSprint1' => [
      'id' => 10,
      'name' => 'ActiveSprint1',
      'active' => true,
      'lead' => 'lead1.name',
      'startDate' => [
        'date' => '2023-05-01 00:00:00.000000',
        'timezone_type' => 3,
        'timezone' => 'UTC'
      ],
      'endDate' => [
        'date' => '2023-05-14 23:59:00.000000',
        'timezone_type' => 3,
        'timezone' => 'UTC'
      ],
      'sprintInterval' => '01.05.2023 0:00 - 14.05.2023 23:59',
      'issues' => [
        'dev-2596' => [
          'key' => 'dev-2596',
          'originalEstimate' => 3600,
          'spentTime' => 0,
          'assignee' => 'developer1.name',
          'curator' => 'lead1.name',
          'isFinished' => 0,
          'estimate' => 0,
          'isReturn' => 0,
          'title' => 'Add new icon',
          'causes' => [],
          'type' => 'Задача на реализацию"
        ],
        ...
      ]
    ],
    ...
  ];
```

2. `/kd/sprint/issues` - получает статистику (в виде json) по задачам
``` php
  #http://localhost/kd/sprint/issues?hash=ofq847yoqiu4fhoa87e4yf
  
  # Ответ практически идентичен статистике спринтов, но методика подсчета спринтов другая
```

3. `/kd/sprint/check` - получает статистику (в виде json) по конкретным задача конкретного спринта
``` php
  #http://localhost/kd/sprint/check?hash=ofq847yoqiu4fhoa87e4yf&sprint=ActiveSprint1&tasks=dev-1000,dev-1001,dev-1002,dev-1003,dev-1004
  
  return [
    'dev-1000' => [
      'key' => 'dev-1000',
      'status' => 'РЕАЛИЗОВАНО',
      'inSprint' => 1,
      'hasCurator' => 1,
      'hasComponents' => 1,
      'hasEstimate' => 1,
      'busKey' => '',
      'causes' => []
    ],
    ...
  ];
```
