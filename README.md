# webdav-sync

A simple library to connect to Nextcloud based webdav ressources

### Dependencies
- cURL
- PHP-8.2 or above
- PHP extensions: DOMDocument

### Installation
`composer require contelli/webdav-sync`

`composer update`

### Usage
require_once 'src/WebDav/WebDavClient.php';

use WebDav\WebDav\WebDavClient;


$web_dav_client = new WebDavClient(
'<URL TO NEXTCLOUD>',
'<USERNAME>',
'<PASSWORD>>'
);

$web_dav_client->upload_file(dirname(__FILE__) . '/FILENAME_TO_UPLOAD', '/DIRECTORY_ON_WEBDAV');

$contents = $web_dav_client->read_contents('/DIRECTORY TO READ'));
$web_dav_client->delete(REMOTE_FILE_TO_DELETE);
$web_dav_client->downloa(REMOTE_FILE_TO_DOWNLOAD, LOCAL_DESTINATION_TO_SAVE);
