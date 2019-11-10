# PHP OGP (Open Game Protcol) Client

This is a fork of official PHP OGP client (http://open-game-protocol.org/download.php) for latest version of PHP.

This SDK allow you to retrieve server status of your game server in real time.

# Usage

```
<?php

require_once 'ogp.php';

$ogp = new \OGP\OGP('127.0.0.1', 27015);
if(!$ogp->getStatus()) {
    die("Error: ".$ogp->error);
}

var_dump(
  $ogp->SERVERINFO, 
  $ogp->PLAYERLIST, 
  $ogp->RULELIST, 
  $ogp->TEAMLIST, 
  $ogp->ADDONLIST,
  $ogp->LIMITLIST, 
  $ogp->ping
);
```

# Games

* Onset (https://store.steampowered.com/app/1105810/Onset/)
* Half-Life
* Battlefield Bad Company 2
* Just Cause 2 (https://www.jc-mp.com/)

# More tools

* See https://onset-server.com/
