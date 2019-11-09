# PHP OGP (Open Game Protcol) Client

This is a fork of official client (http://open-game-protocol.org/download.php) for latest version of PHP.

# Usage

```
<?php

require_once 'ogp.class.php';

$ogp = new OGP('127.0.0.1', 27015);
if(!$ogp->getStatus()) {
    die("Error1: ".$ogp->error);
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

* Onset
* Half-Life
* Battlefield Bad Company 2
* Just Cause 2 (https://www.jc-mp.com/)
