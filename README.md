# PHP OGP (Open Game Protcol) Client

This is a fork of official PHP OGP client (http://open-game-protocol.org/download.php) for latest version of PHP.

This SDK allow you to retrieve server status of your game server in real time.

# Usage

The port is the query port of server (port - 1).

```
<?php

require_once 'ogp.php';

$ogp = new \OGP\OGP('127.0.0.1', 27015);
if(!$ogp->getStatus()) {
    die("Error: ".$ogp->error);
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

# BBCode in hostname

```
<?php

require_once 'ogp.php';

function parseBBCodeHostname($text)
{
    $text = str_replace('?', '', $text);

    $find = array(
        '~\[g\](.*?)\[/g\]~s',
        '~\[b\](.*?)\[/b\]~s',
        '~\[i\](.*?)\[/i\]~s',
        '~\[u\](.*?)\[/u\]~s',
        '~\[c=(.*?)\](.*?)\[/c\]~s',
    );
    
    $replace = array(
        '<b>$1</b>',
        '<b>$1</b>',
        '<i>$1</i>',
        '<span style="text-decoration:underline;">$1</span>',
        '<span style="color:$1;">$2</span>',
    );
    
    return preg_replace($find,$replace,$text);
}


$ogp = new \OGP\OGP('127.0.0.1', 27015);
if(!$ogp->getStatus()) {
    die("Error: ".$ogp->error);
}

echo parseBBCodeHostname($ogp->serverInfo['HostName']);
```
# Games

* Onset (https://store.steampowered.com/app/1105810/Onset/)
* Half-Life
* Battlefield Bad Company 2
* Just Cause 2 (https://www.jc-mp.com/)

# More

* Get started: https://mtxserv.com/article/12440/onset_php_server_query
* More tools for Onset on https://onset-server.com/
