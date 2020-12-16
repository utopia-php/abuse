# Utopia Abuse

[![Build Status](https://travis-ci.org/utopia-php/abuse.svg?branch=master)](https://travis-ci.com/utopia-php/abuse)
![Total Downloads](https://img.shields.io/packagist/dt/utopia-php/abuse.svg)
[![Discord](https://img.shields.io/discord/564160730845151244)](https://appwrite.io/discord)

Utopia framework abuse library is simple and lite library for managing application usage limits. This library is aiming to be as simple and easy to learn and use. This library is maintained by the [Appwrite team](https://appwrite.io).

Although this library is part of the [Utopia Framework](https://github.com/utopia-php/framework) project it is dependency free, and can be used as standalone with any other PHP project or framework.

## Getting Started

Install using composer:
```bash
composer require utopia-php/abuse
```

**Time Limit Abuse**

The time limit abuse allow each key (action) to be performed [X] times in given time frame.
This adapter uses a MySQL / MariaDB to store usage attempts. Before using it create the table schema as documented in this repository (./data/schema.sql)

```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapters\TimeLimit;

// Limit login attempts to 10 time in 5 minutes time frame
$adapter    = new TimeLimit('login-attempt-from-{{ip}}', 10, (60 * 5), function () {/* init and return PDO connection... */});

$adapter
    ->setNamespace('namespace') // DB table namespace
    ->setParam('{{ip}}', '127.0.0.1')
;

$abuse      = new Abuse($adapter);

// Use vars to resolve adapter key

if(!$abuse->check()) {
    throw new Exception('Service was abused!'); // throw error and return X-Rate limit headers here
}
```

**ReCaptcha Abuse**

The ReCaptcha abuse controller is using Google ReCaptcha service to detect when service is being abused by bots.
To use this adapter you need to create an API key from the Google ReCaptcha service [admin console](https://www.google.com/recaptcha/admin).

```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapters\ReCaptcha;

// Limit login attempts to 10 time in 5 minutes time frame
$adapter    = new ReCaptcha('secret-api-key', $_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
$abuse      = new Abuse($adapter);

if(!$abuse->check()) {
    throw new Exception('Service was abused!'); // throw error and return X-Rate limit headers here
}
```

*Notice: The code above is for example purpose only. It is always recommended to validate user input before using it in your code. If you are using a load balancer or any proxy server you might need to get user IP from the HTTP_X_FORWARDE‌​D_FOR header.*

## System Requirements

Utopia Framework requires PHP 7.3 or later. We recommend using the latest PHP version whenever possible.

## Authors

**Eldad Fux**

+ [https://twitter.com/eldadfux](https://twitter.com/eldadfux)
+ [https://github.com/eldadfux](https://github.com/eldadfux)

## Copyright and license

The MIT License (MIT) [http://www.opensource.org/licenses/mit-license.php](http://www.opensource.org/licenses/mit-license.php)
