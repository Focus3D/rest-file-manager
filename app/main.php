<?php
declare (strict_types = 1);

namespace App;

use App\Auth\Auth;
use App\Model\User;
use App\Response;
use SoftCreatR\MimeDetector\MimeDetector;
use SoftCreatR\MimeDetector\MimeDetectorException;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Main.
 *
 * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
 * @since    v0.0.1
 * @version    v1.0.0    Friday, March 29th, 2019.
 * @global
 */
class Main
{
    private $username;
    private $response;
    private $user;
    private $auth;
    private $filesystem;

    private static $current_dir_path;
    private static $uploadFolder;
    private static $tempFolder;
    private static $allowed_types;

    public function __construct()
    {

        $this::$allowed_types = explode(' ', getenv("ALLOWED_TYPES"));

        $this::$current_dir_path = getcwd();
        $this::$uploadFolder = $this::$current_dir_path . DIRECTORY_SEPARATOR . getenv('UPLOADS_FOLDER');
        $this::$tempFolder = $this::$uploadFolder . DIRECTORY_SEPARATOR . getenv('TEMP_FOLDER');

        $this->filesystem = new Filesystem();
        $this->response = new Response();
        $this->user = new User;
        $this->auth = new Auth;
        $this->username = $this->auth->getUsernameFromToken();

    }

    /**
     * showInfo.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @param    string    $data
     * @return    void
     */
    public function showInfo(string $data)
    {
        $this->checkUserAccess("read_file");

        $data = clean_input($data);

        if (!validate_required($data)) {
            $this->finalResponse(415, "You need to provide the File or Folder path in the url!");
        }

        if (!validate_alpha_dash_slash($data)) {
            $this->finalResponse(415, "You need to provide valide Path of a folder or a file!");
        }

        if (hasPathEndsWithFile($data)) {
            $srcPathLessFile = removeFilenameFromPath($data);
            $srcFilename = basename($data);
            $srcPathInput = $srcPathLessFile . DIRECTORY_SEPARATOR . $srcFilename;
        } else {
            $srcPathOnly = $data;
            $srcPathInput = $srcPathOnly;
        }

        $srcFullPath = $this::$uploadFolder . DIRECTORY_SEPARATOR . $srcPathInput;

        if (!$this->filesystem->exists($srcFullPath)) {
            $this->finalResponse(400, "File/Folder " . $srcPathInput . " does Not Exist");
        }

        if (is_dir($srcFullPath)) {
            $isDirEmpty = !(new \FilesystemIterator($srcFullPath))->valid();
            $pathInfo = array(
                "Path" => $srcPathInput,
                "Is Empty" => returnHumanReadableBoolean($isDirEmpty),
                "Created on" => @date("d M Y h:i:s A", filectime($srcFullPath)),
                "Last Accessed on" => @date("d M Y h:i:s A", fileatime($srcFullPath)),
                "Last Modified on" => @date("d M Y h:i:s A", filemtime($srcFullPath)),
                "Path Permissions" => getFilePerms($srcFullPath),
            );

            $this->finalResponse(200, "Folder Info", null, $pathInfo);

        } elseif (is_file($srcFullPath)) {

            $fileInfo = array(
                "Filename" => basename($srcFullPath),
                "Path" => pathinfo($srcPathInput, PATHINFO_DIRNAME),
                "File Type" => mime_content_type($srcFullPath),
                "FileSize" => FileSizeConvert($srcFullPath),
                "Last Accessed" => @date("d M Y h:i:s A", fileatime($srcFullPath)),
                "Last Modified on" => @date("d M Y h:i:s A", filemtime($srcFullPath)),
                "File Permissions" => getFilePerms($srcFullPath),
            );

            $this->finalResponse(200, "File Info", null, $fileInfo);
        }
    }

    /**
     * upload.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @return    void
     */
    public function upload()
    {
        $this->checkContentType("multipart/form-data", true);

        $this->checkUserAccess("create_file");

        if (empty($_FILES) || empty($_POST)) {
            $this->finalResponse(422, "Missing Informations");
        }

        if (!array_key_exists("file", $_FILES) || !array_key_exists("path", $_POST)) {
            $this->finalResponse(422, "Missing file Property");
        }

        if (count($_FILES) != 1) {
            $this->finalResponse(412, "Uploading Multiple Files is Not Allowed");
        }

        $srcPathInput = clean_input($_POST['path']);

        if (!validate_required($srcPathInput)) {
            $this->finalResponse(415, "You need to provide the Folder path!");
        }

        if (!validate_alpha_dash_slash($srcPathInput)) {
            $this->finalResponse(415, "You need to provide a valide source Path of a folder!");
        }

        if (hasPathEndsWithFile($srcPathInput)) {
            $this->finalResponse(400, $srcPathInput . " is not a Path!");
        }

        $srcFileInput = $_FILES['file'];

        if (!file_exists($srcFileInput['tmp_name']) || !is_uploaded_file($srcFileInput['tmp_name'])) {
            $this->finalResponse(412, "File Not uploaded");
        }

        $srcFileTmpInput = $srcFileInput['tmp_name'];
        $srcFileNameInput = filter_filename($srcFileInput['name']);
        $srcFileTypeInput = $srcFileInput['type'];

        //$allowed_types = array("image/jpeg", "image/gif", "image/png", "image/svg", "application/pdf");

        if (!in_array($srcFileTypeInput, $this::$allowed_types)) {
            $this->finalResponse(400, "Filetype Not Allowed");
        }

        $destPathOnly = $srcPathInput;
        $tempFilePath = $this::$tempFolder . DIRECTORY_SEPARATOR . $srcFileNameInput;
        $destPath = $this::$uploadFolder . DIRECTORY_SEPARATOR . $destPathOnly;
        $destFullPath = $destPath . DIRECTORY_SEPARATOR . $srcFileNameInput;

        if ($this->filesystem->exists($destFullPath)) {
            $this->finalResponse(400, "File Already exsits");
        }

        deleteDirectory($this::$tempFolder, true);

        try {
            $this->filesystem->copy($srcFileTmpInput, $tempFilePath);
        } catch (IOExceptionInterface $exception) {
            //dd($exception);
            deleteDirectory($this::$tempFolder, true);
            $this->finalResponse(500, "An error occured while trying to load the given file!");
        }

        $mimeDetector = new MimeDetector();

        try {
            $mimeDetector->setFile($tempFilePath);
        } catch (MimeDetectorException $exception) {
            deleteDirectory($this::$tempFolder, true);
            $this->finalResponse(500, "An error occured!");
        }

        $realMimeType = $mimeDetector->getFileType();

        if ($realMimeType["mime"] != $srcFileTypeInput) {
            deleteDirectory($this::$tempFolder, true);
            $this->finalResponse(400, "Filetype Not Conform");
        }

        try {
            $this->filesystem->copy($tempFilePath, $destFullPath);
        } catch (IOExceptionInterface $exception) {
            deleteDirectory($this::$tempFolder, true);
            $this->finalResponse(500, "Error creating directory at" . $exception);
        }

        deleteDirectory($this::$tempFolder, true);

        $this->finalResponse(200, "File " . $srcFileNameInput . " uploaded successfully to " . $destPathOnly);

    }

    /**
     * addFolder.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @return    void
     */
    public function addFolder()
    {
        $this->checkContentType();

        $this->checkUserAccess("create_file");

        $input = file_get_contents('php://input');
        $object = json_decode($input, true);

        if (!isset($object)) {
            $this->finalResponse(415, "no data");
        }

        $desiredKeys = array("path");

        $this->checkPathResponseData($object, $desiredKeys);

        $srcPathInput = clean_input($object['path']);

        if (!validate_required($srcPathInput)) {
            $this->finalResponse(415, "You need to provide a Folder path !");
        }

        if (!validate_alpha_dash_slash($srcPathInput)) {
            $this->finalResponse(415, "You need to provide a valide Path of a folder!");
        }

        if (hasPathEndsWithFile($srcPathInput)) {
            $this->finalResponse(400, $srcPathInput . " is not a Folder path!");
        }

        $srcFullPath = $this::$uploadFolder . DIRECTORY_SEPARATOR . $srcPathInput;

        $srcInfo = explode("/", $srcPathInput);
        $srcFolderInfo = array_pop($srcInfo);
        $srcFolderPathInfo = implode("/", $srcInfo);

        if ($this->filesystem->exists($srcFullPath)) {
            if (empty($srcFolderPathInfo)) {
                $this->finalResponse(400, $srcFolderInfo . " already Exists in root folder!");
            }
            $this->finalResponse(400, $srcFolderInfo . " already Exists in " . $srcFolderPathInfo);
        }

        //make a new directory
        try {
            $this->filesystem->mkdir($srcFullPath, 0775);
        } catch (IOExceptionInterface $exception) {
            $this->finalResponse(400, "Error creating directory");
        }

        $this->finalResponse(200, "Path " . $srcPathInput . " was successfully created!");

    }

    /**
     * rename.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @return    void
     */
    public function rename()
    {
        $this->checkContentType();
        $this->checkUserAccess("update_file");

        $input = file_get_contents('php://input');
        $object = json_decode($input, true);

        if (!isset($object)) {
            $this->finalResponse(415, "no data");
        }

        $desiredKeys = array("old_file_path", "new_file_path");

        $this->checkPathResponseData($object, $desiredKeys);

        $srcPathInput = clean_input($object['old_file_path']);

        if (!validate_required($srcPathInput)) {
            $this->finalResponse(415, "You need to provide the source File or Folder path!");
        }

        if (!validate_alpha_dash_slash($srcPathInput)) {
            $this->finalResponse(415, "You need to provide valide source Path of a folder or a file!");
        }

        if (hasPathEndsWithFile($srcPathInput)) {
            $srcPathLessFile = removeFilenameFromPath($srcPathInput);
            $srcPathOnly = $srcPathLessFile;
            $srcFilename = basename($srcPathInput);
            $srcPathInput = $srcPathOnly . DIRECTORY_SEPARATOR . $srcFilename;
        } else {
            $srcPathOnly = $srcPathInput;
            $srcPathInput = $srcPathOnly;
        }

        $srcFullPath = $this::$uploadFolder . DIRECTORY_SEPARATOR . $srcPathInput;

        if (!$this->filesystem->exists($srcFullPath)) {
            $this->finalResponse(400, "File/Folder " . $srcPathInput . " does Not Exist");
        }

        $destPathInput = clean_input($object['new_file_path']);

        if (!validate_required($destPathInput)) {
            $this->finalResponse(415, "You need to provide the target File or Folder path!");
        }

        if (!validate_alpha_dash_slash($destPathInput)) {
            $this->finalResponse(415, "You need to provide valide target Path of a folder or a file!");
        }

        if (hasPathEndsWithFile($destPathInput)) {
            $destPathLessFile = removeFilenameFromPath($destPathInput);
            $destPathOnly = $destPathLessFile;
            $destFilename = basename($destPathInput);
            $destPathInput = $destPathOnly . DIRECTORY_SEPARATOR . $destFilename;
        } else {
            $destPathOnly = $destPathInput;
            $destPathInput = $destPathOnly;
        }

        $destFullPath = $this::$uploadFolder . DIRECTORY_SEPARATOR . $destPathInput;

        if ($this->filesystem->exists($destFullPath)) {
            $this->finalResponse(400, "File/Folder " . $destPathInput . " already Exists!");
        }

        try {
            $this->filesystem->rename($srcFullPath, $destFullPath);
        } catch (IOExceptionInterface $exception) {
            $this->finalResponse(400, "Error renaming file or directory!");
        }

        $this->finalResponse(200, "Path " . $srcPathInput . " successfully renamed to " . $destPathInput);

    }

    /**
     * copy.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @return    void
     */
    public function copy()
    {
        $this->checkContentType();
        $this->checkUserAccess("update_file");

        $input = file_get_contents('php://input');
        $object = json_decode($input, true);

        if (!isset($object)) {
            $this->finalResponse(415, "no data");
        }

        $desiredKeys = array("source", "dest");

        $this->checkPathResponseData($object, $desiredKeys);

        $srcPathInput = clean_input($object['source']);

        if (!validate_required($srcPathInput)) {
            $this->finalResponse(415, "You need to provide the source File or Folder path!");
        }

        if (!validate_alpha_dash_slash($srcPathInput)) {
            $this->finalResponse(415, "You need to provide valide source Path of a folder or a file!");
        }

        if (!hasPathEndsWithFile($srcPathInput)) {
            $this->finalResponse(400, $srcPathInput . " does not contain a filename!");
        }

        $destPathInput = clean_input($object['dest']);

        if (!validate_required($destPathInput)) {
            $this->finalResponse(415, "You need to provide the target File or Folder path!");
        }

        if (!validate_alpha_dash_slash($destPathInput)) {
            $this->finalResponse(415, "You need to provide valide target Path of a folder or a file!");
        }

        if (hasPathEndsWithFile($destPathInput)) {
            $this->finalResponse(400, $destPathInput . " is not a Path of a folder!");
        }


        //$srcPathLessFile = pathinfo($srcPathInput, PATHINFO_DIRNAME);
        $srcPathOnly = removeFilenameFromPath($srcPathInput);
        //$srcFilename = substr(strrchr($srcPathInput, "/"), 1);
        $srcFilename = basename($srcPathInput);
        $srcPathInputResult = $srcPathOnly . DIRECTORY_SEPARATOR . $srcFilename;
        $srcFullPath = $this::$uploadFolder . DIRECTORY_SEPARATOR . $srcPathInputResult;

        if (!$this->filesystem->exists($srcFullPath)) {
            $this->finalResponse(400, "File " . $srcPathInputResult . " does Not Exist");
        }

        $destPathInput = $srcPathInputResult;
        $destFullPath = $this::$uploadFolder . DIRECTORY_SEPARATOR . $destPathInput;

        if (!$this->filesystem->exists($destFullPath)) {
            $this->finalResponse(400, "Path " . $destPathInput . " does Not Exist");
        }

        $destFullPathAndFile = $destFullPath . DIRECTORY_SEPARATOR . $srcFilename;

        if ($this->filesystem->exists($destFullPathAndFile)) {
            $this->finalResponse(400, "File " . $srcFilename . " already exists in folder " . $destPathInput);
        }

        try {

            $this->filesystem->copy($srcFullPath, $destFullPathAndFile);

        } catch (IOExceptionInterface $exception) {
            //dd($exception);
            $this->finalResponse(400, "Error copying file");
        }

        $this->finalResponse(200, "File " . $srcFilename . " copied successfully from " . $srcPathOnly . "  to " . $destPathInput);

    }

    /**
     * copyFolder.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @return    void
     */
    public function copyFolder()
    {
        $this->checkContentType();
        $this->checkUserAccess("update_file");

        $input = file_get_contents('php://input');
        $object = json_decode($input, true);

        if (!isset($object)) {
            $this->finalResponse(415, "no data");
        }

        $desiredKeys = array("source", "dest");

        $this->checkPathResponseData($object, $desiredKeys);


        $srcPathInput = clean_input($object['source']);

        if (!validate_required($srcPathInput)) {
            $this->finalResponse(415, "You need to provide the source File or Folder path!");
        }

        if (!validate_alpha_dash_slash($srcPathInput)) {
            $this->finalResponse(415, "You need to provide valide source Path of a folder or a file!");
        }

        if (hasPathEndsWithFile($srcPathInput)) {
            $this->finalResponse(400, $srcPathInput . " is not a Path!");
        }


        $destPathInput = clean_input($object['dest']);

        if (!validate_required($destPathInput)) {
            $this->finalResponse(415, "You need to provide the target File or Folder path!");
        }

        if (!validate_alpha_dash_slash($destPathInput)) {
            $this->finalResponse(415, "You need to provide valide target Path of a folder or a file!");
        }

        if (hasPathEndsWithFile($destPathInput)) {
            $this->finalResponse(400, $destPathInput . " is not a Path!");
        }


        $srcFullPath = $this::$uploadFolder . DIRECTORY_SEPARATOR . $srcPathInput;

        if (!$this->filesystem->exists($srcFullPath)) {
            $this->finalResponse(400, "Path " . $srcPathInput . " does Not Exist");
        }


        $destFullPath = $this::$uploadFolder . DIRECTORY_SEPARATOR . $destPathInput;

        if (!$this->filesystem->exists($destFullPath)) {
            $this->finalResponse(400, "Path " . $destPathInput . " does Not Exist");
        }

        try {
            $this->filesystem->mirror($srcFullPath, $destFullPath);
        } catch (IOExceptionInterface $exception) {
            //dd($exception);
            $this->finalResponse(400, "Error copying directory");
        }

        $this->finalResponse(200, "Content of Path " . $srcPathInput . " successfully copied to " . $destPathInput);

    }

    /**
     * delete.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @return    void
     */
    public function delete()
    {
        $this->checkContentType();
        $this->checkUserAccess("delete_file");

        $input = file_get_contents('php://input');
        $object = json_decode($input, true);

        if (!isset($object)) {
            $this->finalResponse(415, "no data");
        }

        $desiredKeys = array("path");

        $this->checkPathResponseData($object, $desiredKeys);


        $srcPathInput = clean_input($object['path']);

        if (!validate_required($srcPathInput)) {
            $this->finalResponse(415, "You need to provide the Folder path!");
        }

        if (!validate_alpha_dash_slash($srcPathInput)) {
            $this->finalResponse(415, "You need to provide a valide source Path of a folder or a file!");
        }

        if (hasPathEndsWithFile($srcPathInput)) {
            $srcPathLessFile = removeFilenameFromPath($srcPathInput);
            $srcPathOnly = $srcPathLessFile;
            //$srcFilename = substr(strrchr($srcPathInput, "/"), 1);
            $srcFilename = basename($srcPathInput);
            $srcPathInputResult = $srcPathOnly . DIRECTORY_SEPARATOR . $srcFilename;
        } else {
            $srcPathOnly = $srcPathInput;
            $srcPathInputResult = $srcPathOnly;
        }

        $srcFullPath = $this::$uploadFolder . DIRECTORY_SEPARATOR . $srcPathInputResult;

        if (!$this->filesystem->exists($srcFullPath)) {
            $this->finalResponse(400, "File/Folder " . $srcPathInputResult . " does Not Exist");
        }

        if (is_dir($srcFullPath)) {
            $isDirEmpty = !(new \FilesystemIterator($srcFullPath))->valid();
            if (!$isDirEmpty) {
                $this->finalResponse(400, "Path " . $srcPathInputResult . " Is Not Empty");
            }
            try {
                $this->filesystem->remove($srcFullPath);
            } catch (IOExceptionInterface $exception) {
                ($exception);
                $this->finalResponse(400, "Error deleting Folder");
            }
            $this->finalResponse(200, "Path " . $srcPathOnly . " was successfully deleted!");
        } elseif (is_file($srcFullPath)) {
            try {
                $this->filesystem->remove($srcFullPath);
            } catch (IOExceptionInterface $exception) {
                ($exception);
                $this->finalResponse(400, "Error deleting file");
            }
            $this->finalResponse(200, "File " . $srcFilename . " was successfully deleted!");
        }

    }

    /**
     * forceDelete.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @return    void
     */
    public function forceDelete()
    {
        $this->checkContentType();
        $this->checkUserAccess("delete_file");

        $input = file_get_contents('php://input');
        $object = json_decode($input, true);

        if (!isset($object)) {
            $this->finalResponse(415, "no data");
        }

        $desiredKeys = array("path");

        $this->checkPathResponseData($object, $desiredKeys);

        $srcPathFirstCleaning = clean_input($object['path']);

        $srcPathInput = clean_input($object['path']);

        if (!validate_required($srcPathInput)) {
            $this->finalResponse(415, "You need to provide the Folder path!");
        }

        if (!validate_alpha_dash_slash($srcPathInput)) {
            $this->finalResponse(415, "You need to provide a valide source Path of a folder or a file!");
        }

        if (hasPathEndsWithFile($srcPathInput)) {
            $srcPathLessFile = removeFilenameFromPath($srcPathInput);
            $srcPathOnly = $srcPathLessFile;
            //$srcFilename = substr(strrchr($srcPathInput, "/"), 1);
            $srcFilename = basename($srcPathInput);
            $srcPathInputResult = $srcPathOnly . DIRECTORY_SEPARATOR . $srcFilename;
        } else {
            $srcPathOnly = $srcPathInput;
            $srcPathInputResult = $srcPathOnly;
        }

        $srcFullPath = $this::$uploadFolder . DIRECTORY_SEPARATOR . $srcPathInputResult;

        if (!$this->filesystem->exists($srcFullPath)) {
            $this->finalResponse(400, "File/Folder " . $srcPathInputResult . " does Not Exist");
        }

        if (is_dir($srcFullPath)) {
            try {
                $this->filesystem->remove($srcFullPath);
            } catch (IOExceptionInterface $exception) {
                ($exception);
                $this->finalResponse(400, "Error deleting Folder");
            }
            $this->finalResponse(200, "Path " . $srcPathOnly . " was successfully deleted!");
        } elseif (is_file($srcFullPath)) {
            try {
                $this->filesystem->remove($srcFullPath);
            } catch (IOExceptionInterface $exception) {
                ($exception);
                $this->finalResponse(400, "Error deleting file");
            }
            $this->finalResponse(200, "File " . $srcFilename . " was successfully deleted!");
        }

    }

    /**
     * addUser.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Thursday, March 28th, 2019.
     * @access    public
     * @return    void
     */
    public function addUser()
    {
        $this->checkContentType();
        $this->checkUserAccess("create_user");

        $input = file_get_contents('php://input');
        $object = json_decode($input, true);
        $desiredKeys = array("username", "permissions_string");

        if (!isset($object)) {
            $this->finalResponse(415, "no data");
        }

        $this->checkUserResponseData($object, $desiredKeys);

        $userInput = clean_input($object['username']);
        $userInput = convertToLowerCase($userInput);

        if ($this->user->isRegistredUser($userInput)) {
            $this->finalResponse(400, "Username Not Available");
        }

        $permInput = clean_input($object['permissions_string']);

        if (!$this->checkPermInput($permInput)) {
            $this->finalResponse(400, "Permissions Not Accurate");
        }

        $appendUserResponse = $this->user->appendUserToJSON($userInput, $permInput);

        if ($appendUserResponse) {
            $token_generated = $this->auth->generateToken($userInput);

            return $this->finalResponse(200, $appendUserResponse[0], $token_generated, [], $appendUserResponse[1]);

        }
        $this->finalResponse(500, $appendUserResponse[0]);
    }

    /**
     * userInfo.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @param    string    $data
     * @return    void
     */
    public function userInfo(string $data)
    {
        $this->checkUserAccess("read_user");

        if (!isset($data)) {
            $this->finalResponse(415, "no data");
        }

        $data = trim($data, "/");
        $userInput = clean_input($data);

        if (!$this->user->isRegistredUser($userInput)) {
            $this->finalResponse(400, "Username Not Available");
        }

        $userInfo = $this->user->getUserFromJSON($userInput);

        $this->finalResponse(200, $userInfo[0], null, [], [], $userInfo[1]);

    }

    /**
     * listUsers.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @return    void
     */
    public function listUsers()
    {
        $this->checkUserAccess("read_user");

        $users_arr = $this->user->listUsersFromJSON();

        if (isset($users_arr[1])) {
            $this->finalResponse(200, "There are " . $users_arr[0] . " users.", null, [], [], $users_arr[1]);
        }

        $this->finalResponse(200, "There are " . $users_arr[0] . " users.");

    }

    /**
     * updateUser.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @return    void
     */
    public function updateUser()
    {
        $this->checkContentType();
        $this->checkUserAccess("update_users_permissions");

        $input = file_get_contents('php://input');
        $object = json_decode($input, true);
        $desiredKeys = array("username", "permissions_string");

        if (!isset($object)) {
            $this->finalResponse(415, "no data");
        }

        $this->checkUserResponseData($object, $desiredKeys);

        $userInput = clean_input($object['username']);
        $userInput = convertToLowerCase($userInput);

        if (!$this->user->isRegistredUser($userInput)) {
            $this->finalResponse(400, "Username Not Available");
        }

        $permInput = clean_input($object['permissions_string']);

        if (!$this->checkPermInput($permInput)) {
            $this->finalResponse(400, "Permissions Not Accurate");
        }

        $updateUserResponse = $this->user->updateUserInJSON($userInput, $permInput);

        if ($updateUserResponse) {
            return $this->finalResponse(200, $updateUserResponse[0], null, [], $updateUserResponse[1]);
        }

        $this->finalResponse(500, $updateUserResponse[0]);
    }

    /**
     * deleteUser.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    public
     * @return    void
     */
    public function deleteUser()
    {
        $this->checkContentType();
        $this->checkUserAccess("delete_user");

        $input = file_get_contents('php://input');
        $object = json_decode($input, true);
        $desiredKeys = array("username");

        if (!isset($object)) {
            $this->finalResponse(415, "no data");
        }

        $this->checkUserResponseData($object, $desiredKeys);

        $userInput = clean_input($object['username']);
        $userInput = convertToLowerCase($userInput);

        if (!$this->user->isRegistredUser($userInput)) {
            $this->finalResponse(400, "Username Not Available");
        }

        $deleteUserResponse = $this->user->deleteUserFromJSON($userInput);

        if ($deleteUserResponse) {
            $this->finalResponse(200, $deleteUserResponse);
        }
        $this->finalResponse(500, $deleteUserResponse);

    }

    /**
     * checkUserResponseData.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Monday, April 1st, 2019.
     * @access    private
     * @param    array    $object
     * @param    array    $desiredKeys
     * @return    void
     */
    private function checkUserResponseData(array $object, array $desiredKeys): void
    {

        if (!isArrayOfKeysExists($desiredKeys, $object)) {
            $this->finalResponse(400, "Missing Property");
        }

    }

    /**
     * checkPathResponseData.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Sunday, March 31st, 2019.
     * @access    private
     * @param    array      $object
     * @param    boolean    $checkPath      Default: false
     * @param    boolean    $checkSrcDst    Default: false
     * @param    boolean    $checkOldNew    Default: false
     * @return    void
     */
    private function checkPathResponseData(array $object, array $desiredKeys): void
    {

        if (!isArrayOfKeysExists($desiredKeys, $object)) {
            $this->finalResponse(400, "Missing Property");
        }

    }

    /**
     * checkContentType.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Sunday, March 31st, 2019.
     * @access    private
     * @param    string     $contenType    Default: "application/json"
     * @param    bool    $upload        Default: false
     * @return    void
     */
    private function checkContentType(string $contenType = "application/json", bool $upload = false)
    {

        if (!isset(getAllHeaders()["Content-Type"])) {
            $this->finalResponse(401, "Content-Type Missing");

        } else {

            $desiredHeader = getAllHeaders()["Content-Type"];

            if ($upload) {
                $data = explode(";", $desiredHeader);
                if (strcasecmp($data[0], $contenType) !== 0) {
                    $this->finalResponse(415, "Only form-data Allowed for Upload");
                }
            } else {
                if ($desiredHeader !== $contenType) {
                    $this->finalResponse(401, "Only JSON33 Allowed");
                }

            }

        }

    }

    /**
     * checkUserAccess.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    private
     * @param    string    $perm
     * @return    void
     */
    private function checkUserAccess(string $perm)
    {
        if (!$this->user->hasThePerm($this->username, $perm)) {
            $this->finalResponse(401, "Not Authorizated");
        }

    }

    /**
     * finalResponse.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Wednesday, April 3rd, 2019.
     * @access    private
     * @param    int       $status
     * @param    string    $content
     * @param    string    $apiKey          Default: null
     * @param    array     $filePathInfo    Default: []
     * @param    array     $userPermInfo    Default: []
     * @param    array     $userListInfo    Default: []
     * @return    void
     */
    private function finalResponse(int $status, string $content, string $apiKey = null, array $filePathInfo = [], array $userPermInfo = [], array $userListInfo = [])
    {
        $this->response->setStatus($status);

        if ($apiKey) {
            $this->response->setUserCred($apiKey);
        }

        if ($filePathInfo) {
            $this->response->setFilePathInfo($filePathInfo);
        }

        if ($userPermInfo) {
            $this->response->setUserPermInfo($userPermInfo);
        }

        if ($userListInfo) {
            $this->response->setUserListInfo($userListInfo);
        }

        $this->response->setContent($content);
        $this->response->finish();
    }

    /**
     * checkPermInput.
     *
     * @author    Mohamed LAMGOUNI <focus3d.ro@gmail.com>
     * @since    v0.0.1
     * @version    v1.0.0    Friday, March 29th, 2019.
     * @access    private
     * @param    string    $permInput
     * @return    void
     */
    private function checkPermInput(string $permInput)
    {
        $perms_input = explode('-', $permInput);

        if (count($perms_input) != 8) {
            return false;
        }

        $target_arr = explode('-', 'cf-rf-uf-df-cu-ru-uu-du');

        foreach ($perms_input as $key => $val) {
            foreach ($target_arr as $prop => $data) {
                if ($key == $prop) {
                    if ($val != $data && $val != 'xx') {
                        return false;
                    }
                }
            }
        }
        return true;
    }

}
