# PHP library for REST API of VseMayki

This library provides convenient wrapper functions for VseMayki's REST API.
The API is [documented here](http://rest.vsemayki.ru/doc/index.html).
 
## Requirements
 
 - PHP 5.5.0 or greater
 - [Composer](https://getcomposer.org/)
 
## Installation

`composer require vsemayki/restapi`

## Example

```php

require 'vendor/autoload.php';

$rest = new VseMayki\RestConnector($clientId, $clientSecret);

$result = $rest->sendRequest(
    '/order/options',
    [
        'user_id'             => 0,
        'cart'                => [],
        'address'             => [],
        'isMergePickupPoints' => true
    ],
    'POST');

```
