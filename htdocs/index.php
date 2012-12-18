<?php
// requirements
require_once(dirname(__FILE__) . '/../lib/phpFlickr/phpFlickr.php');
require_once(dirname(__FILE__) . '/../lib/tweetify/tweetify.inc');

// config
$config = parse_ini_file(dirname(__FILE__) . '/../config/config.ini', TRUE);

/**
 * Creates flic.kr URL from Flickr photo ID.
 * @param  long   $id   Flickr photo ID
 * @param  string $base Base alphabet used for encoding
 * @return string       Encoded short ID
 */
function getShortID($id, $base = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ') {
	$baseCount = strlen($base);
	$encoded = '';
	while ($id >= $baseCount) {
		$div = $id / $baseCount;
		$mod = $id - ($baseCount * intval($div));
		$encoded = $base[$mod] . $encoded;
		$id = intval($div);
	}
	if ($id) {
		$encoded = $base[$id] . $encoded;
	}
	return $encoded;
}

/**
 * Get hash tags from tweet and put them in an array.
 * @param  string $tweet Tweet content
 * @return array         Tags
 */
function getTags($tweet) {
	preg_match_all("/\#(\S+)/", $tweet, $tags);
	return $tags[1];
}

if (!empty($_POST)) {

	// only allow specified usernames, delete this if client does not send any usernames
	$allowed = explode(' ', $config['twitter']['allowed']);
	if (in_array($_POST['username'], $allowed)) {

		// get and setup Flickr library
		$f = new phpFlickr($config['flickr']['api'], $config['flickr']['secret']);
		$f->setToken($config['flickr']['token']);

		// tweet content
		$username = isset($_POST['username']) ? trim(strip_tags($_POST['username'])) : NULL;
		$source   = isset($_POST['source']) ? trim(strip_tags($_POST['source'])) : NULL;
		$tweet    = isset($_POST['message']) ? trim($_POST['message']) : NULL;

		// create photo title
		$title = $config['content']['title'];

		// create photo content
		if ($username && $source) {
			$appendix = str_replace(
				array('%username%', '%source%'),
				array($username, $source),
				$config['content']['appendBoth']
			);
		} elseif ($username) {
			$appendix = str_replace(
				'%username%',
				$username,
				$config['content']['appendUsername']
			);
		} elseif ($source) {
			$appendix = str_replace(
				'%source%',
				$source,
				$config['content']['appendSource']
			);
		} else {
			$appendix = '';
		}
		$content = ($tweet) ? str_replace('\\', '', clean_tweet($tweet)) . "\n\n" . $appendix : $appendix;

		// create photo tags
		$hashTags = getTags($tweet);
		$tags = '"uploaded:by=' . strtolower($source) . '" ' . strtolower(implode(' ', $hashTags));
		if (isset($config['flickr']['tags']) && !empty($config['flickr']['tags'])) {
			$tags .= ' ' . $config['flickr']['tags'];
		}

		// upload image, TODO: DM = private photo?
		$id = $f->sync_upload($_FILES['media']['tmp_name'], $title, $content, $tags);

		// move image to set (optional)
		if (isset($config['flickr']['photoset']) && !empty($config['flickr']['photoset'])) {
			$photoset = $config['flickr']['photoset'];
			$f->photosets_addPhoto($photoset, $id);
		}

		// send response to client (yay, the famous flic.kr URL)
		$idShort = getShortID($id);
		print '<mediaurl>http://flic.kr/p/' . $idShort . '</mediaurl>';

	}

} else {
?>
<!DOCTYPE html>
<meta charset="utf-8">
<title>Flickr</title>
<p>This is my Twitter upload endpoint for Flickr.
<p>Source: <a href="https://github.com/dreamseer/twickr">Twickr</a> by <a href="http://marcgoertz.de/">Marc Görtz</a>.
<?php
}
