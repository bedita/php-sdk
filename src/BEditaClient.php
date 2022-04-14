<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2022 Atlas Srl, ChannelWeb Srl, Chialab Srl
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 */

namespace BEdita\SDK;

/**
 * BEdita4 API Client class
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
        $this->unsetAuthorization();
        $body = json_encode(compact('username', 'password'));

        return $this->post('/auth', $body, ['Content-Type' => 'application/json']);
    }

    /**
     * Send a GET request a list of resources or objects or a single resource or object
     *
     * @param string $path Endpoint URL path to invoke
     * @param array|null $query Optional query string
     * @param array|null $headers Headers
     * @return array|null Response in array format
     */
    public function get(string $path, ?array $query = null, ?array $headers = null): ?array
    {
        $this->sendRequestRetry('GET', $path, $query, $headers);

        return $this->getResponseBody();
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
     * @param int|string $id Object id
     * @param string $type Object type name
     * @param array|null $query Optional query string
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function getObject($id, string $type = 'objects', ?array $query = null, ?array $headers = null): ?array
    {
        return $this->get(sprintf('/%s/%s', $type, $id), $query, $headers);
    }

    /**
     * Get a list of related resources or objects
     *
     * @param int|string $id Resource id or object uname/id
     * @param string $type Type name
     * @param string $relation Relation name
     * @param array|null $query Optional query string
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function getRelated($id, string $type, string $relation, ?array $query = null, ?array $headers = null): ?array
    {
        return $this->get(sprintf('/%s/%s/%s', $type, $id, $relation), $query, $headers);
    }

    /**
     * Add a list of related resources or objects
     *
     * @param int|string $id Resource id or object uname/id
     * @param string $type Type name
     * @param string $relation Relation name
     * @param array $data Related resources or objects to add, MUST contain id and type
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function addRelated($id, string $type, string $relation, array $data, ?array $headers = null): ?array
    {
        $body = compact('data');

        return $this->post(sprintf('/%s/%s/relationships/%s', $type, $id, $relation), json_encode($body), $headers);
    }

    /**
     * Remove a list of related resources or objects
     *
     * @param int|string $id Resource id or object uname/id
     * @param string $type Type name
     * @param string $relation Relation name
     * @param array $data Related resources or objects to remove from relation
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function removeRelated($id, string $type, string $relation, array $data, ?array $headers = null): ?array
    {
        $body = compact('data');

        return $this->delete(sprintf('/%s/%s/relationships/%s', $type, $id, $relation), json_encode($body), $headers);
    }

    /**
     * Replace a list of related resources or objects: previuosly related are removed and replaced with these.
     *
     * @param int|string $id Object id
     * @param string $type Object type name
     * @param string $relation Relation name
     * @param array $data Related resources or objects to insert
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function replaceRelated($id, string $type, string $relation, array $data, ?array $headers = null): ?array
    {
        $body = compact('data');

        return $this->patch(sprintf('/%s/%s/relationships/%s', $type, $id, $relation), json_encode($body), $headers);
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
     * @param int|string $id Object id
     * @param string $type Object type name
     * @return array|null Response in array format
     */
    public function deleteObject($id, string $type): ?array
    {
        return $this->delete(sprintf('/%s/%s', $type, $id));
    }

    /**
     * Remove an object => permanently remove object from trashcan.
     *
     * @param int|string $id Object id
     * @return array|null Response in array format
     */
    public function remove($id): ?array
    {
        return $this->delete(sprintf('/trash/%s', $id));
    }

    /**
     * Upload file (POST)
     *
     * @param string $filename The file name
     * @param string $filepath File full path: could be on a local filesystem or a remote reachable URL
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     * @throws BEditaClientException
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
     * @throws BEditaClientException
     */
    public function createMediaFromStream($streamId, string $type, array $body): ?array
    {
        $response = $this->post(sprintf('/%s', $type), json_encode($body));
        if (empty($response)) {
            throw new BEditaClientException('Invalid response from POST ' . sprintf('/%s', $type));
        }
        $id = $response['data']['id'];
        $data = compact('id', 'type');
        $body = compact('data');
        $response = $this->patch(sprintf('/streams/%s/relationships/object', $streamId), json_encode($body));
        if (empty($response)) {
            throw new BEditaClientException('Invalid response from PATCH ' . sprintf('/streams/%s/relationships/object', $id));
        }

        return $this->getObject($data['id'], $data['type']);
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
    public function thumbs($id = null, $query = []): ?array
    {
        if (empty($id) && empty($query['ids'])) {
            throw new BEditaClientException('Invalid empty id|ids for thumbs');
        }
        $endpoint = '/media/thumbs';
        if (!empty($id)) {
            $endpoint .= sprintf('/%d', $id);
        }

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
        $h = ['Accept' => 'application/schema+json'];

        return $this->get(sprintf('/model/schema/%s', $type), null, $h);
    }

    /**
     * Get info of a relation (data, params) and get left/right object types
     *
     * @param string $name relation name
     * @return array|null relation data in array format
     */
    public function relationData(string $name): ?array
    {
        $query = [
            'include' => 'left_object_types,right_object_types',
        ];

        return $this->get(sprintf('/model/relations/%s', $name), $query);
    }

    /**
     * Restore object from trash
     *
     * @param int|string $id Object id
     * @param string $type Object type name
     * @return array|null Response in array format
     */
    public function restoreObject($id, string $type): ?array
    {
        $body = [
            'data' => [
                'id' => $id,
                'type' => $type,
            ],
        ];

        return $this->patch(sprintf('/%s/%s', 'trash', $id), json_encode($body));
    }

    /**
     * Send a PATCH request to modify a single resource or object
     *
     * @param string $path Endpoint URL path to invoke
     * @param mixed $body Request body
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function patch(string $path, $body, ?array $headers = null): ?array
    {
        $this->sendRequestRetry('PATCH', $path, null, $headers, $body);

        return $this->getResponseBody();
    }

    /**
     * Send a POST request for creating resources or objects or other operations like /auth
     *
     * @param string $path Endpoint URL path to invoke
     * @param mixed $body Request body
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function post(string $path, $body, ?array $headers = null): ?array
    {
        $this->sendRequestRetry('POST', $path, null, $headers, $body);

        return $this->getResponseBody();
    }

    /**
     * Send a DELETE request
     *
     * @param string $path Endpoint URL path to invoke.
     * @param mixed $body Request body
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format.
     */
    public function delete(string $path, $body = null, ?array $headers = null): ?array
    {
        $this->sendRequestRetry('DELETE', $path, null, $headers, $body);

        return $this->getResponseBody();
    }
}
