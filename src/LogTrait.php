<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2018 ChannelWeb Srl, Chialab Srl
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 */

namespace BEdita\SDK;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Basic SDK logging functions
 */
trait LogTrait
{
    /**
     * internal Logger
     *
     * @var \Monolog\Logger|null
     */
    protected ?Logger $logger = null;

    /**
     * Get configured logger, may be null
     *
     * @return \Monolog\Logger|null
     * @codeCoverageIgnore
     */
    public function getLogger(): ?Logger
    {
        return $this->logger;
    }

    /**
     * Initialize and configure logger
     *
     * @param array $options Configuration options, 'log_file' key with log file path is mandatory
     * @return bool True on successful initialization, false otherwise
     */
    public function initLogger(array $options): bool
    {
        // 'path' to log file is mandatory
        if (empty($options['log_file'])) {
            return false;
        }

        $this->logger = new Logger('be4-php-sdk');
        $this->logger->pushHandler(new StreamHandler($options['log_file'], Logger::DEBUG));

        return true;
    }

    /**
     * Perform request log
     *
     * @param \Psr\Http\Message\RequestInterface $request The request to log
     * @return void
     */
    public function logRequest(RequestInterface $request): void
    {
        if (!$this->logger) {
            return;
        }

        $msg = sprintf(
            'Request: %s %s - Headers %s - Body %s',
            $request->getMethod(),
            $request->getUri(),
            $this->requestHeadersCleanup($request),
            $this->requestBodyCleanup($request)
        );
        $this->logger->info($msg);
    }

    /**
     * Return request body without sensitive information.
     *
     * @param \Psr\Http\Message\RequestInterface $request The request to log
     * @return string
     */
    protected function requestBodyCleanup(RequestInterface $request): string
    {
        $body = $request->getBody()->getContents();
        if (empty($body)) {
            return '(empty)';
        }

        $data = (array)json_decode($body, true);
        foreach (['password', 'old_password', 'confirm-password'] as $field) {
            $this->maskPasswordField($data, $field);
        }

        return json_encode($data);
    }

    /**
     * Mask password fields in $data.
     *
     * @param array $data The data
     * @param string $field The field
     * @return void
     */
    public function maskPasswordField(array &$data, string $field): void
    {
        $mask = '***************';
        if (!empty($data[$field])) {
            $data[$field] = $mask;
        }
        if (!empty($data['data']['attributes'][$field])) {
            $data['data']['attributes'][$field] = $mask;
        }
    }

    /**
     * Return request headers as string without sensitive information.
     *
     * @param \Psr\Http\Message\RequestInterface $request The request to log
     * @return string
     */
    protected function requestHeadersCleanup(RequestInterface $request): string
    {
        $headers = $request->getHeaders();
        foreach (['Authorization', 'X-Api-Key'] as $h) {
            if (!empty($headers[$h]) && !empty(array_diff($headers[$h], ['']))) {
                $headers[$h] = ['***************'];
            }
        }

        return json_encode($headers);
    }

    /**
     * Perform response log
     *
     * @param \Psr\Http\Message\ResponseInterface $response The response to log
     * @return void
     */
    public function logResponse(ResponseInterface $response): void
    {
        if (!$this->logger) {
            return;
        }

        $msg = sprintf(
            'Response: %s %s - Headers %s - Body %s',
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            json_encode($response->getHeaders()),
            $this->responseBodyCleanup($response)
        );
        $this->logger->info($msg);
    }

    /**
     * Return response body without sensitive information.
     *
     * @param \Psr\Http\Message\ResponseInterface $response The response to log
     * @return string
     */
    protected function responseBodyCleanup(ResponseInterface $response): string
    {
        $body = $response->getBody()->getContents();
        if (empty($body)) {
            return '(empty)';
        }

        $data = (array)json_decode($body, true);
        foreach (['jwt', 'renew'] as $tok) {
            if (!empty($data['meta'][$tok])) {
                $data['meta'][$tok] = '***************';
            }
        }

        return json_encode($data);
    }
}
