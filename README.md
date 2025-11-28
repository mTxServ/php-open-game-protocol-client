# PHP OGP (Open Game Protocol) Client

This is a modern PHP 8.3+ client for the Open Game Protocol (OGP). It allows you to retrieve server status of your game server in real time.

Useful for [Onset servers](https://mtxserv.com/onset-server).

## Installation

Install the package via Composer:

```bash
composer require mtxserv/php-ogp
```

## Usage

The port is typically the query port of the server (often port - 1).

```php
<?php

require 'vendor/autoload.php';

use OGP\OGP;

$ogp = new OGP('127.0.0.1', 27015);

if (!$ogp->getStatus()) {
    die("Error: " . $ogp->error);
}

var_dump(
    $ogp->serverInfo,
    $ogp->players,
    $ogp->rules,
    $ogp->teams,
    $ogp->addons,
    $ogp->limits,
    $ogp->ping
);
```

## BBCode in Hostname

Some servers use BBCode in their hostname. You can parse it like this:

```php
<?php

require 'vendor/autoload.php';

use OGP\OGP;

function parseBBCodeHostname($text)
{
    $text = str_replace('?', '', $text);

    $find = [
        '~\[g\](.*?)\[/g\]~s',
        '~\[b\](.*?)\[/b\]~s',
        '~\[i\](.*?)\[/i\]~s',
        '~\[u\](.*?)\[/u\]~s',
        '~\[c=(.*?)\](.*?)\[/c\]~s',
    ];
    
    $replace = [
        '<b>$1</b>',
        '<b>$1</b>',
        '<i>$1</i>',
        '<span style="text-decoration:underline;">$1</span>',
        '<span style="color:$1;">$2</span>',
    ];
    
    return preg_replace($find, $replace, $text);
}

$ogp = new OGP('127.0.0.1', 27015);

if (!$ogp->getStatus()) {
    die("Error: " . $ogp->error);
}

echo parseBBCodeHostname($ogp->serverInfo['HostName'] ?? 'Unknown Hostname');
```

## Supported Games

* [Onset](https://store.steampowered.com/app/1105810/Onset/)
* Half-Life
* Battlefield Bad Company 2
* [Just Cause 2](https://www.jc-mp.com/)
* And other OGP supported games.

## Requirements

* PHP 8.3 or higher

## License

MIT
