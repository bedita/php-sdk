<?php
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
use BEdita\SDK\BEditaClientException;
use PHPUnit\Framework\TestCase;

/**
 * \BEdita\SDK\BEditaClient Test Case
 *
 * @coversDefaultClass \BEdita\SDK\BEditaClient
 */
class BEditaClientTest extends TestCase
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
     * @var \BEdita\SDK\BEditaClient
     */
    private $client = null;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->apiBaseUrl = getenv('BEDITA_API');
        $this->apiKey = getenv('BEDITA_API_KEY');
        $this->adminUser = getenv('BEDITA_ADMIN_USR');
        $this->adminPassword = getenv('BEDITA_ADMIN_PWD');
        $this->client = new BEditaClient($this->apiBaseUrl, $this->apiKey);
    }

    /**
     * Test client constructor
     *
     * @return void
     *
     * @covers ::__construct()
     */
    public function testConstruct()
    {
        $client = new BEditaClient($this->apiBaseUrl, $this->apiKey);
        static::assertNotEmpty($client);
        static::assertEquals($client->getApiBaseUrl(), $this->apiBaseUrl);
    }

    /**
     * Test `get` method
     *
     * @return void
     *
     * @covers ::get()
     */
    public function testGet()
    {
        $response = $this->client->get('/status');
        static::assertNotEmpty($response);
        static::assertNotEmpty($response['meta']['status']);
    }

    /**
     * Test `setupTokens` method
     *
     * @return void
     *
     * @covers ::setupTokens()
     */
    public function testSetupTokens()
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
     *
     * @covers ::getStatusCode()
     * @covers ::getStatusMessage()
     * @covers ::getResponseBody()
     */
    public function testCodeMessageResponse()
    {
        $response = $this->client->get('/status');
        static::assertEquals(200, $this->client->getStatusCode());
        static::assertEquals('OK', $this->client->getStatusMessage());
        static::assertNotEmpty($this->client->getResponseBody());

        $localClient = new BEditaClient($this->apiBaseUrl, $this->apiKey);
        static::assertNull($localClient->getStatusCode());
        static::assertNull($localClient->getStatusMessage());
        static::assertNull($localClient->getResponseBody());
    }

    /**
     * Test `authenticate` method
     *
     * @return void
     *
     * @covers ::authenticate()
     */
    public function testAuthenticate()
    {
        $response = $this->client->authenticate($this->adminUser, $this->adminPassword);
        static::assertEquals(200, $this->client->getStatusCode());
        static::assertEquals('OK', $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('meta', $response);
        static::assertArrayHasKey('jwt', $response['meta']);
        static::assertArrayHasKey('renew', $response['meta']);
        static::assertArrayNotHasKey('error', $response);
    }

    /**
     * Test `authenticate` method failure
     *
     * @return void
     *
     * @covers ::authenticate()
     */
    public function testAuthenticateFail()
    {
        $expected = new BEditaClientException('[401] Login not successful');
        static::expectException(get_class($expected));
        static::expectExceptionMessage($expected->getMessage());
        $this->client->authenticate('baduser', 'badpassword');
    }

    /**
     * Authenticate and set auth tokens
     *
     * @return void
     */
    private function authenticate()
    {
        $response = $this->client->authenticate($this->adminUser, $this->adminPassword);
        $this->client->setupTokens($response['meta']);
    }

    /**
     * Test `getObjects` method
     *
     * @return void
     *
     * @covers ::getObjects()
     */
    public function testGetObjects()
    {
        $this->authenticate();
        $response = $this->client->getObjects();
        static::assertEquals(200, $this->client->getStatusCode());
        static::assertEquals('OK', $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertArrayNotHasKey('error', $response);
    }

    /**
     * Test `getObject` method
     *
     * @return void
     *
     * @covers ::getObject()
     */
    public function testGetObject()
    {
        $this->authenticate();
        $response = $this->client->getObject(1);
        static::assertEquals(200, $this->client->getStatusCode());
        static::assertEquals('OK', $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertArrayNotHasKey('error', $response);
    }

    /**
     * Test `saveObject` method
     *
     * @return void
     *
     * @covers ::saveObject()
     */
    public function testSaveObject()
    {
        $this->authenticate();
        $data = [
            'title' => 'A title',
        ];
        $response = $this->client->saveObject('documents', $data);
        static::assertEquals(201, $this->client->getStatusCode());
        static::assertEquals('Created', $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertNotEmpty($response['data']['id']);

        $data = [
            'id' => $response['data']['id'],
            'title' => 'A new title',
        ];
        $response = $this->client->saveObject('documents', $data);
        static::assertEquals(200, $this->client->getStatusCode());
        static::assertEquals('OK', $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertArrayHasKey('attributes', $response['data']);
        static::assertEquals($data['title'], $response['data']['attributes']['title']);
    }

    /**
     * Test `upload` method
     *
     * @return void
     *
     * @covers ::get()
     * @covers ::upload()
     * @covers ::createStreamMedia()
     * @covers ::patch()
     * @covers ::post()
     * @covers ::getObject()
     */
    public function testUpload()
    {
        $this->authenticate();
        $filename = 'test.png';
        $filepath = sprintf('%s/tests/files/%s', getcwd(), $filename);
        $response = $this->client->upload($filename, $filepath);
        static::assertEquals(201, $this->client->getStatusCode());
        static::assertEquals('Created', $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertArrayHasKey('attributes', $response['data']);
        static::assertEquals($filename, $response['data']['attributes']['file_name']);

        $streamId = $response['data']['id'];
        $response = $this->client->get(sprintf('/streams/%s', $streamId));
        static::assertEquals(200, $this->client->getStatusCode());
        static::assertEquals('OK', $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertArrayHasKey('attributes', $response['data']);
        static::assertEquals($streamId, $response['data']['id']);
        static::assertEquals($filename, $response['data']['attributes']['file_name']);

        $type = 'images';
        $title = 'A new image';
        $attributes = compact('title');
        $data = compact('type', 'attributes');
        $body = compact('data');
        $response = $this->client->createStreamMedia($streamId, $type, $body);
        static::assertEquals(200, $this->client->getStatusCode());
        static::assertEquals('OK', $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertArrayHasKey('attributes', $response['data']);
        static::assertEquals($type, $response['data']['type']);
        static::assertEquals($title, $response['data']['attributes']['title']);
        static::assertArrayHasKey('included', $response);
        static::assertArrayHasKey(0, $response['included']);
        static::assertArrayHasKey('id', $response['included'][0]);
        static::assertArrayHasKey('attributes', $response['included'][0]);
        static::assertEquals($streamId, $response['included'][0]['id']);
        static::assertEquals('streams', $response['included'][0]['type']);
    }
}
