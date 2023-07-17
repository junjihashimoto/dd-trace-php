<?php

namespace DDTrace\Tests\Integrations\WordPress\V6_1;

use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/WordPress/Version_6_1/index.php';
    }

    public function ddSetUp()
    {
        parent::ddSetUp();
        $pdo = new \PDO('mysql:host=mysql_integration;dbname=test', 'test', 'test');
        $pdo->exec(file_get_contents(__DIR__ . '/../../../Frameworks/WordPress/Version_6_1/scripts/wp_initdb.sql'));
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'DD_SERVICE' => 'wordpress_61_test_app'
        ]);
    }

    public function testScenarioGetReturnString()
    {
        if (getenv('DD_TRACE_TEST_SAPI') === 'cgi-fcgi') {
            $this->markTestSkipped("Don't run this test on cgi-fcgi");
        }

        $this->tracesFromWebRequestSnapshot(function () {
            $this->call(
                GetSpec::create(
                    'A simple GET request returning a string',
                    '/simple?key=value&pwd=should_redact'
                )
            );
        });
    }

    public function testScenarioGetReturnStringNginxFastCGI()
    {
        // Has some additional spans coming from the parsed section in class-wp.php:main()
        // that are not present in the Apache version.
        if (getenv('DD_TRACE_TEST_SAPI') !== 'cgi-fcgi') {
            $this->markTestSkipped("Run this test with cgi-fcgi");
        }

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
                    '/simple_view?key=value&pwd=should_redact'
                )
            );
        });
    }

    public function testScenarioGetWithException()
    {
        // Has some additional spans coming from the parsed section in class-wp.php:main()
        // that are not present in the Apache version.
        if (getenv('DD_TRACE_TEST_SAPI') === 'cgi-fcgi') {
            $this->markTestSkipped("Don't run this test on cgi-fcgi");
        }

        $this->tracesFromWebRequestSnapshot(function () {
            $this->call(
                GetSpec::create(
                    'A GET request with an exception',
                    '/error?key=value&pwd=should_redact'
                )->expectStatusCode(200)
            );
        });
    }

    public function testScenarioGetWithExceptionNginxFastCGI()
    {
        if (getenv('DD_TRACE_TEST_SAPI') !== 'cgi-fcgi') {
            $this->markTestSkipped("Run this test with cgi-fcgi");
        }

        $this->tracesFromWebRequestSnapshot(function () {
            $this->call(
                GetSpec::create(
                    'A GET request with an exception',
                    '/error?key=value&pwd=should_redact'
                )->expectStatusCode(200)
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