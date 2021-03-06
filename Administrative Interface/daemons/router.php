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
 * Message router daemon. This daemon creates routes and watches, and everytime a
 * route is called and there's someone watching, notify the user about the event.
 * The notification could be URI call or a NGiNX HTTP Server Push message.
 *
 * @author     Ernani José Camargo Azevedo <azevedo@voipdomain.io>
 * @version    1.0
 * @package    VoIP Domain
 * @subpackage Message Router Daemon
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

/**
 * Parse configuration file. You should put your configuration file OUTSIDE
 * the web server files path, or you must block access to this file at the
 * web server configuration. Your configuration would contain passwords and
 * other sensitive configurations.
 */
$_in = parse_ini_file ( "/etc/voipdomain/router.conf", true);

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
 * HTTP result codes
 */
$_in["resultcodes"] = array ();

// Informational 1xx
$_in["resultcodes"][100] = "Continue";
$_in["resultcodes"][101] = "Switching Protocols";

// Success 2xx
$_in["resultcodes"][200] = "OK";
$_in["resultcodes"][201] = "Created";
$_in["resultcodes"][202] = "Accepted";
$_in["resultcodes"][203] = "Non-Authoritative Information";
$_in["resultcodes"][204] = "No Content";
$_in["resultcodes"][205] = "Reset Content";
$_in["resultcodes"][206] = "Partial Content";

// Redirection 3xx
$_in["resultcodes"][300] = "Multiple Choices";
$_in["resultcodes"][301] = "Moved Permanently";
$_in["resultcodes"][302] = "Found"; // 1.1
$_in["resultcodes"][303] = "See Other";
$_in["resultcodes"][304] = "Not Modified";
$_in["resultcodes"][305] = "Use Proxy";
// 306 is deprecated but reserved
$_in["resultcodes"][307] = "Temporary Redirect";

// Client Error 4xx
$_in["resultcodes"][400] = "Bad Request";
$_in["resultcodes"][401] = "Unauthorized";
$_in["resultcodes"][402] = "Payment Required";
$_in["resultcodes"][403] = "Forbidden";
$_in["resultcodes"][404] = "Not Found";
$_in["resultcodes"][405] = "Method Not Allowed";
$_in["resultcodes"][406] = "Not Acceptable";
$_in["resultcodes"][407] = "Proxy Authentication Required";
$_in["resultcodes"][408] = "Request Timeout";
$_in["resultcodes"][409] = "Conflict";
$_in["resultcodes"][410] = "Gone";
$_in["resultcodes"][411] = "Length Required";
$_in["resultcodes"][412] = "Precondition Failed";
$_in["resultcodes"][413] = "Request Entity Too Large";
$_in["resultcodes"][414] = "Request-URI Too Long";
$_in["resultcodes"][415] = "Unsupported Media Type";
$_in["resultcodes"][416] = "Requested Range Not Satisfiable";
$_in["resultcodes"][417] = "Expectation Failed";

// Server Error 5xx
$_in["resultcodes"][500] = "Internal Server Error";
$_in["resultcodes"][501] = "Not Implemented";
$_in["resultcodes"][502] = "Bad Gateway";
$_in["resultcodes"][503] = "Service Unavailable";
$_in["resultcodes"][504] = "Gateway Timeout";
$_in["resultcodes"][505] = "HTTP Version Not Supported";
$_in["resultcodes"][509] = "Bandwidth Limit Exceeded";

/**
 * Methods
 */
$_in["methods"] = array ();
$_in["methods"][] = "GET";
$_in["methods"][] = "POST";
$_in["methods"][] = "PUT";
$_in["methods"][] = "DELETE";
$_in["methods"][] = "HEAD";
$_in["methods"][] = "OPTIONS";
$_in["methods"][] = "TRACE";
$_in["methods"][] = "CONNECT";

/**
 * Data types
 */
$_in["datatypes"] = array ();
$_in["datatypes"][] = "JSON";
$_in["datatypes"][] = "FORM-DATA";
$_in["datatypes"][] = "PHP";

/**
 * Configure locale and encoding
 */
mb_internal_encoding ( $_in["general"]["charset"]);
setlocale ( LC_ALL, $_in["general"]["language"] . "." . $_in["general"]["charset"]);

/**
 * Show software version header
 */
echo chr ( 27) . "[1;37mVoIP Domain Message Router Daemon" . chr ( 27) . "[1;0m v" . $_in["version"] . "\n";
echo "\n";

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
 * Validate MySQL session
 */
if ( ! is_array ( $_in["mysql"]))
{
  echo "Error: Cannot find \"mysql\" session at configuration file.\n";
  exit ( 1);
}

/**
 * Fetch notifications database
 */
echo "Executing: Fetching notifications database... ";
reload_watches ();
echo chr ( 27) . "[1;37m" . gettext ( "OK") . chr ( 27) . "[1;0m\n";

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
 * Create new socket and start listening port
 */
echo "Executing: Creating TCP socket at port " . $_in["daemon"]["port"] . "... ";
if ( ! $socket = socket_create ( AF_INET, SOCK_STREAM, SOL_TCP))
{
  writeLog ( "Failed to create socket!", VoIP_LOG_FATAL);
}
if ( ! @socket_bind ( $socket, 0, $_in["daemon"]["port"]))
{
  writeLog ( "Cannot bind to TCP port " . $_in["daemon"]["port"] . "!", VoIP_LOG_FATAL);
}
if ( ! socket_listen ( $socket, 0))
{
  writeLog ( "Cannot listen to socket!", VoIP_LOG_FATAL);
}
echo chr ( 27) . "[1;37m" . gettext ( "OK") . chr ( 27) . "[1;0m\n";

/**
 * If possible, change process name
 */
if ( function_exists ( "setproctitle"))
{
  setproctitle ( "VoIP Domain Message Router daemon");
}

/**
 * Show start of operations message
 */
echo "Everything done. Waiting for connections!\n\n";

/**
 * Log system initialization
 */
writeLog ( "VoIP Domain Message Router daemon initialized.");

/**
 * Fork process to daemon mode (except if in debug mode)
 */
error_reporting ( E_ERROR);
set_time_limit ( 0);
ob_implicit_flush ();
if ( function_exists ( "pcntl_async_signals"))
{
  pcntl_async_signals ( true);
} else {
  declare ( ticks = 1);
}
$_in["mainpid"] = posix_getpid ();
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
  posix_setsid ();
}

/**
 * Set default variables
 */
$_in["listening"] = true;
$_in["connections"] = 0;
$_in["curl"] = array ();

/**
 * Handle POSIX signals
 */
pcntl_signal ( SIGTERM, "signal_handler");
pcntl_signal ( SIGINT, "signal_handler");
pcntl_signal ( SIGHUP, "signal_handler");
pcntl_signal ( SIGCHLD, "signal_handler");

/**
 * Listens for incoming client connections
 */
while ( $_in["listening"])
{
  if ( ! $connection = @socket_accept ( $socket))
  {
    usleep ( 100);
    continue;
  }

  /**
   * It's a new client connection, handle it
   */
  socket_getpeername ( $connection, $ip, $port);

  /**
   * Increase connections counter
   */
  $_in["connections"]++;

  /**
   * Create a fork to serve new connection
   */
  if ( $connection > 0)
  {
    $pid = pcntl_fork ();
    if ( $pid == -1)
    {
      writeLog ( "Cannot fork to serve a new client!", VoIP_LOG_FATAL);
    }
    if ( $pid == 0)
    {
      /**
       * If possible, change process name
       */
      if ( function_exists ( "setproctitle"))
      {
        setproctitle ( "VoIP Domain Message Router daemon - Client: " . $ip . ":" . $port);
      }

      /**
       * Handle the client connection
       */
      $_in["listening"] = false;
      socket_close ( $socket);
      handle_client ( $connection, $ip, $port);
      socket_close ( $connection);

      /**
       * If there's any CURL multi instance running, wait to close the thread
       */
      if ( sizeof ( $_in["curl"]) != 0)
      {
        do
        {
          $_in["curl"]["mrc"] = curl_multi_exec ( $_in["curl"]["mh"], $_in["curl"]["active"]);
        } while ( $_in["curl"]["mrc"] == CURLM_CALL_MULTI_PERFORM);
        while ( $_in["curl"]["active"] && $_in["curl"]["mrc"] == CURLM_OK)
        {
          if ( curl_multi_select ( $_in["curl"]["mh"], 60) != -1)
          {
            do
            {
              $_in["curl"]["mrc"] = curl_multi_exec ( $_in["curl"]["mh"], $_in["curl"]["active"]);
            } while ( $_in["curl"]["mrc"] == CURLM_CALL_MULTI_PERFORM);
          }
        }

        /**
         * Close the handlers
         */
        foreach ( $_in["curl"]["mh"]["handlers"] as $handler)
        {
          curl_multi_remove_handle ( $_in["curl"]["mh"]["handler"], $handler);
        }
        curl_multi_close ( $_in["curl"]["mh"]["handler"]);
      }
    }
  }
}

/**
 * Daemon signal handler function.
 *
 * @global array $_in Framework global configuration variable
 * @global object $socket Socket object to close if end the daemon execution
 * @global object $connection Socket object to close the child connection handler
 * @param $port integer Signal received
 * @return void
 */
function signal_handler ( $signal)
{
  global $_in, $socket, $connection;

  switch ( $signal)
  {
    case SIGTERM:
      if ( $socket)
      {
        @socket_close ( $socket);
      }
      if ( $connection)
      {
        @socket_close ( $connection);
      }
      if ( posix_getpid () == $_in["mainpid"])
      {
        writeLog ( "Received SIGTERM, stopping daemon!", VoIP_LOG_FATAL);
      }
      exit ();
      break;
    case SIGINT:
      if ( $socket)
      {
        @socket_close ( $socket);
      }
      if ( $connection)
      {
        @socket_close ( $connection);
      }
      if ( posix_getpid () == $_in["mainpid"])
      {
        writeLog ( "Received SIGINT, stopping daemon!", VoIP_LOG_FATAL);
      }
      exit ();
      break;
    case SIGHUP:
      if ( posix_getpid () == $_in["mainpid"])
      {
        writeLog ( "Received SIGHUP, reloading watches!", VoIP_LOG_NOTIFY);
        reload_watches ();
      }
      break;
    case SIGCHLD:
      pcntl_waitpid ( -1, $status);
      break;
  }
}

/**
 * Function to reload watches from database.
 *
 * @global array $_in Framework global configuration variable
 * @return void
 */
function reload_watches ()
{
  global $_in;

  /**
   * Clear watches variable
   */
  $_in["watches"] = array ();

  /**
   * Connect to database
   */
  if ( ! $_in["mysql"]["id"] = @new mysqli ( $_in["mysql"]["hostname"] . ( ! empty ( $_in["mysql"]["port"]) ? ":" . $_in["mysql"]["port"] : ""), $_in["mysql"]["username"], $_in["mysql"]["password"], $_in["mysql"]["database"]))
  {
    writeLog ( "Cannot connect to database server!", VoIP_LOG_FATAL);
  }

  /**
   * Fetch gateways database
   */
  if ( ! $result = @$_in["mysql"]["id"]->query ( "SELECT * FROM `Notifications`"))
  {
    writeLog ( "Cannot request notifications informations to database!", VoIP_LOG_FATAL);
  }
  $_in["watches"] = array ();
  while ( $watch = $result->fetch_assoc ())
  {
    if ( ! array_key_exists ( $watch["Event"], $_in["watches"]))
    {
      $_in["watches"][$watch["Event"]] = array ();
    }
    $_in["watches"][$watch["Event"]][] = array ( "Method" => $watch["Method"], "URL" => $watch["URL"], "Type" => $watch["Type"], "Headers" => $watch["Headers"], "RelaxSSL" => $watch["RelaxSSL"], "Expire" => ( $watch["Expire"] == "0000-00-00 00:00:00" ? 0 : mktime ( substr ( $watch["Expire"], 11, 2), substr ( $watch["Expire"], 14, 2), substr ( $watch["Expire"], 17, 2), substr ( $watch["Expire"], 5, 2), substr ( $watch["Expire"], 8, 2), substr ( $watch["Expire"], 0, 4))));
  }
  @$_in["mysql"]["id"]->close ();

  return;
}

/**
 * Handle a new client connection function. This function is called when a new client connect and a fork is created.
 *
 * @global array $_in Framework global configuration variable
 * @param $socket object Client connection socket
 * @param $ip string IP address of client TCP/IP connection
 * @param $port integer Port number of client TCP/IP connection
 * @return void
 */
function handle_client ( $socket, $ip, $port)
{
  global $_in;

  /**
   * Start variables
   */
  $client = array ();
  $client["keepalive"] = false;
  $client["vars"] = array ();
  $client["output"] = "";
  $client["headers"] = true;
  $client["processing"] = true;
  $client["result"] = 404;
  $client["extraheaders"] = array ();
  $client["contenttype"] = "text/html; charset=utf-8";
  $client["data"] = array ();

  /**
   * Enter request loop
   */
  while ( true)
  {
    /**
     * Before read, check if have something to return to client
     */
    if ( ! $client["processing"])
    {
      /**
       * Generate log entry
       */
      file_put_contents ( $_in["general"]["accesslogfile"], $ip . " - - [" . date ( "d/M/Y:H:i:s O") . "] \"" . $client["vars"]["REQUEST_METHOD"] . " " . $client["vars"]["REQUEST_URI"] . "\" " . $client["result"] . " " . strlen ( $client["output"]) . " \"-\" \"-\"\n", FILE_APPEND);

      /**
       * Build return buffer
       */
      $buffer = "HTTP/1.1 " . $client["result"] . " " . $_in["resultcodes"][$client["result"]] . "\r\n";
      $buffer .= "Server: VoIPDomain-" . $_in["version"] . "\r\n";
      $buffer .= "Date: " . date ( "r") . "\r\n";
      $buffer .= "Content-Type: " . $client["contenttype"] . "\r\n";
      $buffer .= "Content-Length: " . strlen ( $client["output"]) . "\r\n";
      $buffer .= "Connection: " . ( $client["keepalive"] ? "keep-alive" : "close") . "\r\n";
      if ( sizeof ( $client["extraheaders"]) != 0)
      {
        foreach ( $client["extraheaders"] as $variable => $value)
        {
          $buffer .= $variable . ": " . $value . "\r\n";
        }
      }
      $buffer .= "\r\n";
      $buffer .= $client["output"];

      /**
       * Write response to client
       */
      $length = strlen ( $buffer);
      while ( true)
      {
        if ( ! $sent = @socket_write ( $socket, $buffer, $length))
        {
          writeLog ( "Error writing response to client " . $ip . ":" . $port . "!");
          return;
        }
        if ( $sent < $length)
        {
          $buffer = substr ( $buffer, $sent);
          $length -= $sent;
        } else {
          break;
        }
      }

      /**
       * If keepalive is not enabled, return and close socket
       */
      if ( ! $client["keepalive"])
      {
        return;
      }

      /**
       * Keep alive enabled, clear request data and wait for request
       */
      $client["vars"] = array ();
      $client["output"] = "";
      $client["headers"] = true;
      $client["processing"] = true;
      $client["result"] = 404;
      $client["extraheaders"] = array ();
      $client["contenttype"] = "text/html; charset=utf-8";
      $client["data"] = array ();
    }

    /**
     * Read up to 64k of data
     */
    if ( ! $data = @socket_read ( $socket, 65535, PHP_BINARY_READ))
    {
      return;
    }

    /**
     * Process request headers
     */
    if ( $client["headers"])
    {
      $bytes = 0;
      $line = strtok ( $data, "\n");
      do
      {
        $bytes += strlen ( $line) + 1;
        $line = str_replace ( "\r", "", trim ( $line));

        /**
         * Process first HTTP line (Method URI Version)
         */
        if ( preg_match ( "/^(OPTIONS|GET|HEAD|POST|PUT|DELETE|TRACE|CONNECT)\s+(\S+)\s+(HTTP\/\d+\.\d+)$/", $line, $matches))
        {
          $client["vars"]["REMOTE_ADDR"] = $ip;
          $client["vars"]["REMOTE_PORT"] = $port;
          $client["vars"]["REQUEST_METHOD"] = $matches[1];
          $client["vars"]["REQUEST_URI"] = $matches[2];
          if ( strpos ( $matches[2], "?") !== false)
          {
            $client["vars"]["DOCUMENT_URI"] = substr ( $matches[2], 0, strpos ( $matches[2], "?"));
            $client["vars"]["SCRIPT_NAME"] = substr ( $matches[2], 0, strpos ( $matches[2], "?"));
            $client["vars"]["QUERY_STRING"] = substr ( $matches[2], strpos ( $matches[2], "?") + 1);
          } else {
            $client["vars"]["DOCUMENT_URI"] = $matches[2];
            $client["vars"]["SCRIPT_NAME"] = $matches[2];
            $client["vars"]["QUERY_STRING"] = "";
          }
          $client["vars"]["SERVER_PROTOCOL"] = $matches[3];
          $client["vars"]["HTTP_CONTENT_TYPE"] = "";
          $client["vars"]["CONTENT_LENGTH"] = "";
          $client["vars"]["REQUEST_BODY"] = "";
          continue;
        }

        /**
         * Process header variable
         */
        if ( preg_match ( "/^(\S+): (.*)$/", $line, $matches))
        {
          if ( $matches[1] == "Connection" && $matches[2] == "keep-alive")
          {
            $client["keepalive"] = true;
          }
          if ( $matches[1] == "Content-Type")
          {
            $client["vars"]["CONTENT_TYPE"] = $matches[2];
            continue;
          }
          if ( $matches[1] == "Content-Length")
          {
            $client["vars"]["CONTENT_LENGTH"] = $matches[2];
            continue;
          }
          $client["vars"]["HTTP_" . str_replace ( "-", "_", strtoupper ( $matches[1]))] = $matches[2];
          continue;
        }

        /**
         * Blank line? End of headers!
         */
        if ( $line == "")
        {
          $client["headers"] = false;
          $data = substr ( $data, $bytes);
          break;
        }

        /**
         * If reach here, we received an invalid HTTP request
         */
        $client["processing"] = false;
        $client["result"] = 400;
        $client["output"] = "<html>\n<head><title>400 Bad Request</title></head>\n<body>\n<center><h1>400 Bad Request</h1></center>\n<hr><center>VoIP Domain Message Router</center>\n</body>\n</html>\n";
        continue;
      } while ( $line = strtok ( "\n"));
    }

    /**
     * If reached here, but still at headers, didn't received entire buffer, break
     */
    if ( $client["headers"])
    {
      continue;
    }

    /**
     * Process request body
     */
    if ( $client["vars"]["CONTENT_LENGTH"] != "")
    {
      $client["vars"]["REQUEST_BODY"] .= $data;
      if ( strlen ( $client["vars"]["REQUEST_BODY"]) < (int) $client["vars"]["CONTENT_LENGTH"])
      {
        continue;
      }
    }

    /**
     * If reached here, all headers and body has been received. Start processing the request
     */
    $client["processing"] = false;

    /**
     * Process received variables
     */
    if ( strpos ( $client["vars"]["CONTENT_TYPE"], ";") !== false)
    {
      $type = trim ( substr ( $client["vars"]["CONTENT_TYPE"], 0, strpos ( $client["vars"]["CONTENT_TYPE"], ";")));
      if ( strpos ( $client["vars"]["CONTENT_TYPE"], "charset=") !== false)
      {
        $charset = trim ( substr ( $client["vars"]["CONTENT_TYPE"], strpos ( $client["vars"]["CONTENT_TYPE"], "charset=") + 8));
      } else {
        $charset = "utf-8";
      }
    } else {
      $type = trim ( $client["vars"]["CONTENT_TYPE"]);
      $charset = "utf-8";
    }
    if ( strtolower ( $charset) != "utf-8" && strtolower ( $charset) != "ascii")
    {
      if ( $client["vars"]["REQUEST_METHOD"] == "GET")
      {
        $client["vars"]["QUERY_STRING"] = mb_convert_encoding ( $client["vars"]["QUERY_STRING"], "UTF-8");
      } else {
        $client["vars"]["REQUEST_BODY"] = mb_convert_encoding ( $client["vars"]["REQUEST_BODY"], "UTF-8");
      }
    }
    switch ( $type)
    {
      case "text/json":
      case "application/json":
        $client["data"] = json_decode ( $client["vars"]["REQUEST_BODY"], true);
        break;
      case "text/xml":
      case "application/xml":
      case "application/atom+xml":
        $client["data"] = json_decode ( json_encode ( simplexml_load_string ( $client["vars"]["REQUEST_BODY"], "SimpleXMLElement", LIBXML_NOCDATA)));
        break;
      case "text/php":
      case "text/x-php":
      case "application/php":
      case "application/x-php":
      case "application/x-httpd-php":
      case "application/x-httpd-php-source":
        $client["data"] = unserialize ( $client["vars"]["REQUEST_BODY"]);
        break;
      case "multipart/form-data":
        if ( $client["vars"]["REQUEST_METHOD"] == "GET")
        {
          $client["data"] = parse_str ( $client["vars"]["QUERY_STRING"]);
        } else {
          $client["data"] = parse_str ( $client["vars"]["REQUEST_BODY"]);
        }
        break;
      case "":
        break;
      default:
        // Trigger an error, invalid route requested!
        $client["result"] = 415;
        $client["output"] = "<html>\n<head>\n<title>415 Unsupported Media Type</title>\n</head>\n<body>\n<center><h1>415 Unsupported Media Type</h1></center><hr><center>VoIP Domain Message Router</center>\n</body>\n</html>\n";
        continue 2;
    }

    /**
     * Process request
     */
    $path = explode ( "/", $client["vars"]["REQUEST_URI"]);
    switch ( $path[1])
    {
      case "event":
        if ( $client["vars"]["REQUEST_METHOD"] != "POST")
        {
          $client["result"] = 405;
          $client["extraheaders"]["Allow"] = "POST";
          $client["output"] = "<html>\n<head>\n<title>405 Method Not Allowed</title>\n</head>\n<body>\n<center><h1>405 Method Not Allowed</h1></center><hr><center>VoIP Domain Message Router</center>\n</body>\n</html>\n";
          continue 2;
        }
        if ( empty ( $path[2]) || sizeof ( $client["data"]) == 0)
        {
          $client["result"] = 422;
          $client["output"] = "<html>\n<head>\n<title>422 Unprocessable Entity</title>\n</head>\n<body>\n<center><h1>422 Unprocessable Entity</h1></center><hr><center>VoIP Domain Message Router</center>\n</body>\n</html>\n";
          continue 2;
        }
        if ( array_key_exists ( $path[2], $_in["watches"]))
        {
          foreach ( $_in["watches"][$path[2]] as $index => $watch)
          {
            if ( $watcher["Expire"] != 0 && $watcher["Expire"] < time ())
            {
              unset ( $_in["watches"][$path[2]][$index]);
              if ( sizeof ( $_in["watches"][$path[2]]) == 0)
              {
                unset ( $_in["watches"][$path[2]]);
              }
            } else {
              if ( ! notify_watcher ( $watch, $client["data"]))
              {
                writeLog ( "Cannot notify watcher " . $watch["URL"] . " (method " . $watch["Method"] . ", type " . $watch["Type"] . ")");
              }
            }
          }
        }
        $client["result"] = 200;
        $client["contenttype"] = "application/json; charset=utf-8";
        $client["output"] = "{\"result\":\"OK\"}";
        break;
      case "reload":
        if ( $client["vars"]["REQUEST_METHOD"] != "PUT")
        {
          $client["result"] = 405;
          $client["extraheaders"]["Allow"] = "PUT";
          $client["output"] = "<html>\n<head>\n<title>405 Method Not Allowed</title>\n</head>\n<body>\n<center><h1>405 Method Not Allowed</h1></center><hr><center>VoIP Domain Message Router</center>\n</body>\n</html>\n";
          continue 2;
        }
        posix_kill ( $_in["mainpid"], SIGHUP);
        $client["result"] = 200;
        $client["contenttype"] = "application/json; charset=utf-8";
        $client["output"] = "{\"result\":\"OK\"}";
        break;
      case "report":
        if ( $client["vars"]["REQUEST_METHOD"] != "GET")
        {
          $client["result"] = 405;
          $client["extraheaders"]["Allow"] = "GET";
          $client["output"] = "<html>\n<head>\n<title>405 Method Not Allowed</title>\n</head>\n<body>\n<center><h1>405 Method Not Allowed</h1></center><hr><center>VoIP Domain Message Router</center>\n</body>\n</html>\n";
          continue 2;
        }
        $client["result"] = 200;
        $watches = 0;
        foreach ( $_in["watches"] as $watch)
        {
          $watches += sizeof ( $watch);
        }
        $client["output"] = "<html>\n<head>\n<title>Report</title>\n</head>\n<body>\n<center><h1>Report</h1></center><hr>Current watches: " . $watches . "<br />Events processed: " . $_in["connections"] . "<br />Watches dump:<br /><pre>" . print_r ( $_in["watches"], true) . "</pre><br /><br /><center>VoIP Domain Message Router</center>\n</body>\n</html>\n";
        break;
      default:
        $client["result"] = 404;
        $client["output"] = "<html>\n<head>\n<title>404 Not Found</title>\n</head>\n<body>\n<center><h1>404 Not Found</h1></center><hr><center>VoIP Domain Message Router</center>\n</body>\n</html>\n";
        continue 2;
    }
  }
}

/**
 * Function to notify a watcher of an event.
 *
 * @global array $_in Framework global configuration variable
 * @param $watcher array Array with the watcher data
 * @param $data array Event data to be sent
 * @return boolean Result of operation
 */
function notify_watcher ( $watcher, $data)
{
  global $_in;

  /**
   * Change $data to watcher request data type
   */
  switch ( $watcher["Type"])
  {
    case "JSON":
      $data = json_encode ( $data, true);
      break;
    case "FORM-DATA":
      $data = http_build_query ( $data);
      break;
    case "PHP":
      $data = serialize ( $data);
      break;
    default:
      return false;
      break;
  }

  /**
   * Check if CURL multi handler has instanced, if not, instance it
   */
  if ( ! array_key_exists ( "mh", $_in["curl"]))
  {
    $_in["curl"]["mh"] = curl_multi_init ();
    $_in["curl"]["active"] = null;
    $_in["curl"]["mrc"] = null;
    $_in["curl"]["handlers"] = array ();
  }

  /**
   * Create CURL instance
   */
  $socket = curl_init ();
  curl_setopt ( $socket, CURLOPT_URL, $watcher["URL"] . ( $watcher["Method"] == "GET" ? "?" . $data : ""));
  curl_setopt ( $socket, CURLOPT_CUSTOMREQUEST, $watcher["Method"]);
  if ( $watcher["Method"] != "GET")
  {
    curl_setopt ( $socket, CURLOPT_POSTFIELDS, $data);
  }
  curl_setopt ( $socket, CURLOPT_USERAGENT, "VoIP Domain Message Router v" . $_in["version"] . " (Linux; U)");
  curl_setopt ( $socket, CURLOPT_TIMEOUT, 60);
  if ( $watcher["RelaxSSL"] == "Y")
  {
    curl_setopt ( $socket, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt ( $socket, CURLOPT_SSL_VERIFYHOST, false);
  }
  switch ( $watcher["Type"])
  {
    case "JSON":
      $type = "application/json";
      break;
    case "FORM-DATA":
      $type = "text/html";
      break;
    case "PHP":
      $type = "application/x-php";
      break;
  }
  if ( ! empty ( $watcher["Headers"]))
  {
    curl_setopt ( $socket, CURLOPT_HTTPHEADER, array ( "Content-Type: " . $type, $watcher["Headers"]));
  } else {
    curl_setopt ( $socket, CURLOPT_HTTPHEADER, array ( "Content-Type: " . $type));
  }
  curl_setopt ( $socket, CURLOPT_HEADER, false);
  curl_setopt ( $socket, CURLOPT_RETURNTRANSFER, true);

  /**
   * Add socket to multi handler
   */
  curl_multi_add_handle ( $_in["curl"]["mh"], $socket);
  $_in["curl"]["handlers"][] = $socket;

  /**
   * Execute handler
   */
  $_in["curl"]["mrc"] = curl_multi_exec ( $_in["curl"]["mh"], $_in["curl"]["active"]);

  /**
   * Finish function
   */
  return true;
}
?>
