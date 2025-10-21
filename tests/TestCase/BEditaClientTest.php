<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2023 Atlas Srl, ChannelWeb Srl, Chialab Srl
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
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test class for BEditaClient
 */
class BEditaClientTest extends TestCase
{
    /**
     * Test API base URL
     *
     * @var string
     */
    private string $apiBaseUrl;

    /**
     * Test API KEY
     *
     * @var string
     */
    private string $apiKey;

    /**
     * Test Admin user
     *
     * @var string
     */
    private string $adminUser;

    /**
     * Test Admin user
     *
     * @var string
     */
    private string $adminPassword;

    /**
     * Test client class
     *
     * @var \BEdita\SDK\BEditaClient
     */
    private BEditaClient $client;

    /**
     * Test client class for protected methods testing
     *
     * @var \BEdita\SDK\Test\TestCase\MyBEditaClient
     */
    private MyBEditaClient $myclient;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->apiBaseUrl = (string)getenv('BEDITA_API');
        $this->apiKey = (string)getenv('BEDITA_API_KEY');
        $this->adminUser = (string)getenv('BEDITA_ADMIN_USR');
        $this->adminPassword = (string)getenv('BEDITA_ADMIN_PWD');
        $this->client = new BEditaClient($this->apiBaseUrl, $this->apiKey);
        $this->myclient = new MyBEditaClient($this->apiBaseUrl, $this->apiKey);
    }

    /**
     * Test client constructor
     *
     * @return void
     */
    public function testConstruct(): void
    {
        $client = new BEditaClient($this->apiBaseUrl, $this->apiKey);
        static::assertNotEmpty($client);
        static::assertEquals($client->getApiBaseUrl(), $this->apiBaseUrl);
    }

    /**
     * Test `authenticate` method
     *
     * @return void
     */
    public function testAuthenticate(): void
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
     */
    public function testAuthenticateFail(): void
    {
        $expected = new BEditaClientException('[401] Login request not successful');
        $this->expectException(get_class($expected));
        $this->expectExceptionMessage($expected->getMessage());
        $this->client->authenticate('baduser', 'badpassword');
    }

    /**
     * Authenticate and set auth tokens
     *
     * @return void
     */
    private function authenticate(): void
    {
        $response = $this->client->authenticate($this->adminUser, $this->adminPassword);
        $this->client->setupTokens($response['meta']);
    }

    /**
     * Test `bulkEdit` method
     *
     * @return void
     */
    public function testBulkEdit(): void
    {
        $this->authenticate();
        // create 2 documents with status draft, one locked, one not locked
        $response = $this->client->save('documents', [
            'title' => 'this is a test document 1',
            'status' => 'draft',
        ]);
        $id1 = $response['data']['id'];
        $response = $this->client->save('documents', [
            'title' => 'this is a test document 2',
            'status' => 'draft',
        ]);
        $id2 = $response['data']['id'];
        // lock the second document
        $this->client->patch(
            sprintf('/documents/%s', $id2),
            json_encode([
                'data' => [
                    'id' => $id2,
                    'type' => 'documents',
                    'meta' => [
                        'locked' => true,
                    ],
                ],
            ]),
            ['Content-Type' => 'application/vnd.api+json'],
        );
        $objects = ['documents' => [$id1, $id2]];
        $attributes = ['status' => 'on'];
        $response = $this->client->bulkEdit($objects, $attributes);
        $response = $response['data'];
        static::assertNotEmpty($response);
        static::assertArrayHasKey('saved', $response);
        static::assertArrayNotHasKey('error', $response);
        static::assertEquals([$id1], $response['saved']);
        static::assertEquals([['id' => $id2, 'message' => 'Operation not allowed on "locked" objects']], $response['errors']);
    }

    /**
     * Test `bulkEdit` method retrocompatibility mode
     *
     * @return void
     */
    public function testBulkEditRetrocompatibility(): void
    {
        // mock $this->post('/bulk/edit') to return an exception, to force use retrocompatibility mode
        $client = new class ($this->apiBaseUrl, $this->apiKey) extends BEditaClient {
            public function post(string $path, ?string $body = null, ?array $headers = null): ?array
            {
                if ($path === '/bulk/edit') {
                    throw new BEditaClientException('[404] Not Found', 404);
                }

                return parent::post($path, $body, $headers);
            }
        };
        $response = $client->authenticate($this->adminUser, $this->adminPassword);
        $client->setupTokens($response['meta']);
        // create 2 documents with status draft, one locked, one not locked
        $response = $client->save('documents', [
            'title' => 'this is a test document 1',
            'status' => 'draft',
        ]);
        $id1 = $response['data']['id'];
        $response = $client->save('documents', [
            'title' => 'this is a test document 2',
            'status' => 'draft',
        ]);
        $id2 = $response['data']['id'];
        // lock the second document
        $client->patch(
            sprintf('/documents/%s', $id2),
            json_encode([
                'data' => [
                    'id' => $id2,
                    'type' => 'documents',
                    'meta' => [
                        'locked' => true,
                    ],
                ],
            ]),
            ['Content-Type' => 'application/vnd.api+json'],
        );
        $objects = ['documents' => [$id1, $id2]];
        $attributes = ['status' => 'on'];
        $response = $client->bulkEdit($objects, $attributes);
        $response = $response['data'];
        static::assertNotEmpty($response);
        static::assertArrayHasKey('saved', $response);
        static::assertArrayNotHasKey('error', $response);
        static::assertEquals([$id1], $response['saved']);
        static::assertEquals([['id' => $id2, 'message' => '[403] Not Found']], $response['errors']);
    }

    /**
     * Test `getObjects` method
     *
     * @return void
     */
    public function testGetObjects(): void
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
     */
    public function testGetObject(): void
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
    public static function getRelatedProvider(): array
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
     */
    #[DataProvider('getRelatedProvider')]
    public function testGetRelated($input, $expected): void
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
    public static function relatedProvider(): array
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
     * Test `addRelated`, `removeRelated`, `replaceRelated` methods
     *
     * @param mixed $parentType Parent object type
     * @param mixed $parentData Parent object data
     * @param mixed $childType Child object type
     * @param mixed $childData Child object data
     * @param mixed $relation Relationship name
     * @param mixed $expected Expected result
     * @return void
     */
    #[DataProvider('relatedProvider')]
    public function testRelated($parentType, $parentData, $childType, $childData, $relation, $expected): void
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
        $result = $this->client->replaceRelated($id, $childType, $relation, [
            $data,
        ]);
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());

        $result = $this->client->getRelated($id, $childType, $relation);
        static::assertEquals($parentId, $result['data'][0]['id']);
        static::assertEquals(1, count($result['data']));

        // replace related with meta
        $parent2 = $this->client->save($parentType, $parentData);
        $replace = $data + [
            'meta' => [
                'relation' => [
                    'menu' => false,
                ],
            ],
        ];
        $this->client->replaceRelated($id, $childType, $relation, [
            [
                'id' => $parent2['data']['id'],
                'type' => $parent2['data']['type'],
            ],
            $replace,
        ]);
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());

        $result = $this->client->getRelated($id, $childType, $relation);
        static::assertEquals($parentId, $result['data'][0]['id']);
        static::assertEquals(2, count($result['data']));
        static::assertEquals(false, $result['data'][0]['meta']['relation']['menu']);

        // delete object
        $this->client->deleteObject($id, $childType);
        $this->client->deleteObject($parentId, $parentType);
        $this->client->deleteObject($parent2['data']['id'], $parentType);
        // permanently remove object
        $this->client->remove($id);
    }

    /**
     * Test `upload` and `createMediaFromStream` methods
     *
     * @return void
     */
    public function testUploadCreate(): void
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
    public static function uploadProvider(): array
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
     */
    #[DataProvider('uploadProvider')]
    public function testUpload($input, $expected): void
    {
        $this->authenticate();
        if ($expected instanceof Exception) {
            $this->expectException(get_class($expected));
            $this->expectExceptionMessage($expected->getMessage());
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
     * Test `createMedia` and `addStreamToMedia` methods
     *
     * @return void
     */
    public function testCreateMediaAndAddToStream(): void
    {
        $this->authenticate();
        $type = 'images';
        $body = [
            'data' => [
                'type' => $type,
                'attributes' => [],
            ],
        ];
        $id = $this->client->createMedia($type, $body);
        static::assertIsString($id);
        static::assertNotEmpty($id);

        $filename = 'test.png';
        $filepath = sprintf('%s/tests/files/%s', getcwd(), $filename);
        $response = $this->client->upload($filename, $filepath);
        $streamId = $response['data']['id'];

        $this->client->addStreamToMedia($streamId, $id, $type);
    }

    /**
     * Test `createMedia` when post return empty array
     *
     * @return void
     */
    public function testCreateMediaException(): void
    {
        // mock post to return empty array
        $client = new class ($this->apiBaseUrl, $this->apiKey) extends BEditaClient {
            public function post(string $path, ?string $body = null, ?array $headers = null): ?array
            {
                return [];
            }
        };
        $type = 'images';
        $body = [
            'data' => [
                'type' => $type,
                'attributes' => [],
            ],
        ];
        $this->expectException(BEditaClientException::class);
        $this->expectExceptionMessage('Invalid response from POST /images');
        $this->expectExceptionCode(503);
        $client->createMedia($type, $body);
    }

    /**
     * Test `addStreamToMedia` when patch return empty array
     *
     * @return void
     */
    public function testAddStreamToMediaException(): void
    {
        // mock patch to return empty array
        $client = new class ($this->apiBaseUrl, $this->apiKey) extends BEditaClient {
            public function patch(string $path, ?string $body = null, ?array $headers = null): ?array
            {
                return [];
            }
        };
        $this->expectException(BEditaClientException::class);
        $this->expectExceptionMessage('Invalid response from PATCH /streams/999/relationships/object');
        $this->expectExceptionCode(503);
        $client->addStreamToMedia('123456789', '999', 'images');
    }

    /**
     * Test `thumbs` method
     *
     * @return void
     */
    public function testThumbs(): void
    {
        $this->authenticate();

        // create 2 images
        $id1 = $this->_image();
        $id2 = $this->_image();
        $ids = [$id1, $id2];

        // test thumbs(:id, :query)
        $query = ['preset' => 'default'];
        foreach ($ids as $id) {
            $response = $this->client->thumbs(intval($id), $query);
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
        $this->expectException(get_class($exception));
        $this->expectExceptionMessage($exception->getMessage());
        $this->client->thumbs();
    }

    /**
     * Create image and media stream for test.
     * Return id
     *
     * @return string The image ID.
     */
    private function _image(): string
    {
        $filename = 'test.png';
        $filepath = sprintf('%s/tests/files/%s', getcwd(), $filename);
        $response = $this->client->upload($filename, $filepath);

        $streamId = $response['data']['id'];
        $this->client->get(sprintf('/streams/%s', $streamId));

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
    public static function saveProvider(): array
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
     */
    #[DataProvider('saveProvider')]
    public function testSave($input, $expected): void
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
    public static function deleteObjectProvider(): array
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
     * @param array $input Input data for delete
     * @param array $expected Expected result
     * @return void
     */
    #[DataProvider('deleteObjectProvider')]
    public function testDeleteObject(array $input, array $expected): void
    {
        $this->authenticate();

        $response = $this->client->deleteObject($this->newObject($input), $input['type']);
        static::assertEquals($expected['code'], $this->client->getStatusCode());
        static::assertEquals($expected['message'], $this->client->getStatusMessage());
        static::assertEmpty($response);
    }

    /**
     * Test `deleteObjects`, `removeObjects` and `restoreObjects.
     *
     * @return void
     */
    public function testDeleteRemoveRestore(): void
    {
        $this->authenticate();
        $type = 'documents';
        $docId = $this->newObject([
            'type' => $type,
            'data' => [
                'title' => 'this is a test document',
            ],
        ]);
        $ids = [$docId];
        $response = $this->client->deleteObjects($ids, $type);
        static::assertEquals(204, $this->client->getStatusCode());
        static::assertEquals('No Content', $this->client->getStatusMessage());
        static::assertEmpty($response);
        $response = $this->client->getObjects($type, ['filter' => ['id' => $ids[0]]]);
        static::assertEmpty($response['data']);
        $response = $this->client->restoreObjects($ids, $type);
        static::assertEquals(204, $this->client->getStatusCode());
        static::assertEquals('No Content', $this->client->getStatusMessage());
        static::assertEmpty($response);
        $response = $this->client->getObjects($type, ['filter' => ['id' => $ids[0]]]);
        static::assertNotEmpty($response['data']);
        $response = $this->client->deleteObjects($ids, $type);
        static::assertEquals(204, $this->client->getStatusCode());
        static::assertEquals('No Content', $this->client->getStatusMessage());
        static::assertEmpty($response);
        $response = $this->client->removeObjects($ids, $type);
        static::assertEquals(204, $this->client->getStatusCode());
        static::assertEquals('No Content', $this->client->getStatusMessage());
        static::assertEmpty($response);
    }

    /**
     * Test `deleteObjects` on exception.
     *
     * @return void
     */
    public function testDeleteObjects(): void
    {
        $client = new class ($this->apiBaseUrl, $this->apiKey) extends BEditaClient {
            public function deleteObject($id, string $type): ?array
            {
                return [];
            }
        };
        $response = $client->authenticate($this->adminUser, $this->adminPassword);
        $client->setupTokens($response['meta']);
        $type = 'documents';
        $response = $client->save($type, ['title' => 'this is a test document']);
        $docId = $response['data']['id'];
        $ids = [$docId, 'abc'];
        $actual = $client->deleteObjects($ids, $type);
        static::assertEmpty($actual);
    }

    /**
     * Test `deleteObjects` on exception.
     *
     * @return void
     */
    public function testDeleteObjectsOnException(): void
    {
        $this->authenticate();
        $type = 'documents';
        $docId = $this->newObject([
            'type' => $type,
            'data' => [
                'title' => 'this is a test document',
            ],
        ]);
        $ids = [$docId, 'abc'];
        $this->expectException(BEditaClientException::class);
        $this->client->deleteObjects($ids, $type);
    }

    /**
     * Data provider for `testRestoreObject`
     */
    public static function restoreObjectProvider(): array
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
     */
    #[DataProvider('restoreObjectProvider')]
    public function testRestoreObject($input, $expected): void
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
     * Test `restoreObjects`.
     *
     * @return void
     */
    public function testRestoreObjects(): void
    {
        $this->authenticate();
        $type = 'documents';
        $docId = $this->newObject([
            'type' => $type,
            'data' => [
                'title' => 'this is a test document',
            ],
        ]);
        $ids = [$docId];
        $this->client->deleteObjects($ids, $type);
        $response = $this->client->restoreObjects($ids, $type);
        static::assertEquals(204, $this->client->getStatusCode());
        static::assertEquals('No Content', $this->client->getStatusMessage());
        static::assertEmpty($response);
    }

    /**
     * Data provider for `testRemove`
     */
    public static function removeProvider(): array
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
     */
    #[DataProvider('removeProvider')]
    public function testRemove($input, $expected): void
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
     * Test `removeObjects` on exception.
     *
     * @return void
     */
    public function testRemoveObjects(): void
    {
        $client = new class ($this->apiBaseUrl, $this->apiKey) extends BEditaClient {
            public function remove($id): ?array
            {
                if ($id === 'abc') {
                    return [];
                }

                return $this->delete(sprintf('/trash/%s', $id));
            }
        };
        $response = $client->authenticate($this->adminUser, $this->adminPassword);
        $client->setupTokens($response['meta']);
        $type = 'documents';
        $response = $client->save($type, ['title' => 'this is a test document']);
        $docId = $response['data']['id'];
        $client->deleteObject($docId, $type);
        $ids = [$docId, 'abc'];
        $actual = $client->removeObjects($ids, $type);
        static::assertEmpty($actual);
    }

    /**
     * Test `removeObjects` on exception.
     *
     * @return void
     */
    public function testRemoveObjectsOnException(): void
    {
        $this->authenticate();
        $type = 'documents';
        $docId = $this->newObject([
            'type' => $type,
            'data' => [
                'title' => 'this is a test document',
            ],
        ]);
        $ids = [$docId, 'abc'];
        $this->expectException(BEditaClientException::class);
        $this->client->removeObjects($ids, $type);
    }

    /**
     * Test `schema`.
     *
     * @return void
     */
    public function testSchema(): void
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
     */
    public function testRelationData(): void
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

        $attributes = $response['data']['attributes'];
        // remove JSON-SCHEMA metadata
        unset($attributes['params']['definitions'], $attributes['params']['type'], $attributes['params']['$schema']);
        static::assertEquals($attributes, $data['attributes']);

        // test left and right types inclusion - even if empty arrays
        static::assertEquals([], $response['data']['relationships']['left_object_types']['data']);
        static::assertEquals([], $response['data']['relationships']['right_object_types']['data']);
    }

    /**
     * Data provider for `testSendRequest`
     */
    public static function sendRequestProvider(): array
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
                    'path' => getenv('BEDITA_API') . '/zzzzz',
                    'query' => null,
                    'headers' => null,
                    'body' => null,
                ],
                new BEditaClientException('Not Found', 404),
            ],
        ];
    }

    /**
     * Test `sendRequest` and `requestUri` methods.
     *
     * @param mixed $input Input data
     * @param mixed $expected Expected result
     * @return void
     */
    #[DataProvider('sendRequestProvider')]
    public function testSendRequest($input, $expected): void
    {
        if ($expected instanceof Exception) {
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
        foreach ($expected['fields'] as $val) {
            static::assertNotEmpty($responseBody[$val]);
        }
    }

    /**
     * Create new object for test purposes.
     *
     * @param array $input The input data.
     * @return int the Id.
     */
    private function newObject($input): int
    {
        $response = $this->client->save($input['type'], $input['data']);

        return (int)$response['data']['id'];
    }

    /**
     * Test several methods in sequence:
     *
     * - save
     * - addRelated
     * - getRelated
     * - getObject
     * - removeRelated
     * - deleteObject
     * - remove
     * - restoreObject
     * - upload
     * - createMediaFromStream
     * - createMedia
     * - addStreamToMedia
     * - thumbs
     * - schema
     * - relationData
     * - clone
     *
     * @return void
     */
    public function testMultipurpose(): void
    {
        $this->authenticate();

        // create a folder
        $folder = $this->client->save('folders', ['title' => 'my dummy folder']);
        static::assertNotEmpty($folder['data']['id']);
        static::assertSame('my dummy folder', $folder['data']['attributes']['title']);
        $getObjects = $this->client->getObjects('folders');
        static::assertGreaterThanOrEqual(1, $getObjects['meta']['pagination']['count']);
        $allFoldersCount = $getObjects['meta']['pagination']['count'];

        // create 10 documents
        $documents = [];
        for ($i = 0; $i < 10; $i++) {
            $documents[$i] = $this->client->save('documents', ['title' => sprintf('my dummy document %d', $i + 1)]);
            static::assertNotEmpty($documents[$i]['data']['id']);
            static::assertSame(sprintf('my dummy document %d', $i + 1), $documents[$i]['data']['attributes']['title']);
        }

        // get documents: should be more or equal to 10
        $getObjects = $this->client->getObjects('documents');
        $allDocumentsCount = $getObjects['meta']['pagination']['count'];
        static::assertGreaterThanOrEqual(10, $getObjects['meta']['pagination']['count']);

        // add 10 documents as folder children
        $addRelated = $this->client->addRelated(
            $folder['data']['id'],
            'folders',
            'children',
            array_map(
                function ($document) {
                    return [
                        'id' => $document['data']['id'],
                        'type' => $document['data']['type'],
                    ];
                },
                $documents,
            ),
        );
        static::assertIsArray($addRelated);
        static::assertArrayHasKey('links', $addRelated);
        static::assertArrayHasKey('self', $addRelated['links']);
        static::assertStringContainsString(
            sprintf('/folders/%s/relationships/children', $folder['data']['id']),
            $addRelated['links']['self'],
        );

        // get folder children
        $getRelated = $this->client->getRelated($folder['data']['id'], 'folders', 'children');
        static::assertSame(10, $getRelated['meta']['pagination']['count']);
        foreach ($getRelated['data'] as $i => $document) {
            $d = $documents[$i]['data'];
            static::assertSame($d['id'], $document['id']);
            static::assertSame($d['type'], $document['type']);
            // get single document
            $getObject = $this->client->getObject($document['id'], $document['type']);
            static::assertSame($d['id'], $getObject['data']['id']);
            static::assertSame($d['type'], $getObject['data']['type']);
            static::assertSame($d['attributes']['title'], $getObject['data']['attributes']['title']);
        }
        static::assertIsArray($getRelated);
        static::assertArrayHasKey('links', $getRelated);
        static::assertArrayHasKey('self', $getRelated['links']);
        static::assertStringContainsString(
            sprintf('/folders/%s/children', $folder['data']['id']),
            $getRelated['links']['self'],
        );

        // remove 5 documents from folder children
        $removeRelated = $this->client->removeRelated(
            $folder['data']['id'],
            'folders',
            'children',
            array_map(
                function ($document) {
                    return [
                        'id' => $document['data']['id'],
                        'type' => $document['data']['type'],
                    ];
                },
                array_slice($documents, 0, 5),
            ),
        );
        static::assertIsArray($removeRelated);
        static::assertArrayHasKey('links', $removeRelated);
        static::assertArrayHasKey('self', $removeRelated['links']);
        static::assertStringContainsString(
            sprintf('/folders/%s/relationships/children', $folder['data']['id']),
            $removeRelated['links']['self'],
        );

        // get again folder children: should be 5
        $getRelated = $this->client->getRelated($folder['data']['id'], 'folders', 'children');
        static::assertSame(5, $getRelated['meta']['pagination']['count']);

        // replace related folder children with 2 documents
        $documentsReplace = array_slice($documents, 5, 2);
        $this->client->replaceRelated(
            $folder['data']['id'],
            'folders',
            'children',
            array_map(
                function ($document) {
                    return [
                        'id' => $document['data']['id'],
                        'type' => $document['data']['type'],
                    ];
                },
                $documentsReplace,
            ),
        );

        // get again folder children: should be 2
        $getRelated = $this->client->getRelated($folder['data']['id'], 'folders', 'children');
        static::assertSame(2, $getRelated['meta']['pagination']['count']);

        // remove all documents from folder children
        $this->client->removeRelated(
            $folder['data']['id'],
            'folders',
            'children',
            array_map(
                function ($document) {
                    return [
                        'id' => $document['data']['id'],
                        'type' => $document['data']['type'],
                    ];
                },
                $documentsReplace,
            ),
        );

        // get again folder children: should be 0
        $getRelated = $this->client->getRelated($folder['data']['id'], 'folders', 'children');
        static::assertSame(0, $getRelated['meta']['pagination']['count']);

        // move to trash documents
        foreach ($documents as $document) {
            $this->client->deleteObject($document['data']['id'], $document['data']['type']);
        }

        // count documents: should be 10 less than before
        $getObjects = $this->client->getObjects('documents');
        static::assertSame($allDocumentsCount - 10, $getObjects['meta']['pagination']['count']);

        // restore documents
        foreach ($documents as $document) {
            $this->client->restoreObject($document['data']['id'], $document['data']['type']);
        }
        // count documents: should be the same number as before
        $getObjects = $this->client->getObjects('documents');
        static::assertSame($allDocumentsCount, $getObjects['meta']['pagination']['count']);

        // move to documents to trash again
        foreach ($documents as $document) {
            $this->client->deleteObject($document['data']['id'], $document['data']['type']);
        }
        // permanently remove documents
        foreach ($documents as $document) {
            $this->client->remove($document['data']['id']);
        }
        // move folder to trash
        $this->client->deleteObject($folder['data']['id'], $folder['data']['type']);
        // permanently remove folder
        $this->client->remove($folder['data']['id']);

        // get documents: should be 10 less than before
        $expectedDocumentsCount = $allDocumentsCount - 10;
        $getObjects = $this->client->getObjects('documents');
        static::assertSame($expectedDocumentsCount, $getObjects['meta']['pagination']['count']);

        // get folders: should be 1 less than before
        $expectedFoldersCount = $allFoldersCount - 1;
        $getObjects = $this->client->getObjects('folders');
        static::assertSame($expectedFoldersCount, $getObjects['meta']['pagination']['count']);

        // upload a file
        $upload = $this->client->upload('test.png', sprintf('%s/tests/files/test.png', getcwd()));
        static::assertNotEmpty($upload['data']['id']);
        static::assertSame('test.png', $upload['data']['attributes']['file_name']);
        $streamId = $upload['data']['id'];
        $stream = $this->client->get(sprintf('/streams/%s', $streamId));
        static::assertSame($streamId, $stream['data']['id']);
        static::assertSame('test.png', $stream['data']['attributes']['file_name']);

        // create media from stream
        $type = 'images';
        $title = 'A new image';
        $attributes = compact('title');
        $data = compact('type', 'attributes');
        $body = compact('data');
        $cmfs = $this->client->createMediaFromStream($streamId, $type, $body);
        static::assertSame($type, $cmfs['data']['type']);
        static::assertSame($title, $cmfs['data']['attributes']['title']);
        static::assertArrayHasKey('included', $cmfs);
        static::assertArrayHasKey(0, $cmfs['included']);
        static::assertArrayHasKey('id', $cmfs['included'][0]);
        static::assertArrayHasKey('attributes', $cmfs['included'][0]);
        static::assertSame($streamId, $cmfs['included'][0]['id']);
        static::assertSame('streams', $cmfs['included'][0]['type']);

        // create media
        $type = 'images';
        $title = 'Another new image';
        $attributes = compact('title');
        $data = compact('type', 'attributes');
        $body = compact('data');
        $mediaId = $this->client->createMedia($type, $body);
        static::assertIsString($mediaId);
        static::assertNotEmpty($mediaId);
        $media = $this->client->getObject($mediaId, $type);
        static::assertSame($mediaId, $media['data']['id']);
        static::assertSame($type, $media['data']['type']);
        static::assertSame($title, $media['data']['attributes']['title']);

        // add stream to media
        $this->client->addStreamToMedia($streamId, $mediaId, $type);
        $media = $this->client->get(sprintf('/%s/%s', $type, $mediaId));
        static::assertSame($streamId, $media['included'][0]['id']);

        // get thumbs
        $thumbs = $this->client->thumbs(intval($mediaId), ['preset' => 'default']);
        static::assertNotEmpty($thumbs['meta']['thumbnails']);
        static::assertStringContainsString('/_files/thumbs/', $thumbs['meta']['thumbnails'][0]['url']);

        // get schema
        $schema = $this->client->schema('documents');
        static::assertNotEmpty($schema);
        static::assertStringContainsString('/model/schema/documents', $schema['$id']);

        // create relation
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
                'name' => 'my_owner_of',
                'label' => 'Owner of',
                'inverse_name' => 'my_belongs_to',
                'inverse_label' => 'Belongs to',
                'description' => null,
                'params' => $schema,
            ],
        ];
        $this->client->post('model/relations', json_encode(compact('data')));

        // get relation data
        $relationData = $this->client->relationData('my_owner_of');
        static::assertNotEmpty($relationData);
        static::assertArrayHasKey('data', $relationData);
        static::assertArrayHasKey('attributes', $relationData['data']);
        static::assertArrayHasKey('params', $relationData['data']['attributes']);

        // test clone
        $clone = $this->client->clone('images', $mediaId, ['title' => 'Cloned image', 'status' => 'draft'], ['relationships', 'translations']);
        static::assertNotEmpty($clone['data']['id']);
        static::assertSame('Cloned image', $clone['data']['attributes']['title']);
        static::assertSame('draft', $clone['data']['attributes']['status']);
    }
}
