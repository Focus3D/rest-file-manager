<?php
//declare (strict_types = 1);
namespace App\Auth;

use App\Model\User;
use Firebase\JWT\JWT;

/**
 * Auth.
 *
 * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
 * @since    v0.0.1
 * @version    v1.0.0    Friday, March 29th, 2019.
 * @global
 */
class Auth
{
    public $response;
    public function __construct()
    {

        $this->user = new User;
    }

    /**
     * generateToken.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @param    string    $username
     * @return    string
     */
    public function generateToken(string $username): string
    {
        /*
        iss: The issuer of the token
        aud: The audience that the JWT is intended for
        iat: The timestamp when the JWT was created
        nbf: A "not process before" timestamp defining an allowed start time for processing
        exp: A timestamp defining an expiration time (end time) for the token
         */
        $token = array(
            "iss" => getenv('APP_ISS'),
            "aud" => getenv('APP_AUD'),
            "iat" => getenv('APP_IAT'),
            "nbf" => getenv('APP_NBF'),
            "exp" => time() + 3666600,
            "data" => array(
                "username" => $username,
            ),
        );
        $jwt = JWT::encode($token, getenv('APP_KEY'));

        return $jwt;
    }

    /**
     * validateToken.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @return    bool
     */
    public function validateToken(): bool
    {
        if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
            list($type, $data) = array_pad(explode(" ", $_SERVER["HTTP_AUTHORIZATION"], 2), 2, null);
            //list($type, $data) = explode(" ", $_SERVER["HTTP_AUTHORIZATION"], 2);
            if (strcasecmp($type, "Bearer") == 0) {
                try {
                    JWT::$leeway = 60; // $leeway in seconds for catching DomainException when token is incorrect
                    $decoded = JWT::decode($data, getenv('APP_KEY'), array('HS256'));
                } catch (\Exception $e) {
                    return false;
                }
                $username = $decoded->data->username;
                if (isset($username) && $this->user->isRegistredUser($username)) {
                    return true;
                } else {
                    return false;
                }

            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * getUsernameFromToken.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @return    string
     */
    public function getUsernameFromToken(): string
    {
        if ($this->validateToken()) {
            list($type, $data) = explode(" ", $_SERVER["HTTP_AUTHORIZATION"], 2);
            $decoded = JWT::decode($data, getenv('APP_KEY'), array('HS256'));
            $username = $decoded->data->username;
        }
        return $username;
    }

}
