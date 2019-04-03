<?php
declare (strict_types = 1);

namespace App\Model;

/**
 * User.
 *
 * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
 * @since    v0.0.1
 * @version    v1.0.0    Monday, March 18th, 2019.
 * @global
 */
class User
{

    private static $aclJSON;
    private static $json_arr;

    public static $permissions = array(
        "cf" => "create_file",
        "rf" => "read_file",
        "uf" => "update_file",
        "df" => "delete_file",
        "cu" => "create_user",
        "ru" => "read_user",
        "uu" => "update_users_permissions",
        "du" => "delete_user",
    );
    /**
     * __construct.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Monday, March 18th, 2019.
     * @access    public
     * @return    void
     */
    public function __construct()
    {

        $this::$aclJSON = dirname(__DIR__) . DIRECTORY_SEPARATOR . "DB" . DIRECTORY_SEPARATOR . getenv('JSON_FILE');

        if (!file_exists($this::$aclJSON)) {
            throw new Exception('JSON File NOT FOUND!');
        }

        $this::$json_arr = $this->jsonToArray($this::$aclJSON);

    }

    /**
     * getUserFromJSON.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Wednesday, April 3rd, 2019.
     * @access    public
     * @param    string    $userInput
     * @return    array
     */
    public function getUserFromJSON(string $userInput): array
    {
        $userInfo = "";
        $userInfo_arr = array();
        $perms_arr = array();
        if (array_key_exists(convertToLowerCase($userInput), $this::$json_arr)) {
            $perms = $this::$json_arr[convertToLowerCase($userInput)];
            $perms_arr = $this->permCodeToHumanReadable($perms);
        }

        //$str = implode(", ", $perms_arr);

        if (count($perms_arr)) {
            //$userInfo = "User " . $userInput . " has the following " . count($perms_arr) . " permissions : " . rtrim($str, ', ');
            $userInfo = "User " . $userInput . " has the following " . count($perms_arr) . " permissions.";
            array_push($userInfo_arr, $userInfo, $perms_arr);
        } else {
            $userInfo = "User " . $userInput . " has no permissions!";
            array_push($userInfo_arr, $userInfo, $perms_arr);
        }

        return $userInfo_arr;

    }

    /**
     * listUserFromJSON.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Wednesday, April 3rd, 2019.
     * @access    public
     * @return    array
     */
    public function listUsersFromJSON(): array
    {
        /* $str = '';
        foreach ($this::$json_arr as $key => $val) {
        $str .= $key . ", ";
        }
        return array(count($this::$json_arr), rtrim($str, ', ')); */
        $userList_arr = array();
        $userList_arr = array_keys($this::$json_arr);

        return array(count($this::$json_arr), $userList_arr);
    }

    /**
     * appendUserToJSON.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Wednesday, April 3rd, 2019.
     * @access    public
     * @param    string    $userInput
     * @param    string    $permInput
     * @return    array
     */
    public function appendUserToJSON(string $userInput, string $permInput): array
    {
        $done = false;
        $error = "";
        $appendUserResponse = "";
        $appendUser_arr = array();
        $perms_arr = array();

        $output = array_merge($this::$json_arr, array($userInput => $permInput));

        try {
            file_put_contents($this::$aclJSON, json_encode($output, JSON_PRETTY_PRINT));
            $done = true;
            $error = "";

        } catch (Exception $e) {
            $msg = "Exception " . $e->getCode() . " / " . $e->getMessage();
            $done = false;
            $error = $msg;
        }

        if ($done) {
            $perms_arr = $this->permCodeToHumanReadable($permInput);
            /* $str = implode(", ", $perms_arr);
            if (count($perms_arr)) {
            $appendUserResponse = "User " . $userInput . " was added successfully with the following " . count($perms_arr) . " permissions : " . rtrim($str, ', ');
            } else {
            $appendUserResponse = "User " . $userInput . " was added successfully with no permissions!";
            } */
            if (count($perms_arr)) {
                $appendUserResponse = "User " . $userInput . " was added successfully with the following " . count($perms_arr) . " permissions.";
                array_push($appendUser_arr, $appendUserResponse, $perms_arr);
            } else {
                $appendUserResponse = "User " . $userInput . " was added successfully with no permissions!";
                array_push($appendUser_arr, $appendUserResponse, $perms_arr);
            }

        } else {
            $appendUserResponse = "User " . $userInput . " was NOT added. Server Error";
            array_push($appendUser_arr, $appendUserResponse);
        }

        return $appendUser_arr;

    }

    /**
     * updateUserInJSON.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Wednesday, April 3rd, 2019.
     * @access    public
     * @param    string    $userInput
     * @param    string    $permInput
     * @return    array
     */
    public function updateUserInJSON(string $userInput, string $permInput): array
    {
        $done = false;
        $error = "";
        $updateUser_arr = array();
        $perms_arr = array();
        $updateUserResponse = "";
        foreach ($this::$json_arr as $key => &$val) {
            if ($key == convertToLowerCase($userInput)) {
                if ($val == $permInput) {
                    $updateUserResponse = "Same permissions as before: " . $userInput . " was not updated";
                    array_push($updateUser_arr, $updateUserResponse, $perms_arr);
                    return $updateUser_arr;
                } else {
                    $val = $permInput;
                }
            }
        }

        try {
            file_put_contents($this::$aclJSON, json_encode($this::$json_arr, JSON_PRETTY_PRINT));
            $done = true;
            $error = "";

        } catch (Exception $e) {
            $msg = "Exception " . $e->getCode() . " / " . $e->getMessage();
            $done = false;
            $error = $msg;
        }

        if ($done) {
            $perms_arr = $this->permCodeToHumanReadable($permInput);

            if (count($perms_arr)) {
                $updateUserResponse = "User " . $userInput . " was updated successfully with the following " . count($perms_arr) . " permissions.";
                array_push($updateUser_arr, $updateUserResponse, $perms_arr);
            } else {
                $updateUserResponse = "User " . $userInput . " was updated successfully with no permissions!";
                array_push($updateUser_arr, $updateUserResponse, $perms_arr);
            }

        } else {
            $updateUserResponse = "User " . $userInput . " was NOT updated. Server Error";
            array_push($updateUser_arr, $updateUserResponse);
        }

        return $updateUser_arr;

    }

    /**
     * deleteUserFromJSON.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Wednesday, April 3rd, 2019.
     * @access    public
     * @param    string    $userInput
     * @return    string
     */
    public function deleteUserFromJSON(string $userInput): string
    {
        $done = false;
        $error = "";
        $deleteUserResponse = "";

        try {
            unset($this::$json_arr[$userInput]);
            file_put_contents($this::$aclJSON, json_encode($this::$json_arr, JSON_PRETTY_PRINT));
            $done = true;
            $error = "";

        } catch (Exception $e) {
            $msg = "Exception " . $e->getCode() . " / " . $e->getMessage();
            $done = false;
            $error = $msg;
        }

        if ($done) {
            $deleteUserResponse = "User " . $userInput . " was successfully removed!";
        } else {
            $deleteUserResponse = "";
        }

        return $deleteUserResponse;
    }

    /**
     * isRegistredUser.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @param    string    $user
     * @return    bool
     */
    public function isRegistredUser(string $user): bool
    {

        // if (array_search(convertToLowerCase($user), array_column($this::$json_arr, 'username')) !== false) {
        if (array_key_exists(convertToLowerCase($user), $this::$json_arr)) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * hasThePerm.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @param    string    $user
     * @param    string    $perm
     * @return    bool
     */
    public function hasThePerm(string $user, string $perm): bool
    {
        $perms_arr = array();
        if (array_key_exists(convertToLowerCase($user), $this::$json_arr)) {
            $perms = $this::$json_arr[convertToLowerCase($user)];
            $perms_arr = $this->permCodeToHumanReadable($perms);

            if (in_array($perm, $perms_arr)) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * permCodeToHumanReadable.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Wednesday, April 3rd, 2019.
     * @access    private
     * @param    string    $str
     * @return    array
     */
    private function permCodeToHumanReadable(string $str): array
    {
        $codes_arr = explode('-', $str);
        $perms_arr = array();
        foreach ($codes_arr as $code) {
            if (array_key_exists($code, $this::$permissions)) {
                $perms_arr[] = $this::$permissions[$code];
            }
        }
        return $perms_arr;
    }

    /**
     * jsonToArray.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Wednesday, April 3rd, 2019.
     * @access    private
     * @param    string    $file
     * @return    array
     */
    private function jsonToArray(string $file): array
    {
        $jsonFile = file_get_contents($file);
        $json_array = json_decode($jsonFile, true);
        if (is_array($json_array)) {
            return $json_array;
        }
        return array();
    }

}
