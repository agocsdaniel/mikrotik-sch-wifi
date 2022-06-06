<?php

require('config.php');

class AuthSCHClient
{
    private static $host = "https://auth.sch.bme.hu/";
    private static $username = $oauth_id;  // your application's id
    private static $password = $oauth_secret;  // your application's password
    private static $scope = "basic+displayName+mail+linkedAccounts";      // wanted data, separated with plus sign. For more information see your website profile on auth.sch.bme.hu.

    private $tokens;

    public function __construct($tokens = null)
    {
        $this->tokens = new \stdClass();

        if ($tokens === null) {
            if (session_id() == '') {
                // session isn't started
                session_set_cookie_params(3600, "/");
                session_start();
            }
            if (!isset($_SESSION['tokens'])) {
                // auth token not exists

                // get tokens from auth.sch.bme.hu
                $this->authenticate();

                //save tokendata to session (if we did authentication -> we have refresh token)
                if (isset($this->tokens->refresh_token))
                    $_SESSION['tokens'] = serialize($this->tokens);
            } else {
                // load tokendata from session
                $this->tokens = unserialize($_SESSION['tokens']);
            }

            //refresh access token if it!s too old
            if ($this->tokens->lastUpdate + 3600 < time()) {
                $this->reauthenticate();
                $_SESSION['tokens'] = serialize($this->tokens);
            }
        } else {
            $this->tokens = $tokens;
        }
    }

    public function __destruct()
    {
        if (isset($this->tokens)) {
            unset ($this->tokens);
        }
    }

    private function curlExec($urlPart, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$host . $urlPart);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERPWD, self::$username . ":" . self::$password);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

    private function authenticate()
    {
        // before authentication & authorization
        if (!isset($_GET['code'])) {
            header("Location: " . self::$host . "site/login?response_type=code&client_id=" . self::$username . "&state=" . sha1($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']) . "&scope=" . self::$scope);
        } else {
            $data = array(
                'grant_type' => 'authorization_code',
                'code' => $_GET['code'],
            );

            $ch = $this->curlExec("oauth2/token", $data);
            $tokens = json_decode($ch);
            if ($tokens === null || !isset($tokens->access_token) || empty($tokens->access_token))
                throw new Exception ("invalid token data");

            $this->tokens = $tokens;
            $this->tokens->lastUpdate = time();
        }

    }

    private function reauthenticate()
    {
        $data = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->tokens->refresh_token,
        );

        $ch = $this->curlExec("oauth2/token", $data);
        $tokens = json_decode($ch);
        if ($tokens === null || !isset($tokens->access_token) || empty($tokens->access_token)) {
            throw new Exception ("invalid token data");
        }

        $this->tokens->access_token = $tokens->access_token;
    }

    public function getData()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$host . 'api/profile/?access_token=' . $this->tokens->access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        if (isset($response) && $response !== false && !empty($response)) {
            $data = json_decode($response);
            if ($data !== null) {
                return $data;
            } else {
                throw new Exception('invalid json');
            }
        } else {
            if (isset($_SESSION['tokens'])) {
                unset ($_SESSION['tokens']);
            }
            throw new Exception('invalid response');
        }
    }

}
