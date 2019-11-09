<?php

class OGP
{
    /**
     * OGP::$addr.
     *
     * Stores the address to query
     *
     * @var string
     */
    public $addr = '';
    /**
     * OGP::$port.
     *
     * Stores the port to query
     *
     * @var int
     */
    public $port = 0;
    /**
     * OGP::$timeout.
     *
     * The query timeout (total time)
     *
     * @var int
     */
    public $timeout = 0;
    /**
     * OGP::$error.
     *
     * Stores the last Errormessage
     *
     * @var string
     */
    public $error = '';
    /**
     * OGP::$queryres.
     *
     * Stores the last received Serverdata
     *
     * @var string
     */
    public $queryres = '';
    /**
     * OGP::$result_packets.
     *
     * A Counter for incomming packets
     * Is resetted each time a new query is processed
     *
     * @var int
     */
    public $result_packets = 0;
    /**
     * OGP::$total_result_packets.
     *
     * A Counter for the total number of incomming packets
     *
     * @var int
     */
    public $total_result_packets = 0;
    /**
     * OGP::$pakets.
     *
     * Stores the received packets of the last query
     *
     * @var mixed
     */
    public $pakets = [];
    /**
     * OGP::$timeout_sock.
     *
     * Value of the socket timeout in seconds
     *
     * To Specify a value smaller than one second, use
     * $timeout_sock instead
     *
     * @see $timeout_sock
     *
     * @var int
     */
    public $timeout_sock = 2;
    /**
     * OGP::$timeout_sock_m.
     *
     * Value of the socket timeout in microseconds
     * (one microseconds is 1 / 1 000 000 seconds)
     *
     * @see $timeout_sock
     *
     * @var int
     */
    public $timeout_sock_m = 0;

    /**
     * OGP::$ChallengeNumber.
     *
     * Stores the last parsed ChallengeNumber
     *
     * @var mixed
     */
    public $ChallengeNumber = -1;
    /**
     * OGP::$RequestID.
     *
     * Stores the last parsed RequestID
     *
     * @var mixed
     */
    public $RequestID = -1;

    /**
     * OGP::$SERVERINFO.
     *
     * All vars depending on SERVERINFO
     * are stored in this array
     *
     * @var mixed
     */
    public $SERVERINFO = [];
    /**
     * OGP::$RULELIST.
     *
     * All vars depending on RULELIST
     * are stored in this array
     *
     * @var mixed
     */
    public $RULELIST = [];
    /**
     * OGP::$TEAMLIST.
     *
     * All vars depending on TEAMLIST
     * are stored in this array
     *
     * @var mixed
     */
    public $TEAMLIST = [];
    /**
     * OGP::$PLAYERLIST.
     *
     * All vars depending on PLAYERLIST
     * are stored in this array
     *
     * @var mixed
     */
    public $PLAYERLIST = [];
    /**
     * OGP::$ADDONLIST.
     *
     * All vars depending on ADDONLIST
     * are stored in this array
     *
     * @var mixed
     */
    public $ADDONLIST = [];
    /**
     * OGP::$LIMITLIST.
     *
     * All vars depending on LIMITLIST
     * are stored in this array
     *
     * @var mixed
     */
    public $LIMITLIST = [];

    /**
     * OGP::OGP().
     *
     * the Contructor of the class
     *
     * Just sets the address, port and the default timeout
     *
     * @param string $addr
     * @param int    $port
     * @param int    $timeout
     */
    public function __construct($addr, $port, $timeout = 100)
    {
        $this->addr = $addr;
        $this->port = $port;
        $this->timeout = $timeout;
    }

    /**
     * OGP::timenow().
     *
     * Returns the exact current time
     * Used to calculate the exact difference between to times
     *
     * @return float The exact current time
     */
    public function timenow()
    {
        return (float) (preg_replace('/^0\.([0-9]*) ([0-9]*)$/D', '\\2.\\1', microtime()));
    }

    /**
     * OGP::serverQuery().
     *
     * Does the sending and receiving of the data
     *
     * I sends the command stored in $command to the specified server
     * that is listening on the specified port (using the UDP Protocol)
     * After that it waits for at least one incomming Packets and analyses
     * if it is a valid OGP Packet.
     *
     * If it is valid, the method waits for more packets (if bSplit is
     * specified in the OGP Header) and stops waiting if that many packets
     * as specified in SplitPacketNo were received or if there is a timeout.
     *
     * If it is <b>not</b> valid, it is descarded and the function returns false
     *
     * Finally it generates one string out of the received packets (stripping
     * the headers). This string is stored in $OGP::queryres.
     *
     * @param string $command  The command to send to the server
     * @param string $addr     The address of the OGP server
     * @param int    $port     The port of the OGP server
     * @param int    $waittime The read timeout
     *
     * @return bool True on success, false on failure
     */
    public function serverQuery($command, $addr, $port, $waittime)
    {
        if (!$socket = fsockopen('udp://'.$addr, $port, $errno, $errstr, 2)) {
            $this->error = 'Could not connect!';

            return false;
        }
        @socket_set_timeout($socket, $this->timeout_sock, $this->timeout_sock_m);

        if (!fwrite($socket, $command, strlen($command))) {
            $this->error = 'Could not query!';

            return false;
        }

        $this->pakets = [];
        $this->result_packets = 0;
        $this->queryres = '';
        do {
            $starttime = $this->timenow();
            $serverdata = '';
            do {
                $serverdata .= fgetc($socket);
                $socketstatus = socket_get_status($socket);
                if ($this->timenow() > ($starttime + $waittime)) {
                    fclose($socket);
                    $this->error = 'Connection timed out';

                    return false;
                }
            } while ($socketstatus['unread_bytes'] && !feof($socket));

            ++$this->result_packets;
            ++$this->total_result_packets;

            $req = "\xFF\xFF\xFF\xFFOGP\x00";
            if (substr($serverdata, 0, strlen($req)) != $req) {
                $this->error = 'Not a OGP Server Response (1)';

                return false;
            }

            $flags_check = substr($serverdata, 10, 1);
            $flags = $this->getVarBitArray($flags_check);

            if (1 != $flags[0][3]) { //bSplit
                if (1 == $this->result_packets) {
                    $this->pakets[0] = $serverdata;
                }
                break;
            } else {
                $HeaderSize = substr($serverdata, 8, 1);
                $HeaderSize = $this->parseUint($HeaderSize);

                $serverheader = substr($serverdata, 8, $HeaderSize);

                $SplitPacketCount = substr($serverheader, -2, 1);
                $SplitPacketNo = substr($serverheader, -1, 1);

                $SplitPacketCount = $this->parseUint($SplitPacketCount);
                $SplitPacketNo = $this->parseUint($SplitPacketNo);

                $this->pakets[$SplitPacketNo] = $serverdata;

                if ($this->result_packets >= $SplitPacketCount) {
                    break;
                }
            }
        } while (true);

        fclose($socket);
        $this->ping = round(($this->timenow() - $starttime) * 100);

        for ($i = 0; $i < count($this->pakets); ++$i) {
            $this_headersize = substr($this->pakets[$i], 8, 1);
            $this_headersize = $this->parseUint($this_headersize);

            if ($i > 0) {
                $this->queryres .= substr($this->pakets[$i], $this_headersize + 8);
            } else {
                $this->queryres .= $this->pakets[$i];
            }
        }

        return true;
    }

    /**
     * OGP::getChallengeNumber().
     *
     * This method requests a new ChallengeNumber from the Server,
     * by sending an Invalid Query where no ChallengeNumber is specified.
     *
     * The received ChallengeNumber is stored in OGP::ChallengeNumber
     *
     * @return bool True on success, false on failure
     */
    public function getChallengeNumber()
    {
        $command = "\xFF\xFF\xFF\xFFOGP\x00";  //header
        $Type = "\x01"; //query
        $HeadFlags = '00000000';
        $HeadFlags = bindec($HeadFlags);
        $command2 = $Type.chr($HeadFlags);

        $command = $command.chr(strlen($command2) + 1).$command2;

        if (!$this->serverQuery($command, $this->addr, $this->port, $this->timeout)) {
            return false;
        }

        $result = $this->queryres;

        $req = "\xFF\xFF\xFF\xFFOGP\x00";
        if (substr($result, 0, strlen($req)) != $req) {
            $this->error = 'Not a OGP Server Response (2)';

            return false;
        }

        $result2 = substr($result, 8);
        $size = ord($this->getUint($result2, 8));

        $type = $this->getUint($result2, 8);
        if ("\xFF" != $type) { //must be an error
            $this->error = 'Unexpected value (1)';

            return false;
        }

        $HeadFlags = $this->getVarBitArray($result2);
        if ('1' != $HeadFlags[0][0]) { //must be 1, because we're waiting for an answer
            //packet
            $this->error = 'Unexpected value (2)';

            return false;
        }

        if ('1' != $HeadFlags[0][1]) { //must be 1, because we want a Challengenumber!
            $this->error = 'Unexpected value (3)';

            return false;
        }

        $this->ChallengeNumber = $this->getUint($result2, 32);

        return true;
    }

    /**
     * OGP::getStatus().
     *
     * Does the "Default query v1" as specified on www.online-game-protocol.org
     *
     * It generates the OGP Header and the OGP Query,
     * then calls OGP::serverQuery(); to get the answer packets.
     * After that it parses the result packets, and fills the arrays:
     *  - {@link OGP::$SERVERINFO}
     *  - {@link OGP::$TEAMLIST}
     *  - {@link OGP::$PLAYERLIST}
     *  - {@link OGP::$RULELIST}
     *  - {@link OGP::$ADDONLIST}
     *  - {@link OGP::$LIMITLIST}
     * (the arrays are only filled with the values that are supported by the server)
     *
     * @see OGP::serverQuery()
     *
     * @return bool True on success, false on failure
     */
    public function getStatus()
    {
        if (-1 == $this->ChallengeNumber) {
            if (!$this->getChallengeNumber()) {
                return false;
            }
        }

        if (-1 == $this->ChallengeNumber) {
            $this->error = 'Could not get Challenge Number!';

            return false;
        }

        //HEADER BEGIN

        $command = "\xFF\xFF\xFF\xFFOGP\x00";
        $Type = "\x01"; //query
        $HeadFlags[0][1] = 1;
        $HeadFlags_send = $this->getCharsbyBinary($this->VarBitArray_toString($HeadFlags));
        /*
        Bit 0.0: bAnswer = 0
        Bit 0.1: bChallengeNumber
        Bit 0.2: bRequestID
        Bit 0.3: bSplit
        Bit 0.4: (bPassword)

        The server ignores the query if bAnswer is set.

        The password field is reserved for future.
        It is needed for ogp rcon protocol and to
        request sensitiv information about some
        player via default protocol (e.g. IP address)
        */

        $command2 = $Type.$HeadFlags_send.$this->ChallengeNumber;

        $command = $command.chr(strlen($command2) + 1).$command2;

        //HEADER ENDE

        //QUERY BEGIN

        $RequestFlags[0][0] = 1;
        $RequestFlags[0][1] = 1;
        $RequestFlags[0][2] = 1;
        $RequestFlags[0][3] = 1;
        $RequestFlags[0][4] = 1;
        $RequestFlags[0][5] = 1;
        $RequestFlags[1][0] = 1;
        $RequestFlags_send = $this->getCharsbyBinary($this->VarBitArray_toString($RequestFlags));
        /*
        Bit 0.0: bServerInfo
        Bit 0.1: bTeamList
        Bit 0.2: bPlayerList
        Bit 0.3: bRuleList
        Bit 0.4: bAddOnList
        Bit 0.5: bLimitList

        Bit 1.0: bColoredNames
        */
        if (1 == $RequestFlags[0][0]) {
            $ServerInfoFields[0][0] = 1;
            $ServerInfoFields[0][1] = 1;
            $ServerInfoFields[0][2] = 1;
            $ServerInfoFields[0][3] = 1;

            $ServerInfoFields[1][0] = 1;
            $ServerInfoFields[1][1] = 1;
            $ServerInfoFields[1][2] = 1;
            $ServerInfoFields[1][3] = 1;
            $ServerInfoFields[1][4] = 1;

            $ServerInfoFields[2][0] = 1;
            $ServerInfoFields[2][1] = 1;
            $ServerInfoFields[2][2] = 1;
            $ServerInfoFields[2][3] = 1;
        } else {
            $ServerInfoFields[0][0] = 0;
        }

        $ServerInfoFields_send = $this->getCharsbyBinary($this->VarBitArray_toString($ServerInfoFields));
        /*
         - Depends: bServerInfo
         Bit 0.0: bGameName
         Bit 0.1: bServerFlags
         Bit 0.2: bHostName
         Bit 0.3: bConnectPort

         Bit 1.0: bMod
         Bit 1.1: bGameType
         Bit 1.2: bGameMode
         Bit 1.3: bMap
         Bit 1.4: bNextMap

         Bit 2.0: bPlayerCount
         Bit 2.1: bSlotMax
         Bit 2.2: bBotCount
         Bit 2.3: bReservedSlots
        */
        if (1 == $ServerInfoFields[1][0]) { //ServerInfoFields.bMod
            $ModFields[0][0] = 1;
            $ModFields[0][1] = 1;
            $ModFields[0][2] = 1;
            $ModFields[0][3] = 1;
            $ModFields[0][4] = 1;
        } else {
            $ModFields[0][0] = 0;
        }

        $ModFields_send = $this->getCharsbyBinary($this->VarBitArray_toString($ModFields));
        /*
         - Depends: ServerInfoFields.bMod
          Bit 0.0: bModIdentifier
          Bit 0.1: bModSize
          Bit 0.2: bModVersion
          Bit 0.3: bModURL
          Bit 0.4: bModAuthor
        */

        if (1 == $ServerInfoFields[1][3]) { //ServerInfoFields.bMap
            $MapFields[0][0] = 1;
            $MapFields[0][1] = 1;
            $MapFields[0][2] = 1;
            $MapFields[0][3] = 1;
            $MapFields[0][4] = 1;
            $MapFields[0][5] = 1;
        } else {
            $MapFields[0][0] = 0;
        }

        $MapFields_send = $this->getCharsbyBinary($this->VarBitArray_toString($MapFields));
        /*
         - Depends: ServerInfoFields.bMap
           Bit 0.0: bMapFileName
           Bit 0.1: bMapFileSize
           Bit 0.2: bMapFileMD5
           Bit 0.3: bMapVersion
           Bit 0.4: bMapURL
           Bit 0.5: bMapAuthor
        */
        if (1 == $RequestFlags[0][1]) {
            $TeamFields[0][0] = 1;
            $TeamFields[0][1] = 1;
            $TeamFields[0][2] = 1;
            $TeamFields[0][3] = 1;
            $TeamFields[0][4] = 1;
            $TeamFields[0][5] = 1;
        } else {
            $TeamFields[0][0] = 0;
        }
        $TeamFields_send = $this->getCharsbyBinary($this->VarBitArray_toString($TeamFields));
        /*
         - Depends: bTeamList
        Bit 0.0: bTeamName
        Bit 0.1: bTeamScore
        Bit 0.2: bTeamAveragePing
        Bit 0.3: bTeamAverageLoss
        Bit 0.4: bTeamPlayerCount
        Bit 0.5: bTeamColor
        */
        if (1 == $RequestFlags[0][2]) {
            $PlayerFields[0][0] = 1;
            $PlayerFields[0][1] = 1;
            $PlayerFields[0][2] = 1;
            $PlayerFields[0][3] = 1;
            $PlayerFields[0][4] = 1;
            $PlayerFields[0][5] = 1;

            $PlayerFields[1][0] = 1;
            $PlayerFields[1][1] = 1;
            $PlayerFields[1][2] = 1;
            $PlayerFields[1][3] = 1;
            $PlayerFields[1][4] = 1;
            $PlayerFields[1][5] = 1;

            $PlayerFields[2][0] = 1;
            $PlayerFields[2][1] = 1;
            $PlayerFields[2][2] = 1;
            $PlayerFields[2][3] = 1;
            $PlayerFields[2][4] = 1;
            $PlayerFields[2][5] = 1;

            $PlayerFields[3][0] = 1;
        } else {
            $PlayerFields[0][0] = 0;
        }
        $PlayerFields_send = $this->getCharsbyBinary($this->VarBitArray_toString($PlayerFields));
        /*
         - Depends: bPlayerList
        This field indicates which player information will be returned

        Bit 0.0: bPlayerFlags
        Bit 0.1: bPlayerSlot
        Bit 0.2: bPlayerName
        Bit 0.3: bPlayerTeam
        Bit 0.4: bPlayerClass
        Bit 0.5: bPlayerRace

        Bit 1.0: bPlayerScore
        Bit 1.1: bPlayerFrags
        Bit 1.2: bPlayerKills
        Bit 1.3: bPlayerDeath
        Bit 1.4: bPlayerSuicides
        Bit 1.5: bPlayerTeamKills

        Bit 2.0: bPlayerID
        Bit 2.1: bPlayerGlobalID
        Bit 2.2: bPlayerPing
        Bit 2.3: bPlayerLoss
        Bit 2.4: bPlayerModel
        Bit 2.5: bPlayerTime

        Bit 3.0: bPlayerAddress
        */
        if (1 == $RequestFlags[0][4]) {
            $AddOnFields[0][0] = 1;
            $AddOnFields[0][1] = 1;
            $AddOnFields[0][2] = 1;
            $AddOnFields[0][3] = 1;
        } else {
            $AddOnFields[0][0] = 0;
        }
        $AddOnFields_send = $this->getCharsbyBinary($this->VarBitArray_toString($AddOnFields));
        /*
         - Depends: bAddOnList
        Bit 0.0: bAddOnFlags
        Bit 0.1: bAddOnShortName
        Bit 0.2: bAddOnLongName
        Bit 0.3: bAddOnVersion
        */

        //QUERY ENDE

        $query = $RequestFlags_send.$ServerInfoFields_send.$ModFields_send.$MapFields_send.$TeamFields_send.
                   $PlayerFields_send.$AddOnFields_send;

        $command .= $query;

        if (!$this->serverQuery($command, $this->addr, $this->port, $this->timeout)) {
            return false;
        }

        $result = $this->queryres;

        $req = "\xFF\xFF\xFF\xFFOGP\x00";
        if (substr($result, 0, strlen($req)) != $req) {
            $this->error = 'Not a OGP Server Response (2)';

            return false;
        }

        $result2 = substr($result, 8);
        $HeadSize = ord($this->getUint($result2, 8));
        $Type = $this->getUint($result2, 8);
        if ("\xFF" == $Type) { //Error?
            $err_message = substr($result, $HeadSize + 8);
            $err_id = $this->getUint($err_message, 8);
            $this->error = "Server says Error: '".$this->getErrorbyID($err_id)."' (1)";

            return false;
        } elseif ("\x01" != $Type) { //must be a default query v1
            $this->error = 'Unexpected value (4)';

            return false;
        }

        $HeadFlags = $this->getVarBitArray($result2);
        if (1 != $HeadFlags[0][0]) { //must be an Answer Packet
            $this->error = 'Unexpected value (5)';

            return false;
        }

        if (1 == $HeadFlags[0][1]) { //bChallengeNumber isset
            $this->ChallengeNumber = $this->getInt($result2, 32);
        }
        if (1 == $HeadFlags[0][2]) { //bRequestID isset
            $this->RequestID = $this->getInt($result2, 32);
        }
        if (1 == $HeadFlags[0][3]) { //bSplit isset
            $SplitPacketCount = $this->getInt($result2, 8);
            $SplitPacketNo = $this->getInt($result2, 8);
        }

        ///HEADER ENDE!

        $GameID = $this->getUint($result2, 16);
        $get_RequestFlags = $this->getVarBitArray($result2);

        if (1 == $get_RequestFlags[0][0]) { //bServerInfo
            $get_ServerInfoFields = $this->getVarBitArray($result2);
            if (1 == $get_ServerInfoFields[0][0]) { //bGameName
                $this->SERVERINFO['GameName'] = $this->getSzString($result2);
            }
            if (1 == $get_ServerInfoFields[0][1]) { //bServerFlags
                $this->SERVERINFO['ServerFlags'] = $this->getVarBitArray($result2);
                $this->parseServerFlags();
            }
            if (1 == $get_ServerInfoFields[0][2]) { //bHostName
                $this->SERVERINFO['HostName'] = $this->getSzString($result2);
                if (isset($get_RequestFlags[1]) && 1 == $get_RequestFlags[1][0]) { //bColoredNames
                    $this->SERVERINFO['HostNameColor'] = $this->getStringColorInfo($result2);
                }
            }

            if (1 == $get_ServerInfoFields[0][3]) { //bConnectPort
                $this->SERVERINFO['ConnectPort'] = $this->parseUint($this->getUint($result2, 16));
            }

            if (1 == $get_ServerInfoFields[1][0]) { //bMod
                $this->SERVERINFO['MODINFO']['ModName'] = $this->getSzString($result2);
                if (!empty($this->SERVERINFO['MODINFO']['ModName'])) {
                    $get_ModFields = $this->getVarBitArray($result2);
                    if (1 == $get_ModFields[0][0]) { //bModIdentifier
                        $this->SERVERINFO['MODINFO']['ModIdentifier'] = $this->getSzString($result2);
                        if (empty($this->SERVERINFO['MODINFO']['ModIdentifier'])) {
                            $this->SERVERINFO['MODINFO']['ModIdentifier'] = $this->SERVERINFO['MODINFO']['ModName'];
                        }
                    }
                    if (1 == $get_ModFields[0][1]) { //bModSize
                        $this->SERVERINFO['MODINFO']['ModSize'] = $this->parseUint($this->getUint($result2, 32));
                    }
                    if (1 == $get_ModFields[0][2]) { //bModVersion
                        $this->SERVERINFO['MODINFO']['ModVersion'] = $this->getSzString($result2);
                    }
                    if (1 == $get_ModFields[0][3]) { //bModURL
                        $this->SERVERINFO['MODINFO']['ModURL'] = $this->getSzString($result2);
                    }
                    if (1 == $get_ModFields[0][4]) { //bModAuthor
                        $this->SERVERINFO['MODINFO']['ModAuthor'] = $this->getSzString($result2);
                    }
                }
            }
            if (1 == $get_ServerInfoFields[1][1]) { //bGameType
                $this->SERVERINFO['GameType'] = $this->getSzString($result2);
            }
            if (1 == $get_ServerInfoFields[1][2]) { //bGameMode
                $this->SERVERINFO['GameMode'] = $this->getSzString($result2);
            }
            if (1 == $get_ServerInfoFields[1][3]) { //bMap
                $get_MapFields = $this->getVarBitArray($result2);
                $this->SERVERINFO['MAPINFO']['MapName'] = $this->getSzString($result2);
                if (1 == $get_MapFields[0][0]) { //bMapFileName
                    $this->SERVERINFO['MAPINFO']['MapFileName'] = $this->getSzString($result2);
                }
                if (1 == $get_MapFields[0][1]) { //bMapFileSize
                    $this->SERVERINFO['MAPINFO']['MapFileSize'] = $this->parseUint($this->getUint($result2, 32));
                }
                if (1 == $get_MapFields[0][2]) { //bMapFileMD5
                    $this->SERVERINFO['MAPINFO']['MapFileMD5'] = '';
                    for ($md5c = 0; $md5c < 16; ++$md5c) {
                        $this->SERVERINFO['MAPINFO']['MapFileMD5'] .= chr($this->parseUint($this->getUint($result2, 8)));
                    }
                }
                if (1 == $get_MapFields[0][3]) { //bMapVersion
                    $this->SERVERINFO['MAPINFO']['MapVersion'] = $this->getSzString($result2);
                }
                if (1 == $get_MapFields[0][4]) { //bMapURL
                    $this->SERVERINFO['MAPINFO']['MapURL'] = $this->getSzString($result2);
                }
                if (1 == $get_MapFields[0][5]) { //bMapAuthor
                    $this->SERVERINFO['MAPINFO']['MapAuthor'] = $this->getSzString($result2);
                }
            }
            if (1 == $get_ServerInfoFields[1][3] && 1 == $get_ServerInfoFields[1][4]) { //bMap && bNextMap
                $this->SERVERINFO['NEXTMAPINFO']['MapName'] = $this->getSzString($result2);
                if (1 == $get_MapFields[0][0]) { //bMapFileName
                    $this->SERVERINFO['NEXTMAPINFO']['MapFileName'] = $this->getSzString($result2);
                }
                if (1 == $get_MapFields[0][1]) { //bMapFileSize
                    $this->SERVERINFO['NEXTMAPINFO']['MapFileSize'] = $this->parseUint($this->getUint($result2, 32));
                }
                if (1 == $get_MapFields[0][2]) { //bMapFileMD5
                    $this->SERVERINFO['NEXTMAPINFO']['MapFileMD5'] = '';
                    for ($md5c = 0; $md5c < 16; ++$md5c) {
                        $this->SERVERINFO['NEXTMAPINFO']['MapFileMD5'] .= chr($this->parseUint($this->getUint($result2, 8)));
                    }
                }
                if (1 == $get_MapFields[0][3]) { //bMapVersion
                    $this->SERVERINFO['NEXTMAPINFO']['MapVersion'] = $this->getSzString($result2);
                }
                if (1 == $get_MapFields[0][4]) { //bMapURL
                    $this->SERVERINFO['NEXTMAPINFO']['MapURL'] = $this->getSzString($result2);
                }
                if (1 == $get_MapFields[0][5]) { //bMapAuthor
                    $this->SERVERINFO['NEXTMAPINFO']['MapAuthor'] = $this->getSzString($result2);
                }
            }

            if (1 == $get_ServerInfoFields[2][0]) { //bPlayerCount
                $this->SERVERINFO['PlayerCount'] = $this->parseUint($this->getVarUint($result2));
            }
            if (1 == $get_ServerInfoFields[2][1]) { //bSlotMax
                $this->SERVERINFO['SlotMax'] = $this->parseUint($this->getVarUint($result2));
            }
            if (1 == $get_ServerInfoFields[2][2]) { //bBotCount
                $this->SERVERINFO['BotCount'] = $this->parseUint($this->getVarUint($result2));
            }
            if (1 == $get_ServerInfoFields[2][3]) { //bReservedSlots
                $this->SERVERINFO['ReservedSlots'] = $this->parseUint($this->getVarUint($result2));
            }
        }

        if (1 == $get_RequestFlags[0][1]) { //bTeamList
            $TeamCount = $this->parseUint($this->getVarUint($result2));
            if ($TeamCount > 0) {
                $get_TeamFields = $this->getVarBitArray($result2);
            }

            for ($t = 0; $t < $TeamCount; ++$t) {
                $this_team_entry = [];

                if (1 == $get_TeamFields[0][0]) { //bTeamName
                    $this_team_entry['TeamName'] = $this->getSzString($result2);
                    if (1 == $get_RequestFlags[1][0]) { //bColoredNames
                        $this_team_entry['TeamNameColor'] = $this->getStringColorInfo($result2);
                    }
                }
                if (1 == $get_TeamFields[0][1]) { //bTeamScore
                    $this_team_entry['TeamScore'] = $this->parseInt($this->getVarSint($result2));
                }
                if (1 == $get_TeamFields[0][2]) { //bTeamAveragePing
                    $this_team_entry['TeamAveragePing'] = $this->parseUint($this->getUint($result2, 16));
                }
                if (1 == $get_TeamFields[0][3]) { //bTeamAverageLoss
                    $this_team_entry['TeamAverageLoss'] = $this->parseUint($this->getUint($result2, 16));
                }
                if (1 == $get_TeamFields[0][4]) { //bTeamPlayerCount
                    $this_team_entry['TeamPlayerCount'] = $this->parseUint($this->getVarUint($result2));
                }
                if (1 == $get_TeamFields[0][5]) { //bTeamColor
                    $this_team_entry['TeamColor'] = $this->parseUint($this->getUint($result2, 16));
                }

                array_push($this->TEAMLIST, $this_team_entry);
            }
        }

        if (1 == $get_RequestFlags[0][2]) { //bPlayerList
            $PlayerCount = $this->parseUint($this->getVarUint($result2));
            if ($PlayerCount > 0) {
                $get_PlayerFields = $this->getVarBitArray($result2);
            }

            for ($p = 0; $p < $PlayerCount; ++$p) {
                $this_player_entry = [];

                if (1 == $get_PlayerFields[0][0]) { //bPlayerFlags
                    $this_player_entry['PlayerFlags'] = $this->parsePlayerFlags($this->getVarBitArray($result2));
                }
                if (1 == $get_PlayerFields[0][1]) { //bPlayerSlot
                    $this_player_entry['PlayerSlot'] = $this->parseUint($this->getVarUint($result2));
                }
                if (1 == $get_PlayerFields[0][2]) { //bPlayerName
                    $this_player_entry['PlayerName'] = $this->getSzString($result2);
                    if (1 == $get_RequestFlags[1][0]) { //bColoredNames
                        $this_player_entry['PlayerNameColor'] = $this->getStringColorInfo($result2);
                    }
                }
                if (1 == $get_PlayerFields[0][3]) { //bPlayerTeam
                    $this_player_entry['PlayerTeamNo'] = $this->parseInt($this->getVarSint($result2));
                }
                if (1 == $get_PlayerFields[0][4]) { //bPlayerClass
                    $this_player_entry['PlayerClass'] = $this->getSzString($result2);
                }
                if (1 == $get_PlayerFields[0][5]) { //bPlayerRace
                    $this_player_entry['PlayerRace'] = $this->getSzString($result2);
                }

                if (1 == $get_PlayerFields[1][0]) { //bPlayerScore
                    $this_player_entry['PlayerScore'] = $this->parseInt($this->getVarSint($result2));
                }
                if (1 == $get_PlayerFields[1][1]) { //bPlayerFrags
                    $this_player_entry['PlayerFrags'] = $this->parseInt($this->getVarSint($result2));
                }
                if (1 == $get_PlayerFields[1][2]) { //bPlayerKills
                    $this_player_entry['PlayerKills'] = $this->parseUint($this->getVarUint($result2));
                }
                if (1 == $get_PlayerFields[1][3]) { //bPlayerDeath
                    $this_player_entry['PlayerDeath'] = $this->parseUint($this->getVarUint($result2));
                }
                if (1 == $get_PlayerFields[1][4]) { //bPlayerSuicides
                    $this_player_entry['PlayerSuicides'] = $this->parseUint($this->getVarUint($result2));
                }
                if (1 == $get_PlayerFields[1][5]) { //bPlayerTeamKills
                    $this_player_entry['PlayerTeamKills'] = $this->parseUint($this->getVarUint($result2));
                }

                if (1 == $get_PlayerFields[2][0]) { //bPlayerID
                    $this_player_entry['PlayerID'] = $this->parseUint($this->getUint($result2, 32));
                }
                if (1 == $get_PlayerFields[2][1]) { //bPlayerGlobalID
                    $this_player_entry['PlayerGlobalID'] = $this->getSzString($result2);
                }
                if (1 == $get_PlayerFields[2][2]) { //bPlayerPing
                    $this_player_entry['PlayerPing'] = $this->parseUint($this->getUint($result2, 16));
                }
                if (1 == $get_PlayerFields[2][3]) { //bPlayerLoss
                    $this_player_entry['PlayerLoss'] = $this->parseUint($this->getUint($result2, 16));
                }
                if (1 == $get_PlayerFields[2][4]) { //bPlayerModel
                    $this_player_entry['PlayerModel'] = $this->getSzString($result2);
                }
                if (1 == $get_PlayerFields[2][5]) { //bPlayerTime
                    $this_player_entry['PlayerTime'] = $this->parseUint($this->getUint($result2, 16));
                }

                if (1 == $get_PlayerFields[3][0]) { //bPlayerAddress
                    //$this_player_entry['PlayerAddress'] = $this->parseIP($this->getUint($result2, 32));
                    $PlayerAddressLen = $this->parseUint($this->getVarUint($result2));
                    //FIXME!
                    $PlayerAddress = '';
                    for ($padrc = 0; $padrc < $PlayerAddressLen; ++$padrc) {
                        $PlayerAddress .= $this->getUint($result2, 8);
                    }
                }

                array_push($this->PLAYERLIST, $this_player_entry);
            }
        }

        if (1 == $get_RequestFlags[0][3]) { //bRuleList
            $RuleCount = $this->parseUint($this->getVarUint($result2));

            for ($r = 0; $r < $RuleCount; ++$r) {
                $this->RULELIST[$this->getSzString($result2)] = $this->getSzString($result2);
            }
        }

        if (1 == $get_RequestFlags[0][4]) { //bAddOnList
            $AddOnCount = $this->parseUint($this->getVarUint($result2));
            if ($AddOnCount > 0) {
                $get_AddOnFields = $this->getVarBitArray($result2);
            }

            for ($p = 0; $p < $AddOnCount; ++$p) {
                $this_addon_entry = [];

                if (1 == $get_AddOnFields[0][0]) { //bAddOnFlags
                    $this_addon_entry['AddOnFlags'] = $this->parseAddOnFlags($this->getVarBitArray($result2));
                }
                if (1 == $get_AddOnFields[0][1]) { //bAddOnShortName
                    $this_addon_entry['AddOnShortName'] = $this->getSzString($result2);
                }
                if (1 == $get_AddOnFields[0][2]) { //bAddOnLongName
                    $this_addon_entry['AddOnLongName'] = $this->getSzString($result2);
                }
                if (1 == $get_AddOnFields[0][3]) { //bAddOnVersion
                    $this_addon_entry['AddOnVersion'] = $this->getSzString($result2);
                }

                array_push($this->ADDONLIST, $this_addon_entry);
            }
        }

        if (1 == $get_RequestFlags[0][5]) { //bLimitList
            $LimitCount = $this->parseUint($this->getVarUint($result2));

            for ($p = 0; $p < $LimitCount; ++$p) {
                $this_limit_entry = [];

                $this_limit_entry['LimitType'] = $this->getVarBitArray($result2);
                $bLimitLeft = $this_limit_entry['LimitType'][0][0]; //bLimitLeft

                $LimitType = bindec(strrev($this_limit_entry['LimitType'][0][1].
                           $this_limit_entry['LimitType'][0][2].
                           $this_limit_entry['LimitType'][0][3].
                           $this_limit_entry['LimitType'][0][4]));

                //$LimitType = $this->parseUint($LimitType);
                $this_limit_entry['LimitType'] = $this->parseLimitType($LimitType);

                $this_limit_entry['Limit'] = $this->parseUint($this->getVarUint($result2));
                if (1 == $bLimitLeft) { //bLimitLeft
                    $this_limit_entry['Left'] = $this->parseUint($this->getVarUint($result2));
                }

                array_push($this->LIMITLIST, $this_limit_entry);
            }
        }

        return true;
    }

    /**
     * OGP::parseLimitType().
     *
     * Returnes the name of the supplied
     * LimitType integer.
     *
     * @param int $LimitType A valid LimitType integer
     *
     * @return string The name of the supplied LimitType
     */
    public function parseLimitType($LimitType)
    {
        switch ($LimitType) {
            case 0: return 'Time (in seconds)';
            case 1: return 'Player Score';
            case 2: return 'Round';
            case 3: return 'Team Score';

            default: return 'Unknown';
        }
    }

    /**
     * OGP::parseAddOnFlags().
     *
     * This method reads the supplied array
     * of the type VarBitArray and returns an
     * array filled with known values
     *
     * @param VarBitArray $array The AddOnFlags stored in a VarBitArray
     *
     * @return mixed The parsed AddOnFlags, false on failure
     */
    public function parseAddOnFlags($array)
    {
        if (!isset($array[0])) {
            return false;
        }

        $new_flags = [];
        if (1 == $array[0][0]) { //bActive
            $new_flags['Active'] = true;
        } else {
            $new_flags['Active'] = false;
        }

        if (1 == $array[0][1]) { //bAntiCheatTool
            $new_flags['AntiCheatTool'] = true;
        } else {
            $new_flags['AntiCheatTool'] = false;
        }

        if (1 == $array[0][2]) { //bMutator
            $new_flags['Mutator'] = true;
        } else {
            $new_flags['Mutator'] = false;
        }

        if (1 == $array[0][3]) { //bAdminTool
            $new_flags['AdminTool'] = true;
        } else {
            $new_flags['AdminTool'] = false;
        }

        return $new_flags;
    }

    /**
     * OGP::parsePlayerFlags().
     *
     * This method reads the supplied array
     * of the type VarBitArray and returns an
     * array filled with known values
     *
     * @param VarBitArray $array The PlayerFlags stored in a VarBitArray
     *
     * @return mixed The parsed PlayerFlags, false on failure
     */
    public function parsePlayerFlags($array)
    {
        if (!isset($array[0])) {
            return false;
        }

        $new_flags = [];
        if (1 == $array[0][0]) { //bAlive
            $new_flags['Alive'] = true;
        } else {
            $new_flags['Alive'] = false;
        }

        if (1 == $array[0][1]) { //bDead
            $new_flags['Dead'] = true;
        } else {
            $new_flags['Dead'] = false;
        }

        if (1 == $array[0][2]) { //bBot
            $new_flags['Bot'] = true;
        } else {
            $new_flags['Bot'] = false;
        }

        if (1 == $array[1][0]) { //bBomp
            $new_flags['Bomp'] = true;
        }

        if (1 == $array[1][1]) { //bVIP
            $new_flags['VIP'] = true;
        }

        return $new_flags;
    }

    /**
     * OGP::parseServerFlags().
     *
     * This method reads the array OGP::SERVERINFO['ServerFlags']
     * of the type VarBitArray and sets the variable
     * OGP::SERVERINFO['ServerFlags'] with known values
     *
     * @return bool true on success, false on failure
     */
    public function parseServerFlags()
    {
        if (!isset($this->SERVERINFO['ServerFlags'][0])) {
            return false;
        }

        $new_flags = [];
        $bType = bindec(strrev($this->SERVERINFO['ServerFlags'][0][0].
               $this->SERVERINFO['ServerFlags'][0][1]));
        switch ($bType) {
            case 0: $new_flags['bType'] = 'Unknown'; break;
            case 1: $new_flags['bType'] = 'Listen'; break;
            case 2: $new_flags['bType'] = 'Dedicated'; break;
        }

        if (1 == $this->SERVERINFO['ServerFlags'][0][2]) { //bPassword
            $new_flags['Password'] = true;
        } else {
            $new_flags['Password'] = false;
        }

        if (1 == $this->SERVERINFO['ServerFlags'][0][3]) { //bProxy
            $new_flags['Proxy'] = true;
        } else {
            $new_flags['Proxy'] = false;
        }

        $OperatingSystem = bindec(strrev($this->SERVERINFO['ServerFlags'][0][4].
                           $this->SERVERINFO['ServerFlags'][0][5].
                           $this->SERVERINFO['ServerFlags'][0][6]));

        switch ($OperatingSystem) {
            case 0: $new_flags['OperatingSystem'] = 'Unknown'; break;
            case 1: $new_flags['OperatingSystem'] = 'Windows'; break;
            case 2: $new_flags['OperatingSystem'] = 'Linux'; break;
            case 3: $new_flags['OperatingSystem'] = 'Macintosh'; break;

            default: $new_flags['OperatingSystem'] = 'Unknown ('.$OperatingSystem.')';
        }

        $this->SERVERINFO['ServerFlags'] = $new_flags;

        return true;
    }

    /**
     * OGP::getErrorbyID().
     *
     * Returns the name of a supplied errorid
     *
     * @param int $id the errorid
     *
     * @return string The errormessage
     */
    public function getErrorbyID($id)
    {
        /*
        0 - Banned
        1 - Invalid Type: The query type in header is unkown
        2 - Invalid Value: Any value in header is incorrect
        3 - Invalid Challenge Number: The challenge number is incorrect
        4 - Invalid Query: The query body is incorrect
        */

        $id = $this->parseUint($id);

        switch ($id) {
            case 0: return '0 - Banned';
            case 1: return '1 - Invalid Type: The query type in header is unkown';
            case 2: return '2 - Invalid Value: Any value in header is incorrect';
            case 3: return '3 - Invalid Challenge Number: The challenge number is incorrect';
            case 4: return '4 - Invalid Query: The query body is incorrect';

            default: return $id.' - Unknown';
        }
    }

    /**
     * OGP::parseIP().
     *
     * returns the IP in standart dottet format
     * of the specified UINT32, false on failure.
     *
     * @param string an integer of type UINT32 (4 bytes string)
     *
     * @return string IP in dotted format, false on failure
     */
    public function parseIP($uint)
    {
        if (strlen($uint) < 4) {
            echo '<b>Warning:</b> String to short in parseIP();<br>';

            return false;
        }

        return $this->parseUint(substr($uint, 3, 1)).'.'.
                 $this->parseUint(substr($uint, 2, 1)).'.'.
                 $this->parseUint(substr($uint, 1, 1)).'.'.
                 $this->parseUint(substr($uint, 0, 1));
    }

    /**
     * OGP::getUint().
     *
     * Reads an unsigned integer of the specified type (valid: 8,16,32)
     * from the specified string $string, returns this value and removes ($length / 8)
     * bytes from $string.
     *
     * @param string $string Any string, starting with an unsigned integer of the specified length
     * @param int    $length The type of the unsigned integer to read (8,16,32)
     *
     * @return string the read unsigned integer of the specified type, false on failure
     */
    public function getUint(&$string, $length = 8)
    {
        if (strlen($string) < 1) {
            echo '<b>Warning:</b> Empty String in getUint();<br>';

            return false;
        }

        $length = $length / 8;

        $uint = substr($string, 0, $length);
        $string = substr($string, $length);

        return $uint;
    }

    /**
     * OGP::parseUint().
     *
     * Parses the specified unsigned integer to an integer that
     * can be handled by PHP.
     *
     * @param string $uint The binary unsigned integer to parse
     *
     * @return int The parsed unsigned integer
     */
    public function parseUint($uint)
    {
        if (1 == strlen($uint)) {
            $uint = unpack('Cuint', $uint);
        } elseif (2 == strlen($uint)) {
            $uint = unpack('vuint', $uint);
        } elseif (4 == strlen($uint)) {
            $uint = unpack('Vuint', $uint);
        }

        return $uint['uint'];
    }

    /**
     * OGP::getInt().
     *
     * Reads a signed integer of the specified type (valid: 8,16,32)
     * from the specified string $string, returns this value and removes ($length / 8)
     * bytes from $string.
     *
     * @param string $string Any string, starting with a signed integer of the specified length
     * @param int    $length The type of the signed integer to read (8,16,32)
     *
     * @return string the read signed integer of the specified type, false on failure
     */
    public function getInt(&$string, $length = 8)
    {
        if (strlen($string) < 1) {
            echo '<b>Warning:</b> Empty String in getInt();<br>';

            return false;
        }

        $length = $length / 8;

        $int = substr($string, 0, $length);
        $string = substr($string, $length);

        return $int;
    }

    /**
     * OGP::parseInt().
     *
     * Parses the specified signed integer to an integer that
     * can be handled by PHP.
     *
     * @return int The parsed signed integer
     */
    public function parseInt($int)
    {
        if (1 == strlen($int)) {
            $int = unpack('cint', $int);
        } elseif (2 == strlen($int)) {
            $int = unpack('sint', $int);
        } elseif (4 == strlen($int)) {
            $int = unpack('lint', $int);
        }

        return $int['int'];
    }

    /**
     * OGP::getVarBitArray().
     *
     * Reads a VarBitArray from $string,
     * and removes read data from $string.
     *
     * @param string $string Any string, starting with a VarBitArray
     *
     * @return VarBitArray an array filled with binary data, false on failure
     *
     * @see http://www.open-game-protocol.org/spec/ogp_spec_v0.94.htm#VarBitArray_Type VarBitArray Specification
     */
    public function getVarBitArray(&$string)
    {
        if (strlen($string) < 1) {
            echo '<b>Warning:</b> Empty String in getVarBitArray();<br>';

            return false;
        }

        $varbitarray = [];
        $i = 0;
        while (true) {
            $c = substr($string, 0, 1);
            $string = substr($string, 1);

            $bin = decbin(ord($c));
            $bin = str_repeat('0', 8 - strlen($bin)).$bin;

            $bin_array = [];
            for ($x = 7; $x >= 0; --$x) {
                $b = substr($bin, $x, 1);
                $bin_array[7 - $x] = $b;
            }
            $varbitarray[$i] = $bin_array;

            if (1 != $bin_array[7]) {
                break;
            }
            ++$i;
        }

        return $varbitarray;
    }

    /**
     * OGP::VarBitArray_toString().
     *
     * Writes the array of type VarBitArray to a string
     * containing a binary number.
     *
     * @param VarBitArray The VarBitArray to parse
     *
     * @return string The binary number, false on failure
     */
    public function VarBitArray_toString($array)
    {
        if (count($array) < 1) {
            echo '<b>Warning:</b> Empty Array in VarBitArray_toString();<br>';

            return false;
        }

        $string = '';
        for ($i = 0; $i < count($array); ++$i) {
            if (!isset($array[$i])) {
                echo '<b>Warning:</b> Array not valid VarBitArray_toString();<br>';

                return false;
            }

            if ($i < count($array) - 1) {
                $array[$i][7] = 1;
            }

            for ($x = 7; $x >= 0; --$x) {
                if (!isset($array[$i][$x])) {
                    $array[$i][$x] = 0;
                }
                $string .= $array[$i][$x];
            }
        }

        return $string;
    }

    /**
     * OGP::getCharsbyBinary().
     *
     * Parses a binary number to binary data.
     *
     * @param string $binary The binary number to parse
     *
     * @return string The parsed binary string, false on failure
     */
    public function getCharsbyBinary($binary)
    {
        if (strlen($binary) < 1) {
            echo '<b>Warning:</b> Empty String in getCharsbyBinary();<br>';

            return false;
        }

        if (strlen($binary) / 8 != floor(strlen($binary) / 8)
        || strlen($binary) / 8 != ceil(strlen($binary) / 8)) {
            echo '<b>Warning:</b> String must have length that can be devided by 8 in getCharsbyBinary();<br>';

            return false;
        }

        $string = '';

        $count = strlen($binary) / 8;
        for ($i = 0; $i < $count; ++$i) {
            $string .= chr(bindec(substr($binary, $i * 8, 8)));
        }

        return $string;
    }

    /**
     * OGP::getSzString().
     *
     * Returns a null terminated String from $string,
     * and removes is from $string.
     *
     * @param string $string The string starting with a null terminated string
     *
     * @return string The result string
     */
    public function getSzString(&$string)
    {
        if (strlen($string) < 1) {
            echo '<b>Warning:</b> Empty String in getSzString();<br>';

            return false;
        }

        $szstring = substr($string, 0, strpos($string, "\x00"));
        $string = substr($string, strlen($szstring) + 1);

        return $szstring;
    }

    /**
     * OGP::getStringColorInfo().
     *
     * Reads a string containing color information
     * from $string
     *
     * @param string $string Any string starting with a StringColorInfo string
     *
     * @return array the array filled with the color information
     *
     * @see http://www.open-game-protocol.org/spec/ogp_spec_v0.94.htm#StringColorInfo_Type StringColorInfo Specification
     */
    public function getStringColorInfo(&$string)
    {
        $ColorSize = $this->getVarUint($string);

        $total_size = 0;
        $ColorSize_p = $this->parseUint($ColorSize_p);
        $Data = [];
        while ($total_size < $ColorSize_p) {
            $ColorInfoEntry = $this->getStringColorInfoEntry($string);
            $total_size += $ColorInfoEntry['size'];
            array_push($Data, $ColorInfoEntry);
        }

        return $Data;
    }

    /**
     * OGP::getStringColorInfoEntry().
     *
     * Reads a StringColorInfoEntry containing color information
     * from $string.
     *
     * @param string $string Any string starting with a StringColorInfoEntry string
     *
     * @return array the array filled with the color information
     *
     * @see http://www.open-game-protocol.org/spec/ogp_spec_v0.94.htm#StringColorInfoEntry_Type StringColorInfoEntry Specification
     */
    public function getStringColorInfoEntry(&$string)
    {
        if (strlen($string) < 1) {
            echo '<b>Warning:</b> Empty String in getStringColorInfo();<br>';

            return false;
        }

        $size_before = strlen($string);

        $DeltaPosition = $this->getVarUint($string);

        $ColorValue = $this->getUint($string, 8);
        if ($this->parseInt($ColorValue) >= 0x90 ||
        $this->parseInt($ColorValue) <= 0x9F) {
            $ColorValue16 = $this->getUint($string, 16);
        }

        $size_after = strlen($string);
        $size = $size_before - $size_after;

        return ['DeltaPosition' => $DeltaPosition,
                                     'ColorValue' => $ColorValue,
                                     'ColorValue16' => $ColorValue16,
                                     'size' => $size, ];
    }

    /**
     * OGP::getVarUint().
     *
     * Reads a VarUint from $string
     *
     * @param string $string Any string starting with a VarUint
     *
     * @return string an unsigned integer in a binary string
     *
     * @see http://www.open-game-protocol.org/spec/ogp_spec_v0.94.htm#VarUINT8-32_Type Specification
     */
    public function getVarUint(&$string)
    {
        if (strlen($string) < 1) {
            echo '<b>Warning:</b> Empty String in getVarUint();<br>';

            return false;
        }

        $uint = $this->getUint($string, 8);
        $uint2 = $this->parseUint($uint);

        if ($uint2 <= 0xFD) {
            return $uint;
        }

        if (0xFE == $uint2) {
            $uint = $this->getUint($string, 16);

            return $uint;
        }

        if (0xFF == $uint2) {
            $uint = $this->getUint($string, 32);

            return $uint;
        }

        echo '<b>Warning:</b> Unknown type in getVarUint();<br>';

        return false;
    }

    /**
     * OGP::getVarSint().
     *
     * Reads a VarUint from $string
     *
     * @param string $string Any string starting with a VarUint
     *
     * @return string a signed integer in a binary string
     *
     * @see http://www.open-game-protocol.org/spec/ogp_spec_v0.94.htm#VarSINT8-32_Type VarSint Specification
     */
    public function getVarSint(&$string)
    {
        if (strlen($string) < 1) {
            echo '<b>Warning:</b> Empty String in getVarSint();<br>';

            return false;
        }

        $int = $this->getInt($string, 8);
        $int2 = $this->parseInt($int);
        if ($int2 <= 0x7F && -0x7E <= $int2) {
            return $int;
        }

        if (-0x80 == $int2) {
            $int = $this->getInt($string, 16);

            return $int;
        }

        if (-0x7F == $int2) {
            $int = $this->getInt($string, 32);

            return $int;
        }

        echo '<b>Warning:</b> Unknown type in getVarSint();<br>';

        return false;
    }

    /**
     * OGP::color_web_to16bit().
     *
     * Converts a 32-Bit hexadecimal colorstring
     * to a 16-Bit colorstring
     */
    public function color_web_to16bit($color)
    {
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));

        $r = round($r / (255 / 31));
        $g = round($g / (255 / 63));
        $b = round($b / (255 / 31));

        $r = substr(decbin($r), -5);
        $g = substr(decbin($g), -6);
        $b = substr(decbin($b), -5);

        $r = str_pad($r, 5, '0', STR_PAD_LEFT);
        $g = str_pad($g, 6, '0', STR_PAD_LEFT);
        $b = str_pad($b, 5, '0', STR_PAD_LEFT);

        $res = bindec($r.$g.$b);

        return $res;
    }

    /**
     * OGP::color_16bit_toweb().
     *
     * Converts a 16-Bit colorstring
     * to a 32-Bit hexadecimal colorstring
     */
    public function color_16bit_toweb($color)
    {
        $binary = decbin($color);
        $binary = str_pad($binary, 16, '0', STR_PAD_LEFT);

        $r = bindec(substr($binary, 0, 5));
        $g = bindec(substr($binary, 5, 6));
        $b = bindec(substr($binary, 11, 5));

        $r = round($r * (255 / 31));
        $g = round($g * (255 / 63));
        $b = round($b * (255 / 31));

        $r = str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
        $g = str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
        $b = str_pad(dechex($b), 2, '0', STR_PAD_LEFT);

        return strtoupper($r.$g.$b);
    }
}

if (!function_exists('fragsort')) {
    /**
     * Document::fragsort().
     *
     * Tool function to sort with
     * {@link http://www.php.net/manual/en/function.uasort.php uasort()}
     *
     * @see http://www.php.net/manual/en/function.uasort.php PHP's asort() Documentation
     */
    function fragsort($a, $b)
    {
        if ($a['frags'] == $b['frags']) {
            return 0;
        }

        if ($a['frags'] > $b['frags']) {
            return -1;
        } else {
            return 1;
        }
    }
}
