# BEdita API PHP SDK

BEdita4 PHP Official PHP SDK

[![Github Actions](https://github.com/bedita/php-sdk/workflows/php/badge.svg)](https://github.com/bedita/php-sdk/actions?query=workflow%3Aphp)
[![Code Coverage](https://codecov.io/gh/bedita/php-sdk/branch/master/graph/badge.svg)](https://codecov.io/gh/bedita/bedita/branch/master)

## Prerequisites

* PHP 7.2, 7.3 or 7.4
* [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)

## Install

To install the latest version of this library, run the command below:

```bash
composer require bedita/php-sdk
```

## Basic Usage

### Init BEditaClient

Instantiate BEditaClient with api url and api key.

```php
    $apiUrl = 'http://your-api-url';
    $apiKey = 'your-api-key';
    /** @var \BEdita\SDK\BEditaClient $client */
    $client = new BEditaClient($apiUrl, $apiKey);
```

### Init Logger for custom log

You can use a custom log, calling `initLogger`:

```php
   $client->initLogger(['log_file' => '/path/to/file/name.log']);
```

### Retrieve data

You can use `getObjects($type, $query)` to retrieve a list of objects by type, `getObject($id, $type, $query)` to get a single object by unique identifier and type.

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
        'lang' => 'en_EN',
    ];
    $response = (array)$client->getObjects('documents', $query); // arguments passed are string $type and array $query
```

```php
    // get document 17823
    $response = (array)$client->getObject(17823, 'documents'); // arguments passed are int|string $id, string $type

    // get document 17823, english version, including related data by relations "has_media" and "part_of"
    $query = [
        'lang' => 'en_EN',
        'include' => 'has_media,part_of',
    ];
    $response = (array)$client->getObject(17823, 'documents', ['lang' => 'en_EN']); // arguments passed are int|string $id, string $type and array $query

    // get folder website-footer
    $response = (array)$client->getObject('website-footer', 'folders'); // arguments passed are int|string $id, string $type
```

You can also use `get($path, $query)` to retrieve a list of resources or objects or a single resource or object

```php
    // get api status
    $response = (array)$client->get('/status'); // argument passed is string $path

    // get api home info
    $response = (array)$client->get('/home'); // argument passed is string $path

    // get a list of documents
    $query = [
        'filter' => ['parent' => 'some-parent-id'],
    ];
    $response = (array)$client->get('/documents', $query); // arguments passed are string $path and array $query

    // get a stream by id
    $response = (array)$client->get('/streams/the-stream-id'); // argument passed is string $path
```

When you need to get relationships related data, you can you `getRelated($id, $type, $relation, $query)`.

```php
    // get subfolders for a folder with ID 888
    $query = [
        'filter' => ['type' => 'folders'],
    ];
    $subfolders = $client->getRelated(888, 'folders', 'children', $query); // arguments passed are int|string $id, string $type, string $relation, array $query

    // get children (all types) inside a folder with unique name 'my-folder-uname'
    $children = $client->getRelated('my-folder-uname', 'folders', 'children'); // arguments passed are int|string $id, string $type, string $relation
```

### Save data

You can use `save($type, $data)` or `saveObject($type, $data)` (deprecated, use `save` instead) to save data.

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

You can add related data using `addRelated($id, $type, $relation, $relationData)`.

```php
    // save a document and add related data, in this example a "see_also" relation between documents and documents is involved
    $document = $client->save('documents', ['title' => 'My new doc']);
    $relatedData = [
        [
            'id' => 9999, // another doc id
            'type' => 'documents',
        ],
    ];
    $client->addRelated($document['data']['id'], 'documents', 'see_also', $relatedData); // arguments passed are int|string $id, string $type, string $relation, array $relationData
```

Other methods doc TBD: patch, post, replaceRelated

### Delete and restore data

#### Soft delete

Soft delete puts object into the trashcan.
You can trash an object with `delete($path)` or `deleteObject($id, $type)`.

```php
    // delete annotation by ID 99999
    $response = $client->delete('/annotations/99999'); // argument passed is string $path

    // delete annotation by ID 99999 and type documents
    $response = $client->deleteObject(99999, 'annotations'); // arguments passed are string|int $id, string $type
```

#### Restore data

Data in trashcan can be restored with `restoreObject($id, $type)`.

```php
    // restore annotation 99999
    $response = $client->restoreObject(99999, 'annotations'); // arguments passed are string|int $id, string $type
```

#### Hard delete

Hard delete removes object from trashcan.
You can remove an object from trashcan with `remove($id)`.

```php
    // delete annotation by ID 99999
    $response = $client->deleteObject(99999, 'annotations'); // arguments passed are string|int $id, string $type

    // permanently remove annotations 99999
    $response = $client->remove(99999); // argument passed is string|int $id
```

Other methods doc TBD: removeRelated,

### Other: TBD

Other methods doc TBD: upload, createMediaFromStream, thumbs, schema, relationData, restoreObject
