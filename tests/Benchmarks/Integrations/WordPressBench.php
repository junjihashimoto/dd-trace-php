<?php

declare(strict_types=1);

namespace DDTrace\Benchmarks;

use DDTrace\Tests\Common\TracerTestTrait;
use DDTrace\Tests\Common\Utils;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

class WordPressBench extends WebFrameworkTestCase
{
    use TracerTestTrait;

    public static function getAppIndexScript()
    {
        return __DIR__ . '/../../Frameworks/WordPress/Version_6_1/index.php';
    }

    public function disableWordPressTracing()
    {
        $pdo = new \PDO('mysql:host=mysql_integration;dbname=test', 'test', 'test');
        $pdo->exec(file_get_contents(__DIR__ . '/../../Frameworks/WordPress/Version_6_1/scripts/wp_initdb.sql'));
        $this->setUpWebServer([
            'DD_TRACE_ENABLED' => 0,
        ]);
    }

    public function afterMethod()
    {
        $this->TearDownAfterClass();
    }

    public function enableWordPressTracing()
    {
        $this->setUpWebServer([
            'DD_TRACE_ENABLED' => 1,
            'DD_TRACE_DEBUG' => 1,
        ]);
    }

    public function enableEnhancedWordPressTracing()
    {
        $this->setUpWebServer([
            'DD_TRACE_ENABLED' => 1,
            'DD_TRACE_DEBUG' => 1,
            'DD_TRACE_WORDPRESS_ENHANCED_INTEGRATION' => '1'
        ]);
    }

    /**
     * @BeforeMethods("disableWordPressTracing")
     * @AfterMethods("afterMethod")
     * @Revs(5)
     * @Iterations(5)
     * @OutputTimeUnit("microseconds")
     * @RetryThreshold(3.0)
     */
    public function benchWordPressBaseline()
    {
        Utils::call(GetSpec::create(
            'A simple GET request with a view',
            '/simple_view?key=value&pwd=should_redact'
        ));
    }

    /**
     * @BeforeMethods("enableWordPressTracing")
     * @AfterMethods("afterMethod")
     * @Revs(5)
     * @Iterations(5)
     * @OutputTimeUnit("microseconds")
     * @RetryThreshold(3.0)
     */
    public function benchWordPressOverhead()
    {
        Utils::call(GetSpec::create(
            'A simple GET request with a view',
            '/simple_view?key=value&pwd=should_redact'
        ));
    }

    /**
     * @BeforeMethods("enableEnhancedWordPressTracing")
     * @AfterMethods("afterMethod")
     * @Revs(5)
     * @Iterations(5)
     * @OutputTimeUnit("microseconds")
     * @RetryThreshold(3.0)
     */
    public function benchEnhancedWordPressOverhead()
    {
        Utils::call(GetSpec::create(
            'A simple GET request with a view',
            '/simple_view?key=value&pwd=should_redact'
        ));
    }
}
