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

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Http\Adapter\Guzzle7\Client;
use Psr\Http\Message\ResponseInterface;
use WoohooLabs\Yang\JsonApi\Client\JsonApiClient;

class BaseClient
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
     * @param string|null $apiKey API key
     * @param array $tokens JWT Autorization tokens as associative array ['jwt' => '###', 'renew' => '###']
     * @param array $guzzleConfig Additional default configuration for GuzzleHTTP client.
     * @return void
     */
    public function __construct(string $apiUrl, ?string $apiKey = null, array $tokens = [], array $guzzleConfig = [])
    {
        $this->apiBaseUrl = $apiUrl;
        $this->apiKey = $apiKey;

        $this->defaultHeaders['X-Api-Key'] = $this->apiKey;
        $this->setupTokens($tokens);

        // setup an asynchronous JSON API client
        $guzzleClient = Client::createWithConfig($guzzleConfig);
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
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    /**
     * Get API base URL used tokens
     *
     * @return string API base URL
     */
    public function getApiBaseUrl(): string
    {
        return $this->apiBaseUrl;
    }

    /**
     * Get current used tokens
     *
     * @return array Current tokens
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * Get last HTTP response
     *
     * @return ResponseInterface|null Response PSR interface
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

        return is_array($responseBody) ? $responseBody : null;
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
        $data = ['grant_type' => 'refresh_token'];

        $this->sendRequest('POST', '/auth', [], $headers, json_encode($data));
        $body = $this->getResponseBody();
        if (empty($body['meta']['jwt'])) {
            throw new BEditaClientException('Invalid response from server');
        }

        $this->setupTokens($body['meta']);
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
     * Refresh and retry.
     *
     * @param string $method HTTP Method.
     * @param string $path Endpoint URL path.
     * @param array|null $query Query string parameters.
     * @param string[]|null $headers Custom request headers.
     * @param string|resource|\Psr\Http\Message\StreamInterface|null $body Request body.
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function refreshAndRetry(string $method, string $path, ?array $query = null, ?array $headers = null, $body = null): ResponseInterface
    {
        $this->refreshTokens();
        unset($headers['Authorization']);

        return $this->sendRequest($method, $path, $query, $headers, $body);
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
     * Unset Authorization from defaultHeaders.
     *
     * @return void
     */
    protected function unsetAuthorization(): void
    {
        if (!array_key_exists('Authorization', $this->defaultHeaders)) {
            return;
        }
        unset($this->defaultHeaders['Authorization']);
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
