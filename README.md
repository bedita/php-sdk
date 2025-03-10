# BEdita API PHP SDK

BEdita PHP Official PHP SDK

[![Github Actions PHP](https://github.com/bedita/php-sdk/workflows/php/badge.svg)](https://github.com/bedita/php-sdk/actions?query=workflow%3Aphp)
[![Code Coverage](https://codecov.io/gh/bedita/php-sdk/branch/master/graph/badge.svg)](https://codecov.io/gh/bedita/bedita/branch/master)
[![phpstan](https://img.shields.io/badge/PHPStan-level%200-brightgreen.svg)](https://phpstan.org)
[![psalm](https://img.shields.io/badge/psalm-level%208-brightgreen.svg)](https://psalm.dev)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/bedita/php-sdk/badges/quality-score.png)](https://scrutinizer-ci.com/g/bedita/php-sdk/)
[![Version](https://img.shields.io/packagist/v/bedita/php-sdk.svg?label=stable)](https://packagist.org/packages/bedita/php-sdk)
[![License](https://img.shields.io/badge/license-MIT-green)](https://github.com/bedita/php-sdk/blob/master/LICENSE.txt)

## Prerequisites

* PHP >= 8.3
* [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)

## Install

To install the latest version of this library, run the command below:

```bash
composer require bedita/php-sdk
```

## Basic Usage

### Init BEditaClient

Instantiate BEditaClient with API url and API key.
This will usually be done via factory methods like [ApiclientProvider::getApiClient()](https://github.com/bedita/web-tools/blob/master/src/ApiClientProvider.php) that will read from environment variables and/or configuration files.

```php
    $apiUrl = 'http://your-api-url';
    $apiKey = 'your-api-key';
    /** @var \BEdita\SDK\BEditaClient $client */
    $client = new BEditaClient($apiUrl, $apiKey);
```

### Init Logger for custom log

You can activate a detailed request/response log to file of API calls using `initLogger()` method.
This will be also usually performed by factory methods like [ApiclientProvider::getApiClient()](https://github.com/bedita/web-tools/blob/master/src/ApiClientProvider.php).

```php
   $client->initLogger(['log_file' => '/path/to/file/name.log']);
```

### Request Headers

Some headers are handled as default in API calls:

* `Accept` as `application/vnd.api+json` to use JSON-API format
* `Content-Type` as `application/json` when a request body is set
* `Authorization` with `Bearer {token}` if a JWT token has been set
* `X-Api-Key` with the key passed to contructor

Upon every API call you can add new headers or overwrite defaults in the `$header` array argument (see below).

### Authenticate and tokens

To retrieve or set JWT tokens you can:

* use `authenticate(string $username, string $password)` method to perform a classic username/password authentication
* use `setupTokens()` to set JWT tokens, retrieved from previous `authenticate` or other methods, for subsequent calls

```php
    $response = (array)$client->authenticate('my-username', 'my-password');
    if (!empty($response['meta'])) {
        $client->setupTokens($response['meta']);
    }
```

On every subsequent API call JWT token will be used in `Authentication` header and expired token response will also be handled via refresh token automatically by the SDK.

### Direct API calls and utility methods

In order to use BEdita 4 API you have generally two options:

1. use direct API calls invoking POST/GET/PATCH/DELETE HTTP methods using correspondent `get()`/`post()`/`patch()`/`delete()` methods making sure that path, query string, body and headers are correctly populated;
2. use the utility methods explained below like `save`, `addRelated` and others to simplify settings and obtain a clearer code.

### Read objects and resources

You can use `getObjects(string $type = 'objects', ?array $query = null, ?array $headers = null)` to retrieve a list of objects by type, `getObject(int|string $id, string $type = 'objects', ?array $query = null, ?array $headers = null)` to get a single object by unique identifier and type.

Examples:

```php
    // get documents
    $response = (array)$client->getObjects('documents'); // argument passed is a string $type

    // get documents using a filter
    $query = [
        'filter' => ['parent' => 'some-parent-id'],
    ];
    $response = (array)$client->getObjects('documents', $query); // arguments passed are string $type and array $query

    // get documents using a filter and more query options
    // include: including related data by relations "has_media" and "part_of"
    // lang: the desired lang (if translation is available)
    $query = [
        'filter' => ['parent' => 'some-parent-id'],
        'include' => 'has_media,part_of',
        'lang' => 'en',
    ];
    $response = (array)$client->getObjects('documents', $query); // arguments passed are string $type and array $query
```

```php
    // get document 17823
    $response = (array)$client->getObject(17823, 'documents'); // arguments passed are int|string $id, string $type

    // get document 17823, english version, including related data by relations "has_media" and "part_of"
    $query = [
        'lang' => 'en',
        'include' => 'has_media,part_of',
    ];
    $response = (array)$client->getObject(17823, 'documents', ['lang' => 'en_EN']); // arguments passed are int|string $id, string $type and array $query

    // get folder website-footer
    $response = (array)$client->getObject('website-footer', 'folders'); // arguments passed are int|string $id, string $type
```

You can also use `get(string $path, ?array $query = null, ?array $headers = null)` to make an explicit GET API call, specifying the complete request path.
This is the correct way to call special endpoints like `/home` or `/status` for instance (GET calls that are not used to retrieve objects o resources).

```php
    // get API status
    $response = (array)$client->get('/status'); // argument passed is string $path

    // get API home info
    $response = (array)$client->get('/home'); // argument passed is string $path

    // get a list of documents
    $query = [
        'filter' => ['parent' => 'some-parent-id'],
    ];
    $response = (array)$client->get('/documents', $query); // arguments passed are string $path and array $query

    // get a stream by id
    $response = (array)$client->get('/streams/the-stream-id'); // argument passed is string $path
```

When you need to get relationships related data, you can you `getRelated(int|string $id, string $type, string $relation, ?array $query = null, ?array $headers = null)`.

```php
    // get subfolders for a folder with ID 888
    $query = [
        'filter' => ['type' => 'folders'],
    ];
    $subfolders = $client->getRelated(888, 'folders', 'children', $query); // arguments passed are int|string $id, string $type, string $relation, array $query

    // get children (all types) inside a folder with unique name 'my-folder-uname'
    $children = $client->getRelated('my-folder-uname', 'folders', 'children'); // arguments passed are int|string $id, string $type, string $relation
```

### Save objects and resources

You can use `save(string $type, array $data, ?array $headers = null)` method: this is the standard method to use to create or update objects or resources in BEdita 4.
Please note that the payload is made of object attributes, without the need to specify a `data.attribute` section that you must use when dealing with direct POST o PATCH API calls.

Example:

```php
    // save a new document
    $data = [
        'title' => 'My new doc',
        'status' => 'on',
    ];
    $response = (array)$client->save('documents', $data); // arguments passed are string $type, array $data
    // retrocompatibility version with saveObject, deprecated
    $response = (array)$client->saveObject('documents', $data); // arguments passed are string $type, array $data

    // save an existing document
    $data = [
        'id' => 999,
        'title' => 'My new doc, changed title',
    ];
    $response = (array)$client->save('documents', $data); // arguments passed are string $type, array $data
    // retrocompatibility version with saveObject, deprecated
    $response = (array)$client->saveObject('documents', $data); // arguments passed are string $type, array $data
```

`save` and `saveObject` use internally `patch(string $path, mixed $body, ?array $headers = null)` (when saving an existing object) or `post(string $path, mixed $body, ?array $headers = null)` (when saving a new object).

If you like to use them directly:

```php
    // save a new document
    $data = [
        'type' => 'documents',
        'attributes' => [
            'title' => 'My doc',
            'status' => 'on',
        ],
    ];
    $response = (array)$client->post('/documents', $data); // arguments passed are string $path, array $data

    // save an existing document
    $data = [
        'type' => 'documents',
        'attributes' => [
            'title' => 'My doc',
            'status' => 'on',
        ],
    ];
    $response = (array)$client->post('/documents/999', $data); // arguments passed are string $path, array $data
```

### Delete and restore data

#### Soft delete

Soft delete puts object into the trashcan.
You can trash an object with `delete(string $path, mixed $body = null, ?array $headers = null)` or `deleteObject(int|string $id, string $type)`.

```php
    // delete document by ID 99999
    $response = $client->delete('/documents/99999'); // argument passed is string $path

    // delete document by ID 99999 and type documents
    $response = $client->deleteObject(99999, 'documents'); // arguments passed are string|int $id, string $type

    // delete multiple
    $response = $client->deleteObjects([999991, 999992, 999993], 'documents');

```

#### Restore data

Data in trashcan can be restored with `restoreObject(int|string $id, string $type)`.

```php
    // restore document 99999
    $response = $client->restoreObject(99999, 'documents'); // arguments passed are string|int $id, string $type

    // restore multiple
    $response = $client->restoreObjects([999991, 999992, 999993], 'documents');
```

#### Hard delete

Hard delete removes object from trashcan.
You can remove an object from trashcan with `remove(int|string $id)`.

```php
    // delete document by ID 99999
    $response = $client->deleteObject(99999, 'documents'); // arguments passed are string|int $id, string $type

    // permanently remove documents 99999
    $response = $client->remove(99999); // argument passed is string|int $id

    // permanently remove multiple
    $response = $client->removeObjects([999991, 999992, 999993]);
```

### Working with relations

#### Add related objects

You can add related objects using `addRelated(int|string $id, string $type, string $relation, array $data, ?array $headers = null)`.

```php
    // save a document and add related objects, in this example a "see_also" relation between documents and documents is involved
    $document = $client->save('documents', ['title' => 'My new doc']);
    $relatedData = [
        [
            'id' => 9999, // another doc id
            'type' => 'documents',
        ],
    ];
    $client->addRelated($document['data']['id'], 'documents', 'see_also', $relatedData); // arguments passed are int|string $id, string $type, string $relation, array $relationData
```

#### Replace related objects

`replaceRelated(int|string $id, string $type, string $relation, array $data, ?array $headers = null)` is handy to replace related objects.

```php
    // replace related objects, in this example a "see_also" relation between document 8888 and document 9999
    $relatedData = [
        [
            'id' => 9999,
            'type' => 'documents',
        ],
    ];
    $client->replaceRelated(8888, 'documents', 'see_also', $relatedData); // arguments passed are int|string $id, string $type, string $relation, array $relationData
```

Note: internally `addRelated` uses `post` and `replaceRelated` uses `patch`. Both will call `/:type/:id/relationships/:relation`

#### Remove related objects

Related objects can be removed using `removeRelated(int|string $id, string $type, string $relation, array $data, ?array $headers = null)`.

```php
    // remove related data, in this example a "see_also" relation between document 8888 and document 9999
    $relatedData = [
        [
            'id' => 9999,
            'type' => 'documents',
        ],
    ];
    $client->removeRelated(8888, 'documents', 'see_also', $relatedData); // arguments passed are int|string $id, string $type, string $relation, array $relationData
```

### Upload

The easiest way to create a new media object with file upload is to call a POST with file content as body.

Example of image upload:

```php
    $content = file_get_contents('/path/to/image.png');

    $response = $client->post('/images/upload/image.png',
        $content,
        ['Content-type' => 'image/png']
    );
```

A new object of type `images` will be created having `image.png` file as related stream resource.
If you need instead to handle more low level actions via streams read the paragraphs below.

#### Upload streams

Use `upload(string $filename, string $filepath, ?array $headers = null)` to perform a `POST /streams/upload/:filename` and create a new stream with your file.

```php
    // upload the image /home/gustavo/sample.jpg
    $response = $client->upload('sample.jpg', '/home/gustavo/sample.jpg');
```

Note: if you don't pass `$headers` argument, the function uses `mime_content_type($filepath)`.

```php
    // upload the image /home/gustavo/sample.jpg, passing content type
    $response = $client->upload('sample.jpg', '/home/gustavo/sample.jpg', ['Content-type' => 'image/jpeg']);
```

#### Create media from stream

You create a media object from a stream with `createMediaFromStream(string $streamId, string $type, array $body)`. This basically makes 3 calls:

* `POST /:type` with `$body` as payload, create media object
* `PATCH /streams/:stream_id/relationships/object` modify stream adding relation to media
* `GET /:type/:id` get media data

```php
    // upload an audio file
    $filepath = '/home/gustavo/sample.mp3';
    $filename = basename($filepath);
    $headers = ['Content-type' => 'audio/mpeg'];
    $response = $client->upload($filename, $filepath, $headers);

    // create media from stream
    $streamId = Hash::get($response, 'data.id');
    $body = [
        'data' => [
            'type' => 'audios',
            'attributes' => [
                'title' => $filename,
                'status' => 'on',
            ],
        ],
    ];
    $response = $client->createMediaFromStream($id, $type, $body);
```

### Thumbnails

Media thumbnails can be retrived using `thumbs(int|null $id, $query = [])`.

Usage:

```php
    // get thumbnail for media 123 => GET /media/thumbs/123
    $client->thumbs(123);

    // get thumbnail for media 123 with a preset => GET /media/thumbs/123&preset=glide
    $client->thumbs(123, ['preset' => 'glide']);

    // get thumbnail for multiple media => GET /media/thumbs?ids=123,124,125
    $client->thumbs(null, ['ids' => '123,124,125']);

    // get thumbnail for multiple media with a preset => GET /media/thumbs?ids=123,124,125&preset=async
    $client->thumbs(null, ['ids' => '123,124,125', 'preset' => 'async']);

    // get thumbnail media 123 with specific options (these options could be not available... just set in preset(s)) => GET /media/thumbs/123/options[w]=100&options[h]=80&options[fm]=jpg
    $client->thumbs(123, ['options' => ['w' => 100, 'h' => 80, 'fm' => 'jpg']]);
```

### Schema

You can get the JSON SCHEMA of a resource or object with `schema(string $type)`.

```php
    // get schema of users => GET /model/schema/users
    $schema = $client->schema('users');
```

Get info of a relation (data, params) and get left/right object types using `relationData(string $name)`.

```php
    // get relation data of relation see_also => GET /model/relations/see_also
    $schema = $client->relationData('see_also');
```
