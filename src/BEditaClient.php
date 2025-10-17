<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2023 Atlas Srl, ChannelWeb Srl, Chialab Srl
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 */

namespace BEdita\SDK;

use Exception;

/**
 * BEdita API Client class
 */
class BEditaClient extends BaseClient
{
    /**
     * Classic authentication via POST /auth using username and password
     *
     * @param string $username username
     * @param string $password password
     * @return array|null Response in array format
     */
    public function authenticate(string $username, string $password): ?array
    {
        // remove `Authorization` header containing user data in JWT token when using API KEY
        $headers = $this->getDefaultHeaders();
        if (!empty($headers['X-Api-Key'])) {
            unset($headers['Authorization']);
            $this->setDefaultHeaders($headers);
        }
        $body = (string)json_encode(compact('username', 'password') + ['grant_type' => 'password']);

        return $this->post('/auth', $body, ['Content-Type' => 'application/json']);
    }

    /**
     * Bulk edit objects using `POST /bulk/edit` endpoint.
     * If the endpoint is not available, it fallback to edit one by one (retrocompatible way).
     *
     * @param array $ids Object ids
     * @param array $data Data to modify
     * @return array
     */
    public function bulkEdit(array $ids, array $data): array
    {
        $result = [];
        try {
            $ids = array_map('intval', $ids);
            $result = (array)$this->post(
                '/bulk/edit',
                json_encode(compact('ids', 'data')),
                ['Content-Type' => 'application/json'],
            );
        } catch (Exception $e) {
            $result['saved'] = [];
            $result['errors'] = [];
            // fallback to edit one by one, to be retrocompatible
            foreach ($ids as $id) {
                try {
                    $response = $this->getObject($id);
                    $response = $this->save($response['data']['type'], $data + ['id' => (string)$id]);
                    $result['saved'][] = $response['data']['id'];
                } catch (Exception $e) {
                    $responseBody = $this->getResponseBody();
                    $status = $responseBody['error']['status'];
                    $message = $responseBody['error']['message'] ?? $e->getMessage();
                    $message = intval($status) === 403 ? '[403] Forbidden' : $message;
                    $result['errors'][] = [
                        'id' => $id,
                        'message' => $message,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * GET a list of resources or objects of a given type
     *
     * @param string $type Object type name
     * @param array|null $query Optional query string
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function getObjects(string $type = 'objects', ?array $query = null, ?array $headers = null): ?array
    {
        return $this->get(sprintf('/%s', $type), $query, $headers);
    }

    /**
     * GET a single object of a given type
     *
     * @param string|int $id Object id
     * @param string $type Object type name
     * @param array|null $query Optional query string
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function getObject(
        string|int $id,
        string $type = 'objects',
        ?array $query = null,
        ?array $headers = null,
    ): ?array {
        return $this->get(sprintf('/%s/%s', $type, $id), $query, $headers);
    }

    /**
     * Get a list of related resources or objects
     *
     * @param string|int $id Resource id or object uname/id
     * @param string $type Type name
     * @param string $relation Relation name
     * @param array|null $query Optional query string
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function getRelated(
        string|int $id,
        string $type,
        string $relation,
        ?array $query = null,
        ?array $headers = null,
    ): ?array {
        return $this->get(sprintf('/%s/%s/%s', $type, $id, $relation), $query, $headers);
    }

    /**
     * Add a list of related resources or objects
     *
     * @param string|int $id Resource id or object uname/id
     * @param string $type Type name
     * @param string $relation Relation name
     * @param array $data Related resources or objects to add, MUST contain id and type
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function addRelated(
        string|int $id,
        string $type,
        string $relation,
        array $data,
        ?array $headers = null,
    ): ?array {
        return $this->post(
            sprintf('/%s/%s/relationships/%s', $type, $id, $relation),
            json_encode(compact('data')),
            $headers,
        );
    }

    /**
     * Remove a list of related resources or objects
     *
     * @param string|int $id Resource id or object uname/id
     * @param string $type Type name
     * @param string $relation Relation name
     * @param array $data Related resources or objects to remove from relation
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function removeRelated(
        string|int $id,
        string $type,
        string $relation,
        array $data,
        ?array $headers = null,
    ): ?array {
        return $this->delete(
            sprintf('/%s/%s/relationships/%s', $type, $id, $relation),
            json_encode(compact('data')),
            $headers,
        );
    }

    /**
     * Replace a list of related resources or objects: previuosly related are removed and replaced with these.
     *
     * @param string|int $id Object id
     * @param string $type Object type name
     * @param string $relation Relation name
     * @param array $data Related resources or objects to insert
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function replaceRelated(
        string|int $id,
        string $type,
        string $relation,
        array $data,
        ?array $headers = null,
    ): ?array {
        return $this->patch(
            sprintf(
                '/%s/%s/relationships/%s',
                $type,
                $id,
                $relation,
            ),
            json_encode(compact('data')),
            $headers,
        );
    }

    /**
     * Create a new object or resource (POST) or modify an existing one (PATCH)
     *
     * @param string $type Object or resource type name
     * @param array $data Object or resource data to save
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function save(string $type, array $data, ?array $headers = null): ?array
    {
        $id = null;
        if (array_key_exists('id', $data)) {
            $id = $data['id'];
            unset($data['id']);
        }

        $body = [
            'data' => [
                'type' => $type,
                'attributes' => $data,
            ],
        ];
        if (!$id) {
            return $this->post(sprintf('/%s', $type), json_encode($body), $headers);
        }
        $body['data']['id'] = $id;

        return $this->patch(sprintf('/%s/%s', $type, $id), json_encode($body), $headers);
    }

    /**
     * [DEPRECATED] Create a new object (POST) or modify an existing one (PATCH)
     *
     * @param string $type Object type name
     * @param array $data Object data to save
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     * @deprecated Use `save()` method instead
     * @codeCoverageIgnore
     */
    public function saveObject(string $type, array $data, ?array $headers = null): ?array
    {
        return $this->save($type, $data, $headers);
    }

    /**
     * Delete an object (DELETE) => move to trashcan.
     *
     * @param string|int $id Object id
     * @param string $type Object type name
     * @return array|null Response in array format
     */
    public function deleteObject(string|int $id, string $type): ?array
    {
        return $this->delete(sprintf('/%s/%s', $type, $id));
    }

    /**
     * Delete objects (DELETE) => move to trashcan.
     *
     * @param array $ids Object ids
     * @param string|null $type Object type name
     * @return array|null Response in array format
     */
    public function deleteObjects(array $ids, string $type = 'objects'): ?array
    {
        $response = null;
        try {
            $response = $this->delete(sprintf('/%s?ids=%s', $type, implode(',', $ids)));
        } catch (Exception $e) {
            // fallback to delete one by one, to be retrocompatible
            foreach ($ids as $id) {
                $response = !empty($response) ? $response : $this->deleteObject($id, $type);
            }
        }

        return $response;
    }

    /**
     * Remove an object => permanently remove object from trashcan.
     *
     * @param string|int $id Object id
     * @return array|null Response in array format
     */
    public function remove(string|int $id): ?array
    {
        return $this->delete(sprintf('/trash/%s', $id));
    }

    /**
     * Remove objects => permanently remove objects from trashcan.
     *
     * @param array $ids Object ids
     * @return array|null Response in array format
     */
    public function removeObjects(array $ids): ?array
    {
        $response = null;
        try {
            $response = $this->delete(sprintf('/trash?ids=%s', implode(',', $ids)));
        } catch (Exception $e) {
            // fallback to delete one by one, to be retrocompatible
            foreach ($ids as $id) {
                $response = !empty($response) ? $response : $this->remove($id);
            }
        }

        return $response;
    }

    /**
     * Upload file (POST)
     *
     * @param string $filename The file name
     * @param string $filepath File full path: could be on a local filesystem or a remote reachable URL
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     * @throws \BEdita\SDK\BEditaClientException
     */
    public function upload(string $filename, string $filepath, ?array $headers = null): ?array
    {
        if (!file_exists($filepath)) {
            throw new BEditaClientException('File not found', 500);
        }
        $file = file_get_contents($filepath);
        if (!$file) {
            throw new BEditaClientException('File get contents failed', 500);
        }
        if (empty($headers['Content-Type'])) {
            $headers['Content-Type'] = mime_content_type($filepath);
        }

        return $this->post(sprintf('/streams/upload/%s', $filename), $file, $headers);
    }

    /**
     * Create media by type and body data and link it to a stream:
     *  - `POST /:type` with `$body` as payload, create media object
     *  - `PATCH /streams/:stream_id/relationships/object` modify stream adding relation to media
     *  - `GET /:type/:id` get media data
     *
     * @param string $streamId The stream identifier
     * @param string $type The type
     * @param array $body The body data
     * @return array|null Response in array format
     * @throws \BEdita\SDK\BEditaClientException
     */
    public function createMediaFromStream(string $streamId, string $type, array $body): ?array
    {
        $id = $this->createMedia($type, $body);
        $this->addStreamToMedia($streamId, $id, $type);

        return $this->getObject($id, $type);
    }

    /**
     * Create media.
     *
     * @param string $type The type
     * @param array $body The body
     * @return string
     * @throws \BEdita\SDK\BEditaClientException
     */
    public function createMedia(string $type, array $body): string
    {
        $response = $this->post(sprintf('/%s', $type), json_encode($body));
        if (empty($response)) {
            throw new BEditaClientException('Invalid response from POST ' . sprintf('/%s', $type));
        }

        return (string)$response['data']['id'];
    }

    /**
     * Add stream to media using patch /streams/%s/relationships/object.
     *
     * @param string $streamId The stream ID
     * @param string $id The object ID
     * @param string $type The type
     * @return void
     * @throws \BEdita\SDK\BEditaClientException
     */
    public function addStreamToMedia(string $streamId, string $id, string $type): void
    {
        $response = $this->patch(
            sprintf('/streams/%s/relationships/object', $streamId),
            json_encode([
                'data' => [
                    'id' => $id,
                    'type' => $type,
                ],
            ]),
        );
        if (empty($response)) {
            throw new BEditaClientException(
                'Invalid response from PATCH ' . sprintf('/streams/%s/relationships/object', $id),
            );
        }
    }

    /**
     * Thumbnail request using `GET /media/thumbs` endpoint
     *
     *  Usage:
     *          thumbs(123) => `GET /media/thumbs/123`
     *          thumbs(123, ['preset' => 'glide']) => `GET /media/thumbs/123&preset=glide`
     *          thumbs(null, ['ids' => '123,124,125']) => `GET /media/thumbs?ids=123,124,125`
     *          thumbs(null, ['ids' => '123,124,125', 'preset' => 'async']) => `GET /media/thumbs?ids=123,124,125&preset=async`
     *          thumbs(123, ['options' => ['w' => 100, 'h' => 80, 'fm' => 'jpg']]) => `GET /media/thumbs/123/options[w]=100&options[h]=80&options[fm]=jpg` (these options could be not available... just set in preset(s))
     *
     * @param int|null $id the media Id.
     * @param array $query The query params for thumbs call.
     * @return array|null Response in array format
     */
    public function thumbs(?int $id = null, array $query = []): ?array
    {
        if (empty($id) && empty($query['ids'])) {
            throw new BEditaClientException('Invalid empty id|ids for thumbs');
        }
        $endpoint = empty($id) ? '/media/thumbs' : sprintf('/media/thumbs/%d', $id);

        return $this->get($endpoint, $query);
    }

    /**
     * Get JSON SCHEMA of a resource or object
     *
     * @param string $type Object or resource type name
     * @return array|null JSON SCHEMA in array format
     */
    public function schema(string $type): ?array
    {
        return $this->get(
            sprintf('/model/schema/%s', $type),
            null,
            ['Accept' => 'application/schema+json'],
        );
    }

    /**
     * Get info of a relation (data, params) and get left/right object types
     *
     * @param string $name relation name
     * @return array|null relation data in array format
     */
    public function relationData(string $name): ?array
    {
        return $this->get(
            sprintf('/model/relations/%s', $name),
            ['include' => 'left_object_types,right_object_types'],
        );
    }

    /**
     * Restore object from trash
     *
     * @param string|int $id Object id
     * @param string $type Object type name
     * @return array|null Response in array format
     */
    public function restoreObject(string|int $id, string $type): ?array
    {
        return $this->patch(
            sprintf('/trash/%s', $id),
            json_encode([
                'data' => [
                    'id' => $id,
                    'type' => $type,
                ],
            ]),
        );
    }

    /**
     * Restore objects from trash
     *
     * @param array $ids Object ids
     * @param string|null $type Object type
     * @return array|null Response in array format
     */
    public function restoreObjects(array $ids, string $type = 'objects'): ?array
    {
        $res = null;
        foreach ($ids as $id) {
            $res = !empty($res) ? $res : $this->restoreObject($id, $type);
        }

        return $res;
    }

    /**
     * Clone an object.
     * This requires BEdita API >= 5.36.0
     *
     * @param string $type Object type name
     * @param string $id Source object id
     * @param array $modified Object attributes to overwrite
     * @param array $include Associations included: can be 'relationships' and 'translations'
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function clone(string $type, string $id, array $modified, array $include, ?array $headers = null): ?array
    {
        $body = json_encode([
            'data' => [
                'type' => $type,
                'attributes' => $modified,
                'meta' => [
                    'include' => $include,
                ],
            ],
        ]);

        return $this->post(sprintf('/%s/%s/actions/clone', $type, $id), $body, $headers);
    }
}
