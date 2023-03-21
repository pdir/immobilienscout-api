# Simple Immobilienscout24 PHP API

## Requirements

PHP: >= 7.2  
Extensions: [Composer](https://getcomposer.org/), [PHP-JSON](https://www.php.net/manual/en/book.json.php)

## Install

composer:  
`composer require pdir/immoscout-api`

## Usage

Search for the official API Documentation [here](https://api.immobilienscout24.de/api-docs/get-started/introduction/).  
You need an *Consumer Key* and *Consumer Secret* - [Get your client credentials](https://api.immobilienscout24.de/api-docs/get-started/get-your-client-credentials/).

Use [IS24 RestAPI playground](https://playground.immobilienscout24.de/rest/playground) for testing and getting the Access Token and Access Secret.

Manual [generate Access Token and Access Secret](https://pdir.de/docs/de/customer/immobileinscout24-api/) (German)

### Basic
```php
// store keys in .env file or use credentials array
$credentials = [
    'consumerKey' => 'IS24_CONSUMER_KEY',
    'consumerSecret' => 'IS24_CONSUMER_SECRET',
    'tokenKey' => 'IS24_TOKEN_KEY',
    'tokenSecret' => 'IS24_TOKEN_SECRET',    
];

$api = new \Pdir\Immoscout\Api();
or
$api = new \Pdir\Immoscout\Api($credentials);

// get all real estates with details
$estates = $api->getAllRealEstates(true);

// get only active real estates with details
$estates = $api->getAllRealEstates(true, false, true);

// get real estate by id
$estate = $api->getRealEstate('1234567890');

// get attachments by id
$attachments = $api->getAttachments('1234567890');

// get contact by id
$contact = $api->getContact('1234567890');
```