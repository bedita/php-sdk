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
}
