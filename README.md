# Лабораторная работа №8: Непрерывная интеграция с помощью GitHub Actions

## Цель работы

Ознакомиться с понятием непрерывной интеграции и научиться использовать GitHub Actions для автоматической сборки и тестирования web-приложений на базе Docker. В процессе лабораторной создаётся PHP-приложение, база данных SQLite, настраиваются тесты и CI.

---

## Задание

- Реализовать PHP-приложение, которое использует SQLite для хранения данных.
- Создать модульные тесты для проверки работы приложения.
- Настроить Dockerfile для сборки контейнера с приложением.
- Настроить GitHub Actions для автоматического запуска CI при каждом коммите.

---

## Ход работы

### 1. Клонирован пустой Git-репозиторий `containers08`

```bash
git clone git@github.com:iurii1801/containers08.git
```

![image](https://i.imgur.com/u8biQGB.png)

### 2. Создание структуры проекта

В корне репозитория создана структура директорий и файлов, строго соответствующая условиям задания:

Папка `site/` содержит все исходные файлы приложения: модули, шаблоны, стили и точку входа `index.php`.

Папка `sql/` содержит скрипт `schema.sql` для создания таблицы и начального наполнения БД.

Папка `tests/` содержит фреймворк тестирования и набор тестов.

Файл `Dockerfile` расположен в корне проекта и описывает процесс сборки контейнера.

В каталоге `.github/workflows/` создан файл `main.yml` — сценарий для запуска CI.

**Создание структуры выполнено через команды PowerShell:**

```powershell
New-Item -ItemType Directory -Name site
New-Item -ItemType Directory -Name sql
New-Item -ItemType Directory -Name tests
New-Item -ItemType Directory -Path .github\workflows -Force

# Создание файлов
New-Item site/index.php
New-Item site/config.php
New-Item site/modules/database.php
New-Item site/modules/page.php
New-Item site/templates/index.tpl
New-Item site/styles/style.css
New-Item sql/schema.sql
New-Item tests/testframework.php
New-Item tests/tests.php
New-Item Dockerfile
New-Item .github/workflows/main.yml
```

#### Структура проекта и назначение файлов

```sh
containers08/
├── site/                       # Исходный код приложения
│   ├── index.php               # Главная точка входа в приложение
│   ├── config.php              # Конфигурационный файл с параметрами БД
│   ├── modules/
│   │   ├── database.php        # Класс Database — обёртка над SQLite
│   │   └── page.php            # Класс Page — шаблонизатор
│   ├── templates/
│   │   └── index.tpl           # HTML-шаблон страницы
│   └── styles/
│       └── style.css           # Стили оформления страницы
├── sql/
│   └── schema.sql              # SQL-скрипт создания таблицы и данных
├── tests/
│   ├── testframework.php       # Простая реализация тестового фреймворка
│   └── tests.php               # Набор тестов на функциональность
├── Dockerfile                  # Инструкция сборки Docker-образа
├── .github/
│   └── workflows/
│       └── main.yml            # GitHub Actions workflow (CI)
└── README.md                   # Отчёт по работе
```

![image](https://i.imgur.com/36Ur9jy.png)

### 3. Реализация PHP-приложения

#### site/config.php

```php
<?php
$config = [
    "db" => [
        "path" => "/var/www/db/db.sqlite"
    ]
];
```

- Это файл конфигурации, в котором задаются параметры подключения к базе данных. В данном случае, массив `$config` содержит путь к файлу SQLite. Этот путь затем используется при создании объекта `Database`, чтобы он знал, к какой базе подключаться. Такой подход позволяет легко изменить путь к базе, не затрагивая код логики приложения.

#### site/modules/database.php

```php
<?php
class Database {
    private $pdo;

    public function __construct($path) {
        $this->pdo = new PDO("sqlite:" . $path);
    }

    public function Execute($sql) {
        return $this->pdo->exec($sql);
    }

    public function Fetch($sql) {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function Create($table, $data) {
        $columns = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        $stmt = $this->pdo->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

    public function Read($table, $id) {
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function Update($table, $id, $data) {
        $set = implode(", ", array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;
        $stmt = $this->pdo->prepare("UPDATE $table SET $set WHERE id = :id");
        return $stmt->execute($data);
    }

    public function Delete($table, $id) {
        $stmt = $this->pdo->prepare("DELETE FROM $table WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function Count($table) {
        return $this->pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    }
}
```

- Файл содержит класс `Database`, реализующий удобный интерфейс работы с SQLite через PDO. Внутри конструктора происходит подключение к базе. Метод `Execute` позволяет выполнить произвольные SQL-запросы без возврата данных, такие как INSERT или UPDATE. Метод `Fetch` используется для выборки данных. Методы `Create`, `Read`, `Update` и `Delete` реализуют основные CRUD-операции для работы с таблицами. Метод `Count` возвращает количество записей в таблице. Все операции используют подготовленные выражения, что обеспечивает безопасность и предотвращает SQL-инъекции. Благодаря этому классу, вся работа с базой данных инкапсулирована в одном модуле.

#### site/modules/page.php

```php
<?php
class Page {
    private $template;

    public function __construct($template) {
        $this->template = file_get_contents($template);
    }

    public function Render($data) {
        $output = $this->template;
        foreach ($data as $key => $value) {
            $output = str_replace("{{" . $key . "}}", htmlspecialchars($value), $output);
        }
        return $output;
    }
}
```

- Файл реализует простой шаблонизатор через класс `Page`. Конструктор загружает HTML-шаблон из файла. Метод `Render` принимает ассоциативный массив данных и заменяет в шаблоне все плейсхолдеры вида `{{ключ}}` на соответствующие значения. При этом значения экранируются через `htmlspecialchars`, чтобы предотвратить XSS. Таким образом, класс `Page` отделяет логику отображения от логики данных.

#### site/index.php

```php
<?php
require_once __DIR__ . '/modules/database.php';
require_once __DIR__ . '/modules/page.php';
require_once __DIR__ . '/config.php';

$db = new Database($config["db"]["path"]);
$page = new Page(__DIR__ . '/templates/index.tpl');

$pageId = $_GET['page'] ?? 1;
$data = $db->Read("page", $pageId);

echo $page->Render($data);
```

- Главный исполняемый файл веб-приложения. Здесь подключаются модули `Database` и `Page`, загружается конфигурация, создаются соответствующие объекты. С помощью GET-параметра page выбирается ID страницы, которая будет загружена из таблицы `page` и отображена через шаблон `index.tpl`. Если параметр не указан, по умолчанию используется `pageId = 1`. Полученные из базы данные передаются шаблонизатору, который генерирует и возвращает HTML-страницу.

#### site/templates/index.tpl

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{title}}</title>
    <link rel="stylesheet" href="/styles/style.css">
</head>
<body>
    <h1>{{title}}</h1>
    <div>{{content}}</div>
</body>
</html>
```

- Файл-шаблон HTML-страницы. Содержит статическую разметку и плейсхолдеры вида `{{title}}` и `{{content}}`, которые заменяются на реальные значения в процессе рендеринга. Шаблон используется классом `Page` для генерации итоговой HTML-страницы, отображаемой пользователю.

#### site/styles/style.css

```css
body {
    font-family: Arial, sans-serif;
    margin: 40px;
    background-color: #f0f0f0;
}
h1 {
    color: #333;
}
```

- Файл со стилями, подключаемый в шаблоне HTML. Оформляет страницу: задаёт шрифт, цвет заголовка, отступы и фон. Используется для базового визуального улучшения без лишней сложности.

### 4. Подготовка базы данных

#### sql/schema.sql

```sql
CREATE TABLE page (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT,
    content TEXT
);

INSERT INTO page (title, content) VALUES ('Page 1', 'Content 1');
INSERT INTO page (title, content) VALUES ('Page 2', 'Content 2');
INSERT INTO page (title, content) VALUES ('Page 3', 'Content 3');
```

- SQL-скрипт, который создаёт таблицу `page` с полями `id`, `title`, `content`, и вставляет в неё три записи. Этот скрипт используется в Dockerfile для автоматической инициализации базы данных при сборке контейнера. Таким образом, после сборки контейнер уже содержит предварительно загруженные страницы, готовые к отображению.

### 5. Написание тестов

#### tests/testframework.php

```php
<?php
function message($type, $message) {
    $time = date('Y-m-d H:i:s');
    echo "{$time} [{$type}] {$message}" . PHP_EOL;
}
function info($message) {
    message('INFO', $message);
}
function error($message) {
    message('ERROR', $message);
}
function assertExpression($expression, $pass = 'Pass', $fail = 'Fail'): bool {
    if ($expression) {
        info($pass);
        return true;
    }
    error($fail);
    return false;
}
class TestFramework {
    private $tests = [];
    private $success = 0;
    public function add($name, $test) {
        $this->tests[$name] = $test;
    }
    public function run() {
        foreach ($this->tests as $name => $test) {
            info("Running test {$name}");
            if ($test()) {
                $this->success++;
            }
            info("End test {$name}");
        }
    }
    public function getResult() {
        return "{$this->success} / " . count($this->tests);
    }
}
```

- Файл содержит простейший тестовый фреймворк на PHP. Он реализует систему логирования сообщений (info и error) и метод `assertExpression` для проверки булевых выражений. Класс `TestFramework` позволяет добавлять тесты, запускать их и считать успешные. Такой подход обеспечивает простую систему автоматической проверки, без зависимости от внешних библиотек.

#### tests/tests.php

```php
<?php
require_once __DIR__ . '/testframework.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/database.php';
require_once __DIR__ . '/../modules/page.php';

$tests = new TestFramework();

$tests->add('Database connection', function() {
    global $config;
    try {
        $db = new Database($config["db"]["path"]);
        return assertExpression(true, "DB OK", "DB FAIL");
    } catch (Exception $e) {
        return assertExpression(false, "DB OK", "DB FAIL");
    }
});
$tests->add('Table count', function() {
    global $config;
    $db = new Database($config["db"]["path"]);
    return assertExpression($db->Count("page") >= 3, "Count OK", "Count FAIL");
});
$tests->add('Read existing record', function() {
    global $config;
    $db = new Database($config["db"]["path"]);
    $data = $db->Read("page", 1);
    return assertExpression(isset($data["title"]), "Read OK", "Read FAIL");
});
$tests->add('Render template', function() {
    $tpl = new Page(__DIR__ . '/../templates/index.tpl');
    $html = $tpl->Render(['title' => 'Test', 'content' => 'Hello']);
    return assertExpression(strpos($html, 'Test') !== false && strpos($html, 'Hello') !== false, "Render OK", "Render FAIL");
});
$tests->run();
echo "Result: " . $tests->getResult() . PHP_EOL;
```

- Файл, в котором определены конкретные тесты для проверки работы приложения. Подключается конфигурация, модули базы и шаблонов, создаётся экземпляр `TestFramework`, после чего добавляются 4 теста: проверка подключения к базе, проверка количества записей, чтение одной записи и рендер шаблона. Каждый тест формулируется как анонимная функция, возвращающая true или false, и снабжается соответствующим сообщением. В конце все тесты запускаются, и выводится общее количество успешных проверок.

### 6. Написание Dockerfile

#### Dockerfile

```dockerfile
FROM php:7.4-fpm
RUN apt-get update && \
    apt-get install -y sqlite3 libsqlite3-dev && \
    docker-php-ext-install pdo_sqlite
VOLUME ["/var/www/db"]
COPY sql/schema.sql /var/www/db/schema.sql
RUN cat /var/www/db/schema.sql | sqlite3 /var/www/db/db.sqlite && \
    chmod 777 /var/www/db/db.sqlite && \
    rm /var/www/db/schema.sql
COPY site /var/www/html
```

- Инструкция по сборке Docker-образа приложения. Внутри образа устанавливается PHP и SQLite, затем копируется SQL-скрипт и с его помощью создаётся база данных. После этого копируется содержимое папки `site`, где размещается приложение. В результате создаётся образ, который может быть запущен в любом окружении с предустановленной базой и кодом.

### 7. Настройка GitHub Actions (CI)

#### .github/workflows/main.yml

```yaml
name: CI
on:
  push:
    branches:
      - main
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
      - name: Build Docker image
        run: docker build -t containers08 .
      - name: Create container
        run: docker create --name container --volume database:/var/www/db containers08
      - name: Copy tests into container
        run: docker cp ./tests container:/var/www/html
      - name: Start container
        run: docker start container
      - name: Run tests
        run: docker exec container php /var/www/html/tests/tests.php
      - name: Stop container
        run: docker stop container
      - name: Remove container
        run: docker rm container
      - name: Remove Docker image
        run: docker rmi containers08 || true
```

- Это файл конфигурации GitHub Actions. Он запускается при каждом пуше в ветку `main`. Внутри него по шагам: скачивается код, собирается Docker-образ, создаётся контейнер с volume для БД, в него копируются тесты, контейнер запускается, и тесты выполняются внутри него. После завершения тестов контейнер и образ удаляются. Таким образом, реализована автоматическая проверка работоспособности кода при каждом изменении в репозитории.

### 8. Публикация и запуск

После загрузки всех файлов в репозиторий (`git push`), во вкладке `Actions` в GitHub выполняется `workflow`.
Все шаги успешно проходят, вывод тестов подтверждает корректную работу всех компонентов:

```pgsql
INFO DB OK
INFO Count OK
INFO Read OK
INFO Render OK
Result: 4 / 4
```

![image](https://i.imgur.com/pFWq2Ag.png)

![image](https://i.imgur.com/jPPgb1u.png)

---

## Ответы на вопросы

### Что такое непрерывная интеграция?

Непрерывная интеграция (CI, от англ. Continuous Integration) — это практика в разработке программного обеспечения, при которой изменения в коде автоматически объединяются в общий репозиторий и проходят автоматическую проверку (например, сборку, запуск тестов и анализ кода) на каждом этапе разработки.

Цель непрерывной интеграции — как можно раньше выявлять ошибки и конфликты при слиянии изменений от разных разработчиков. Это повышает надёжность кода и ускоряет цикл разработки.

В данной лабораторной работе непрерывная интеграция реализована с помощью GitHub Actions: каждый раз, когда выполняется `push` в ветку `main`, запускается сборка Docker-образа и выполняются автоматические тесты.

### Для чего нужны юнит-тесты? Как часто их нужно запускать?

Юнит-тесты (модульные тесты) — это тесты, направленные на проверку работы отдельных единиц (модулей) программы, таких как функции, классы, методы.

**Зачем они нужны:**

- Обнаруживают ошибки в логике до развертывания

- Позволяют изменять код с уверенностью (рефакторинг)

- Автоматически проверяют корректность при каждом изменении

- Повышают надёжность кода

**Как часто их запускать:**

Юнит-тесты должны запускаться автоматически:

- при каждом коммите (локально или на сервере)

- при каждом `push` в удалённый репозиторий

- при каждом Pull Request

В рамках CI, тесты запускаются каждый раз, когда разработчик вносит изменения в проект.

### Что нужно изменить в `.github/workflows/main.yml`, чтобы тесты запускались при создании Pull Request?

В текущем файле `main.yml` тесты запускаются только при `push` в ветку `main`:

```yaml
on:
  push:
    branches:
      - main
```

Чтобы тесты запускались также при создании **Pull Request**, нужно добавить блок `pull_request`:

```yaml
on:
  push:
    branches:
      - main
  pull_request:
    branches:
      - main
```

Теперь CI будет запускаться и при `push`, и при каждом новом Pull Request в `main`.

### Что нужно добавить в `.github/workflows/main.yml`, чтобы удалять Docker-образы после выполнения тестов?

После завершения тестов, чтобы не загружать систему лишними образами, их желательно удалять. Для этого в конце workflow нужно добавить шаг удаления Docker-образа:

```yaml
- name: Remove Docker image
  run: docker rmi containers08 || true
```

Пояснение:

- `docker rmi containers08` — удаляет образ с именем `containers08`

- `|| true` — гарантирует, что даже если команда завершится ошибкой (например, если образ уже удалён), весь workflow не упадёт

Этот шаг должен идти после удаления контейнера, чтобы образ не использовался в процессе.

## Вывод

В ходе выполнения лабораторной работы было разработано PHP-приложение с использованием базы данных `SQLite` и реализована система непрерывной интеграции с помощью `GitHub Actions`. Приложение включает модуль для работы с базой, шаблонизатор и структуру для отображения данных. Создан простой тестовый фреймворк и написаны модульные тесты, проверяющие основные функции: подключение к базе, чтение и отображение данных. Настроен `Dockerfile` для сборки образа и автоматическая проверка проекта при каждом коммите. Все этапы успешно выполнены, проект протестирован, что подтверждает корректность реализации и соответствие требованиям задания.

---

## Библиография

1. [Официальная документация Docker](https://docs.docker.com/)
2. [GitHub Actions Documentation](https://docs.github.com/en/actions)
3. [DockerHub: php:7.4-fpm](https://hub.docker.com/_/php)
4. [PHP: Руководство по PDO и SQLite](https://www.php.net/manual/ru/book.pdo.php)
5. [SQLite Documentation](https://www.sqlite.org/docs.html)
6. [PHP: htmlspecialchars – Официальная документация](https://www.php.net/manual/ru/function.htmlspecialchars.php)
7. [YAML Syntax for GitHub Actions](https://docs.github.com/en/actions/using-workflows/workflow-syntax-for-github-actions)
8. [PHP: Командная строка и запуск скриптов](https://www.php.net/manual/ru/features.commandline.php)
9. [SQL CREATE TABLE – SQLite](https://sqlite.org/lang_createtable.html)