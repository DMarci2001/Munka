<?php
/**
 * Created by PhpStorm.
 * User: Janos
 * Date: 2018. 12. 19.
 * Time: 15:14
 */



/*

használat:
$downloader = new GoogleDriveDownloader();

File lista lekérdezése:
$files = $downloader->getFiles()
a fileok adatait egy tömbben kapod vissza, pl:
Array
(
    [0] => Array
        (
            [id] => 1QjTTBkhK-eoF3y1dhsCVv3y_O3ncHhmD
            [name] => asdfasdfasdf.pdf
            [createdtime] => 2018-12-21T08:46:01.833Z
            [modifiedtime] => 2018-11-23T12:25:59.000Z
        )

    [1] => Array
        (
            [id] => 14RfVVbyUY5Gk6cJxOgZREBHlYqz-rPwS
            [name] => teszt-zaro.pdf
            [createdtime] => 2018-12-21T08:46:57.002Z
            [modifiedtime] => 2018-11-23T12:24:33.000Z
        )

    [2] => Array
        (
            [id] => 1TKqDdigtBmEp5qIqWGOc4nl85OQ1gZLY
            [name] => beutalĂłDorgo.pdf
            [createdtime] => 2018-12-21T08:46:00.978Z
            [modifiedtime] => 2018-04-24T12:04:51.000Z
        )

    [3] => Array
        (
            [id] => 183WxzNHCtNI19zoNV7es9eFVo0K-c3IH
            [name] => beutalĂł.pdf
            [createdtime] => 2018-12-21T08:46:01.833Z
            [modifiedtime] => 2018-04-13T13:39:00.000Z
        )
)

Egy file letöltése
$fileName = $downloader->getFile($id)
pl: "/doc/drive/1TKqDdigtBmEp5qIqWGOc4nl85OQ1gZLY.pdf"

*/


//$downloader = new GoogleDriveDownloader();
//print_r($downloader->getFiles());
//print_r($downloader->getFile("1TKqDdigtBmEp5qIqWGOc4nl85OQ1gZLY"));


class GoogleDriveDownloader {
    private $driveDir = __DIR__."/../doc/drive";
    //ez a GDPR mappa id-je
    private $dirToDownload = "1f1AgXheQ7Dd5zLYwlLfoMf3H_dTZGnWx";
    private $clientSecretFile = __DIR__."/client_secret_584459797624-fku0d1423s66l1en52cihtpr024i1u0t.apps.googleusercontent.com.json";
    public $client;
    public $drive;

    public function __construct()
    {
        require_once __DIR__."/../../vendor/autoload.php";

        $this->client = $this->getClient();
        $this->drive = new Google_Service_Drive($this->client);
    }


    public function getFile($id)
    {
        $fileName = $this->driveDir."/{$id}.pdf";
        try {
            $response = $this->drive->files->get($id, array('alt' => 'media'));
        } catch (Exception $e) {
            return "404";
        }
        $content = $response->getBody()->getContents();
        file_put_contents($fileName,$content);
        return $fileName;
    }

    public function getFiles()
    {
        $optParams = array(
            'q' => "trashed = false and (mimeType contains 'pdf') and parents in '".$this->dirToDownload."'",
            'pageSize' => 1000,
            'fields' => 'nextPageToken, files(id, name, webContentLink, webViewLink, mimeType, createdTime, modifiedTime, parents)',
            'orderBy' => 'folder'
        );

        $results = $this->drive->files->listFiles($optParams);

        $files=[];

        foreach ($results->getFiles() as $f) {
            $file["id"] = $f->getId();
            $file["name"] = $f->getName();
            $file["createdtime"] = $f->getcreatedTime();
            $file["modifiedtime"] = $f->getmodifiedTime();

            $files[]=$file;
        }
        return $files;
    }

    public function getClient()
    {
        $client = new Google_Client();
        $client->setApplicationName('Hungariamed downloader');
        $client->addScope(Google_Service_Drive::DRIVE);
        $client->setAuthConfig($this->clientSecretFile);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        $tokenPath = $this->driveDir.'/token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();
                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));
                }
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
        return $client;
    }

}