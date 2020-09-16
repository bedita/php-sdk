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
 * A simple class that extends BEditaClient.
 * Used to test protected methods.
 */
class MyBEditaClient extends BEditaClient
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
     * Test client class for protected methods testing
     *
     * @var \BEdita\SDK\Test\TestCase\MyBEditaClient
     */
    private $myclient = null;

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
        $this->myclient = new MyBEditaClient($this->apiBaseUrl, $this->apiKey);
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
        $expected = new BEditaClientException('[401] Login request not successful');
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
     * @param mixed $input Input data
     * @param mixed $expected Expected result
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
     * Data provider for `testRelated`
     */
    public function relatedProvider()
    {
        return [
            'folder => parent => folder' => [
                // parent object type
                'folders',
                // parent object data
                [
                    'title' => 'parent',
                ],
                // child object type
                'documents',
                // child object data
                [
                    'title' => 'child',
                ],
                // the relation
                'parents',
                // expected response
                [
                    'code' => 200,
                    'message' => 'OK',
                ],
            ],
        ];
    }

    /**
     * Test `addRelated` method
     *
     * @param mixed $parentType Parent object type
     * @param mixed $parentData Parent object data
     * @param mixed $childType Child object type
     * @param mixed $childData Child object data
     * @param mixed $relation Relationship name
     * @param mixed $expected Expected result
     * @return void
     *
     * @covers ::addRelated()
     * @covers ::removeRelated()
     * @covers ::replaceRelated()
     * @dataProvider relatedProvider
     */
    public function testRelated($parentType, $parentData, $childType, $childData, $relation, $expected)
    {
        $this->authenticate();

        // create object
        $parent = $this->client->save($parentType, $parentData);
        $child = $this->client->save($childType, $childData);
        $id = $child['data']['id'];
        $parentId = $parent['data']['id'];

        // add related
        $data = [
            'id' => $parentId,
            'type' => $parentType,
        ];
        $result = $this->client->addRelated($id, $childType, $relation, [$data]);
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());

        $result = $this->client->getRelated($id, $childType, $relation);
        static::assertEquals($parentId, $result['data'][0]['id']);
        static::assertEquals(1, count($result['data']));

        // remove related
        $result = $this->client->removeRelated($id, $childType, $relation, $data);
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());

        $result = $this->client->getRelated($id, $childType, $relation);
        static::assertEmpty($result['data']);

        // replace related
        $replace = $data + [
            'meta' => [
                'relation' => [
                    'menu' => true,
                ],
            ],
        ];
        $result = $this->client->replaceRelated($id, $childType, $relation, [$replace], null, true);
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());

        $result = $this->client->getRelated($id, $childType, $relation);
        var_dump($result['data'][0]);
        static::assertEquals($parentId, $result['data'][0]['id']);
        static::assertEquals(1, count($result['data']));
        static::assertEquals(true, $result['data'][0]['meta']['relation']['menu']);

        // delete object
        $response = $this->client->deleteObject($id, $childType);
        $response = $this->client->deleteObject($parentId, $parentType);
        // permanently remove object
        $response = $this->client->remove($id);
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

    /**
     * Test `thumbs` method
     *
     * @return void
     * @covers ::thumbs()
     */
    public function testThumbs()
    {
        $this->authenticate();

        // create 2 images
        $id1 = $this->_image();
        $id2 = $this->_image();
        $ids = [$id1, $id2];

        // test thumbs(:id, :query)
        $query = ['preset' => 'default'];
        foreach ($ids as $id) {
            $response = $this->client->thumbs($id, $query);
            static::assertNotEmpty($response['meta']);
            static::assertNotEmpty($response['meta']['thumbnails']);
        }

        // test thumbs(null, ['ids' =< :ids])
        $query = ['ids' => implode(',', $ids)];
        $response = $this->client->thumbs(null, $query);
        static::assertNotEmpty($response['meta']);
        static::assertNotEmpty($response['meta']['thumbnails']);

        // test thumbs() -> exception
        $exception = new BEditaClientException('Invalid empty id|ids for thumbs');
        static::expectException(get_class($exception));
        static::expectExceptionMessage($exception->getMessage());
        $response = $this->client->thumbs();
    }

    /**
     * Create image and media stream for test.
     * Return id
     *
     * @return int The image ID.
     */
    private function _image()
    {
        $filename = 'test.png';
        $filepath = sprintf('%s/tests/files/%s', getcwd(), $filename);
        $response = $this->client->upload($filename, $filepath);

        $streamId = $response['data']['id'];
        $response = $this->client->get(sprintf('/streams/%s', $streamId));

        $type = 'images';
        $title = 'The test image';
        $attributes = compact('title');
        $data = compact('type', 'attributes');
        $body = compact('data');
        $response = $this->client->createMediaFromStream($streamId, $type, $body);

        return $response['data']['id'];
    }

    /**
     * Data provider for `testSave`
     */
    public function saveProvider()
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
                    [
                        'code' => 201,
                        'message' => 'Created',
                    ],
                    [
                        'code' => 200,
                        'message' => 'OK',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test `save`.
     *
     * @param mixed $input Input data for save
     * @param mixed $expected Expected result
     * @return void
     *
     * @dataProvider saveProvider
     * @covers ::save()
     */
    public function testSave($input, $expected)
    {
        $this->authenticate();

        // create
        $response = $this->client->save($input['type'], $input['data']);
        static::assertEquals($expected[0]['code'], $this->client->getStatusCode());
        static::assertEquals($expected[0]['message'], $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertNotEmpty($response['data']['id']);
        static::assertEquals($input['data']['title'], $response['data']['attributes']['title']);

        // update
        $input['data']['id'] = $response['data']['id'];
        $newtitle = 'This is a new title';
        $input['data']['title'] = $newtitle;
        $response = $this->client->save($input['type'], $input['data']);
        static::assertEquals($expected[1]['code'], $this->client->getStatusCode());
        static::assertEquals($expected[1]['message'], $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertNotEmpty($response['data']['id']);
        static::assertEquals($newtitle, $response['data']['attributes']['title']);
    }

    /**
     * Data provider for `testDeleteObject`
     */
    public function deleteObjectProvider()
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
                    'code' => 204,
                    'message' => 'No Content',
                ],
            ],
        ];
    }

    /**
     * Test `deleteObject`.
     *
     * @param mixed $input Input data for delete
     * @param mixed $expected Expected result
     * @return void
     *
     * @dataProvider deleteObjectProvider
     * @covers ::deleteObject()
     */
    public function testDeleteObject($input, $expected)
    {
        $this->authenticate();

        $response = $this->client->deleteObject($this->newObject($input), $input['type']);
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());
        static::assertEmpty($response);
    }

    /**
     * Data provider for `testRestoreObject`
     */
    public function restoreObjectProvider()
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
                    'code' => 204,
                    'message' => 'No Content',
                ],
            ],
        ];
    }

    /**
     * Test `restoreObject`.
     *
     * @param mixed $input Input data for restore
     * @param mixed $expected Expected result
     * @return void
     *
     * @dataProvider restoreObjectProvider
     * @covers ::restoreObject()
     */
    public function testRestoreObject($input, $expected)
    {
        $this->authenticate();

        $id = $this->newObject($input);
        $this->client->deleteObject($id, $input['type']);
        $response = $this->client->restoreObject($id, $input['type']);
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());
        static::assertEmpty($response);
    }

    /**
     * Data provider for `testRemove`
     */
    public function removeProvider()
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
                    'code' => 204,
                    'message' => 'No Content',
                ],
            ],
        ];
    }

    /**
     * Test `remove`.
     *
     * @param mixed $input Input data for remove
     * @param mixed $expected Expected result
     * @return void
     *
     * @dataProvider removeProvider
     * @covers ::remove()
     */
    public function testRemove($input, $expected)
    {
        $this->authenticate();

        $id = $this->newObject($input);
        $this->client->deleteObject($id, $input['type']);
        $response = $this->client->remove($id);
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());
        static::assertEmpty($response);
    }

    /**
     * Data provider for `testPost`
     */
    public function postProvider()
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
                // expected response from post
                [
                    'code' => 201,
                    'message' => 'Created',
                ],
            ],
        ];
    }

    /**
     * Test `post`.
     *
     * @param mixed $input Input data for post
     * @param mixed $expected Expected result
     * @return void
     *
     * @dataProvider postProvider
     * @covers ::post()
     */
    public function testPost($input, $expected)
    {
        $this->authenticate();

        $type = $input['type'];
        $body = [
            'data' => [
                'type' => $type,
                'attributes' => $input['data'],
            ],
        ];
        $response = $this->client->post(sprintf('/%s', $type), json_encode($body));
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertNotEmpty($response['data']['id']);
    }

    /**
     * Data provider for `testPatch`
     */
    public function patchProvider()
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
                // expected response from patch
                [
                    'code' => 200,
                    'message' => 'OK',
                ],
            ],
        ];
    }

    /**
     * Test `patch`.
     *
     * @param mixed $input Input data for patch
     * @param mixed $expected Expected result
     * @return void
     *
     * @dataProvider patchProvider
     * @covers ::patch()
     */
    public function testPatch($input, $expected)
    {
        $this->authenticate();

        $type = $input['type'];
        $title = $input['data']['title'];
        $input['data']['title'] = 'another title before patch';
        $response = $this->client->save($type, $input['data']);
        $id = $response['data']['id'];
        $input['data']['title'] = $title;
        $body = [
            'data' => [
                'id' => $id,
                'type' => $type,
                'attributes' => $input['data'],
            ],
        ];
        $response = $this->client->patch(sprintf('/%s/%s', $type, $id), json_encode($body));
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertArrayHasKey('data', $response);
        static::assertNotEmpty($response['data']['id']);
    }

    /**
     * Data provider for `testDelete`
     */
    public function deleteProvider()
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
                // expected response from delete
                [
                    'code' => 204,
                    'message' => 'No Content',
                ],
            ],
        ];
    }

    /**
     * Test `delete`.
     *
     * @param mixed $input Input data for delete
     * @param mixed $expected Expected result
     * @return void
     *
     * @dataProvider deleteProvider
     * @covers ::delete()
     */
    public function testDelete($input, $expected)
    {
        $this->authenticate();

        $type = $input['type'];
        $response = $this->client->save($type, $input['data']);
        $id = $response['data']['id'];
        $response = $this->client->delete(sprintf('/%s/%s', $type, $id));
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());
        static::assertEmpty($response);
    }

    /**
     * Test `schema`.
     *
     * @return void
     *
     * @covers ::schema()
     */
    public function testSchema()
    {
        $this->authenticate();

        $response = $this->client->schema('documents');
        static::assertEquals(200, $this->client->getStatusCode());
        static::assertEquals('OK', $this->client->getStatusMessage());
        static::assertNotEmpty($response);
    }

    /**
     * Test `relationData`.
     *
     * @return void
     *
     * @covers ::relationData()
     */
    public function testRelationData()
    {
        $this->authenticate();

        $schema = [
            'properties' => [
                'isNumber' => [
                    'type' => 'boolean',
                    'description' => 'custom params is boolean',
                ],
            ],
        ];

        $data = [
            'type' => 'relations',
            'attributes' => [
                'name' => 'owner_of',
                'label' => 'Owner of',
                'inverse_name' => 'belongs_to',
                'inverse_label' => 'Belongs to',
                'description' => null,
                'params' => $schema,
            ],
        ];

        $this->client->post('model/relations', json_encode(compact('data')));

        $response = $this->client->relationData('owner_of');
        static::assertEquals(200, $this->client->getStatusCode());
        static::assertEquals('OK', $this->client->getStatusMessage());
        static::assertNotEmpty($response);
        static::assertEquals($response['data']['attributes'], $data['attributes']);
        // test left and right types inclusion - even if empty arrays
        static::assertEquals([], $response['data']['relationships']['left_object_types']['data']);
        static::assertEquals([], $response['data']['relationships']['right_object_types']['data']);
    }

    /**
     * Data provider for `testDelete`
     */
    public function responseBodyProvider()
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
    public function testGetResponseBody($input, $expected)
    {
        $response = $this->myclient->authenticate($this->adminUser, $this->adminPassword);
        $this->myclient->setupTokens($response['meta']);
        if ($expected instanceof \Exception) {
            static::expectException(get_class($expected));
            static::expectExceptionCode($expected->getCode());
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
    public function sendRequestProvider()
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
    public function testSendRequest($input, $expected)
    {
        if ($expected instanceof \Exception) {
            static::expectException(get_class($expected));
            static::expectExceptionCode($expected->getCode());
            static::expectExceptionMessage($expected->getMessage());
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
    public function sendRequestRetryProvider()
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
    public function testSendRequestRetry($input, $expected)
    {
        if ($expected instanceof \Exception) {
            static::expectException(get_class($expected));
            static::expectExceptionCode($expected->getCode());
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
    public function refreshTokensProvider()
    {
        return [
            'renew token as not logged' => [
                ['authenticate' => false],
                new \BadMethodCallException('You must be logged in to renew token'),
            ],
            'wrong renew token' => [
                ['authenticate' => true, 'token' => true ],
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
    public function testRefreshTokens($input, $expected)
    {
        if ($expected instanceof \Exception) {
            static::expectException(get_class($expected));
            static::expectExceptionCode($expected->getCode());
            static::expectExceptionMessage($expected->getMessage());
        }
        if ($input['authenticate'] === true) {
            $this->authenticate();
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

    /**
     * Create new object for test purposes.
     *
     * @param array $input The input data.
     * @return int|string the Id.
     */
    private function newObject($input)
    {
        $response = $this->client->save($input['type'], $input['data']);

        return $response['data']['id'];
    }
}
