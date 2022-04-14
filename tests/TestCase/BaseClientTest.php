<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2022 Atlas Srl, ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\SDK\Test\TestCase;

use BEdita\SDK\BaseClient;
use BEdita\SDK\BEditaClient;
use BEdita\SDK\BEditaClientException;
use PHPUnit\Framework\TestCase;

/**
 * A simple class that extends BaseClient.
 * Used to test protected methods.
 */
class MyBaseClient extends BaseClient
{
    /**
     * @inheritDoc
     */
    public function sendRequestRetry(string $method, string $path, ?array $query = null, ?array $headers = null, $body = null): \Psr\Http\Message\ResponseInterface
    {
        return parent::sendRequestRetry($method, $path, $query, $headers, $body);
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(string $method, string $path, ?array $query = null, ?array $headers = null, $body = null): \Psr\Http\Message\ResponseInterface
    {
        return parent::sendRequest($method, $path, $query, $headers, $body);
    }
}

/**
 * \BEdita\SDK\BaseClient Test Case
 *
 * @coversDefaultClass \BEdita\SDK\BaseClient
 */
class BaseClientTest extends TestCase
{
    /**
     * Test API base URL
     *
     * @var string
     */
    private $apiBaseUrl = null;

    /**
     * Test API KEY
     *
     * @var string
     */
    private $apiKey = null;

    /**
     * Test Admin user
     *
     * @var string
     */
    private $adminUser = null;

    /**
     * Test Admin user
     *
     * @var string
     */
    private $adminPassword = null;

    /**
     * Test client class
     *
     * @var \BEdita\SDK\BaseClient
     */
    private $client = null;

    /**
     * BEdita client class
     *
     * @var \BEdita\SDK\BEditaClient
     */
    private $beditaClient = null;

    /**
     * Test client class for protected methods testing
     *
     * @var \BEdita\SDK\Test\TestCase\MyBaseClient
     */
    private $myclient = null;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->apiBaseUrl = getenv('BEDITA_API');
        $this->apiKey = getenv('BEDITA_API_KEY');
        $this->adminUser = getenv('BEDITA_ADMIN_USR');
        $this->adminPassword = getenv('BEDITA_ADMIN_PWD');
        $this->client = new BaseClient($this->apiBaseUrl, $this->apiKey);
        $this->myclient = new MyBaseClient($this->apiBaseUrl, $this->apiKey);
        $this->beditaClient = new BEditaClient($this->apiBaseUrl, $this->apiKey);
    }

    /**
     * Test client constructor
     *
     * @return void
     * @covers ::__construct()
     */
    public function testConstruct(): void
    {
        $client = new BaseClient($this->apiBaseUrl, $this->apiKey);
        static::assertNotEmpty($client);
        static::assertEquals($client->getApiBaseUrl(), $this->apiBaseUrl);
    }

    /**
     * Test `setupTokens` method
     *
     * @return void
     * @covers ::setupTokens()
     */
    public function testSetupTokens(): void
    {
        $token = ['jwt' => '12345', 'renew' => '67890'];
        $this->client->setupTokens($token);

        $headers = $this->client->getDefaultHeaders();
        static::assertNotEmpty($headers['Authorization']);
        static::assertEquals('Bearer 12345', $headers['Authorization']);

        $this->client->setupTokens([]);
        $headers = $this->client->getDefaultHeaders();
        static::assertArrayNotHasKey('Authorization', $headers);
    }

    /**
     * Test code/message/response body method
     *
     * @return void
     * @covers ::getStatusCode()
     * @covers ::getStatusMessage()
     * @covers ::getResponseBody()
     */
    public function testCodeMessageResponse(): void
    {
        $this->beditaClient->get('/status');
        static::assertEquals(200, $this->beditaClient->getStatusCode());
        static::assertEquals('OK', $this->beditaClient->getStatusMessage());
        static::assertNotEmpty($this->beditaClient->getResponseBody());

        $localClient = new BaseClient($this->apiBaseUrl, $this->apiKey);
        static::assertNull($localClient->getStatusCode());
        static::assertNull($localClient->getStatusMessage());
        static::assertNull($localClient->getResponseBody());
    }

    /**
     * Data provider for `testGetResponseBody`
     */
    public function responseBodyProvider(): array
    {
        return [
            'get users' => [
                [
                    'method' => 'GET',
                    'path' => '/users',
                    'query' => null,
                    'headers' => null,
                    'body' => null,
                ],
                [
                    'code' => 200,
                    'message' => 'OK',
                    'fields' => ['data', 'links', 'meta'],
                ],
            ],
            'get unexisting user' => [
                [
                    'method' => 'GET',
                    'path' => '/users/9999999',
                    'query' => null,
                    'headers' => null,
                    'body' => null,
                ],
                new BEditaClientException('[404] Not Found', 404),
            ],
        ];
    }

    /**
     * Test `getResponseBody`.
     *
     * @param mixed $input Input data
     * @param mixed $expected Expected result
     * @return void
     *
     * @covers ::getResponseBody()
     * @dataProvider responseBodyProvider()
     */
    public function testGetResponseBody($input, $expected): void
    {
        $response = $this->beditaClient->authenticate($this->adminUser, $this->adminPassword);
        $this->myclient->setupTokens($response['meta']);
        if ($expected instanceof \Exception) {
            $this->expectException(get_class($expected));
            $this->expectExceptionCode($expected->getCode());
        }
        $this->myclient->sendRequestRetry($input['method'], $input['path']);
        $response = $this->myclient->getResponseBody();
        static::assertEquals($expected['code'], $this->myclient->getStatusCode());
        static::assertEquals($expected['message'], $this->myclient->getStatusMessage());
        static::assertNotEmpty($response);
        foreach ($expected['fields'] as $key => $val) {
            static::assertNotEmpty($response[$val]);
        }
    }

    /**
     * Data provider for `testSendRequest`
     */
    public function sendRequestProvider(): array
    {
        return [
            'get users' => [
                [
                    'method' => 'GET',
                    'path' => '/users',
                    'query' => ['q' => 'gustavosupporto'],
                    'headers' => null,
                    'body' => null,
                ],
                [
                    'code' => 200,
                    'message' => 'OK',
                    'fields' => ['links', 'meta'],
                ],
            ],
            'get users query in path' => [
                [
                    'method' => 'GET',
                    'path' => '/users?name=gustavo',
                    'query' => ['surname' => 'supporto'],
                    'headers' => null,
                    'body' => null,
                ],
                [
                    'code' => 200,
                    'message' => 'OK',
                    'fields' => ['links', 'meta'],
                ],
            ],
            'post users bad query' => [
                [
                    'method' => 'POST',
                    'path' => '/users/a/b/c',
                    'query' => null,
                    'headers' => null,
                    'body' => 'body',
                ],
                new BEditaClientException('[404] Not Found', 404),
            ],
            'no start slash' => [
                [
                    'method' => 'GET',
                    'path' => 'users',
                    'query' => null,
                    'headers' => null,
                    'body' => null,
                ],
                [
                    'code' => 200,
                    'message' => 'OK',
                    'fields' => ['data', 'links', 'meta'],
                ],
            ],
            'get absolute' => [
                [
                    'method' => 'GET',
                    'path' => getenv('BEDITA_API') . '/users',
                    'query' => null,
                    'headers' => null,
                    'body' => null,
                ],
                [
                    'code' => 200,
                    'message' => 'OK',
                    'fields' => ['data', 'links', 'meta'],
                ],
            ],
            'absolute path with 404' => [
                [
                    'method' => 'GET',
                    'path' => 'http://example.com/zzzzz',
                    'query' => null,
                    'headers' => null,
                    'body' => null,
                ],
                new BEditaClientException('Not Found', 404),
            ],
        ];
    }

    /**
     * Test `sendRequest`.
     *
     * @param mixed $input Input data
     * @param mixed $expected Expected result
     * @return void
     *
     * @covers ::sendRequest()
     * @covers ::requestUri()
     * @dataProvider sendRequestProvider()
     */
    public function testSendRequest($input, $expected): void
    {
        if ($expected instanceof \Exception) {
            $this->expectException(get_class($expected));
            $this->expectExceptionCode($expected->getCode());
            $this->expectExceptionMessage($expected->getMessage());
        }
        $method = $input['method'];
        $path = $input['path'];
        $query = $input['query'];
        $headers = $input['headers'];
        $body = $input['body'];
        $response = $this->myclient->sendRequest($method, $path, $query, $headers, $body);
        $responseBody = json_decode((string)$response->getBody(), true);
        static::assertEquals($expected['code'], $this->myclient->getStatusCode());
        static::assertEquals($expected['message'], $this->myclient->getStatusMessage());
        static::assertNotEmpty($responseBody);
        foreach ($expected['fields'] as $key => $val) {
            static::assertNotEmpty($responseBody[$val]);
        }
    }

    /**
     * Data provider for `testSendRequestRetry`
     */
    public function sendRequestRetryProvider(): array
    {
        return [
            'get users' => [
                [
                    'method' => 'GET',
                    'path' => '/users',
                    'query' => null,
                    'headers' => null,
                    'body' => null,
                ],
                [
                    'code' => 200,
                    'message' => 'OK',
                    'fields' => ['data', 'links', 'meta'],
                ],
            ],
            'get users bad query' => [
                [
                    'method' => 'GET',
                    'path' => '/users/a/b/c',
                    'query' => null,
                    'headers' => null,
                    'body' => null,
                ],
                new BEditaClientException('[404] Not Found', 404),
            ],
            'post users unauthorized' => [
                [
                    'method' => 'POST',
                    'path' => '/users',
                    'query' => null,
                    'headers' => null,
                    'body' => null,
                ],
                new BEditaClientException('Unauthorized', 401),
            ],
        ];
    }

    /**
     * Test `sendRequestRetry`.
     *
     * @param mixed $input Input data
     * @param mixed $expected Expected result
     * @return void
     *
     * @covers ::sendRequestRetry()
     * @dataProvider sendRequestRetryProvider()
     */
    public function testSendRequestRetry($input, $expected): void
    {
        if ($expected instanceof \Exception) {
            $this->expectException(get_class($expected));
            $this->expectExceptionCode($expected->getCode());
        }
        $method = $input['method'];
        $path = $input['path'];
        $query = $input['query'];
        $headers = $input['headers'];
        $body = $input['body'];
        $response = $this->myclient->sendRequestRetry($method, $path, $query, $headers, $body);
        $responseBody = json_decode((string)$response->getBody(), true);
        static::assertEquals($expected['code'], $this->myclient->getStatusCode());
        static::assertEquals($expected['message'], $this->myclient->getStatusMessage());
        static::assertNotEmpty($responseBody);
        if (!empty($expected['fields'])) {
            foreach ($expected['fields'] as $key => $val) {
                static::assertNotEmpty($responseBody[$val]);
            }
        }
    }

    /**
     * Data provider for `testRefreshTokens`
     */
    public function refreshTokensProvider(): array
    {
        return [
            'renew token as not logged' => [
                ['authenticate' => false],
                new \BadMethodCallException('You must be logged in to renew token'),
            ],
            // FIXME: restore this test when BE 4.7 is released
            'wrong renew token' => [
                ['authenticate' => true, 'token' => 'gustavo' ],
                new BEditaClientException('[401] Wrong number of segments', 401),
            ],
            'renew token as logged' => [
                ['authenticate' => true],
                [
                    'code' => 200,
                    'message' => 'OK',
                ],
            ],
        ];
    }

    /**
     * Test `refreshTokens`.
     *
     * @param mixed $input Input data
     * @param mixed $expected Expected result
     * @return void
     *
     * @covers ::refreshTokens()
     * @dataProvider refreshTokensProvider()
     */
    public function testRefreshTokens($input, $expected): void
    {
        if ($expected instanceof \Exception) {
            $this->expectException(get_class($expected));
            $this->expectExceptionCode($expected->getCode());
            $this->expectExceptionMessage($expected->getMessage());
        }
        if ($input['authenticate'] === true) {
            $response = $this->beditaClient->authenticate($this->adminUser, $this->adminPassword);
            $this->client->setupTokens($response['meta']);
        }
        if (!empty($input['token'])) {
            $token = [
                'jwt' => $input['token'],
                'renew' => $input['token'],
            ];
            $this->client->setupTokens($token);
        }
        $response = $this->client->refreshTokens();
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());
        static::assertEmpty($response);
    }
}
