<?php

namespace DDTrace\Tests\Integrations\RdKafkaTest;

use DDTrace\Tag;
use DDTrace\Integrations\PHPRedis\PHPRedisIntegration;
use DDTrace\Tests\Common\IntegrationTestCase;
use DDTrace\Tests\Common\SpanAssertion;
use Exception;

class RdKafkaTest extends IntegrationTestCase
{
    const A_STRING = 'A_STRING';

    private $host = 'redis_integration';
    /** Redis */
    private $redis;
    /** \RdKafka\Producer */
    private $producer;
    /** \RdKafka\Consumer */
    private $consumer;

    private function produceAndWaitForConsume($nMessages) {
        $conf = new \RdKafka\Conf();
        $this->producer = new \RdKafka\Producer($conf);
        $this->producer->addBrokers("kafka_integration:9092");
        $producerTopic = $this->producer->newTopic("test");

        for ($i = 0; $i < $nMessages; $i++) {
            $producerTopic->produce(RD_KAFKA_PARTITION_UA, 0, "Message payload " . $i);
        }

        $this->consumer = new \RdKafka\Consumer($conf);
        $this->consumer->addBrokers("kafka_integration:9092");
        $consumerTopic = $this->consumer->newTopic("test");
        $consumerTopic->consumeStart(0, RD_KAFKA_OFFSET_BEGINNING);

        $readMessages = 0;
        while ($readMessages < $nMessages) {
            // The first argument is the partition (again).
            // The second argument is the timeout.
            $msg = $consumerTopic->consume(0, 1000);
            if (null === $msg || $msg->err === RD_KAFKA_RESP_ERR__PARTITION_EOF) {
                // Constant check required by librdkafka 0.11.6. Newer librdkafka versions will return NULL instead.
                continue;
            } elseif ($msg->err) {
                printf($msg->errstr());
                break;
            } else {
                printf($msg->payload);
                $readMessages++;
            }
        }
    }

    public function ddSetUp()
    {
        parent::ddSetUp();
    }

    public function testKafkaNoIntegration()
    {
        $this->produceAndWaitForConsume(3);
        $this->assertTrue(true);
    }
    public function ddTearDown()
    {
        $this->producer->flush(1000);
        parent::ddTearDown();
    }
}
