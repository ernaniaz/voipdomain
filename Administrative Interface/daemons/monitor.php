#!/usr/bin/php -q
<?php
/**   ___ ___       ___ _______     ______                        __
 *   |   Y   .-----|   |   _   |   |   _  \ .-----.--------.---.-|__.-----.
 *   |.  |   |  _  |.  |.  1   |   |.  |   \|  _  |        |  _  |  |     |
 *   |.  |   |_____|.  |.  ____|   |.  |    |_____|__|__|__|___._|__|__|__|
 *   |:  1   |     |:  |:  |       |:  1    /
 *    \:.. ./      |::.|::.|       |::.. . /
 *     `---'       `---`---'       `------'
 *
 * Copyright (C) 2016-2018 Ernani José Camargo Azevedo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * This daemon connects to Asterisk through AMI interface, and keep listening to
 * events and doing actions when needed.
 *
 * @author     Ernani José Camargo Azevedo <azevedo@intellinews.com.br>
 * @version    1.0
 * @package    VoIP Domain
 * @subpackage AMI Interface
 * @copyright  2016-2018 Ernani José Camargo Azevedo. All rights reserved.
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html
 */

/**
 * Set error reporting level
 */
error_reporting ( E_ERROR);
ini_set ( "display_errors", "false");
error_reporting ( E_ALL); ini_set ( "display_errors", "true");

/**
 * Check if script is running from CLI
 */
if ( ! defined ( "STDIN"))
{
  echo "This script must be executed into CLI!\n";
  exit ( 1);
}

/**
 * Include functions library
 */
require_once ( dirname ( __FILE__) . "/includes/functions.inc.php");
require_once ( dirname ( __FILE__) . "/includes/plugins.inc.php");
require_once ( dirname ( __FILE__) . "/includes/asterisk.inc.php");

/**
 * Parse configuration file. You should put your configuration file OUTSIDE
 * the web server files path, or you must block access to this file at the
 * web server configuration. Your configuration would contain passwords and
 * other sensitive configurations.
 */
$_in = parse_ini_file ( "/etc/voipdomain/monitor.conf", true);

/**
 * Include all modules configuration files
 */
foreach ( glob ( dirname ( __FILE__) . "/modules/*/fastagi.php") as $filename)
{
  require_once ( $filename);
}

/**
 * Check for mandatory basic configurations (if didn't exist, set default)
 */
if ( ! array_key_exists ( "general", $_in))
{
  $_in["general"] = array ();
}
if ( ! array_key_exists ( "version", $_in["general"]))
{
  $_in["version"] = "1.0";
} else {
  $_in["version"] = $_in["general"]["version"];
  unset ( $_in["general"]["version"]);
}
if ( ! array_key_exists ( "charset", $_in["general"]))
{
  $_in["general"]["charset"] = "UTF-8";
}
if ( ! array_key_exists ( "language", $_in["general"]))
{
  $_in["general"]["language"] = "en_US";
}

/**
 * Configure locale and encoding
 */
mb_internal_encoding ( $_in["general"]["charset"]);
setlocale ( LC_ALL, $_in["general"]["language"] . "." . $_in["general"]["charset"]);

/**
 * Show software version header
 */
echo chr ( 27) . "[1;37mVoIP Domain Asterisk Gateway Monitor Daemon" . chr ( 27) . "[1;0m v" . $_in["version"] . "\n";
echo "\n";

/**
 * Validate MySQL session
 */
if ( ! is_array ( $_in["mysql"]))
{
  echo "Error: Cannot find \"mysql\" session at configuration file.\n";
  exit ( 1);
}

/**
 * Process parameters
 */
$debug = false;
for ( $x = 1; $x < $argc; $x++)
{
  switch ( $argv[$x])
  {
    case "--debug":
    case "-d":
      $debug = true;
      break;
    case "--help":
    case "-h":
      echo "Usage: " . basename ( $argv[0]) . " [--help|-h] [--debug|-d]\n";
      echo "  --help|-h:    Show this help informations\n";
      echo "  --debug|-d:   Enable debug messages (do not fork the daemon)\n";
      exit ();
      break;
    default:
      echo "ERROR: Invalid parameter \"" . $argv[$x] . "\"!\n";
      exit ( -1);
      break;
  }
}

/**
 * Conect to the database
 */
echo "Executing: Connecting to database... ";
if ( ! $_in["mysql"]["id"] = @new mysqli ( $_in["mysql"]["hostname"] . ( ! empty ( $_in["mysql"]["port"]) ? ":" . $_in["mysql"]["port"] : ""), $_in["mysql"]["username"], $_in["mysql"]["password"], $_in["mysql"]["database"]))
{
  writeLog ( "Cannot connect to database server!", VoIP_LOG_FATAL);
}
echo chr ( 27) . "[1;37m" . gettext ( "OK") . chr ( 27) . "[1;0m\n";

/**
 * Fetch dialer campaigns
 */
echo "Executing: Fetching database configurations... ";
update_campaigns ();
echo chr ( 27) . "[1;37m" . gettext ( "OK") . chr ( 27) . "[1;0m\n";

/**
 * If possible, change process name
 */
if ( function_exists ( "setproctitle"))
{
  setproctitle ( "VoIP Domain AMI daemon");
}

/**
 * Change effective UID/GID to an unprivileged user
 */
echo "Executing: Changing effective UID/GID... ";
if ( ! $uid = posix_getpwnam ( $_in["daemon"]["uid"]))
{
  writeLog ( "Cannot check for the user \"" . $_in["daemon"]["uid"] . "\"!", VoIP_LOG_FATAL);
}
if ( ! $gid = posix_getgrnam ( $_in["daemon"]["gid"]))
{
  writeLog ( "Cannot check for the group \"" . $_in["daemon"]["gid"] . "\"!", VoIP_LOG_FATAL);
}
if ( ! posix_setgid ( $gid["gid"]))
{
  writeLog ( "Cannot change to GID " . $gid["gid"] . " \"" . $_in["daemon"]["gid"] . "\"!", VoIP_LOG_FATAL);
}
if ( ! posix_setuid ( $uid["uid"]))
{
  writeLog ( "Cannot change to UID " . $uid["uid"] . " \"" . $_in["daemon"]["uid"] . "\"!", VoIP_LOG_FATAL);
}
echo chr ( 27) . "[1;37m" . gettext ( "OK") . chr ( 27) . "[1;0m\n";

/**
 * Show start of operations message
 */
echo "Everything done. Waiting for events!\n\n";

/**
 * Log system initialization
 */
writeLog ( "VoIP Domain AMI daemon initialized.");

/**
 * Fork process to daemon mode (except if in debug mode)
 */
error_reporting ( E_ERROR);
set_time_limit ( 0);
if ( ! $debug)
{
  $pid = pcntl_fork ();
  if ( $pid == -1)
  {
    writeLog ( "Cannot fork process!", VoIP_LOG_FATAL);
  }
  if ( $pid)
  {
    exit ();
  }
}

/**
 * Start system variables
 */
$_in["Queues"] = array ();

/**
 * Start monitor loop
 */
unset ( $ami);
$lastevent = time ();
while ( true)
{
  /**
   * Check if connection is alive
   */
  if ( time () - $lastevent > 600 && $ami)
  {
    $ping = $ami->request ( "Ping", array ());
    if ( $ping["Response"] != "Success" || $ping["Pong"])
    {
      writelog ( "Closing inactive session to server.", VoIP_LOG_WARNING);
      unset ( $ami);
    }
  }

  /**
   * If doesn't have an active connection, connect to AMI server
   */
  if ( ! $ami)
  {
    $ami = new asterisk ( "", "", "", "", false);
    $ami->events_toggle ( true);
    if ( ! $ami->open ( $_in["ami"]["username"], $_in["ami"]["password"], $_in["ami"]["hostname"], $_in["ami"]["port"], false))
    {
      writelog ( "Error connecting to server " . $_in["ami"]["hostname"] . ":" . $_in["ami"]["port"] . ".", VoIP_LOG_WARNING);
      unset ( $ami);
      sleep ( 10);
      continue;
    }
    writelog ( "Connected to server " . $_in["ami"]["hostname"] . ":" . $_in["ami"]["port"] . ".");
  }

  /**
   * Look for active campaign with associated queue
   */
  foreach ( $_in["Campaigns"] as $cid => $campaign)
  {
    if ( $campaign["State"] == "A" && $campaign["Queue"] != NULL)
    {
      /**
       * Check if this active campaign has available members, if so, dial to it
       */
      $queue = "queue_" . $campaign["Queue"];
      foreach ( $_in["Queues"][$queue]["Members"] as $member => $status)
      {
        if ( $status == "A")
        {
          /**
           * Look for waiting entries
           */
          $dialed = false;
          foreach ( $campaign["Entries"] as $eid => $entry)
          {
            if ( $entry["State"] == "W")
            {
              $groupfree = true;
              foreach ( $campaign["Entries"] as $group)
              {
                /**
                 * If there's someone talking to this group, or some number has finished, mark to skip it
                 */
                if ( $group["Grouper"] == $entry["Grouper"] && ( $group["State"] == "A" || $group["State"] == "F" || $group["State"] == "C" || $group["State"] == "I" || $group["State"] == "D" || $group["State"] == "R"))
                {
                  $groupfree = false;
                  break;
                }
              }
              if ( $groupfree)
              {
                $dialed = true;
                writeLog ( "Dialing to " . $entry["Number"] . ", campaign " . $cid . ", member " . preg_replace ( "/^(.*)\/u([0-9]+)-[0-9]$/", "$2", $member) . ".");
                $_in["Queues"][$queue]["Members"][$member] = $eid;
                file_put_contents ( "/var/spool/asterisk/outgoing/dialer-" . $eid . ".call", "channel: " . "LOCAL/" . $entry["Number"] . "@dialer-common\nmaxretries: 1\nwaittime: 60\nretrytime: 60\ncallerid: \"VoIP Domain dialer\" <" . $entry["Number"] . ">\ncontext: VoIPDomain-extensions\nextension: " . preg_replace ( "/^(.*)\/u([0-9]+)-[0-9]$/", "$2", $member) . "\npriority: 1\n");
                update_campaign_entry ( $eid, $_in["Campaigns"][$cid]["Entries"][$eid]["Tries"]++, "D");
                break;
              }
            }
          }
          if ( ! $dialed)
          {
            writeLog ( "Campaign " . $cid . " with all entries dialed!");
            disable_campaign ( $cid);
          }
        }
      }
    }
  }

  /**
   * Loop while there's events
   */
  if ( $ami->events_check ())
  {
    $event = $ami->events_shift ();
    switch ( $event["Event"])
    {
      case "FullyBooted":
        $ami->request ( "QueueStatus", array ());
        break;
      case "AGIExecStart":
      case "AGIExecEnd":
      case "Newexten":
      case "Registry":
      case "SuccessfulAuth":
      case "QueueSummaryComplete":
      case "QueueStatusComplete":
        break;
      case "DeviceStateChange":
        echo "Device " . $event["Device"] . " changed state to " . $event["State"] . ".\n";
        break;
      case "QueueSummary":
        echo "Queue " . $event["Queue"] . " summary:\n";
        echo "  Logged in: " . $event["LoggedIn"] . "\n";
        echo "  Available: " . $event["Available"] . "\n";
        echo "  Callers: " . $event["Callers"] . "\n";
        echo "  Hold time: " . $event["Holdtime"] . "\n";
        echo "  Talk time: " . $event["TalkTime"] . "\n";
        echo "  Longest hold time: " . $event["LongestHoldTime"] . "\n";
        if ( ! array_key_exists ( $event["Queue"], $_in["Queues"]))
        {
          $_in["Queues"][$event["Queue"]] = array ();
          $_in["Queues"][$event["Queue"]]["Members"] = array ();
        }
        $_in["Queues"][$event["Queue"]]["Logged"] = $event["LoggedIn"];
        $_in["Queues"][$event["Queue"]]["Available"] = $event["Available"];
        $_in["Queues"][$event["Queue"]]["Callers"] = $event["Callers"];
        break;
      case "QueueParams":
        echo "Queue status for queue " . $event["Queue"] . " is:\n";
        echo "  Strategy: " . $event["Strategy"] . "\n";
        echo "  Max calls: " . $event["Max"] . "\n";
        echo "  Total Calls: " . $event["Calls"] . "\n";
        echo "  Hold time: " . $event["Holdtime"] . "\n";
        echo "  Talk time: " . $event["TalkTime"] . "\n";
        echo "  Completed: " . $event["Completed"] . "\n";
        echo "  Abandoned: " . $event["Abandoned"] . "\n";
        echo "  Service level: " . $event["ServiceLevel"] . "\n";
        echo "  Service level perf: " . $event["ServicelevelPerf"] . "\n";
        echo "  Service level perf 2: " . $event["ServicelevelPerf2"] . "\n";
        echo "  Weight: " . $event["Weight"] . "\n";
        $ami->request ( "QueueSummary", array ( "Queue" => $event["Queue"]));
        if ( ! array_key_exists ( $event["Queue"], $_in["Queues"]))
        {
          $_in["Queues"][$event["Queue"]] = array ();
          $_in["Queues"][$event["Queue"]]["Members"] = array ();
        }
        break;
      case "QueueMemberAdded":
        echo "Queue " . $event["Queue"] . " added new member " . $event["MemberName"] . ".\n";
        $ami->request ( "QueueStatus", array ());
        api_call ( "/notifications/QueueMemberAdded", "POST", array ( "Queue" => substr ( $event["Queue"], 6), "MemberName" => $event["MemberName"]));
        break;
      case "QueueMemberPause":
        if ( $event["Paused"] == "0")
        {
          echo "Queue " . $event["Queue"] . " paused member " . $event["MemberName"] . ".\n";
          api_call ( "/notifications/QueueMemberPaused", "POST", array ( "Queue" => substr ( $event["Queue"], 6), "MemberName" => $event["MemberName"]));
        } else {
          echo "Queue " . $event["Queue"] . " unpaused member " . $event["MemberName"] . ".\n";
          api_call ( "/notifications/QueueMemberUnPaused", "POST", array ( "Queue" => substr ( $event["Queue"], 6), "MemberName" => $event["MemberName"]));
        }
        break;
      case "QueueMemberRemoved":
        echo "Queue " . $event["Queue"] . " removed member " . $event["MemberName"] . ".\n";
        unset ( $_in["Queues"][$event["Queue"]]["Members"][$event["MemberName"]]);
        api_call ( "/notifications/QueueMemberRemoved", "POST", array ( "Queue" => substr ( $event["Queue"], 6), "MemberName" => $event["MemberName"]));
        break;
      case "QueueMember":
      case "QueueMemberStatus":
        $name = ( ! empty ( $event["Name"]) ? $event["Name"] : $event["MemberName"]);
        echo "Queue " . $event["Queue"] . " member " . $name . " with status:\n";
        echo "  Membership type: " . $event["Membership"] . "\n";
        echo "  Penalty: " . $event["Penalty"] . "\n";
        echo "  Calls taken: " . $event["CallsTaken"] . "\n";
        echo "  Last call: " . $event["LastCall"] . " (" . ( $event["LastCall"] == 0 ? "never" : date ( "%r", $event["LastCall"])) . ")\n";
        echo "  Last pause: " . $event["LastPause"] . " (" . ( $event["LastPause"] == 0 ? "never" : date ( "%r", $event["LastPause"])) . ")\n";
        echo "  In call: " . ( $event["InCall"] == 0 ? "No" : "Yes") . "\n";
        echo "  Status: ";
        switch ( $event["Status"])
        {
          case 0:
            echo "Unknown";
            $_in["Queues"][$event["Queue"]]["Members"][iname] = "U";
            break;
          case 1:
            echo "Not in use";
            $_in["Queues"][$event["Queue"]]["Members"][$name] = "A";
            break;
          case 2:
            echo "In use";
            $_in["Queues"][$event["Queue"]]["Members"][$name] = "B";
            break;
          case 3:
            echo "Busy";
            $_in["Queues"][$event["Queue"]]["Members"][$name] = "B";
            break;
          case 4:
            echo "Invalid";
            $_in["Queues"][$event["Queue"]]["Members"][$name] = "U";
            break;
          case 5:
            echo "Unavailable";
            $_in["Queues"][$event["Queue"]]["Members"][$name] = "U";
            break;
          case 6:
            echo "Ringing";
            $_in["Queues"][$event["Queue"]]["Members"][$name] = "B";
            break;
          case 7:
            echo "Ring in use";
            $_in["Queues"][$event["Queue"]]["Members"][$name] = "B";
            break;
          case 8:
            echo "On hold";
            $_in["Queues"][$event["Queue"]]["Members"][$name] = "B";
            break;
          default:
            echo "Unknown status (" . $event["Status"] . ")!";
            $_in["Queues"][$event["Queue"]]["Members"][$name] = "U";
            break;
        }
        echo "\n";
        echo "  Paused: " . ( $event["Paused"] == 0 ? "No" : "Yes") . "\n";
        echo "  Paused reason: " . $event["PausedReason"] . "\n";
        echo "  Ring in use: " . ( $event["Ringinuse"] == 0 ? "No" : "Yes") . "\n";
        unset ( $name);
        break;
      case "PeerStatus":
        echo "Peer " . $event["Peer"] . " is now ";
        switch ( $event["PeerStatus"])
        {
          case "Registered":
            echo "registered (at " . $event["Address"] . ")";
            break;
          case "Unreachable":
            echo "unreachable";
            break;
          case "Reachable":
            echo "reachable";
            break;
          default:
            echo "with unknown status (" . $event["PeerStatus"] . ")";
            break;
        }
        echo ".\n";
        if ( $event["ChannelType"] == "SIP" && preg_match ( "/^SIP\/u[0-9]+-[0-9]$/", $event["Peer"]))
        {
          switch ( $event["PeerStatus"])
          {
            case "Registered":
              api_call ( "/notifications/PeerRegistered", "POST", array ( "Peer" => $event["Peer"], "Status" => $event["PeerStatus"], "Address" => $event["Address"] . ":" . $event["Port"]));
              break;
            case "Unregistered":
              api_call ( "/notifications/PeerUnregistered", "POST", array ( "Peer" => $event["Peer"], "Status" => $event["PeerStatus"]));
              break;
            case "Unreachable":
              api_call ( "/notifications/PeerUnreachable", "POST", array ( "Peer" => $event["Peer"], "Status" => $event["PeerStatus"], "Address" => $event["Address"] . ":" . $event["Port"], "Time" => $event["Time"]));
              break;
            case "Rejected":
              api_call ( "/notifications/PeerRejected", "POST", array ( "Peer" => $event["Peer"], "Status" => $event["PeerStatus"], "Address" => $event["Address"] . ":" . $event["Port"]));
              break;
            case "Lagged":
              api_call ( "/notifications/PeerLagged", "POST", array ( "Peer" => $event["Peer"], "Status" => $event["PeerStatus"], "Address" => $event["Address"] . ":" . $event["Port"], "Time" => $event["Time"]));
              break;
          }
        }
        break;
      case "Newstate":
        if ( $event["Context"] == "dialer-common")
        {
          /**
           * Locate campaign entry to update
           */
          foreach ( $_in["Campaigns"] as $cid => $campaign)
          {
            foreach ( $campaign["Entries"] as $eid => $entry)
            {
              if ( $entry["Number"] == $event["Exten"])
              {
                switch ( $event["ChannelState"])
                {
                  case "5":
                    update_campaign_entry ( $eid, $_in["Campaigns"][$cid]["Entries"][$eid]["Tries"], "R");
                    break;
                  case "6":
                    $membertalking = NULL;
                    $queue = NULL;
                    foreach ( $_in["Queues"] as $queuenumber => $queues)
                    {
                      foreach ( $queues["Members"] as $member => $relatedeid)
                      {
                        if ( $relatedeid == $eid)
                        {
                          $membertalking = preg_replace ( "/^(.*)\/u([0-9]+)-[0-9]$/", "$2", $member);
                          $queue = substr ( $queuenumber, 6);
                        }
                      }
                    }
                    update_campaign_entry ( $eid, $_in["Campaigns"][$cid]["Entries"][$eid]["Tries"], "A", $membertalking);
                    api_call ( "/notifications/CampaignCallAnswered", "POST", array ( "Queue" => $queue, "Extension" => $membertalking, "eid" => $eid));
                    break;
                  default:
                    break 2;
                }
              }
            }
          }
        }
        break;
      case "ChallengeSent":
      case "Newchannel":
      case "DialState":
      case "NewConnectedLine":
      case "NewCallerid":
      case "DialBegin":
      case "DialEnd":
      case "LocalBridge":
      case "RTCPReceived":
      case "RTCPSent":
      case "LocalOptimizationBegin":
      case "LocalOptimizationEnd":
      case "BridgeCreate":
      case "BridgeEnter":
      case "BridgeLeave":
      case "BridgeDestroy":
      case "SoftHangupRequest":
      case "HangupRequest":
        break;
      case "Hangup":
        if ( $event["Context"] == "dialer-common")
        {
          /**
           * Locate campaign entry to update
           */
          foreach ( $_in["Campaigns"] as $cid => $campaign)
          {
            foreach ( $campaign["Entries"] as $eid => $entry)
            {
              if ( $entry["Number"] == $event["Exten"])
              {
                switch ( $event["Cause"])
                {
                  case "16": // Normal clearing
                    update_campaign_entry ( $eid, $_in["Campaigns"][$cid]["Entries"][$eid]["Tries"], "F");
                    foreach ( $campaign["Entries"] as $gid => $group)
                    {
                      /**
                       * If there's someone else waiting at same group, set to "I" (invalid)
                       */
                      if ( $eid != $gid && $group["Grouper"] == $entry["Grouper"] && $group["State"] == "W")
                      {
                        update_campaign_entry ( $gid, $_in["Campaigns"][$cid]["Entries"][$gid]["Tries"], "I");
                        break;
                      }
                    }
                    break;
                  default:
                    if ( $_in["Campaigns"][$cid]["Entries"][$eid]["Tries"] >= $_in["general"]["maxretries"])
                    {
                      update_campaign_entry ( $eid, $_in["Campaigns"][$cid]["Entries"][$eid]["Tries"], "C");
                    } else {
                      update_campaign_entry ( $eid, $_in["Campaigns"][$cid]["Entries"][$eid]["Tries"], "W");
                    }
                    break;
                }
              }
            }
          }
        }
        break;
      case "VarSet":
        if ( $event["Channel"] == "none" && $event["Uniqueid"] == "none")
        {
          if ( substr ( $event["Variable"], 0, 6) == "queue_")
          {
            if ( $event["Value"] != "")
            {
              echo "Dialer campaign " . $event["Value"] . " associated to queue " . substr ( $event["Variable"], 6) . "!\n";
              $ami->request ( "QueueStatus", array ());
              $_in["Campaign"]["Queue"] = $event["Value"];
            } else {
              echo "Queue " . substr ( $event["Variable"], 6) . " unassociated from dialer campaign!\n";
            }
            update_campaigns ();
          }
          if ( $event["Variable"] == "dump")
          {
            echo "Campaigns:\n";
            var_dump ( $_in["Campaigns"]);
            echo "Queues:\n";
            var_dump ( $_in["Queues"]);
          }
        }
        break;
      default:
        echo "Unknown event received: " . $event["Event"] . "\n";
        var_dump ( $event);
        break;
    }
  } else {
    $ami->wait_event ();
    $lastevent = time ();
  }
}

/**
 * Function to update campaigns from database.
 *
 * @global array $_in Framework global configuration variable
 * @return void
 */
function update_campaigns ()
{
  global $_in;

  if ( ! $result = @$_in["mysql"]["id"]->query ( "SELECT * FROM `Campaigns`"))
  {
    writeLog ( "Cannot fetch dialer campaigns from database!", VoIP_LOG_FATAL);
  }
  $_in["Campaigns"] = array ();
  while ( $data = $result->fetch_assoc ())
  {
    $_in["Campaigns"][$data["ID"]] = array ( "State" => $data["State"], "Queue" => $data["Queue"], "Entries" => array ());
    update_campaign_entries ( $data["ID"]);
  }
}

/**
 * Function to update campaign entries from database.
 *
 * @global array $_in Framework global configuration variable
 * @param integer CID Campaign ID
 * @return void
 */
function update_campaign_entries ( $cid)
{
  global $_in;

  if ( ! $result = @$_in["mysql"]["id"]->query ( "SELECT * FROM `CampaignEntries` WHERE `Campaign` = " . $_in["mysql"]["id"]->real_escape_string ( (int) $cid) . " ORDER BY `Grouper`, `Number`"))
  {
    writeLog ( "Cannot fetch dialer campaign entries from database!", VoIP_LOG_FATAL);
  }
  $_in["Campaigns"][$cid]["Entries"] = array ();
  while ( $data = $result->fetch_assoc ())
  {
    $_in["Campaigns"][$cid]["Entries"][$data["ID"]] = array ( "Grouper" => $data["Grouper"], "Number" => $data["Number"], "Tries" => $data["Tries"], "State" => $data["State"]);
  }
}

/**
 * Function to update a campaign entry.
 *
 * @global array $_in Framework global configuration variable
 * @param integer EID Campaign entry ID
 * @param integer Tries Number of actual tries
 * @param string State New state of entry
 * @param integer Member Member talking (optional)
 * @return void
 */
function update_campaign_entry ( $eid, $tries, $state, $member = NULL)
{
  global $_in;

  if ( ! $result = @$_in["mysql"]["id"]->query ( "SELECT * FROM `CampaignEntries` WHERE `ID` = " . $_in["mysql"]["id"]->real_escape_string ( (int) $eid)))
  {
    writeLog ( "Cannot fetch dialer campaign entry from database!", VoIP_LOG_FATAL);
  }
  if ( $result->num_rows != 1)
  {
    writeLog ( "Cannot find dialer campaign entry from database!", VoIP_LOG_FATAL);
  }
  $entry = $result->fetch_assoc ();
  $_in["Campaigns"][$entry["Campaign"]]["Entries"][$eid]["Tries"] = $tries;
  $_in["Campaigns"][$entry["Campaign"]]["Entries"][$eid]["State"] = $state;
  if ( ! @$_in["mysql"]["id"]->query ( "UPDATE `CampaignEntries` SET `Tries` = " . $_in["mysql"]["id"]->real_escape_string ( (int) $tries) . ", `State` = '" . $_in["mysql"]["id"]->real_escape_string ( $state) . "' WHERE `ID` = " . $_in["mysql"]["id"]->real_escape_string ( (int) $eid)))
  {
    writeLog ( "Error updating dialer campaign entry at database!", VoIP_LOG_FATAL);
  }
  if ( $member == NULL)
  {
    api_call ( "/campaigns/entry/" . $eid, "PATCH", array ( "tries" => $tries, "state" => $state));
  } else {
    api_call ( "/campaigns/entry/" . $eid, "PATCH", array ( "tries" => $tries, "state" => $state, "member" => $member));
  }
}

/**
 * Function to disable a campaign.
 *
 * @global array $_in Framework global configuration variable
 * @param integer CID Campaign ID
 * @return void
 */
function disable_campaign ( $cid)
{
  global $_in;

  if ( ! @$_in["mysql"]["id"]->query ( "UPDATE `Campaigns` SET `State` = 'I' WHERE `ID` = " . $_in["mysql"]["id"]->real_escape_string ( (int) $cid)))
  {
    writeLog ( "Cannot set campaign unavailable at database!", VoIP_LOG_FATAL);
  }
  $_in["Campaigns"][$cid]["State"] = "I";
  api_call ( "/campaigns/" . $cid . "/status", "PATCH", array ( "state" => "I"));
}
?>
