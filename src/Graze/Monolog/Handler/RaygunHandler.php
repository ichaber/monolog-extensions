<?php
/*
 * This file is part of Monolog Extensions
 *
 * Copyright (c) 2014 Nature Delivered Ltd. <http://graze.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see  http://github.com/graze/MonologExtensions/blob/master/LICENSE
 * @link http://github.com/graze/MonologExtensions
 */
namespace Graze\Monolog\Handler;

use function error_log;
use Graze\Monolog\Formatter\RaygunFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Raygun4php\RaygunClient;

class RaygunHandler extends AbstractProcessingHandler
{
    /**
     * @var RaygunClient
     */
    protected $client = null;

    /**
     * @param RaygunClient|null $client
     * @param int $level
     * @param bool $bubble
     */
    public function __construct($client = null, $level = Logger::DEBUG, $bubble = true)
    {
        $this->client = $client;

        parent::__construct($level, $bubble);
    }

    public function setClient(RaygunClient $client)
    {
        $this->client = $client;
    }

    /**
     * @return null|RaygunClient Returns the instance of RaygunClient if set or null otherwise
     */
    public function getClient()
    {
        if (!$this->client instanceof RaygunClient) {
            error_log("RaygunClient is not set, but getter is called.");
        }
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function isHandling(array $record)
    {
        if(parent::isHandling($record) && $this->client instanceof RaygunClient) {
            $context = $record['context'];

            //Ensure only valid records will be handled and no InvalidArgumentException will be thrown
            if ((isset($context['exception']) &&
                    (
                        $context['exception'] instanceof \Exception ||
                        (PHP_VERSION_ID > 70000 && $context['exception'] instanceof \Throwable)
                    )
                ) || (isset($context['file']) && $context['line'])
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $record
     */
    protected function write(array $record)
    {
        $context = $record['context'];

        if (isset($context['exception']) &&
            (
                $context['exception'] instanceof \Exception ||
                (PHP_VERSION_ID > 70000 && $context['exception'] instanceof \Throwable)
            )
        ) {
            $this->writeException(
                $record,
                $record['formatted']['tags'],
                $record['formatted']['custom_data'],
                $record['formatted']['timestamp']
            );
        } elseif (isset($context['file']) && $context['line']) {
            $this->writeError(
                $record['formatted'],
                $record['formatted']['tags'],
                $record['formatted']['custom_data'],
                $record['formatted']['timestamp']
            );
        } else {
            throw new \InvalidArgumentException('Invalid record given.');
        }
    }

    /**
     * @param array $record
     * @param array $tags
     * @param array $customData
     * @param int|float $timestamp
     */
    protected function writeError(array $record, array $tags = array(), array $customData = array(), $timestamp = null)
    {
        $client = $this->getClient();
        if (!empty($client)) {
            $context = $record['context'];
            $this->getClient()->SendError(
                0,
                $record['message'],
                $context['file'],
                $context['line'],
                $tags,
                $customData,
                $timestamp
            );
        }
    }

    /**
     * @param array $record
     * @param array $tags
     * @param array $customData
     * @param int|float $timestamp
     */
    protected function writeException(array $record, array $tags = array(), array $customData = array(), $timestamp = null)
    {
        $client = $this->getClient();
        if (!empty($client)) {
            $this->getClient()->SendException($record['context']['exception'], $tags, $customData, $timestamp);
        }
    }

    /**
     * @return \Monolog\Formatter\FormatterInterface
     */
    protected function getDefaultFormatter()
    {
        return new RaygunFormatter();
    }
}

