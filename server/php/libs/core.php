<?php
/**
 * Common functions
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Restyaboard
 * @subpackage Core
 * @author     Restya <info@restya.com>
 * @copyright  2014-2018 Restya
 * @license    http://restya.com/ Restya Licence
 * @link       http://restya.com/
 */
/**
 * Returns an OAuth2 access token to the client
 *
 * @param array $post Post data
 *
 * @return mixed
 */
function getToken($post)
{
    $old_server_method = $_SERVER['REQUEST_METHOD'];
    if (!empty($_SERVER['CONTENT_TYPE'])) {
        $old_content_type = $_SERVER['CONTENT_TYPE'];
    }
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    $_POST = $post;
    OAuth2\Autoloader::register();
    $oauth_config = array(
        'user_table' => 'users'
    );
    $val_array = array(
        'dsn' => 'pgsql:host=' . R_DB_HOST . ';dbname=' . R_DB_NAME . ';port=' . R_DB_PORT,
        'username' => R_DB_USER,
        'password' => R_DB_PASSWORD
    );
    $storage = new OAuth2\Storage\Pdo($val_array, $oauth_config);
    $server = new OAuth2\Server($storage);
    if (isset($_POST['grant_type']) && $_POST['grant_type'] == 'password') {
        $val_array = array(
            'password' => $_POST['password']
        );
        $users = array(
            $_POST['username'] => $val_array
        );
        $user_credentials = array(
            'user_credentials' => $users
        );
        $storage = new OAuth2\Storage\Memory($user_credentials);
        $server->addGrantType(new OAuth2\GrantType\UserCredentials($storage));
    } elseif (isset($_POST['grant_type']) && $_POST['grant_type'] == 'refresh_token') {
        $always_issue_new_refresh_token = array(
            'always_issue_new_refresh_token' => true
        );
        $server->addGrantType(new OAuth2\GrantType\RefreshToken($storage, $always_issue_new_refresh_token));
    } elseif (isset($_POST['grant_type']) && $_POST['grant_type'] == 'authorization_code') {
        $server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));
    } else {
        $val_array = array(
            'client_secret' => OAUTH_CLIENT_SECRET
        );
        $clients = array(
            OAUTH_CLIENTID => $val_array
        );
        $credentials = array(
            'client_credentials' => $clients
        );
        $storage = new OAuth2\Storage\Memory($credentials);
        $server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));
    }
    $response = $server->handleTokenRequest(OAuth2\Request::createFromGlobals())->send('return');
    $_SERVER['REQUEST_METHOD'] = $old_server_method;
    if (!empty($old_content_type)) {
        $_SERVER['CONTENT_TYPE'] = $old_content_type;
    }
    return json_decode($response, true);
}
/**
 * To generate random string
 *
 * @param array  $arr_characters Random string options
 * @param string $length         Length of the random string
 *
 * @return string
 */
function getRandomStr($arr_characters, $length)
{
    $rand_str = '';
    $characters_length = count($arr_characters);
    for ($i = 0; $i < $length; ++$i) {
        $rand_str.= $arr_characters[rand(0, $characters_length - 1) ];
    }
    return $rand_str;
}
/**
 * To generate the encrypted password
 *
 * @param string $str String to be encrypted
 *
 * @return string
 */
function getCryptHash($str)
{
    $salt = '';
    if (CRYPT_BLOWFISH) {
        if (version_compare(PHP_VERSION, '5.3.7') >= 0) { // http://www.php.net/security/crypt_blowfish.php
            $algo_selector = '$2y$';
        } else {
            $algo_selector = '$2a$';
        }
        $workload_factor = '12$'; // (around 300ms on Core i7 machine)
        $val_arr = array(
            '.',
            '/'
        );
        $range1 = range('0', '9');
        $range2 = range('a', 'z');
        $range3 = range('A', 'Z');
        $res_arr = array_merge($val_arr, $range1, $range2, $range3);
        $salt = $algo_selector . $workload_factor . getRandomStr($res_arr, 22); // './0-9A-Za-z'
        
    } else if (CRYPT_MD5) {
        $algo_selector = '$1$';
        $char1 = chr(33);
        $char2 = chr(127);
        $range = range($char1, $char2);
        $salt = $algo_selector . getRandomStr($range, 12); // actually chr(0) - chr(255), but used ASCII only
        
    } else if (CRYPT_SHA512) {
        $algo_selector = '$6$';
        $workload_factor = 'rounds=5000$';
        $char1 = chr(33);
        $char2 = chr(127);
        $range = range($char1, $char2);
        $salt = $algo_selector . $workload_factor . getRandomStr($range, 16); // actually chr(0) - chr(255), but used ASCII only
        
    } else if (CRYPT_SHA256) {
        $algo_selector = '$5$';
        $workload_factor = 'rounds=5000$';
        $char1 = chr(33);
        $char2 = chr(127);
        $range = range($char1, $char2);
        $salt = $algo_selector . $workload_factor . getRandomStr($range, 16); // actually chr(0) - chr(255), but used ASCII only
        
    } else if (CRYPT_EXT_DES) {
        $algo_selector = '_';
        $val_arr = array(
            '.',
            '/'
        );
        $range1 = range('0', '9');
        $range2 = range('a', 'z');
        $range3 = range('A', 'Z');
        $res_arr = array_merge($val_arr, $range1, $range2, $range3);
        $salt = $algo_selector . getRandomStr($res_arr, 8); // './0-9A-Za-z'.
        
    } else if (CRYPT_STD_DES) {
        $algo_selector = '';
        $val_arr = array(
            '.',
            '/'
        );
        $range1 = range('0', '9');
        $range2 = range('a', 'z');
        $range3 = range('A', 'Z');
        $res_arr = array_merge($val_arr, $range1, $range2, $range3);
        $salt = $algo_selector . getRandomStr($res_arr, 2); // './0-9A-Za-z'
        
    }
    return crypt($str, $salt);
}
/**
 * Execute CURL Request
 *
 * @param string $url    URL
 * @param string $method optional Method of CURL default value : get
 * @param mixed  $post   optional CURL Values default value : array ()
 * @param string $format optional Format for values default value : plain
 *
 * @return mixed
 */
function curlExecute($url, $method = 'get', $post = array() , $format = 'plain')
{
    $filename = $return = $error = array();
    $mediadir = '';
    if ($format == 'image') {
        $mediadir = $post;
        if (!file_exists($mediadir)) {
            mkdir($mediadir, 0777, true);
        }
        $path = explode('/', $url);
        $filename['file_name'] = $path[count($path) - 1];
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    if ($format != 'image') {
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 300 seconds (5min)
        
    }
    if ($method == 'get') {
        curl_setopt($ch, CURLOPT_POST, false);
        if ($format == 'image') {
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
        }
    } elseif ($method == 'post') {
        if ($format == 'json') {
            $post_string = json_encode($post);
            $curl_opt = array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($post_string)
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_opt);
        } else {
            $post_string = http_build_query($post, '', '&');
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
    }
    $response = curl_exec($ch);
    if ($format == 'token') {
        return $response;
    }
    if ($format == 'image') {
        $info = curl_getinfo($ch);
        array_change_key_case($info);
        $content_type = explode('/', $info['content_type']);
        $extension = explode(';', $content_type[1]);
        $filename['extension'] = $extension[0];
        $filename['file_name'] = (strpos($filename['file_name'], '.') !== false) ? $filename['file_name'] : $filename['file_name'] . '.' . $content_type[1];
        $filename['file_name'] = preg_replace('/[^A-Za-z0-9\-.]/', '', $filename['file_name']);
        curl_close($ch);
        if (file_exists($mediadir . DIRECTORY_SEPARATOR . $filename['file_name'])) {
            unlink($mediadir . DIRECTORY_SEPARATOR . $filename['file_name']);
        }
        $fp = fopen($mediadir . DIRECTORY_SEPARATOR . $filename['file_name'], 'x');
        fwrite($fp, $response);
        fclose($fp);
        return $filename;
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        $return['error']['message'] = curl_error($ch);
        curl_close($ch);
        return $return;
    }
    switch ($http_code) {
    case 201:
    case 200:
        $return = json_decode($response, true);
        if ($return === null) {
            $error['error']['code'] = 1;
            $error['error']['message'] = 'Syntax error, malformed JSON';
            $return = $error;
        }
        break;

    case 401:
        $return['error']['code'] = 1;
        $return['error']['message'] = 'Unauthorized';
        break;

    default:
        $return['error']['code'] = 1;
        $return['error']['message'] = 'Not Found';
    }
    curl_close($ch);
    return $return;
}
/**
 * Post url by using CURL
 *
 * @param string $url    URL
 * @param array  $post   (optional) default value : array ()
 * @param string $format (optional) default value : plain
 *
 * @return mixed
 */
function doPost($url, $post = array() , $format = 'plain')
{
    return curlExecute($url, 'post', $post, $format);
}
/**
 * Get url by using CURL
 *
 * @param string $url URL
 *
 * @return mixed
 */
function doGet($url)
{
    $return = curlExecute($url);
    return $return;
}
/**
 * Record each activities
 *
 * @param integer $user_id     UserID
 * @param string  $comment     Comment to insert
 * @param string  $type        Type of the comment
 * @param array   $foreign_ids Optional default value : array ()
 * @param mixed   $revision    Optional default value : null
 * @param integer $foreign_id  Optional default value : null
 *
 * @return mixed
 */
function insertActivity($user_id, $comment, $type, $foreign_ids = array() , $revision = null, $foreign_id = null)
{
    global $r_debug, $db_lnk;
    $result = '';
    $fields = array(
        'created',
        'modified',
        'user_id',
        'comment',
        'type',
        'revisions'
    );
    $values = array(
        'now()',
        'now()',
        $user_id,
        $comment,
        $type,
        $revision
    );
    if ($foreign_id !== null) {
        array_push($fields, 'foreign_id');
        array_push($values, $foreign_id);
    }
    foreach ($foreign_ids as $key => $value) {
        if ($key != 'id' && $key != 'user_id') {
            array_push($fields, $key);
            if ($value === false) {
                array_push($values, 'false');
            } else {
                array_push($values, $value);
            }
        }
    }
    if (!empty($foreign_ids['board_id']) || !empty($foreign_ids['organization_id']) || !empty($foreign_ids['user_id'])) {
        $val = '';
        for ($i = 1, $len = count($values); $i <= $len; $i++) {
            $val.= '$' . $i;
            $val.= ($i != $len) ? ', ' : '';
        }
        $result = pg_query_params($db_lnk, 'INSERT INTO activities (' . implode(', ', $fields) . ') VALUES (' . $val . ') RETURNING *', $values);
    }
    $row = pg_fetch_assoc($result);
    $id_converted = base_convert($row['id'], 10, 36);
    $materialized_path = sprintf("%08s", $id_converted);
    $freshness_ts = date('Y-m-d h:i:s');
    $path = 'P' . $row['id'];
    $depth = 0;
    $qry_val_arr = array(
        $materialized_path,
        $path,
        $depth,
        $freshness_ts,
        $row['id']
    );
    $result = pg_query_params($db_lnk, 'UPDATE activities SET materialized_path = $1, path = $2, depth = $3, freshness_ts = $4 WHERE id = $5 RETURNING *', $qry_val_arr);
    $row = pg_fetch_assoc($result);
    $qry_val_arr = array(
        $row['id']
    );
    $s_row = pg_query_params($db_lnk, 'SELECT * FROM activities_listing WHERE id = $1', $qry_val_arr);
    $row = pg_fetch_assoc($s_row);
    return $row;
}
/**
 * Get difference between current and previous version
 *
 * @param string $from_text Original text
 * @param string $to_text   Changed text
 *
 * @return difference
 */
function getRevisiondifference($from_text, $to_text)
{
    // limit input
    if (!empty($from_text) && is_string($from_text)) {
        $from_text = substr($from_text, 0, 1024 * 100);
    } else {
        return false;
    }
    if (!empty($to_text) && is_string($to_text)) {
        $to_text = substr($to_text, 0, 1024 * 100);
    } else {
        return false;
    }
    $granularity = 2; // 0: Paragraph/lines, 1: Sentence, 2: Word, 3: Character
    $granularityStacks = array(
        FineDiff::$paragraphGranularity,
        FineDiff::$sentenceGranularity,
        FineDiff::$wordGranularity,
        FineDiff::$characterGranularity
    );
    $diff_opcodes = FineDiff::getDiffOpcodes($from_text, $to_text, $granularityStacks[$granularity]);
    $difference = FineDiff::renderDiffToHTMLFromOpcodes($from_text, $diff_opcodes);
    return $difference;
}
/**
 * Check the current url and method can access by the user
 *
 * @param string $r_request_method Optional default value : 'GET'
 * @param string $r_resource_cmd   Optional default value : '/users'
 * @param string $r_resource_vars  Resource variable
 * @param string $post_data        Post data
 *
 * @return true if links allowed false otherwise
 */
function checkAclLinks($r_request_method = 'GET', $r_resource_cmd = '/users', $r_resource_vars = array() , $post_data = array())
{
    global $r_debug, $db_lnk, $authUser;
    $role = 3; // Guest role id
    if (is_plugin_enabled('r_support_app')) {
        require_once APP_PATH . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'SupportApp' . DIRECTORY_SEPARATOR . 'functions.php';
        if (checkSupportAppEnabled($r_resource_vars)) {
            return true;
        }
    }
    if ($authUser) {
        $role = $authUser['role_id'];
        if ($authUser['role_id'] == 1) {
            return true;
        }
    }
    if (!empty($r_resource_vars['boards'])) {
        $qry_val_arr = array(
            $r_resource_vars['boards']
        );
        $board = executeQuery('SELECT board_visibility FROM boards WHERE id = $1', $qry_val_arr);
        if ($board['board_visibility'] == 2 && $r_request_method == 'GET') {
            return true;
        }
    }
    if (!empty($r_resource_vars['organizations'])) {
        $qry_val_arr = array(
            $r_resource_vars['organizations']
        );
        $organizations = executeQuery('SELECT organization_visibility FROM organizations WHERE id = $1', $qry_val_arr);
        if ($organizations['organization_visibility'] == 1 && $r_request_method == 'GET') {
            return true;
        }
    }
    $board_temp_arr = array(
        '/boards_users/?'
    );
    $organization_temp_arr = array(
        '/organizations_users/?'
    );
    $board_exception_arr = array(
        '/boards/?'
    );
    $board_exception_method_arr = array(
        'PUT'
    );
    $organization_exception_arr = array(
        '/organizations/?'
    );
    $organization_exception_method_arr = array(
        'DELETE',
        'PUT'
    );
    $board_star = true;
    $public_board_exception_url = array(
        '/boards/?/boards_stars/?',
        '/boards/?/boards_stars',
        '/boards/?/lists/?/cards/?/comments',
        '/boards/?/board_subscribers',
        '/boards/?/lists/?/list_subscribers',
        '/boards/?/lists/?/cards/?/card_subscribers'
    );
    if (in_array($r_resource_cmd, $public_board_exception_url)) {
        $board_star = false;
    }
    //temp fix
    if (((!empty($r_resource_vars['boards']) && (!in_array($r_resource_cmd, $board_exception_arr) || (in_array($r_resource_cmd, $board_exception_arr) && in_array($r_request_method, $board_exception_method_arr)))) || in_array($r_resource_cmd, $board_temp_arr)) && $board_star) {
        if ($r_request_method == 'PUT' && in_array($r_resource_cmd, $board_temp_arr)) {
            $r_resource_vars['boards'] = $post_data['board_id'];
        }
        $qry_val_arr = array(
            $r_resource_vars['boards'],
            $authUser['id']
        );
        $board_user_role_id = executeQuery('SELECT board_user_role_id FROM boards_users WHERE board_id = $1 AND user_id = $2', $qry_val_arr);
        $role = $board_user_role_id['board_user_role_id'];
        $qry_val_arr = array(
            $role,
            $r_request_method,
            $r_resource_cmd
        );
        $board_allowed_link = executeQuery('SELECT * FROM acl_board_links_listing WHERE board_user_role_id = $1 AND method = $2 AND url = $3', $qry_val_arr);
        if (empty($board_allowed_link)) {
            return false;
        }
    } else if (!empty($r_resource_vars['organizations']) && (!in_array($r_resource_cmd, $organization_exception_arr) || (in_array($r_resource_cmd, $organization_exception_arr) && in_array($r_request_method, $organization_exception_method_arr))) || in_array($r_resource_cmd, $organization_temp_arr)) {
        if ($r_request_method == 'PUT' && in_array($r_resource_cmd, $organization_temp_arr)) {
            $r_resource_vars['organizations'] = $post_data['organization_id'];
        }
        $qry_val_arr = array(
            $r_resource_vars['organizations'],
            $authUser['id']
        );
        $organization_user_role_id = executeQuery('SELECT organization_user_role_id FROM organizations_users WHERE organization_id = $1 AND user_id = $2', $qry_val_arr);
        $role = $organization_user_role_id['organization_user_role_id'];
        $qry_val_arr = array(
            $role,
            $r_request_method,
            $r_resource_cmd
        );
        $organization_allowed_link = executeQuery('SELECT * FROM acl_organization_links_listing WHERE organization_user_role_id = $1 AND method = $2 AND url = $3', $qry_val_arr);
        if (empty($organization_allowed_link)) {
            return false;
        }
    } else {
        if (!empty($r_request_method) && ($r_request_method === 'POST') && !empty($r_resource_cmd) && ($r_resource_cmd === '/settings')) {
            $r_request_method = 'GET';
        }
        if (!empty($r_request_method) && ($r_request_method === 'GET') && !empty($r_resource_cmd) && ($r_resource_cmd === '/users/?/activities')) {
            return true;
        }
        if (!empty($r_request_method) && ($r_request_method === 'PUT') && !empty($r_resource_cmd) && $r_resource_cmd === '/users/?' && !empty($post_data['last_activity_id'])) {
            return true;
        }
        $qry_val_arr = array(
            $role,
            $r_request_method,
            $r_resource_cmd
        );
        $allowed_link = executeQuery('SELECT * FROM acl_links_listing WHERE role_id = $1 AND method = $2 AND url = $3', $qry_val_arr);
        if (empty($allowed_link)) {
            return false;
        }
    }
    return true;
}
/**
 * To execute the query
 *
 * @param string $qry SQL query to execute
 * @param array  $arr Query values
 *
 * @return mixed query results
 */
function executeQuery($qry, $arr = array())
{
    global $db_lnk;
    $result = pg_query_params($db_lnk, $qry, $arr);
    if (pg_num_rows($result)) {
        return pg_fetch_assoc($result);
    } else {
        return false;
    }
}
/**
 * Common method to send mail
 *
 * @param string $template        Email template name
 * @param array  $replace_content Email content replace array
 * @param string $to              To email address
 * @param string $reply_to_mail   Reply to email address
 *
 * @return void
 */
function sendMail($template, $replace_content, $to, $reply_to_mail = '')
{
    global $r_debug, $db_lnk, $_server_domain_url;
    if (file_exists(SITE_URL_FOR_SHELL)) {
        include_once SITE_URL_FOR_SHELL;
    }
    $default_content = array(
        '##SITE_NAME##' => SITE_NAME,
        '##SITE_URL##' => $_server_domain_url,
        '##FROM_EMAIL##' => DEFAULT_FROM_EMAIL_ADDRESS,
        '##CONTACT_EMAIL##' => DEFAULT_CONTACT_EMAIL_ADDRESS
    );
    $qry_val_arr = array(
        $template
    );
    $emailFindReplace = array_merge($default_content, $replace_content);
    $templates = executeQuery('SELECT * FROM email_templates WHERE name = $1', $qry_val_arr);
    if ($templates) {
        $message = strtr($templates['email_text_content'], $emailFindReplace);
        $message.= '<div itemscope itemtype="http://schema.org/EmailMessage"><div itemprop="potentialAction" itemscope itemtype="http://schema.org/ViewAction"><link itemprop="target" href="' . $_server_domain_url . '"/><meta itemprop="name" content="View on Restyaboard"/></div><meta itemprop="description" content="View on Restyaboard"/></div>';
        $subject = strtr($templates['subject'], $emailFindReplace);
        $from_email = strtr($templates['from_email'], $emailFindReplace);
        $headers = 'From:' . $from_email . PHP_EOL;
        if (!empty($reply_to_mail)) {
            $headers.= 'Reply-To:' . $reply_to_mail . PHP_EOL;
        }
        $headers.= "MIME-Version: 1.0" . PHP_EOL;
        $headers.= "Content-Type: text/html; charset=UTF-8" . PHP_EOL;
        $headers.= "X-Mailer: Restyaboard (0.6.5; +http://restya.com/board)" . PHP_EOL;
        $headers.= "X-Auto-Response-Suppress: All" . PHP_EOL;
        $result = mail($to, $subject, $message, $headers, '-f' . DEFAULT_FROM_EMAIL_ADDRESS);
        if (R_DEBUG) {
            if (!$result) {
                $compose_string = 'F, ' . $from_email . ', ' . $to . ', ' . $subject;
            } else {
                $compose_string = 'S, ' . $from_email . ', ' . $to . ', ' . $subject;
            }
            error_log($compose_string, 3, APP_PATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'mail.log');
        }
    }
}
/**
 * Insert current access ip address into IPs table
 *
 * @return int IP id
 */
function saveIp()
{
    global $db_lnk;
    $qry_val_arr = array(
        $_SERVER['REMOTE_ADDR']
    );
    $ip_row = executeQuery('SELECT id FROM ips WHERE ip = $1', $qry_val_arr);
    if (!$ip_row) {
        $country_id = 0;
        $_geo = array();
        if (function_exists('geoip_record_by_name')) {
            $_geo = @geoip_record_by_name($_SERVER['REMOTE_ADDR']);
        }
        if (!empty($_geo)) {
            $qry_val_arr = array(
                $_geo['country_code']
            );
            $country_row = executeQuery('SELECT id FROM countries WHERE iso_alpha2 = $1', $qry_val_arr);
            if ($country_row) {
                $country_id = $country_row['id'];
            }
            $qry_val_arr = array(
                $_geo['region']
            );
            $state_row = executeQuery('SELECT id FROM states WHERE name = $1', $qry_val_arr);
            if (!$state_row) {
                $qry_val_arr = array(
                    $_geo['region'],
                    $country_id
                );
                $result = pg_query_params($db_lnk, 'INSERT INTO states (created, modified, name, country_id) VALUES (now(), now(), $1, $2) RETURNING id', $qry_val_arr);
                $state_row = pg_fetch_assoc($result);
            }
            $qry_val_arr = array(
                $_geo['city']
            );
            $city_row = executeQuery('SELECT id FROM cities WHERE name = $1', $qry_val_arr);
            if (!$city_row) {
                $qry_val_arr = array(
                    $_geo['city'],
                    $state_row['id'],
                    $country_id,
                    $_geo['latitude'],
                    $_geo['longitude']
                );
                $result = pg_query_params($db_lnk, 'INSERT INTO cities (created, modified, name, state_id, country_id, latitude, longitude) VALUES (now(), now(), $1, $2, $3, $4, $5) RETURNING id ', $qry_val_arr);
                $city_row = pg_fetch_assoc($result);
            }
        }
        $user_agent = !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $state_id = (!empty($state_row['id'])) ? $state_row['id'] : 0;
        $city_id = (!empty($city_row['id'])) ? $city_row['id'] : 0;
        $lat = (!empty($_geo['latitude'])) ? $_geo['latitude'] : 0.00;
        $lng = (!empty($_geo['longitude'])) ? $_geo['longitude'] : 0.00;
        $qry_val_arr = array(
            $_SERVER['REMOTE_ADDR'],
            gethostbyaddr($_SERVER['REMOTE_ADDR']) ,
            $city_id,
            $state_id,
            $country_id,
            $lat,
            $lng,
            $user_agent
        );
        $result = pg_query_params($db_lnk, 'INSERT INTO ips (created, modified, ip, host, city_id, state_id, country_id, latitude, longitude, user_agent) VALUES (now(), now(), $1, $2, $3, $4, $5, $6, $7, $8) RETURNING id', $qry_val_arr);
        $ip_row = pg_fetch_assoc($result);
    }
    return $ip_row['id'];
}
/**
 * Copy Card
 *
 * @param mixed   $cards        Card record array
 * @param integer $new_list_id  List id of the new card
 * @param string  $name         Card name
 * @param integer $new_board_id Board id of the new card
 *
 * @return void
 */
function copyCards($cards, $new_list_id, $name, $new_board_id = '')
{
    global $db_lnk, $authUser;
    $foreign_ids = $response = array();
    while ($card = pg_fetch_object($cards)) {
        $old_list_id = $card->list_id;
        $card->list_id = $new_list_id;
        $card_id = $card->id;
        if ($card->due_date === null) {
            unset($card->due_date);
        }
        $card_result = pg_execute_insert('cards', $card);
        if ($card_result) {
            $card_result = pg_fetch_assoc($card_result);
            $new_card_id = $card_result['id'];
            $foreign_ids['card_id'] = $new_card_id;
            $foreign_ids['board_id'] = $new_board_id;
            $foreign_ids['list_id'] = $new_list_id;
            $comment = sprintf(__l('##USER_NAME## added %s card to %s.') , $card_result['name'], $name);
            insertActivity($authUser['id'], $comment, 'add_card', $foreign_ids);
            // Copy card attachments
            $attachment_fields = 'list_id, card_id, name, path, mimetype';
            if (!empty($new_board_id)) {
                $attachment_fields = 'board_id, list_id, card_id, name, path, mimetype';
            }
            $qry_val_arr = array(
                $card_id
            );
            $attachments = pg_query_params($db_lnk, 'SELECT id, ' . $attachment_fields . ' FROM card_attachments WHERE card_id = $1 ORDER BY id', $qry_val_arr);
            if ($attachments && pg_num_rows($attachments)) {
                while ($attachment = pg_fetch_object($attachments)) {
                    $attachment->board_id = $new_board_id;
                    $attachment->list_id = $new_list_id;
                    $attachment->card_id = $new_card_id;
                    $attachment_result = pg_execute_insert('card_attachments', $attachment);
                    $attachment_result = pg_fetch_assoc($attachment_result);
                    $comment = __l('##USER_NAME## added attachment to this card ##CARD_LINK##');
                    insertActivity($authUser['id'], $comment, 'add_card_attachment', $foreign_ids, null, $attachment_result['id']);
                }
            }
            // Copy card comments
            $comment_fields = 'list_id, card_id, board_id, user_id, type, comment, root, freshness_ts, depth, path, materialized_path';
            $qry_val_arr = array(
                $card_id,
                'add_comment'
            );
            $comments = pg_query_params($db_lnk, 'SELECT id, ' . $comment_fields . ' FROM activities WHERE card_id = $1 AND type = $2 ORDER BY id', $qry_val_arr);
            if ($comments && pg_num_rows($comments)) {
                while ($comment = pg_fetch_object($comments)) {
                    $comment->board_id = $new_board_id;
                    $comment->list_id = $new_list_id;
                    $comment->card_id = $new_card_id;
                    pg_execute_insert('activities', $comment);
                }
            }
            // Copy checklists
            $checklist_fields = 'card_id, user_id, name, checklist_item_count, checklist_item_completed_count, position';
            $qry_val_arr = array(
                $card_id
            );
            $checklists = pg_query_params($db_lnk, 'SELECT id, ' . $checklist_fields . ' FROM checklists WHERE card_id = $1 ORDER BY id', $qry_val_arr);
            if ($checklists && pg_num_rows($checklists)) {
                while ($checklist = pg_fetch_object($checklists)) {
                    $checklist_id = $checklist->id;
                    $checklist->card_id = $new_card_id;
                    $checklist_result = pg_execute_insert('checklists', $checklist);
                    if ($checklist_result) {
                        $checklist_result = pg_fetch_assoc($checklist_result);
                        $new_checklist_id = $checklist_result['id'];
                        $comment = __l('##USER_NAME## added checklist to this card ##CARD_LINK##');
                        insertActivity($authUser['id'], $comment, 'add_card_checklist', $foreign_ids, '', $new_checklist_id);
                        $checklist_item_fields = 'card_id, checklist_id, user_id, name, position';
                        $qry_val_arr = array(
                            $checklist_id
                        );
                        $checklist_items = pg_query_params($db_lnk, 'SELECT id, ' . $checklist_item_fields . ' FROM checklist_items WHERE checklist_id = $1 ORDER BY id', $qry_val_arr);
                        if ($checklist_items && pg_num_rows($checklist_items)) {
                            while ($checklist_item = pg_fetch_object($checklist_items)) {
                                $checklist_item->card_id = $new_card_id;
                                $checklist_item->checklist_id = $new_checklist_id;
                                $checklist_item_result = pg_execute_insert('checklist_items', $checklist_item);
                                $checklist_item_result = pg_fetch_assoc($checklist_item_result);
                                $comment = __l('##USER_NAME## added checklist item to this card ##CARD_LINK##');
                                insertActivity($authUser['id'], $comment, 'add_checklist_item', $foreign_ids, '', $checklist_item_result['id']);
                            }
                        }
                    }
                }
            }
            // Copy card labels
            $cards_label_fields = 'list_id, card_id, board_id, label_id';
            if (!empty($new_board_id)) {
                $cards_label_fields = 'board_id, list_id, card_id, label_id';
            }
            $qry_val_arr = array(
                $card_id
            );
            $cards_labels = pg_query_params($db_lnk, 'SELECT id, ' . $cards_label_fields . ' FROM cards_labels WHERE card_id = $1 ORDER BY id', $qry_val_arr);
            if ($cards_labels && pg_num_rows($cards_labels)) {
                while ($cards_label = pg_fetch_object($cards_labels)) {
                    if (!empty($new_board_id)) {
                        $cards_label->board_id = $new_board_id;
                        $cards_label->list_id = $new_list_id;
                        $cards_label->card_id = $new_card_id;
                    }
                    pg_execute_insert('cards_labels', $cards_label);
                    $comment = __l('##USER_NAME## added label(s) to this card ##CARD_LINK## - ##LABEL_NAME##');
                    insertActivity($authUser['id'], $comment, 'add_card_label', $foreign_ids);
                }
            }
            // Copy card users
            $cards_user_fields = 'card_id, user_id';
            $qry_val_arr = array(
                $card_id
            );
            $cards_users = pg_query_params($db_lnk, 'SELECT id, ' . $cards_user_fields . ' FROM cards_users WHERE card_id = $1 ORDER BY id', $qry_val_arr);
            if ($cards_users && pg_num_rows($cards_users)) {
                while ($cards_user = pg_fetch_object($cards_users)) {
                    $cards_user->card_id = $new_card_id;
                    $cards_user_result = pg_execute_insert('cards_users', $cards_user);
                    $cards_user_result = pg_fetch_assoc($cards_user_result);
                    $qry_val_arr = array(
                        $cards_user->user_id
                    );
                    $_user = executeQuery('SELECT username FROM users WHERE id = $1', $qry_val_arr);
                    $comment = sprintf(__l('##USER_NAME## added %s as member to this card ##CARD_LINK##') , $_user['username']);
                    $response['activity'] = insertActivity($authUser['id'], $comment, 'add_card_user', $foreign_ids, '', $cards_user_result['id']);
                }
            }
            // Copy card custom fields
            $cards_custom_fields = 'list_id, card_id, board_id, custom_field_id';
            if (!empty($new_board_id)) {
                $cards_custom_fields = 'board_id, list_id, card_id, custom_field_id';
            }
            $qry_val_arr = array(
                $card_id
            );
            $cards_custom_field = pg_query_params($db_lnk, 'SELECT id, ' . $cards_custom_fields . ',value,is_active,value FROM cards_custom_fields WHERE card_id = $1 ORDER BY id', $qry_val_arr);
            if ($cards_custom_field && pg_num_rows($cards_custom_field)) {
                while ($cards_field = pg_fetch_object($cards_custom_field)) {
                    if (!empty($new_board_id)) {
                        $cards_field->board_id = $new_board_id;
                        $cards_field->list_id = $new_list_id;
                        $cards_field->card_id = $new_card_id;
                    }
                    pg_execute_insert('cards_custom_fields', $cards_field);
                    $comment = __l('##USER_NAME## added card custom field(s) to this card ##CARD_LINK## ');
                    insertActivity($authUser['id'], $comment, 'add_card_custom_field', $foreign_ids);
                }
            }
        }
    }
    return $response;
}
/**
 * To generate query by passed args and insert into table
 *
 * @param string  $table_name Table name to execute the query
 * @param mixed   $r_post     Values
 * @param integer $return_row Return rows
 *
 * @return mixed
 */
function pg_execute_insert($table_name, $r_post, $return_row = 1)
{
    global $db_lnk;
    $fields = 'created, modified';
    $values = 'now(), now()';
    $val_arr = array();
    $i = 1;
    foreach ($r_post as $key => $value) {
        if ($key != 'id') {
            $fields.= ', "' . $key . '"';
            $values.= ', $' . $i;
            if ($value === false) {
                $val_arr[] = 'false';
            } else if ($value === null) {
                $val_arr[] = null;
            } else {
                $val_arr[] = $value;
            }
            $i++;
        }
    }
    if (!empty($return_row)) {
        $row = pg_query_params($db_lnk, 'INSERT INTO ' . $table_name . ' (' . $fields . ') VALUES (' . $values . ') RETURNING *', $val_arr);
    } else {
        $row = pg_query_params($db_lnk, 'INSERT INTO ' . $table_name . ' (' . $fields . ') VALUES (' . $values . ')', $val_arr);
    }
    return $row;
}
/**
 * Common method to get binded values
 *
 * @param string $table Table name to get values
 * @param mixed  $data  Field list
 *
 * @return mixed
 */
function getbindValues($table, $data)
{
    global $db_lnk;
    $qry_val_arr = array(
        $table
    );
    $result = pg_query_params($db_lnk, 'SELECT * FROM information_schema.columns WHERE table_name = $1 ', $qry_val_arr);
    $bindValues = array();
    while ($field_details = pg_fetch_assoc($result)) {
        $field = $field_details['column_name'];
        $val_arr = array(
            'created',
            'modified'
        );
        if (in_array($field, $val_arr)) {
            continue;
        }
        //todo : get list_id from lists table
        if ($field == 'id' && $table == 'lists' && array_key_exists('list_id', $data)) {
            $bindValues['id'] = $data['list_id'];
        }
        if ($field == 'ip_id') {
            $data['ip'] = !empty($data['ip']) ? $data['ip'] : '';
            $ip_id = saveIp();
            $bindValues[$field] = $ip_id;
        } elseif (array_key_exists($field, $data)) {
            if ($field == 'is_active' || $field == 'is_allow_email_alias') {
                $boolean = !empty($data[$field]) ? 'true' : 'false';
                $bindValues[$field] = $boolean;
            } else if ($field == 'due_date' && $data[$field] == null) {
                $bindValues[$field] = null;
            } else {
                $bindValues[$field] = $data[$field];
            }
        }
    }
    return $bindValues;
}
/**
 * Create Trello member
 *
 * @param array $member member details
 * @param array $admin_user_id admin user ids
 * @param array $new_board newly created board details
 *
 * @return mixed
 */
function createTrelloMember($member = array() , $admin_user_id = array() , $new_board = array())
{
    global $r_debug, $db_lnk, $authUser, $_server_domain_url;
    $user_id = '';
    $qry_val_arr = array(
        utf8_decode($member['username'])
    );
    $userExist = executeQuery('SELECT * FROM users WHERE username = $1', $qry_val_arr);
    if (!$userExist) {
        $default_email_notification = 0;
        if (DEFAULT_EMAIL_NOTIFICATION === 'Periodically') {
            $default_email_notification = 1;
        } else if (DEFAULT_EMAIL_NOTIFICATION === 'Instantly') {
            $default_email_notification = 2;
        }
        $member['is_send_newsletter'] = $default_email_notification;
        $member['default_desktop_notification'] = (DEFAULT_DESKTOP_NOTIFICATION === 'Enabled') ? 'true' : 'false';
        $member['is_list_notifications_enabled'] = IS_LIST_NOTIFICATIONS_ENABLED;
        $member['is_card_notifications_enabled'] = IS_CARD_NOTIFICATIONS_ENABLED;
        $member['is_card_members_notifications_enabled'] = IS_CARD_MEMBERS_NOTIFICATIONS_ENABLED;
        $member['is_card_labels_notifications_enabled'] = IS_CARD_LABELS_NOTIFICATIONS_ENABLED;
        $member['is_card_checklists_notifications_enabled'] = IS_CARD_CHECKLISTS_NOTIFICATIONS_ENABLED;
        $member['is_card_attachments_notifications_enabled'] = IS_CARD_ATTACHMENTS_NOTIFICATIONS_ENABLED;
        $qry_val_arr = array(
            utf8_decode($member['username']) ,
            getCryptHash('restya') ,
            utf8_decode($member['initials']) ,
            utf8_decode($member['fullName']) ,
            $member['is_send_newsletter'],
            $member['default_desktop_notification'],
            $member['is_list_notifications_enabled'],
            $member['is_card_notifications_enabled'],
            $member['is_card_members_notifications_enabled'],
            $member['is_card_labels_notifications_enabled'],
            $member['is_card_checklists_notifications_enabled'],
            $member['is_card_attachments_notifications_enabled']
        );
        $user = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO users (created, modified, role_id, username, email, password, is_active, is_email_confirmed, initials, full_name, is_send_newsletter, default_desktop_notification, is_list_notifications_enabled, is_card_notifications_enabled, is_card_members_notifications_enabled, is_card_labels_notifications_enabled, is_card_checklists_notifications_enabled, is_card_attachments_notifications_enabled) VALUES (now(), now(), 2, $1, \'\', $2, true, true, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12) RETURNING id', $qry_val_arr));
        $user_id = $user['id'];
    } else {
        $user_id = $userExist['id'];
    }
    $board_user_role_id = 2;
    if (in_array($member['id'], $admin_user_id)) {
        $board_user_role_id = 1;
    }
    $query_val = array(
        $user_id,
        $new_board['id']
    );
    $is_board_user_exist = executeQuery('SELECT * FROM boards_users WHERE user_id = $1 and board_id = $2', $query_val);
    if (!$is_board_user_exist) {
        $qry_val_arr = array(
            $user_id,
            $new_board['id'],
            $board_user_role_id
        );
        pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO boards_users (created, modified, user_id, board_id, board_user_role_id) VALUES (now(), now(), $1, $2, $3) RETURNING id', $qry_val_arr));
    }
    $is_board_subscribers_exist = executeQuery('SELECT * FROM board_subscribers WHERE user_id = $1 and board_id = $2', $query_val);
    $auto_subscribe_on_board = (AUTO_SUBSCRIBE_ON_BOARD === 'Enabled') ? 'true' : false;
    if ($auto_subscribe_on_board && !$is_board_subscribers_exist) {
        $qry_val_arr = array(
            $user_id,
            $new_board['id'],
            true
        );
        pg_query_params($db_lnk, 'INSERT INTO board_subscribers (created, modified, user_id, board_id, is_subscribed) VALUES (now(), now(), $1, $2, $3)', $qry_val_arr);
    }
    return $user_id;
}
/**
 * Import Trello board
 *
 * @param array $board Boards from trello
 *
 * @return mixed
 */
function importTrelloBoard($board = array())
{
    global $r_debug, $db_lnk, $authUser, $_server_domain_url;
    $users = $lists = $cards = $cardLists = $listNames = array();
    if (!empty($board)) {
        $user_id = $authUser['id'];
        $board_visibility = 0;
        if ($board['prefs']['permissionLevel'] == 'public') {
            $board_visibility = 2;
        }
        $background_image = $background_pattern = '';
        if (!empty($board['prefs']['backgroundImage'])) {
            if ($board['prefs']['backgroundTile'] == 'true') {
                $background_pattern = $board['prefs']['backgroundImage'];
            } else {
                $background_image = $board['prefs']['backgroundImage'];
            }
        }
        $qry_val_arr = array(
            utf8_decode($board['name']) ,
            (!empty($board['prefs']['backgroundColor'])) ? $board['prefs']['backgroundColor'] : null,
            $background_image,
            $background_pattern,
            $user_id,
            $board_visibility
        );
        $new_board = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO boards (created, modified, name, background_color, background_picture_url, background_pattern_url, user_id, board_visibility) VALUES (now(), now(), $1, $2, $3, $4, $5, $6) RETURNING id', $qry_val_arr));
        $server = strtolower($_SERVER['SERVER_SOFTWARE']);
        if (strpos($server, 'apache') !== false) {
            ob_end_clean();
            header("Connection: close\r\n");
            header("Content-Encoding: none\r\n");
            ignore_user_abort(true); // optional
            ob_start();
            echo json_encode($new_board);
            $size = ob_get_length();
            header("Content-Length: $size");
            ob_end_flush(); // Strange behaviour, will not work
            flush(); // Unless both are called !
            ob_end_clean();
        } else {
            echo json_encode($new_board);
            fastcgi_finish_request();
        }
        $admin_user_id = array();
        if (!empty($board['members'])) {
            foreach ($board['memberships'] as $membership) {
                if ($membership['memberType'] == 'admin') {
                    $admin_user_id[] = $membership['idMember'];
                }
            }
        }
        if (!empty($board['labelNames'])) {
            foreach ($board['labelNames'] as $label) {
                if (!empty($label['name'])) {
                    $qry_val_arr = array(
                        utf8_decode($label['name'])
                    );
                    $check_label = executeQuery('SELECT id FROM labels WHERE name = $1', $qry_val_arr);
                    if (empty($check_label)) {
                        $qry_val_arr = array(
                            utf8_decode($label['name'])
                        );
                        $check_label = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO labels (created, modified, name) VALUES (now(), now(), $1) RETURNING id', $qry_val_arr));
                    }
                }
            }
        }
        if (!empty($board['members'])) {
            foreach ($board['members'] as $member) {
                $qry_val_arr = array(
                    utf8_decode($member['username'])
                );
                $userExist = executeQuery('SELECT * FROM users WHERE username = $1', $qry_val_arr);
                if (!$userExist) {
                    $default_email_notification = 0;
                    if (DEFAULT_EMAIL_NOTIFICATION === 'Periodically') {
                        $default_email_notification = 1;
                    } else if (DEFAULT_EMAIL_NOTIFICATION === 'Instantly') {
                        $default_email_notification = 2;
                    }
                    $member['is_send_newsletter'] = $default_email_notification;
                    $member['default_desktop_notification'] = (DEFAULT_DESKTOP_NOTIFICATION === 'Enabled') ? 'true' : 'false';
                    $member['is_list_notifications_enabled'] = IS_LIST_NOTIFICATIONS_ENABLED;
                    $member['is_card_notifications_enabled'] = IS_CARD_NOTIFICATIONS_ENABLED;
                    $member['is_card_members_notifications_enabled'] = IS_CARD_MEMBERS_NOTIFICATIONS_ENABLED;
                    $member['is_card_labels_notifications_enabled'] = IS_CARD_LABELS_NOTIFICATIONS_ENABLED;
                    $member['is_card_checklists_notifications_enabled'] = IS_CARD_CHECKLISTS_NOTIFICATIONS_ENABLED;
                    $member['is_card_attachments_notifications_enabled'] = IS_CARD_ATTACHMENTS_NOTIFICATIONS_ENABLED;
                    $qry_val_arr = array(
                        utf8_decode($member['username']) ,
                        getCryptHash('restya') ,
                        utf8_decode($member['initials']) ,
                        utf8_decode($member['fullName']) ,
                        $member['is_send_newsletter'],
                        $member['default_desktop_notification'],
                        $member['is_list_notifications_enabled'],
                        $member['is_card_notifications_enabled'],
                        $member['is_card_members_notifications_enabled'],
                        $member['is_card_labels_notifications_enabled'],
                        $member['is_card_checklists_notifications_enabled'],
                        $member['is_card_attachments_notifications_enabled']
                    );
                    $user = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO users (created, modified, role_id, username, email, password, is_active, is_email_confirmed, initials, full_name, is_send_newsletter, default_desktop_notification, is_list_notifications_enabled, is_card_notifications_enabled, is_card_members_notifications_enabled, is_card_labels_notifications_enabled, is_card_checklists_notifications_enabled, is_card_attachments_notifications_enabled) VALUES (now(), now(), 2, $1, \'\', $2, true, true, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12) RETURNING id', $qry_val_arr));
                    $users[$member['id']] = $user['id'];
                    if ($member['avatarUrl']) {
                        $mediadir = APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'User' . DIRECTORY_SEPARATOR . $user['id'];
                        $save_path = 'media' . DIRECTORY_SEPARATOR . 'User' . DIRECTORY_SEPARATOR . $user['id'];
                        $save_path = str_replace('\\', '/', $save_path);
                        $filename = curlExecute($member['avatarUrl'] . '/170.png', 'get', $mediadir, 'image');
                        $path = $save_path . DIRECTORY_SEPARATOR . $filename['file_name'];
                        $qry_val_arr = array(
                            $path,
                            $user['id']
                        );
                        pg_query_params($db_lnk, 'UPDATE users SET profile_picture_path = $1 WHERE id = $2', $qry_val_arr);
                    }
                } else {
                    $users[$member['id']] = $userExist['id'];
                }
                $board_user_role_id = 2;
                if (in_array($member['id'], $admin_user_id)) {
                    $board_user_role_id = 1;
                }
                $qry_val_arr = array(
                    $users[$member['id']],
                    $new_board['id'],
                    $board_user_role_id
                );
                pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO boards_users (created, modified, user_id, board_id, board_user_role_id) VALUES (now(), now(), $1, $2, $3) RETURNING id', $qry_val_arr));
                $auto_subscribe_on_board = (AUTO_SUBSCRIBE_ON_BOARD === 'Enabled') ? 'true' : false;
                if ($auto_subscribe_on_board) {
                    $qry_val_arr = array(
                        $users[$member['id']],
                        $new_board['id'],
                        true
                    );
                    pg_query_params($db_lnk, 'INSERT INTO board_subscribers (created, modified, user_id, board_id, is_subscribed) VALUES (now(), now(), $1, $2, $3)', $qry_val_arr);
                }
            }
        }
        $qry_val_arr = array(
            $authUser['id'],
            $new_board['id'],
            1
        );
        pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO boards_users (created, modified, user_id, board_id, board_user_role_id) VALUES (now(), now(), $1, $2, $3) RETURNING id', $qry_val_arr));
        $auto_subscribe_on_board = (AUTO_SUBSCRIBE_ON_BOARD === 'Enabled') ? 'true' : false;
        if ($auto_subscribe_on_board) {
            $qry_val_arr = array(
                $authUser['id'],
                $new_board['id'],
                true
            );
            pg_query_params($db_lnk, 'INSERT INTO board_subscribers (created, modified, user_id, board_id, is_subscribed) VALUES (now(), now(), $1, $2, $3)', $qry_val_arr);
        }
        if (!empty($board['lists'])) {
            $i = 0;
            foreach ($board['lists'] as $list) {
                $i+= 1;
                $is_closed = ($list['closed']) ? 'true' : 'false';
                $qry_val_arr = array(
                    utf8_decode($list['name']) ,
                    $new_board['id'],
                    $i,
                    $user_id,
                    $is_closed
                );
                $_list = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO lists (created, modified, name, board_id, position, user_id, is_archived) VALUES (now(), now(), $1, $2, $3, $4, $5) RETURNING id', $qry_val_arr));
                $lists[$list['id']] = $_list['id'];
                $listNames[$list['id']] = $list['name'];
            }
        }
        if (!empty($board['cards'])) {
            foreach ($board['cards'] as $card) {
                $is_closed = ($card['closed']) ? 'true' : 'false';
                $date = (!empty($card['due'])) ? $card['due'] : NULL;
                $qry_val_arr = array(
                    $new_board['id'],
                    $lists[$card['idList']],
                    utf8_decode($card['name']) ,
                    utf8_decode($card['desc']) ,
                    $is_closed,
                    $card['pos'],
                    $date,
                    $user_id
                );
                $_card = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO cards (created, modified, board_id, list_id, name, description, is_archived, position, due_date, user_id) VALUES (now(), now(), $1, $2, $3, $4, $5, $6, $7, $8) RETURNING id', $qry_val_arr));
                $cards[$card['id']] = $_card['id'];
                $cardLists[$card['id']] = $lists[$card['idList']];
                if (!empty($card['labels'])) {
                    foreach ($card['labels'] as $label) {
                        if (!empty($label['name'])) {
                            $qry_val_arr = array(
                                utf8_decode($label['name'])
                            );
                            $check_label = executeQuery('SELECT id FROM labels WHERE name = $1', $qry_val_arr);
                            if (empty($check_label)) {
                                $qry_val_arr = array(
                                    utf8_decode($label['name'])
                                );
                                $check_label = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO labels (created, modified, name) VALUES (now(), now(), $1) RETURNING id', $qry_val_arr));
                            }
                            $qry_val_arr = array(
                                $new_board['id'],
                                $lists[$card['idList']],
                                $_card['id'],
                                $check_label['id']
                            );
                            pg_query_params($db_lnk, 'INSERT INTO cards_labels (created, modified, board_id, list_id, card_id, label_id) VALUES (now(), now(), $1, $2, $3, $4)', $qry_val_arr);
                        }
                    }
                }
                if (!empty($card['attachments'])) {
                    foreach ($card['attachments'] as $attachment) {
                        $created = $modified = $attachment['date'];
                        if ($attachment['isUpload']) {
                            $mediadir = APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'Card' . DIRECTORY_SEPARATOR . $_card['id'];
                            $save_path = 'media' . DIRECTORY_SEPARATOR . 'Card' . DIRECTORY_SEPARATOR . $_card['id'];
                            $save_path = str_replace('\\', '/', $save_path);
                            $filename = curlExecute($attachment['url'], 'get', $mediadir, 'image');
                            $path = $save_path . DIRECTORY_SEPARATOR . $filename['file_name'];
                            $qry_val_arr = array(
                                $created,
                                $modified,
                                $new_board['id'],
                                $lists[$card['idList']],
                                $_card['id'],
                                $filename['file_name'],
                                $path,
                                $attachment['mimeType']
                            );
                            pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO card_attachments (created, modified, board_id, list_id, card_id, name, path, mimetype) VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING id', $qry_val_arr));
                        } else {
                            $qry_val_arr = array(
                                $created,
                                $modified,
                                $_card['id'],
                                $attachment['url'],
                                'NULL',
                                $lists[$card['idList']],
                                $new_board['id'],
                                'NULL',
                                $attachment['url']
                            );
                            pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO card_attachments (created, modified, card_id, name, path, list_id, board_id, mimetype, link) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9) RETURNING id', $qry_val_arr));
                        }
                    }
                }
                if (!empty($card['idMembersVoted'])) {
                    foreach ($card['idMembersVoted'] as $votedMemberId) {
                        $qry_val_arr = array(
                            $_card['id'],
                            $users[$votedMemberId]
                        );
                        pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO card_voters (created, modified, card_id, user_id) VALUES (now(), now(), $1, $2) RETURNING id', $qry_val_arr));
                    }
                }
                if (!empty($card['idMembers'])) {
                    foreach ($card['idMembers'] as $cardMemberId) {
                        $qry_val_arr = array(
                            $_card['id'],
                            $users[$cardMemberId]
                        );
                        pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO cards_users (created, modified, card_id, user_id) VALUES (now(), now(), $1, $2) RETURNING id', $qry_val_arr));
                    }
                }
            }
        }
        if (!empty($board['checklists'])) {
            $checklists = array();
            foreach ($board['checklists'] as $checklist) {
                $qry_val_arr = array(
                    utf8_decode($checklist['name']) ,
                    $checklist['pos'],
                    $cards[$checklist['idCard']],
                    $user_id
                );
                $_checklist = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO checklists (created, modified, name, position, card_id, user_id) VALUES (now(), now(), $1, $2, $3, $4) RETURNING id', $qry_val_arr));
                $checklists[$checklist['id']] = $_checklist['id'];
                if (!empty($checklist['checkItems'])) {
                    foreach ($checklist['checkItems'] as $checkItem) {
                        $is_completed = ($checkItem['state'] == 'complete') ? 'true' : 'false';
                        $qry_val_arr = array(
                            utf8_decode($checkItem['name']) ,
                            $checkItem['pos'],
                            $cards[$checklist['idCard']],
                            $_checklist['id'],
                            $is_completed,
                            $user_id
                        );
                        pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO checklist_items (created, modified, name, position, card_id, checklist_id, is_completed, user_id) VALUES (now(), now(), $1, $2, $3, $4, $5, $6) RETURNING id', $qry_val_arr));
                    }
                }
            }
        }
        if (!empty($board['actions'])) {
            $type = $comment = '';
            $board['actions'] = array_msort($board['actions'], array(
                'date' => SORT_ASC
            ));
            foreach ($board['actions'] as $action) {
                $type = '';
                if ($action['type'] == 'commentCard') {
                    $type = 'add_comment';
                    $comment = $action['data']['text'];
                } else if ($action['type'] == 'addMemberToCard' && !empty($action['member'])) {
                    $type = 'add_card_user';
                    $memberName = '';
                    if (!empty($action['member'])) {
                        $memberName = $action['member']['fullName'];
                    }
                    $comment = sprintf(__l('##USER_NAME## added %s as member to this card ##CARD_LINK##') , $memberName);
                } else if ($action['type'] == 'createCard') {
                    $type = 'add_card';
                    $listName = '';
                    if (!empty($listNames[$action['data']['list']['id']])) {
                        $listName = utf8_decode($listNames[$action['data']['list']['id']]);
                    }
                    $comment = sprintf(__l('##USER_NAME## added card ##CARD_LINK## to list %s. ') , $listName);
                } else if ($action['type'] == 'createList') {
                    $type = 'add_list';
                    $listName = '';
                    if (!empty($listNames[$action['data']['list']['id']])) {
                        $listName = utf8_decode($listNames[$action['data']['list']['id']]);
                    }
                    $comment = sprintf(__l('##USER_NAME## added list %s.') , $listName);
                } else if ($action['type'] == 'createBoard') {
                    $type = 'add_board';
                    $comment = __l('##USER_NAME## created board');
                } else if ($action['type'] == 'updateBoard') {
                    if (!empty($action['data']['board']['closed']) && isset($action['data']['board']['closed'])) {
                        $type = 'reopen_board';
                        $comment = __l('##USER_NAME## closed ##BOARD_NAME## board.');
                    } else if (!empty($action['data']['board']['closed'])) {
                        $type = 'reopen_board';
                        $comment = __l('##USER_NAME## reopened ##BOARD_NAME## board.');
                    } else if (!empty($action['data']['board']['prefs']['permissionLevel'])) {
                        $type = 'change_visibility';
                        $comment = sprintf(__l('##USER_NAME## changed visibility to %s') , $action['data']['board']['prefs']['permissionLevel']);
                    } else if (!empty($action['data']['board']['prefs']['background'])) {
                        $type = 'change_background';
                        $comment = sprintf(__l('##USER_NAME## changed backgound to board %s') , $action['data']['board']['prefs']['background']);
                    } else if (!empty($action['data']['board']['name'])) {
                        $type = 'edit_board';
                        $comment = __l('##USER_NAME## renamed ##BOARD_NAME## board.');
                    }
                } else if ($action['type'] == 'updateList') {
                    if (isset($action['data']['list']['closed'])) {
                        $type = 'archive_list';
                        $comment = __l('##USER_NAME## archived ##LIST_NAME##');
                    } else if (!empty($action['data']['list']['pos'])) {
                        $type = 'change_list_position';
                        $listName = '';
                        if (!empty($listNames[$action['data']['list']['id']])) {
                            $listName = utf8_decode($listNames[$action['data']['list']['id']]);
                        }
                        $comment = sprintf(__l('##USER_NAME## changed list %s position.') , $listName);
                    } else if (!empty($action['data']['list']['name'])) {
                        $type = 'edit_list';
                        $comment = '##USER_NAME## renamed this list.';
                    }
                } else if ($action['type'] == 'updateCard') {
                    if (!empty($action['data']['card']['pos'])) {
                        $type = 'change_card_position';
                        $comment = __l('##USER_NAME## moved this card to different position.');
                    } else if (!empty($action['data']['card']['idList'])) {
                        $type = 'moved_list_card';
                        $listBeforeName = '';
                        if (!empty($listNames[$action['data']['listBefore']['id']])) {
                            $listBeforeName = utf8_decode($listNames[$action['data']['listBefore']['id']]);
                        }
                        $listAfterName = '';
                        if (!empty($listNames[$action['data']['listAfter']['id']])) {
                            $listAfterName = utf8_decode($listNames[$action['data']['listAfter']['id']]);
                        }
                        $comment = sprintf(__l('##USER_NAME## moved cards FROM %s to  %s') , $listBeforeName, $listAfterName);
                    } else if (!empty($action['data']['card']['due'])) {
                        $type = 'add_card_duedate';
                        $comment = __l('##USER_NAME## SET due date to this card ##CARD_LINK##');
                    } else if (!empty($action['data']['card']['desc'])) {
                        $type = 'add_card_desc';
                        $comment = __l('##USER_NAME## added card description in ##CARD_LINK## - ##DESCRIPTION##');
                    } else if (!empty($action['data']['card']['name'])) {
                        $type = 'edit_card';
                        $comment = sprintf(__l('##USER_NAME## edited %s card in this board.') , utf8_decode($action['data']['card']['name']));
                    }
                } else if ($action['type'] == 'addChecklistToCard') {
                    $type = 'add_card_checklist';
                    $comment = __l('##USER_NAME## added checklist ##CHECKLIST_NAME## to this card ##CARD_LINK##');
                } else if ($action['type'] == 'deleteAttachmentFromCard') {
                    $type = 'delete_card_attachment';
                    $comment = __l('##USER_NAME## deleted attachment from card ##CARD_LINK##');
                } else if ($action['type'] == 'addAttachmentToCard') {
                    $type = 'add_card_attachment';
                    $comment = __l('##USER_NAME## added attachment to this card ##CARD_LINK##');
                } else if ($action['type'] == 'addMemberToBoard') {
                    $type = 'add_board_user';
                    $comment = __l('##USER_NAME## added member to board');
                } else if ($action['type'] == 'removeChecklistFromCard') {
                    $type = 'delete_checklist';
                    $comment = __l('##USER_NAME## deleted checklist ##CHECKLIST_NAME## from card ##CARD_LINK##');
                }
                if (!empty($type)) {
                    $comment = utf8_decode($comment);
                    $created = $modified = $action['date'];
                    if (!empty($action['data']['list']['id'])) {
                        if (array_key_exists($action['data']['list']['id'], $lists)) {
                            $lists_key = $lists[$action['data']['list']['id']];
                        } else {
                            $lists_key = '';
                        }
                    }
                    if (!empty($action['data']['card']['id'])) {
                        if (array_key_exists($action['data']['card']['id'], $cards)) {
                            $cards_key = $cards[$action['data']['card']['id']];
                            $lists_key = $cardLists[$action['data']['card']['id']];
                        } else {
                            $cards_key = '';
                        }
                    }
                    if (!array_key_exists($action['idMemberCreator'], $users) || empty($users[$action['idMemberCreator']])) {
                        if (!empty($action['memberCreator'])) {
                            $users[$action['idMemberCreator']] = createTrelloMember($action['memberCreator'], $admin_user_id, $new_board);
                        } else {
                            $users[$action['idMemberCreator']] = 1;
                        }
                    }
                    if (empty($lists_key) && empty($cards_key)) {
                        $qry_val_arr = array(
                            $created,
                            $modified,
                            $new_board['id'],
                            $users[$action['idMemberCreator']],
                            $type,
                            $comment
                        );
                        $activity = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO activities (created, modified, board_id, user_id, type, comment) VALUES ($1, $2, $3, $4, $5, $6) RETURNING id', $qry_val_arr));
                    } else if (!empty($lists_key) && empty($cards_key)) {
                        $qry_val_arr = array(
                            $created,
                            $modified,
                            $new_board['id'],
                            $lists_key,
                            $users[$action['idMemberCreator']],
                            $type,
                            $comment
                        );
                        $activity = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO activities (created, modified, board_id, list_id, user_id, type, comment) VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id', $qry_val_arr));
                    } else if (empty($lists_key) && !empty($cards_key)) {
                        $qry_val_arr = array(
                            $created,
                            $modified,
                            $new_board['id'],
                            $cards_key,
                            $users[$action['idMemberCreator']],
                            $type,
                            $comment
                        );
                        $activity = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO activities (created, modified, board_id, card_id, user_id, type, comment) VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id', $qry_val_arr));
                    } else if (!empty($lists_key) && !empty($cards_key)) {
                        $qry_val_arr = array(
                            $created,
                            $modified,
                            $new_board['id'],
                            $lists_key,
                            $cards_key,
                            $users[$action['idMemberCreator']],
                            $type,
                            $comment
                        );
                        $activity = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO activities (created, modified, board_id, list_id, card_id, user_id, type, comment) VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING id', $qry_val_arr));
                    }
                    if (!empty($activity)) {
                        $id_converted = base_convert($activity['id'], 10, 36);
                        $materialized_path = sprintf("%08s", $id_converted);
                        $path = 'P' . $activity['id'];
                        $depth = 0;
                        $root = $activity['id'];
                        $freshness_ts = $created;
                        $qry_val_arr = array(
                            $materialized_path,
                            $path,
                            $depth,
                            $root,
                            $freshness_ts,
                            $activity['id']
                        );
                        pg_query_params($db_lnk, 'UPDATE activities SET materialized_path = $1, path = $2, depth = $3, root = $4, freshness_ts = $5 WHERE id = $6', $qry_val_arr);
                    }
                }
            }
        }
        if (!empty($cards)) {
            foreach ($cards as $value) {
                $conditions = array(
                    $value
                );
                $activity_count = executeQuery("SELECT COUNT(id) as total_count FROM activities WHERE type = 'add_comment' AND card_id = $1", $conditions);
                $activity_count = (!empty($activity_count)) ? $activity_count['total_count'] : 0;
                $qry_val_arr = array(
                    $activity_count,
                    $value
                );
                pg_query_params($db_lnk, 'UPDATE cards SET comment_count = $1 WHERE id = $2', $qry_val_arr);
            }
        }
        return $new_board;
    }
}
/**
 * Import Wekan board
 *
 * @param array $board Boards from trello
 *
 * @return mixed
 */
function importWekanBoard($board = array())
{
    set_time_limit(1800);
    global $r_debug, $db_lnk, $authUser, $_server_domain_url;
    $lists_data = $comments_data = $user_data = $users = $lists = $cards = array();
    if (!empty($board)) {
        $user_id = $authUser['id'];
        $board_visibility = 0;
        if ($board['permission'] == 'public') {
            $board_visibility = 2;
        }
        $background_image = $background_pattern = '';
        if (!empty($board['backgroundImage'])) {
            if ($board['backgroundTile'] == 'true') {
                $background_pattern = $board['backgroundImage'];
            } else {
                $background_image = $board['backgroundImage'];
            }
        }
        //board Creation
        $qry_val_arr = array(
            utf8_decode($board['title']) ,
            $board['color'],
            $background_image,
            $background_pattern,
            $user_id,
            $board_visibility
        );
        $new_board = board_creation($qry_val_arr, $db_lnk);
        $server = strtolower($_SERVER['SERVER_SOFTWARE']);
        if (strpos($server, 'apache') !== false) {
            ob_end_clean();
            header("Connection: close\r\n");
            header("Content-Encoding: none\r\n");
            ignore_user_abort(true); // optional
            ob_start();
            echo json_encode($new_board);
            $size = ob_get_length();
            header("Content-Length: $size");
            ob_end_flush(); // Strange behaviour, will not work
            flush(); // Unless both are called !
            ob_end_clean();
        } else {
            echo json_encode($new_board);
            fastcgi_finish_request();
        }
        if (!empty($board['labels'])) {
            foreach ($board['labels'] as $label) {
                if (!empty($label['name'])) {
                    $qry_val_arr = array(
                        utf8_decode($label['name'])
                    );
                    $check_label = executeQuery('SELECT id FROM labels WHERE name = $1', $qry_val_arr);
                    if (empty($check_label)) {
                        $qry_val_arr = array(
                            utf8_decode($label['name'])
                        );
                        $check_label = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO labels (created, modified, name) VALUES (now(), now(), $1) RETURNING id', $qry_val_arr));
                    }
                }
            }
        }
        if (!empty($board['users'])) {
            foreach ($board['users'] as $member) {
                $wekan_user_id = $member['_id'];
                $qry_val_arr = array(
                    utf8_decode($member['username'])
                );
                $username = $member['username'];
                $userExist = executeQuery('SELECT * FROM users WHERE username = $1', $qry_val_arr);
                if (!$userExist) {
                    $default_email_notification = 0;
                    if (DEFAULT_EMAIL_NOTIFICATION === 'Periodically') {
                        $default_email_notification = 1;
                    } else if (DEFAULT_EMAIL_NOTIFICATION === 'Instantly') {
                        $default_email_notification = 2;
                    }
                    $member['is_send_newsletter'] = $default_email_notification;
                    $member['default_desktop_notification'] = (DEFAULT_DESKTOP_NOTIFICATION === 'Enabled') ? 'true' : 'false';
                    $member['is_list_notifications_enabled'] = IS_LIST_NOTIFICATIONS_ENABLED;
                    $member['is_card_notifications_enabled'] = IS_CARD_NOTIFICATIONS_ENABLED;
                    $member['is_card_members_notifications_enabled'] = IS_CARD_MEMBERS_NOTIFICATIONS_ENABLED;
                    $member['is_card_labels_notifications_enabled'] = IS_CARD_LABELS_NOTIFICATIONS_ENABLED;
                    $member['is_card_checklists_notifications_enabled'] = IS_CARD_CHECKLISTS_NOTIFICATIONS_ENABLED;
                    $member['is_card_attachments_notifications_enabled'] = IS_CARD_ATTACHMENTS_NOTIFICATIONS_ENABLED;
                    $qry_val_arr = array(
                        utf8_decode($member['username']) ,
                        getCryptHash('restya') ,
                        utf8_decode($username['0']) ,
                        utf8_decode($member['username']) ,
                        $member['is_send_newsletter'],
                        $member['default_desktop_notification'],
                        $member['is_list_notifications_enabled'],
                        $member['is_card_notifications_enabled'],
                        $member['is_card_members_notifications_enabled'],
                        $member['is_card_labels_notifications_enabled'],
                        $member['is_card_checklists_notifications_enabled'],
                        $member['is_card_attachments_notifications_enabled']
                    );
                    $user = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO users (created, modified, role_id, username, email, password, is_active, is_email_confirmed, initials, full_name, is_send_newsletter, default_desktop_notification, is_list_notifications_enabled, is_card_notifications_enabled, is_card_members_notifications_enabled, is_card_labels_notifications_enabled, is_card_checklists_notifications_enabled, is_card_attachments_notifications_enabled) VALUES (now(), now(), 2, $1, \'\', $2, true, true, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12) RETURNING id', $qry_val_arr));
                    $users[$wekan_user_id] = $user['id'];
                    $user_data[$wekan_user_id] = $username;
                } else {
                    $users[$wekan_user_id] = $userExist['id'];
                    $user_data[$wekan_user_id] = $username;
                }
                foreach ($board['members'] as $member) {
                    if ($wekan_user_id === $member['userId']) {
                        $board_user_role_id = 2;
                        if ($member['isAdmin'] === '1') {
                            $board_user_role_id = 1;
                        }
                        $qry_val_arr = array(
                            $users[$wekan_user_id],
                            $new_board['id'],
                            $board_user_role_id
                        );
                        pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO boards_users (created, modified, user_id, board_id, board_user_role_id) VALUES (now(), now(), $1, $2, $3) RETURNING id', $qry_val_arr));
                        $auto_subscribe_on_board = (AUTO_SUBSCRIBE_ON_BOARD === 'Enabled') ? 'true' : false;
                        if ($auto_subscribe_on_board) {
                            $qry_val_arr = array(
                                $users[$wekan_user_id],
                                $new_board['id'],
                                true
                            );
                            pg_query_params($db_lnk, 'INSERT INTO board_subscribers (created, modified, user_id, board_id, is_subscribed) VALUES (now(), now(), $1, $2, $3)', $qry_val_arr);
                        }
                    }
                }
            }
        }
        $qry_val_arr = array(
            $authUser['id'],
            $new_board['id'],
            1
        );
        pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO boards_users (created, modified, user_id, board_id, board_user_role_id) VALUES (now(), now(), $1, $2, $3) RETURNING id', $qry_val_arr));
        $auto_subscribe_on_board = (AUTO_SUBSCRIBE_ON_BOARD === 'Enabled') ? 'true' : false;
        if ($auto_subscribe_on_board) {
            $qry_val_arr = array(
                $authUser['id'],
                $new_board['id'],
                true
            );
            pg_query_params($db_lnk, 'INSERT INTO board_subscribers (created, modified, user_id, board_id, is_subscribed) VALUES (now(), now(), $1, $2, $3)', $qry_val_arr);
        }
        if (!empty($board['lists'])) {
            $i = 0;
            foreach ($board['lists'] as $list) {
                $i+= 1;
                $is_closed = ($list['archived']) ? 'true' : 'false';
                $qry_val_arr = array(
                    utf8_decode($list['title']) ,
                    $new_board['id'],
                    $i,
                    $user_id,
                    $is_closed
                );
                $_list = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO lists (created, modified, name, board_id, position, user_id, is_archived) VALUES (now(), now(), $1, $2, $3, $4, $5) RETURNING id', $qry_val_arr));
                $lists[$list['_id']] = $_list['id'];
                $lists_data[$list['_id']] = utf8_decode($list['title']);
            }
        }
        if (!empty($board['cards'])) {
            foreach ($board['cards'] as $card) {
                $is_closed = ($card['archived']) ? 'true' : 'false';
                $date = (!empty($card['dueAt'])) ? $card['dueAt'] : NULL;
                if (isset($card['description']) && !empty($card['description'])) {
                    $description = $card['description'];
                } else {
                    $description = '';
                }
                $qry_val_arr = array(
                    $new_board['id'],
                    $lists[$card['listId']],
                    utf8_decode($card['title']) ,
                    utf8_decode($description) ,
                    $is_closed,
                    $card['sort'],
                    $date,
                    $user_id
                );
                $_card = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO cards (created, modified, board_id, list_id, name, description, is_archived, position, due_date, user_id) VALUES (now(), now(), $1, $2, $3, $4, $5, $6, $7, $8) RETURNING id', $qry_val_arr));
                $cards[$card['_id']] = $_card['id'];
                if (!empty($card['labelIds'])) {
                    foreach ($card['labelIds'] as $label) {
                        foreach ($board['labels'] as $label_data) {
                            if ($label_data['_id'] === $label) {
                                if (!empty($label_data['name'])) {
                                    $qry_val_arr = array(
                                        utf8_decode($label_data['name'])
                                    );
                                    $check_label = executeQuery('SELECT id FROM labels WHERE name = $1', $qry_val_arr);
                                    $qry_val_arr = array(
                                        $new_board['id'],
                                        $lists[$card['listId']],
                                        $_card['id'],
                                        $check_label['id']
                                    );
                                    pg_query_params($db_lnk, 'INSERT INTO cards_labels (created, modified, board_id, list_id, card_id, label_id) VALUES (now(), now(), $1, $2, $3, $4)', $qry_val_arr);
                                }
                            }
                        }
                    }
                }
                if (!empty($board['attachments'])) {
                    foreach ($board['attachments'] as $attachment) {
                        if ($card['_id'] === $attachment['cardId']) {
                            $mediadir = APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'Card' . DIRECTORY_SEPARATOR . $_card['id'];
                            $save_path = 'media' . DIRECTORY_SEPARATOR . 'Card' . DIRECTORY_SEPARATOR . $_card['id'];
                            $save_path = str_replace('\\', '/', $save_path);
                            $filename = curlExecute($attachment['url'], 'get', $mediadir, 'image');
                            $path = $save_path . DIRECTORY_SEPARATOR . $filename['file_name'];
                            $created = $modified = date('Y-m-d h:i:s');
                            $qry_val_arr = array(
                                $created,
                                $modified,
                                $new_board['id'],
                                $lists[$card['listId']],
                                $cards[$card['_id']],
                                $filename['file_name'],
                                $path,
                                'image/jpeg'
                            );
                            pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO card_attachments (created, modified, board_id, list_id, card_id, name, path, mimetype) VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING id', $qry_val_arr));
                        }
                    }
                }
                if (!empty($card['members'])) {
                    foreach ($card['members'] as $cardMemberId) {
                        $qry_val_arr = array(
                            $_card['id'],
                            $users[$cardMemberId]
                        );
                        pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO cards_users (created, modified, card_id, user_id) VALUES (now(), now(), $1, $2) RETURNING id', $qry_val_arr));
                    }
                }
            }
        }
        if (!empty($board['comments'])) {
            foreach ($board['comments'] as $comment_datas) {
                $comments_data[$comment_datas['_id']] = $comment_datas['text'];
            }
        }
        if (!empty($board['activities'])) {
            $type = $comment = '';
            $board['activities'] = array_msort($board['activities'], array(
                'createdAt' => SORT_ASC
            ));
            foreach ($board['activities'] as $action) {
                if ($action['activityType'] == 'addComment') {
                    $type = 'add_comment';
                    $comment = $comments_data[$action['commentId']];
                } else if ($action['activityType'] == 'joinMember') {
                    $type = 'add_card_user';
                    $comment = sprintf(__l('##USER_NAME## added %s as member to this card ##CARD_LINK##') , utf8_decode($user_data[$action['memberId']]));
                } else if ($action['activityType'] == 'createCard') {
                    $type = 'add_card';
                    $comment = sprintf(__l('##USER_NAME## added card ##CARD_LINK## to list %s."') , utf8_decode($lists_data[$action['listId']]));
                } else if ($action['activityType'] == 'createList') {
                    $type = 'add_list';
                    $comment = sprintf(__l('##USER_NAME## added list %s.') , utf8_decode($lists_data[$action['listId']]));
                } else if ($action['activityType'] == 'createBoard') {
                    $type = 'add_board';
                    $comment = __l('##USER_NAME## created board');
                } else if ($action['activityType'] == 'archivedList') {
                    $type = 'archive_list';
                    $comment = __l('##USER_NAME## archived ##LIST_NAME##');
                } else if ($action['activityType'] == 'moveCard') {
                    $type = 'moved_list_card';
                    $comment = sprintf(__l('##USER_NAME## moved cards FROM %s to %s') , utf8_decode($lists_data[$action['listId']]) , utf8_decode($lists_data[$action['listId']]));
                } else if ($action['activityType'] == 'addAttachment') {
                    $type = 'add_card_attachment';
                    $comment = __l('##USER_NAME## added attachment to this card ##CARD_LINK##');
                } else if ($action['activityType'] == 'addBoardMember') {
                    $type = 'add_board_user';
                    $comment = __l('##USER_NAME## added member to board');
                }
                $comment = utf8_decode($comment);
                $created = $modified = $action['createdAt'];
                if (!empty($action['listId'])) {
                    if (array_key_exists($action['listId'], $lists)) {
                        $lists_key = $lists[$action['listId']];
                    } else {
                        $lists_key = '';
                    }
                }
                if (!empty($action['cardId'])) {
                    if (array_key_exists($action['cardId'], $cards)) {
                        $cards_key = $cards[$action['cardId']];
                    } else {
                        $cards_key = '';
                    }
                }
                if (empty($lists_key) && empty($cards_key)) {
                    $qry_val_arr = array(
                        $created,
                        $modified,
                        $new_board['id'],
                        $users[$action['userId']],
                        $type,
                        $comment
                    );
                    $activity = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO activities (created, modified, board_id, user_id, type, comment) VALUES ($1, $2, $3, $4, $5, $6) RETURNING id', $qry_val_arr));
                } else if (!empty($lists_key) && empty($cards_key)) {
                    $qry_val_arr = array(
                        $created,
                        $modified,
                        $new_board['id'],
                        $lists_key,
                        $users[$action['userId']],
                        $type,
                        $comment
                    );
                    $activity = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO activities (created, modified, board_id, list_id, user_id, type, comment) VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id', $qry_val_arr));
                } else if (empty($lists_key) && !empty($cards_key)) {
                    $qry_val_arr = array(
                        $created,
                        $modified,
                        $new_board['id'],
                        $cards_key,
                        $users[$action['userId']],
                        $type,
                        $comment
                    );
                    $activity = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO activities (created, modified, board_id, card_id, user_id, type, comment) VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id', $qry_val_arr));
                } else if (!empty($lists_key) && !empty($cards_key)) {
                    $qry_val_arr = array(
                        $created,
                        $modified,
                        $new_board['id'],
                        $lists_key,
                        $cards_key,
                        $users[$action['userId']],
                        $type,
                        $comment
                    );
                    $activity = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO activities (created, modified, board_id, list_id, card_id, user_id, type, comment) VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING id', $qry_val_arr));
                }
                if (!empty($activity)) {
                    $id_converted = base_convert($activity['id'], 10, 36);
                    $materialized_path = sprintf("%08s", $id_converted);
                    $path = 'P' . $activity['id'];
                    $depth = 0;
                    $root = $activity['id'];
                    $freshness_ts = $created;
                    $qry_val_arr = array(
                        $materialized_path,
                        $path,
                        $depth,
                        $root,
                        $freshness_ts,
                        $activity['id']
                    );
                    pg_query_params($db_lnk, 'UPDATE activities SET materialized_path = $1, path = $2, depth = $3, root = $4, freshness_ts = $5 WHERE id = $6', $qry_val_arr);
                    $qry_val_arr = array(
                        $freshness_ts,
                        $root
                    );
                    pg_query_params($db_lnk, 'UPDATE activities SET freshness_ts = $1 WHERE root = $2', $qry_val_arr);
                }
            }
        }
        return $new_board;
    }
}
/**
 * Email to name
 *
 * @param string $email Email
 *
 * @return string
 */
function email2name($email)
{
    $email = substr($email, 0, strrpos($email, '@'));
    // replace non-text
    $name = trim(ucwords(preg_replace('/[\W\d_]+/', ' ', strtolower($email))));
    // split by final space
    if (preg_match('/(.*)?\s(.*)$/', $name, $matches)) {
        $full_name = $matches[1] . ' ' . $matches[2];
    } else {
        $full_name = $name;
    }
    return $full_name;
}
/**
 * Find and replace comment variables
 *
 * @param array $activity is activity informations
 *
 * @return string
 */
function findAndReplaceVariables($activity)
{
    global $_server_domain_url;
    if (file_exists(SITE_URL_FOR_SHELL)) {
        include_once SITE_URL_FOR_SHELL;
    }
    $data = array(
        '##ORGANIZATION_LINK##' => $activity['organization_name'],
        '##CARD_LINK##' => '<a href="' . $_server_domain_url . '/#/board/' . $activity['board_id'] . '/card/' . $activity['card_id'] . '">' . $activity['card_name'] . '</a>',
        '##LABEL_NAME##' => $activity['label_name'],
        '##CARD_NAME##' => '<a href="' . $_server_domain_url . '/#/board/' . $activity['board_id'] . '/card/' . $activity['card_id'] . '">' . $activity['card_name'] . '</a>',
        '##DESCRIPTION##' => $activity['card_description'],
        '##LIST_NAME##' => $activity['list_name'],
        '##BOARD_NAME##' => '<a href="' . $_server_domain_url . '/#/board/' . $activity['board_id'] . '">' . $activity['board_name'] . '</a>',
        '##USER_NAME##' => '<strong>' . $activity['full_name'] . '</strong>',
        '##CHECKLIST_ITEM_NAME##' => $activity['checklist_item_name'],
        '##CHECKLIST_ITEM_PARENT_NAME##' => $activity['checklist_item_parent_name'],
        '##CHECKLIST_NAME##' => $activity['checklist_name']
    );
    $comment = strtr($activity['comment'], $data);
    return $comment;
}
/**
 * Common method to convert boolean values
 *
 * @param string $table Table name to get values
 * @param array  $row   Field list
 *
 * @return mixed
 */
function convertBooleanValues($table, $row)
{
    global $db_lnk;
    $qry_val_arr = array(
        $table
    );
    $result = pg_query_params($db_lnk, 'SELECT * FROM information_schema.columns WHERE table_name = $1 ', $qry_val_arr);
    while ($field_details = pg_fetch_assoc($result)) {
        if ($field_details['data_type'] == 'boolean') {
            $row[$field_details['column_name']] = ($row[$field_details['column_name']] == 'f') ? 0 : 1;
        }
    }
    return $row;
}
function paginate_data($c_sql, $db_lnk, $pg_params, $r_resource_filters)
{
    global $r_debug, $db_lnk, $authUser, $_server_domain_url;
    $c_result = pg_query_params($db_lnk, $c_sql, $pg_params);
    $c_data = pg_fetch_object($c_result, 0);
    $page = (isset($r_resource_filters['page']) && $r_resource_filters['page']) ? $r_resource_filters['page'] : 1;
    $page_count = PAGING_COUNT;
    if (!empty($limit) && $limit == 'all') {
        $page_count = $c_data->count;
    }
    $start = ($page - 1) * $page_count;
    $total_page = !empty($page_count) ? ceil($c_data->count / $page_count) : 0;
    $showing = (($start + $page_count) > $c_data->count) ? ($c_data->count - $start) : $page_count;
    $_metadata = array(
        'noOfPages' => $total_page,
        'total_records' => $c_data->count,
        'limit' => $page_count,
        'offset' => $start,
        'showing' => $showing,
        'maxSize' => 5
    );
    $sql = ' ';
    $arr['sql'] = $sql;
    $arr['_metadata'] = $_metadata;
    return $arr;
}
function update_query($table_name, $id, $r_resource_cmd, $r_put, $comment = '', $activity_type = '', $foreign_ids = '')
{
    global $r_debug, $db_lnk, $authUser, $_server_domain_url;
    $values = array(
        'now()'
    );
    $sfields = '';
    $fields = 'modified';
    if (!empty($table_name) && !empty($id)) {
        $put = getbindValues($table_name, $r_put);
        if ($table_name == 'users') {
            unset($put['ip_id']);
        }
        foreach ($put as $key => $value) {
            if ($key != 'id') {
                $fields.= ', ' . $key;
                if ($value === false) {
                    array_push($values, 'false');
                } elseif ($value === 'null' || $value === 'NULL' || $value === 'null') {
                    array_push($values, null);
                } else {
                    array_push($values, $value);
                }
            }
            if ($key != 'id') {
                $sfields.= (empty($sfields)) ? $key : ", " . $key;
            }
        }
        if (!empty($comment)) {
            $revision = '';
            if ($activity_type != 'reopen_board' && $activity_type != 'moved_list_card' && $activity_type != 'moved_card_checklist_item' && $activity_type != 'delete_organization_attachment' && $activity_type != 'move_card') {
                $qry_va_arr = array(
                    $id
                );
                $revisions['old_value'] = executeQuery('SELECT ' . $sfields . ' FROM ' . $table_name . ' WHERE id =  $1', $qry_va_arr);
                if (!empty($r_put['position'])) {
                    unset($r_put['position']);
                }
                if (!empty($r_put['id'])) {
                    unset($r_put['id']);
                }
                $revisions['new_value'] = $r_put;
                $revision = serialize($revisions);
            }
            $foreign_id = $id;
            if ($activity_type == 'moved_list_card' || $activity_type == 'move_card') {
                $foreign_id = $r_put['list_id'];
            }
            $response['activity'] = insertActivity($authUser['id'], $comment, $activity_type, $foreign_ids, $revision, $foreign_id);
            if (!empty($response['activity']['revisions']) && trim($response['activity']['revisions']) != '') {
                $revisions = unserialize($response['activity']['revisions']);
            }
            if (!empty($revisions) && $response['activity']['type'] != 'moved_card_checklist_item') {
                if (!empty($revisions['new_value'])) {
                    $bool = true;
                    foreach ($revisions['new_value'] as $key => $value) {
                        if ($key != 'is_archived' && $key != 'is_deleted' && $key != 'created' && $key != 'modified' && $key != 'is_offline' && $key != 'uuid' && $key != 'to_date' && $key != 'temp_id' && $activity_type != 'moved_card_checklist_item' && $activity_type != 'add_card_desc' && $activity_type != 'add_card_duedate' && $activity_type != 'delete_card_duedate' && $activity_type != 'add_background' && $activity_type != 'change_background' && $activity_type != 'change_visibility') {
                            $old_val = (isset($revisions['old_value'][$key])) ? $revisions['old_value'][$key] : '';
                            $new_val = (isset($revisions['new_value'][$key])) ? $revisions['new_value'][$key] : '';
                            $diff[] = nl2br(getRevisiondifference($old_val, $new_val));
                        }
                        if ($activity_type == 'add_card_desc' || $activity_type == 'edit_card_duedate' || $activity_type == 'add_background' || $activity_type == 'change_background' || $activity_type == 'change_visibility') {
                            $diff[] = $revisions['new_value'][$key];
                        }
                        $bool = false;
                    }
                    if ($bool && $activity_type == 'delete_card_comment') {
                        $old_val = (isset($revisions['old_value'])) ? $revisions['old_value'] : '';
                        $new_val = (isset($revisions['new_value'])) ? $revisions['new_value'] : '';
                        $diff[] = nl2br(getRevisiondifference($old_val, $new_val));
                    }
                } else if (!empty($revisions['old_value']) && isset($obj['type']) && $obj['type'] == 'delete_card_comment') {
                    $diff[] = nl2br(getRevisiondifference($revisions['old_value'], ''));
                }
            }
            if (isset($diff)) {
                $response['activity']['difference'] = $diff;
            }
            if (isset($r_put['description'])) {
                $response['activity']['description'] = $r_put['description'];
            }
        }
        if ($r_resource_cmd == '/users/?') {
            if (isset($r_put['is_active']) && $r_put['is_active'] == false) {
                executeQuery('SELECT username FROM users WHERE id =' . $id);
                // Todo handle with jaxl for ban_account
                
            }
        }
        if ($r_resource_cmd == '/boards_users/?') {
            if (is_plugin_enabled('r_chat')) {
                xmppChangeGrantMember($r_put);
            }
        }
        $val = '';
        for ($i = 1, $len = count($values); $i <= $len; $i++) {
            $val.= '$' . $i;
            $val.= ($i != $len) ? ', ' : '';
        }
        array_push($values, $id);
        $query = 'UPDATE ' . $table_name . ' SET (' . $fields . ') = (' . $val . ') WHERE id = ' . '$' . $i;
        if ($r_resource_cmd == '/boards/?/lists/?/cards') {
            $query = 'UPDATE ' . $table_name . ' SET (' . $fields . ') = (' . $val . ') WHERE list_id = ' . '$' . $i;
        }
        pg_query_params($db_lnk, $query, $values);
    }
    if (empty($response)) {
        $response = 'Success';
    }
    return $response;
}
function json_response($table_name, $r_resource_vars)
{
    global $r_debug, $db_lnk, $authUser, $_server_domain_url;
    if ($table_name == 'organizations') {
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM organizations_listing ul WHERE id = $1) as d ';
        array_push($pg_params, $r_resource_vars['organizations']);
    } elseif ($table_name == 'organizations_users') {
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM organizations_users_listing ul WHERE id = $1) as d ';
        array_push($pg_params, $r_resource_vars['organizations_users']);
    } elseif ($table_name == 'lists') {
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM lists_listing WHERE id = $1) as d ';
        array_push($pg_params, $r_resource_vars['lists']);
    } elseif ($table_name == 'cards' && !empty($r_resource_vars['cards'])) {
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM cards_listing WHERE id = $1) as d ';
        array_push($pg_params, $r_resource_vars['cards']);
    } elseif ($table_name == 'cards' && !empty($r_resource_vars['lists'])) {
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM cards_listing WHERE list_id = $1) as d ';
        array_push($pg_params, $r_resource_vars['lists']);
    }
    if ($result = pg_query_params($db_lnk, $sql, $pg_params)) {
        $count = pg_num_rows($result);
        $i = 0;
        while ($row = pg_fetch_row($result)) {
            if ($i == 0 && $count > 1) {
                echo '[';
            }
            echo $row[0];
            $i++;
            if ($i < $count) {
                echo ',';
            } else {
                if ($count > 1) {
                    echo ']';
                }
            }
        }
        pg_free_result($result);
    }
}
function slugify($string)
{
    $separator = '-';
    $length = 100;
    $string = mb_strtolower($string, 'UTF-8');
    $string = preg_replace('/[\.\!\$\*\(\)\=\{\}\[\]\;\|\%\#\/\?\:\s\&\+\`\^\'\"\,\>\<\@]/i', $separator, $string);
    $string = preg_replace('/' . preg_quote($separator) . '[' . preg_quote($separator) . ']*/', $separator, $string);
    if (strlen($string) > $length) {
        $string = substr($string, 0, $length);
    }
    $string = preg_replace('/' . preg_quote($separator) . '$/', '', $string);
    $string = preg_replace('/^' . preg_quote($separator) . '/', '', $string);
    return $string;
}
function is_plugin_enabled($plugin_name)
{
    $file = APP_PATH . '/client/apps/' . $plugin_name . '/app.json';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if ($data['enabled'] === true) {
            return true;
        }
    }
    return false;
}
function array_msort($array, $cols)
{
    $colarr = array();
    foreach ($cols as $col => $order) {
        $colarr[$col] = array();
        foreach ($array as $k => $row) {
            $colarr[$col]['_' . $k] = strtolower($row[$col]);
        }
    }
    $eval = 'array_multisort(';
    foreach ($cols as $col => $order) {
        $eval.= '$colarr[\'' . $col . '\'],' . $order . ',';
    }
    $eval = substr($eval, 0, -1) . ');';
    eval($eval);
    $ret = array();
    foreach ($colarr as $col => $arr) {
        foreach ($arr as $k => $v) {
            $k = substr($k, 1);
            if (!isset($ret[$k])) $ret[$k] = $array[$k];
            $ret[$k][$col] = $array[$k][$col];
        }
    }
    return $ret;
}
function board_creation($qry_val_arr = array() , $db_lnk)
{
    $new_board = pg_fetch_assoc(pg_query_params($db_lnk, 'INSERT INTO boards (created, modified, name, background_color, background_picture_url, background_pattern_url, user_id, board_visibility) VALUES (now(), now(), $1, $2, $3, $4, $5, $6) RETURNING id', $qry_val_arr));
    return $new_board;
}
/**
 * Generate client id
 *
 * @return client_id
 */
function isClientIdAvailable()
{
    do {
        $client_id = '';
        for ($i = 0; $i < 16; $i++) {
            $client_id.= mt_rand(0, 9);
        }
        $qry_val_arr = array(
            $client_id
        );
        $oauth_client = executeQuery('SELECT * FROM oauth_clients WHERE client_id = $1', $qry_val_arr);
    } while (!empty($oauth_client));
    return $client_id;
}
/**
 * Generate client secret
 *
 * @return client_secret
 */
function isClientSecretAvailable()
{
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
    do {
        $client_secret = '';
        for ($i = 0; $i < 26; $i++) {
            $client_secret.= $characters[mt_rand(0, strlen($characters) - 1) ];
        }
        $qry_val_arr = array(
            $client_secret
        );
        $oauth_client = executeQuery('SELECT * FROM oauth_clients WHERE client_secret = $1', $qry_val_arr);
    } while (!empty($oauth_client));
    return $client_secret;
}
/**
 * Multi language support
 *
 * @return translated text
 */
function __l($text)
{
    global $locales;
    if (!empty($locales) && !empty($locales[$text])) {
        return $locales[$text];
    }
    return $text;
}
