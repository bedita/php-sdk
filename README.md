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

You can use `getObjects` to retrieve a list of objects by type, `getObject` to get a single object by unique identifier and type.

Examples:

```php
    // get documents
    $response = (array)$client->getObjects('documents');

    // get documents using a filter
    $query = [
        'filter' => ['parent' => 'some-parent-id'],
    ];
    $response = (array)$client->getObjects('documents', $query);

    // get documents using a filter and more query options
    // include: including related data by relations "has_media" and "part_of"
    // lang: the desired lang (if translation is available)
    $query = [
        'filter' => ['parent' => 'some-parent-id'],
        'include' => 'has_media,part_of',
        'lang' => 'en_EN',
    ];
    $response = (array)$client->getObjects('documents', $query);
```

```php
    // get document 17823
    $response = (array)$client->getObject(17823, 'documents');

    // get document 17823, english version, including related data by relations "has_media" and "part_of"
    $query = [
        'lang' => 'en_EN',
        'include' => 'has_media,part_of',
    ];
    $response = (array)$client->getObject(17823, 'documents', ['lang' => 'en_EN']);

    // get folder website-footer
    $response = (array)$client->getObject('website-footer', 'folders');
```

You can also use `get` to retrieve a list of resources or objects or a single resource or object

```php
    // get api status
    $response = (array)$client->get('/status');

    // get api home info
    $response = (array)$client->get('/home');

    // get a list of documents
    $query = [
        'filter' => ['parent' => 'some-parent-id'],
    ];
    $response = (array)$client->get('/documents', $query);

    // get a stream by id
    $response = (array)$client->get('/streams/the-stream-id');
```

When you need to get relationships related data, you can you `getRelated`.

```php
    // get subfolders for a folder with ID 888
    $subfolders = $client->getRelated(888, 'folders', 'children', [
        'filter' => ['type' => 'folders'],
    ]);

    // get children (all types) inside a folder with unique name 'my-folder-uname'
    $children = $client->getRelated('my-folder-uname', 'folders', 'children');
```

### Save data

You can use `save` to save data.

Example:
```php
    // save a new document
    $data = [
        'title' => 'My new doc',
        'status' => 'on',
    ];
    $response = (array)$client->save('documents', $data);


    // save an existing document
    $data = [
        'id' => 999,
        'title' => 'My new doc, changed title',
    ];
    $response = (array)$client->save('documents', $data);
```

You can add related data using `addRelated`.

```php
    // save a document and add related data, in this example a "see_also" relation between documents and documents is involved
    $document = $client->save('documents', ['title' => 'My new doc']);
    $relatedData = [
        [
            'id' => 9999, // another doc id
            'type' => 'documents',
        ],
    ];
    $client->addRelated($document['data']['id'], 'documents', 'see_also', $relatedData);
```

Other methods doc TBD: saveObject, patch, post, replaceRelated

### Delete data: TBD

Other methods doc TBD: deleteObject delete, remove, removeRelated,

### Other: TBD

Other methods doc TBD: upload, createMediaFromStream, thumbs, schema, relationData, restoreObject
