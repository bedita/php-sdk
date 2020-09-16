<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2018 ChannelWeb Srl, Chialab Srl
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 */

namespace BEdita\SDK;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Http\Adapter\Guzzle6\Client;
use Psr\Http\Message\ResponseInterface;
use WoohooLabs\Yang\JsonApi\Client\JsonApiClient;

/**
 * BEdita4 API Client class
 */
class BEditaClient
{
    use LogTrait;

    /**
     * Last response.
     *
     * @var \Psr\Http\Message\ResponseInterface
     */
    private $response = null;

    /**
     * BEdita4 API base URL
     *
     * @var string
     */
    private $apiBaseUrl = null;

    /**
     * BEdita4 API KEY
     *
     * @var string
     */
    private $apiKey = null;

    /**
     * Default headers in request
     *
     * @var array
     */
    private $defaultHeaders = [
        'Accept' => 'application/vnd.api+json',
    ];

    /**
     * Default headers in request
     *
     * @var array
     */
    private $defaultContentTypeHeader = [
        'Content-Type' => 'application/json',
    ];

    /**
     * JWT Auth tokens
     *
     * @var array
     */
    private $tokens = [];

    /**
     * JSON API BEdita4 client
     *
     * @var \WoohooLabs\Yang\JsonApi\Client\JsonApiClient
     */
    private $jsonApiClient = null;

    /**
     * Setup main client options:
     *  - API base URL
     *  - API KEY
     *  - Auth tokens 'jwt' and 'renew' (optional)
     *
     * @param string $apiUrl API base URL
     * @param string $apiKey API key
     * @param array $tokens JWT Autorization tokens as associative array ['jwt' => '###', 'renew' => '###']
     * @return void
     */
    public function __construct(string $apiUrl, ?string $apiKey = null, array $tokens = [])
    {
        $this->apiBaseUrl = $apiUrl;
        $this->apiKey = $apiKey;

        $this->defaultHeaders['X-Api-Key'] = $this->apiKey;
        $this->setupTokens($tokens);

        // setup an asynchronous JSON API client
        $guzzleClient = Client::createWithConfig([]);
        $this->jsonApiClient = new JsonApiClient($guzzleClient);
    }

    /**
     * Setup JWT access and refresh tokens.
     *
     * @param array $tokens JWT tokens as associative array ['jwt' => '###', 'renew' => '###']
     * @return void
     */
    public function setupTokens(array $tokens): void
    {
        $this->tokens = $tokens;
        if (!empty($tokens['jwt'])) {
            $this->defaultHeaders['Authorization'] = sprintf('Bearer %s', $tokens['jwt']);
        } else {
            unset($this->defaultHeaders['Authorization']);
        }
    }

    /**
     * Get default headers in use on every request
     *
     * @return array Default headers
     * @codeCoverageIgnore
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    /**
     * Get API base URL used tokens
     *
     * @return string API base URL
     * @codeCoverageIgnore
     */
    public function getApiBaseUrl(): string
    {
        return $this->apiBaseUrl;
    }

    /**
     * Get current used tokens
     *
     * @return array Current tokens
     * @codeCoverageIgnore
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * Get last HTTP response
     *
     * @return ResponseInterface|null Response PSR interface
     * @codeCoverageIgnore
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Get HTTP response status code
     * Return null if no response is available
     *
     * @return int|null Status code.
     */
    public function getStatusCode(): ?int
    {
        return $this->response ? $this->response->getStatusCode() : null;
    }

    /**
     * Get HTTP response status message
     * Return null if no response is available
     *
     * @return string|null Message related to status code.
     */
    public function getStatusMessage(): ?string
    {
        return $this->response ? $this->response->getReasonPhrase() : null;
    }

    /**
     * Get response body serialized into a PHP array
     *
     * @return array|null Response body as PHP array.
     */
    public function getResponseBody(): ?array
    {
        $response = $this->getResponse();
        if (empty($response)) {
            return null;
        }
        $responseBody = json_decode((string)$response->getBody(), true);
        if (!is_array($responseBody)) {
            return null;
        }

        return $responseBody;
    }

    /**
     * Classic authentication via POST /auth using username and password
     *
     * @param string $username username
     * @param string $password password
     * @return array|null Response in array format
     */
    public function authenticate(string $username, string $password): ?array
    {
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
     * @param string $data Related resources or objects to add, MUST contain id and type
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
     * @param string $data Related resources or objects to remove from relation
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
     * @param string $data Related resources or objects to insert
     * @param array|null $headers Custom request headers
     * @return array|null Response in array format
     */
    public function replaceRelated($id, string $type, string $relation, array $data, ?array $headers = null): ?array
    {
        $body = compact('data');

        $result = $this->patch(sprintf('/%s/%s/relationships/%s', $type, $id, $relation), json_encode($body), $headers);

        $dataWithMeta = array_filter($body, function ($item) {
            return !empty($item['meta']);
        });
        if (!empty($bodyWithMeta)) {
            $response = $this->response;
            $this->post(sprintf('/%s/%s/relationships/%s', $type, $id, $relation), json_encode(['data' => $bodyWithMeta]), $headers);
            $this->response = $response;
        }

        return $result;
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

    /**
     * Send a generic JSON API request with a basic retry policy on expired token exception.
     *
     * @param string $method HTTP Method.
     * @param string $path Endpoint URL path.
     * @param array|null $query Query string parameters.
     * @param string[]|null $headers Custom request headers.
     * @param string|resource|\Psr\Http\Message\StreamInterface|null $body Request body.
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function sendRequestRetry(string $method, string $path, ?array $query = null, ?array $headers = null, $body = null): ResponseInterface
    {
        try {
            return $this->sendRequest($method, $path, $query, $headers, $body);
        } catch (BEditaClientException $e) {
            // Handle error.
            $attributes = $e->getAttributes();
            if ($e->getCode() !== 401 || empty($attributes['code']) || $attributes['code'] !== 'be_token_expired') {
                // Not an expired token's fault.
                throw $e;
            }

            // Refresh and retry.
            $this->refreshTokens();
            unset($headers['Authorization']);

            return $this->sendRequest($method, $path, $query, $headers, $body);
        }
    }

    /**
     * Send a generic JSON API request and retrieve response $this->response
     *
     * @param string $method HTTP Method.
     * @param string $path Endpoint URL path (with or without starting `/`) or absolute API path
     * @param array|null $query Query string parameters.
     * @param string[]|null $headers Custom request headers.
     * @param string|resource|\Psr\Http\Message\StreamInterface|null $body Request body.
     * @return \Psr\Http\Message\ResponseInterface
     * @throws BEditaClientException Throws an exception if server response code is not 20x.
     */
    protected function sendRequest(string $method, string $path, ?array $query = null, ?array $headers = null, $body = null): ResponseInterface
    {
        $uri = $this->requestUri($path, $query);
        $headers = array_merge($this->defaultHeaders, (array)$headers);

        // set default `Content-Type` if not set and $body not empty
        if (!empty($body)) {
            $headers = array_merge($this->defaultContentTypeHeader, $headers);
        }

        // Send the request synchronously to retrieve the response.
        // Request and response log performed only if configured via `initLogger()`
        $request = new Request($method, $uri, $headers, $body);
        $this->logRequest($request);
        $this->response = $this->jsonApiClient->sendRequest($request);
        $this->logResponse($this->response);
        if ($this->getStatusCode() >= 400) {
            // Something bad just happened.
            $response = $this->getResponseBody();
            // Message will be 'error` array, if absent use status massage
            $message = empty($response['error']) ? $this->getStatusMessage() : $response['error'];
            throw new BEditaClientException($message, $this->getStatusCode());
        }

        return $this->response;
    }

    /**
     * Create request URI from path.
     * If path is absolute, i.e. it starts with 'http://' or 'https://', path is unchanged.
     * Otherwise `$this->apiBaseUrl` is prefixed, prepending a `/` if necessary.
     *
     * @param string $path Endpoint URL path (with or without starting `/`) or absolute API path
     * @param array|null $query Query string parameters.
     * @return Uri
     */
    protected function requestUri(string $path, ?array $query = null): Uri
    {
        if (strpos($path, 'https://') !== 0 && strpos($path, 'http://') !== 0) {
            if (substr($path, 0, 1) !== '/') {
                $path = '/' . $path;
            }
            $path = $this->apiBaseUrl . $path;
        }
        $uri = new Uri($path);

        // if path contains query strings, remove them from path and add them to query filter
        parse_str($uri->getQuery(), $uriQuery);
        if ($query) {
            $query = array_merge((array)$uriQuery, (array)$query);
            $uri = $uri->withQuery(http_build_query($query));
        }

        return $uri;
    }

    /**
     * Refresh JWT access token.
     *
     * On success `$this->tokens` data will be updated with new access and renew tokens.
     *
     * @throws \BadMethodCallException Throws an exception if client has no renew token available.
     * @throws \Cake\Network\Exception\ServiceUnavailableException Throws an exception if server response doesn't
     *      include the expected data.
     * @return void
     * @throws BEditaClientException Throws an exception if server response code is not 20x.
     */
    public function refreshTokens(): void
    {
        if (empty($this->tokens['renew'])) {
            throw new \BadMethodCallException('You must be logged in to renew token');
        }

        $headers = [
            'Authorization' => sprintf('Bearer %s', $this->tokens['renew']),
        ];

        $this->sendRequest('POST', '/auth', [], $headers);
        $body = $this->getResponseBody();
        if (empty($body['meta']['jwt'])) {
            throw new BEditaClientException('Invalid response from server');
        }

        $this->setupTokens($body['meta']);
    }
}
