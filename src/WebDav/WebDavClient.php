<?php
/**
 * File: WebDavClient.php
 *
 * @since 2024-12-19
 * @license GPL-3.0-or-later
 *
 * @package contelli\webdav-sync
 */

namespace WebDav\WebDav;

use DOMDocument;

/**
 * Class WebDavClient
 *
 * This class provides functionalities to interact with a WebDAV server including uploading, downloading,
 * deleting files, reading directory contents, and creating directories.
 */
class WebDavClient
{
    private string $webdav_url;
    private string $webdav_user;
    private string $webdav_pass;

    /**
     * Constructor for the class.
     *
     * @param string $webdav_url The URL for WebDav connection.
     * @param string $webdav_user The username for WebDav connection.
     * @param string $webdav_pass The password for WebDav connection.
     * @return void
     */
    public function __construct( string $webdav_url, string $webdav_user, string $webdav_pass) {
        $this->webdav_url = $webdav_url;
        $this->webdav_user = $webdav_user;
        $this->webdav_pass = $webdav_pass;
    }

    /**
     * Uploads a file to the specified target directory using WebDav protocol.
     *
     * @param string $file_source The path to the file to be uploaded.
     * @param string $target_dir The target directory where the file will be uploaded.
     * @return bool Returns true if the file was successfully uploaded, false otherwise.
     */
    public function upload_file( string $file_source, string $target_dir) : bool {
        if (!$this->create_dir($target_dir)) {
            return false;
        }

        $file_content = file_get_contents( $file_source );
        if ( $file_content === false ) {
            return false;
        }


        $filename = basename( $file_source );

        $webdav_url =$this->webdav_url . '/' . rawurlencode($target_dir . '/' . $filename) ;

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $webdav_url );
        curl_setopt( $ch, CURLOPT_USERPWD, "$this->webdav_user:$this->webdav_pass" );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "PUT" );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $file_content );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        $response = curl_exec( $ch );
        $http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        if ( $http_status !== 201 && $http_status !== 204 ) {
            return false;
        }

        return true;
    }

    /**
     * Downloads a file from the remote location to the local storage directory.
     *
     * @param string $remote_file The name of the file to be downloaded from the remote location.
     * @param string $local_storage_dir The local directory where the file will be stored.
     * @return bool Returns true if the file is downloaded successfully, false otherwise.
     */
    public function download_file( string $remote_file, string $local_storage_dir) : bool {
        $webdav_url = $this->webdav_url . rawurlencode($remote_file);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webdav_url);
        curl_setopt( $ch, CURLOPT_USERPWD, "$this->webdav_user:$this->webdav_pass" );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Für self-signed Zertifikate, falls nötig

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status === 200) {
            file_put_contents($local_storage_dir . '/' .basename($remote_file), $response);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Deletes a file from the remote server via WebDav connection.
     *
     * @param string $remote_file The file to be deleted on the remote server.
     * @return bool Returns true if the file was successfully deleted, false otherwise.
     */
    public function delete( string $remote_file) : bool {
        $webdav_url = $this->webdav_url . '/' . rawurlencode($remote_file);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webdav_url);
        curl_setopt( $ch, CURLOPT_USERPWD, "$this->webdav_user:$this->webdav_pass" );
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status === 204) {
            return true;
            echo "Datei erfolgreich gelöscht: $remote_file_path\n";
        }
        return false;
    }

    /**
     * Read the contents of a directory via WebDav connection.
     *
     * @param string $directory The directory to read contents from.
     * @return array|null An array containing information about the directory contents,
     *                    or null if unable to retrieve contents.
     */
    public function read_contents( string $directory) : ?array {
        $webdav_url =$this->webdav_url . '/' . rawurlencode($directory) ;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webdav_url);
        curl_setopt( $ch, CURLOPT_USERPWD, "$this->webdav_user:$this->webdav_pass" );
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PROPFIND");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Depth: 1"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status !== 207) {
            return null;
        }


        $dom = new DOMDocument();
        if (!$dom->loadXML($response)) {
            return null;
        }

        $responses = $dom->getElementsByTagName('response');

        $contents = array();
        foreach ($responses as $response) {
            $href = $response->getElementsByTagName('href')->item(0)->nodeValue;
            $name = urldecode(basename($href));
            $is_collection = false;

            $resourcetype = $response->getElementsByTagName('resourcetype')->item(0);
            if ($resourcetype) {
                $collection = $resourcetype->getElementsByTagName('collection')->item(0);
                $is_collection = $collection !== null;
            }

            $contents[] = [
                'name' => $name,
                'is_directory' => $is_collection,
            ];
        }

        return $contents;
    }

    /**
     * Creates a directory in the WebDav connection.
     *
     * @param string $target_dir The directory path to be created.
     * @return bool True if the directory was successfully created, false otherwise.
     */
    public function create_dir( string $target_dir) : bool {
        $webdav_url = $this->webdav_url . '/' . $target_dir;



        $segments = explode('/', trim($webdav_url, '/'));
        $base_url = implode('/', array_slice($segments, 0, 3)); // Domain + erster Pfad
        $path_segments = array_slice($segments, 3);

        $current_path = $base_url . '/';
        foreach ($path_segments as $segment) {
            $current_path .= rawurlencode($segment) . '/';


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $current_path);
            curl_setopt( $ch, CURLOPT_USERPWD, "$this->webdav_user:$this->webdav_pass" );
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_status === 404 && !str_ends_with($current_path, 'remote.php/')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $current_path);
                curl_setopt( $ch, CURLOPT_USERPWD, "$this->webdav_user:$this->webdav_pass" );
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "MKCOL");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_status !== 201) {
                    return false;
                }
            }
        }

        return true;
    }
}