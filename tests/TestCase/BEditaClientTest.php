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
     * Data provider for `testGetRelated`
     */
    public function getRelatedProvider()
    {
        return [
            '200 OK, User 1 Roles' => [
                [
                    'id' => 1,
                    'type' => 'users',
                    'relation' => 'roles',
                ],
                [
                    'code' => 200,
                    'message' => 'OK',
                ],
            ],
        ];
    }

    /**
     * Test `getRelated` method
     *
     * @return void
     *
     * @covers ::getRelated()
     * @dataProvider getRelatedProvider
     */
    public function testGetRelated($input, $expected)
    {
        $this->authenticate();
        $result = $this->client->getRelated($input['id'], $input['type'], $input['relation']);
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());
        static::assertNotEmpty($result);
        static::assertArrayHasKey('data', $result);
    }

    /**
     * Data provider for `testAddRemoveRelated`
     */
    public function addRemoveRelatedProvider()
    {
        return [
            'user => roles => admin' => [
                [
                    // new user data
                    [
                        'type' => 'users',
                        'data' => [
                            'title' => 'test user',
                            'username' => base64_encode(random_bytes(10)), // random ~14 chars
                            'password' => base64_encode(random_bytes(10)),
                            'uname' => base64_encode(random_bytes(10)),
                        ],
                    ],
                    // the relation
                    [
                        'relation' => 'roles',
                    ],
                    // the role data
                    [
                        'type' => 'roles',
                        'id' => 1,
                    ],
                ],
                [
                    // expected response from addRelated
                    [
                        'code' => 200,
                        'message' => 'OK',
                    ],
                    // expected response from removeRelated
                    [
                        'code' => 200,
                        'message' => 'OK',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test `addRelated` method
     *
     * @return void
     *
     * @covers ::addRelated()
     * @covers ::removeRelated()
     * @dataProvider addRemoveRelatedProvider
     */
    public function testAddRemoveRelated($input, $expected)
    {
        $this->authenticate();

        // create object
        $object = $input[0];
        $response = $this->client->saveObject($object['type'], $object['data']);

        // add related
        $id = $response['data']['id'];
        $type = $response['data']['type'];
        $relation = $input[1]['relation'];
        $relationPayload = $input[2];
        $result = $this->client->addRelated($id, $type, $relation, $relationPayload);
        static::assertEquals($expected[0]['code'], $this->client->getStatusCode());
        static::assertEquals($expected[0]['message'], $this->client->getStatusMessage());

        // remove related
        $result = $this->client->removeRelated($id, $type, $relation, $relationPayload);
        static::assertEquals($expected[1]['code'], $this->client->getStatusCode());
        static::assertEquals($expected[1]['message'], $this->client->getStatusMessage());

        // delete object
        $response = $this->client->deleteObject($id, $type);

        // permanently remove object
        $response = $this->client->remove($id);
    }

    /**
     * Data provider for `testAddRemoveRelated`
     */
    public function saveDeleteProvider()
    {
        return [
            'document' => [
                [
                    // new document data
                    'type' => 'documents',
                    'data' => [
                        'title' => 'this is a test document',
                    ],
                ],
                [
                    // expected response from saveObject new
                    [
                        'code' => 201,
                        'message' => 'Created',
                    ],
                    // expected response from saveObject update
                    [
                        'code' => 200,
                        'message' => 'OK',
                    ],
                    // expected response from deleteObject
                    [
                        'code' => 204,
                        'message' => 'No Content',
                    ],
                    // expected response from restoreObject
                    [
                        'code' => 204,
                        'message' => 'No Content',
                    ],
                    // expected response from remove
                    [
                        'code' => 204,
                        'message' => 'No Content',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test `saveObject` method
     *
     * @return void
     *
     * @covers ::saveObject()
     * @covers ::deleteObject()
     * @covers ::restoreObject()
     * @covers ::remove()
     * @dataProvider saveDeleteProvider
     */
    public function testSaveDeleteObject($input, $expected)
    {
        $this->authenticate();

        // save new object
        $response = $this->client->saveObject($input['type'], $input['data']);
        static::assertEquals($expected[0]['code'], $this->client->getStatusCode());
        static::assertEquals($expected[0]['message'], $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertNotEmpty($response['data']['id']);

        // update object
        $input['data']['id'] = $response['data']['id'];
        $input['data']['title'] = 'A new title';
        $response = $this->client->saveObject($input['type'], $input['data']);
        static::assertEquals($expected[1]['code'], $this->client->getStatusCode());
        static::assertEquals($expected[1]['message'], $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertArrayHasKey('attributes', $response['data']);
        static::assertEquals($input['data']['title'], $response['data']['attributes']['title']);

        // delete object
        $id = $response['data']['id'];
        $type = $response['data']['type'];
        $response = $this->client->deleteObject($id, $type);
        static::assertEquals($expected[2]['code'], $this->client->getStatusCode());
        static::assertEquals($expected[2]['message'], $this->client->getStatusMessage());

        // restore object
        $response = $this->client->restoreObject($id, $type);
        static::assertEquals($expected[3]['code'], $this->client->getStatusCode());
        static::assertEquals($expected[3]['message'], $this->client->getStatusMessage());

        // delete again the object (previously restored)
        $response = $this->client->deleteObject($id, $type);

        // permanently remove object
        $response = $this->client->remove($id);
        static::assertEquals($expected[4]['code'], $this->client->getStatusCode());
        static::assertEquals($expected[4]['message'], $this->client->getStatusMessage());
    }

    /**
     * Test `upload` and `createMediaFromStream` methods
     *
     * @return void
     *
     * @covers ::upload()
     * @covers ::createMediaFromStream()
     */
    public function testUploadCreate()
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
        $response = $this->client->createMediaFromStream($streamId, $type, $body);
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

    /**
     * Data provider for `testUpload`
     */
    public function uploadProvider()
    {
        return [
            '500 File not found' => [
                [
                    'filename' => 'not-on-file-system.jpg',
                    'filepath' => getcwd() . '/tests/files/not-on-file-system.jpg',
                    'headers' => null,
                ],
                new BEditaClientException('File not found', 500),
            ],
            '500 File get contents failed' => [
                [
                    'filename' => 'test-bad.jpg',
                    'filepath' => getcwd() . '/tests/files/test-bad.jpg',
                    'headers' => null,
                ],
                new BEditaClientException('File get contents failed', 500),
            ],
            '201 Created, Force content type' => [
                [
                    'filename' => 'test.png',
                    'filepath' => getcwd() . '/tests/files/test.png',
                    'headers' => [ 'Content-Type' => 'image/png' ],
                ],
                [
                    'code' => 201,
                    'message' => 'Created',
                ],
            ],
            '201 Created, Content type from mime' => [
                [
                    'filename' => 'test.png',
                    'filepath' => getcwd() . '/tests/files/test.png',
                    'headers' => null,
                ],
                [
                    'code' => 201,
                    'message' => 'Created',
                ],
            ],
        ];
    }

    /**
     * Test `upload`.
     *
     * @param mixed $input Input data for upload
     * @param mixed $expected Expected result
     * @return void
     *
     * @dataProvider uploadProvider
     * @covers ::upload()
     * @covers ::createMediaFromStream()
     */
    public function testUpload($input, $expected)
    {
        $this->authenticate();
        if ($expected instanceof \Exception) {
            static::expectException(get_class($expected));
            static::expectExceptionMessage($expected->getMessage());
        }
        $result = $this->client->upload($input['filename'], $input['filepath'], $input['headers']);
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());
        static::assertNotEmpty($result);
        static::assertArrayHasKey('data', $result);
        static::assertArrayHasKey('attributes', $result['data']);
        static::assertEquals($input['filename'], $result['data']['attributes']['file_name']);
    }
}
