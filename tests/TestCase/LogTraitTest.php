<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2018 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\SDK\Test\TestCase;

use BEdita\SDK\BEditaClient;
use BEdita\SDK\LogTrait;
use PHPUnit\Framework\TestCase;

/**
 * Test class for LogTrait
 */
class LogTraitTest extends TestCase
{
    /**
     * SDK Client class
     *
     * @var \BEdita\SDK\BEditaClient
     */
    private BEditaClient $client;

    /**
     * Log file path
     *
     * @var string
     */
    private string $logFile;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->client = new BEditaClient(getenv('BEDITA_API'), getenv('BEDITA_API_KEY'));
        $response = $this->client->authenticate(getenv('BEDITA_ADMIN_USR'), getenv('BEDITA_ADMIN_PWD'));
        $this->client->setupTokens($response['meta']);

        // init empty log file
        $this->logFile = getcwd() . '/tests/files/api.log';
        file_put_contents($this->logFile, '');
    }

    /**
     * Test `initLogger`
     *
     * @return void
     */
    public function testInitLogger(): void
    {
        $res = $this->client->initLogger(['log_file' => $this->logFile]);
        static::assertTrue($res);
    }

    /**
     * Test `initLogger` failure
     *
     * @return void
     */
    public function testInitLoggerFailure(): void
    {
        $res = $this->client->initLogger([]);
        static::assertFalse($res);
    }

    /**
     * Test `logRequest` & `logResponse` (requestHeadersCleanup, requestBodyCleanup)
     *
     * @return void
     */
    public function testLogRequestResponse(): void
    {
        $res = $this->client->initLogger(['log_file' => $this->logFile]);
        static::assertTrue($res);

        $this->client->get('/home');
        $lines = file($this->logFile);
        static::assertNotEmpty($lines);
        static::assertEquals(2, count($lines));
        static::assertTrue(strpos($lines[0], '***************') !== false);
        static::assertTrue(strpos($lines[0], 'Body (empty)') !== false);
    }

    /**
     * Test `maskPasswordField`.
     *
     * @return void
     */
    public function testMaskPasswordField(): void
    {
        $mask = '***************';
        $data = [
            'password' => 'secret',
            'old_password' => 'secret',
            'confirm-password' => 'secret',
        ];
        $expected = [
            'password' => $mask,
            'old_password' => $mask,
            'confirm-password' => $mask,
        ];
        $dummy = new class {
            use LogTrait;
        };
        $dummy->maskPasswordField($data, 'password');
        $dummy->maskPasswordField($data, 'old_password');
        $dummy->maskPasswordField($data, 'confirm-password');
        static::assertSame($expected, $data);

        $data = ['data' => ['attributes' => $data]];
        $expected = ['data' => ['attributes' => $expected]];
        $dummy->maskPasswordField($data, 'password');
        $dummy->maskPasswordField($data, 'old_password');
        $dummy->maskPasswordField($data, 'confirm-password');
        static::assertSame($expected, $data);
    }

    /**
     * Test empty logRequest` & `logResponse`
     *
     * @return void
     */
    public function testEmptyLogRequestResponse(): void
    {
        $this->client->get('/home');
        $lines = file($this->logFile);
        static::assertEmpty($lines);
    }

    /**
     * Test `requestBodyCleanup` & `responseBodyCleanup()` (+ maskPasswordField)
     *
     * @return void
     */
    public function testBodyCleanup(): void
    {
        $res = $this->client->initLogger(['log_file' => $this->logFile]);
        static::assertTrue($res);

        $this->client->authenticate(getenv('BEDITA_ADMIN_USR'), getenv('BEDITA_ADMIN_PWD'));
        $lines = file($this->logFile);
        static::assertEquals(2, count($lines));
        static::assertTrue(strpos($lines[0], '***************') !== false);
        static::assertTrue(strpos($lines[1], '***************') !== false);
    }

    /**
     * Test empty `responseBodyCleanup`
     *
     * @return void
     */
    public function testEmptyResponseBodyCleanup(): void
    {
        $res = $this->client->initLogger(['log_file' => $this->logFile]);
        static::assertTrue($res);

        $data = ['type' => 'documents'];
        $response = $this->client->post('/documents', json_encode(compact('data')));
        $response = $this->client->deleteObject($response['data']['id'], 'documents');
        $lines = file($this->logFile);
        static::assertEquals(4, count($lines));
        static::assertTrue(strpos($lines[3], 'Body (empty)') !== false);
    }
}
