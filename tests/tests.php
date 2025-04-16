<?php

require_once __DIR__ . '/testframework.php';
require_once __DIR__ . '/../site/config.php';
require_once __DIR__ . '/../site/modules/database.php';
require_once __DIR__ . '/../site/modules/page.php';

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
    $tpl = new Page(__DIR__ . '/../site/templates/index.tpl');
    $html = $tpl->Render(['title' => 'Test', 'content' => 'Hello']);
    return assertExpression(strpos($html, 'Test') !== false && strpos($html, 'Hello') !== false, "Render OK", "Render FAIL");
});

$tests->run();
echo "Result: " . $tests->getResult() . PHP_EOL;
