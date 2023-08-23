<?php

namespace DDTrace\Tests\Integrations\Drupal\V8_9;

use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

class CommonScenariosTest extends WebFrameworkTestCase
{
    public $drupalRoot = __DIR__ . '/../../../Frameworks/Drupal/Version_8_9';

    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Drupal/Version_8_9/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), ['DD_SERVICE' => 'test_drupal_89']);
    }

    public function ddSetUp()
    {
        parent::ddSetUp();
        // Run the drupalRoot/scripts/drupal_db_init.php script
        // to create the database and install Drupal.
        //$this->runScript($this->drupalRoot . '/scripts/drupal_db_init.php');
        $pdo = new \PDO('mysql:host=mysql_integration;dbname=test', 'test', 'test');
        $cacheTables = $pdo->query("SHOW TABLES LIKE 'cache%'");
        while ($table = $cacheTables->fetchColumn()) {
            //fwrite(STDERR, "Truncating table $table" . PHP_EOL);
            $pdo->query('TRUNCATE ' . $table);
        }
    }

    public function runScript($script)
    {
        $cmd = 'php ' . $script;
        $output = [];
        $returnCode = null;
        exec($cmd, $output, $returnCode);
        if ($returnCode !== 0) {
            fwrite(STDERR, "Output from $cmd:" . PHP_EOL);
            fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
            throw new \Exception("Script $script failed with return code $returnCode");
        }
    }

    public function testScenarioGetReturnString()
    {
        $this->tracesFromWebRequestSnapshot(function () {
            $this->call(
                GetSpec::create(
                    'A simple GET request returning a string',
                    '/simple?key=value&pwd=should_redact'
                )
            );
        });
    }

    public function testScenarioGetWithView()
    {
        $this->tracesFromWebRequestSnapshot(function () {
            $this->call(
                GetSpec::create(
                    'A simple GET request with a view',
                    '/simple_view'
                )
            );
        });
    }

    public function testScenarioGetWithException()
    {
        $this->tracesFromWebRequestSnapshot(function () {
            $this->call(
                GetSpec::create(
                    'A GET request with an exception',
                    '/error?key=value&pwd=should_redact'
                )->expectStatusCode(500)
            );
        });
    }

    public function testScenarioGetToMissingRoute()
    {
        $this->tracesFromWebRequestSnapshot(function () {
            $this->call(
                GetSpec::create(
                    'A GET request to a missing route',
                    '/does_not_exist?key=value&pwd=should_redact'
                )->expectStatusCode(404)
            );
        });
    }
}
