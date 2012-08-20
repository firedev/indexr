<?php
/**
 * Indexr 1.2
 * Fancy directory lister with thumbnails
 *
 * imagevuex.com
 */

/**
 * Prefix for thumbnails
 */
define('THUMB_PREFIX', 'tn_');

/**
 * Thumbnail dimensions in format of 'WIDTHxHEIGHT'
 * If only the width is specified (e.g. '160x'), all thumbnails will be one width but different height.
 * If only the height is specified (e.g. 'x120'), all thumbnails will be one height but different width.
 * If width and height will be empty both, thubnail will be a copy of original image.
 */
define('THUMB_DIMENSIONS', '120x');

/**
 * Thumbnail quality
 */
define('THUMB_QUALITY', '85');

/**
 * Date format
 */
define('DATE_FORMAT', 'd M Y');

/**
 * List of masks for allowed files
 *
 * Empty string means all files allowed.
 * You can use shell wildcards:
 *   * - zero or more characters (any);
 *   ? - exactly one character (any).
 * Divide masks with | character.
 */
define('ALLOWED_FILES', '*.png|*.jpg|*.gif|*.mp3|*.htm*|*.txt|*.zip|*.flv|*.swf|*.pdf|*.doc');

/**
 * Sorting method
 *
 * Possible values:
 *   - name+ - sort by name ascending
 *   - name- - sort by name descending
 *   - date+ - sort by date ascending
 *   - date- - sort by date descending
 */
define('SORT_METHOD', 'date-');

/**
 * Number of columns in directory list
 *
 */
define('NUMBER_OF_COLUMNS', 3);

define('DIRNAME', dirname(__FILE__) . '/');
define('CURRENT_FILENAME', basename(__FILE__));

/*-- Set of functions --*/

if (!function_exists('fnmatch')) {
	/**
	 * Match filename against a pattern
	 *
	 * @param  string  $pattern
	 * @param  string  $string
	 * @return boolean
	 */
	function fnmatch($pattern, $string) {
		return @preg_match(
			'/^' . strtr(addcslashes($pattern, '/\\.+^$(){}=!<>|'),
			array('*' => '.*', '?' => '.?')) . '$/i', $string
		);
	}
}

/**
 * Checks if given file is allowed
 *
 * @param  string  $filename
 * @return boolean
 */
function isAllowedFile($filename) {
	if (!defined('ALLOWED_FILES')) return true;
	$allowedFiles = explode('|', ALLOWED_FILES);
	foreach ($allowedFiles as $allowedFile) {
		if (fnmatch($allowedFile, strtolower($filename))) {
			return true;
		}
	}
	return false;
}

/**
 * Sorts files (callback)
 *
 * @param  string  $file1
 * @param  string  $file2
 * @return integer
 */
function sortContent($file1, $file2)
{
	$asc = '-' != substr(SORT_METHOD, -1);
	switch (strtolower(substr(SORT_METHOD, 0, 4))) {
		case 'name':
			return compare($asc ? $file1 : $file2, $asc ? $file2 : $file1);
			break;
		case 'date':
			return compare(
				filectime(DIRNAME . ($asc ? $file1 : $file2)),
				filectime(DIRNAME . ($asc ? $file2 : $file1))
			);
			break;
	}
	return 0;
}

/**
 * Compare function
 *
 * @param  mixed   $value1
 * @param  mixed   $value2
 * @return integer
 */
function compare($value1, $value2)
{
	if ($value1 < $value2) {
		return -1;
	} elseif ($value1 > $value2) {
		return 1;
	}
	return 0;
}

/**
 * Normalizes file size
 *
 * @param  integer $size
 * @return string
 */
function normalizeSize($size)
{
	if ($size > 1048576) {
		return round($size / 1048576, 1) . ' MB';
	}
	if ($size > 1024) {
		return round($size / 1024, 1) . ' kB';
	}
	return $size . ' B';
}

/**
 * Normalizes date
 *
 * @param  integer $timestamp
 * @return string
 */
function normalizeDate($timestamp)
{
	return date(DATE_FORMAT, $timestamp);
}

/**
 * Creates file data object
 *
 * @param  string   $fileName
 * @param  array    $content
 * @return stdClass
 */
function fileFactory($fileName, $content)
{
	$fullPath = DIRNAME . $fileName;
	$file = new stdClass();
	$file->name = $fileName;
	preg_match('/\.(\w+)$/', $fileName, $matches);
	$file->extension = isset($matches[1]) ? $matches[1] : false;
	$file->size = filesize($fullPath);
	$file->date = filemtime($fullPath);
	$file->thumbnail = "?thumb=$fileName";
	if (in_array(strtolower($file->extension), array('jpg', 'jpeg', 'jpe', 'png', 'gif', 'bmp')) && $params = @getimagesize($fullPath)) {
		$file->isDimensional = true;
		$file->width = $params[0];
		$file->height = $params[1];
		$userThumbPath = DIRNAME . getThumbName($fileName);
		if (file_exists($userThumbPath)) {
			$file->thumbnail = getThumbName($fileName);
		} elseif (mkdirRecursive(DIRNAME . 'thumbs/')) {
			$thumbPath = DIRNAME . 'thumbs/' . getThumbName($fileName);
			if (file_exists($thumbPath)) {
				$file->thumbnail = 'thumbs/' . getThumbName($fileName);
			}
		}
	} else {
		$file->isDimensional = false;
	}
	$flipped = array_flip($content);
	$key = $flipped[$fileName];
	$file->num = $key + 1;
	$file->previousFile = $key > 0 ? $content[$key - 1] : end($content);
	$file->nextFile = $key < count($content) - 1 ? $content[$key + 1] : reset($content);
	return $file;
}

/**
 * Returns thumbnail name for given file name
 *
 * @param  string $fileName
 * @return string
 */
function getThumbName($fileName)
{
	$lastDotPosition = strrpos($fileName, '.');
	if (false !== $lastDotPosition) {
		$fileName = substr($fileName, 0, $lastDotPosition);
	}
	return THUMB_PREFIX . $fileName . '.jpg';
}

/**
 * Recursively makes directory, returns TRUE if exists or made
 *
 * @param  string  $path The directory path
 * @param  integer $mode
 * @return boolean       TRUE if exists or made or FALSE on failure
 */
function mkdirRecursive($path, $mode = 0777)
{
	$parentPath = dirname($path);
	if (!is_dir($parentPath) && !mkdirRecursive($parentPath, $mode)) {
		return false;
	}
	return is_dir($path) || (@mkdir($path, $mode) && @chmod($path, $mode));
}

/**
 * Generates thumbnail for image
 *
 * @param  string  $filePath
 * @param  string  $thumbPath
 * @return boolean
 *
 * @todo Save only jpeg with Imagemagick
 * @todo Check, won't it resample small images
 * @todo Add GD functions result checking
 */
function makeThumb($filePath, $thumbPath)
{
	if (!($imageParams = @getimagesize($filePath))) {
		return false;
	}
	list($imageWidth, $imageHeight) = $imageParams;

	$dims = explode('x', THUMB_DIMENSIONS);
	$thumbWidth = empty($dims[0]) ? null : (integer) $dims[0];
	$thumbHeight = isset($dims[1]) && !empty($dims[1]) ? (integer) $dims[1] : null;
	if (!$thumbWidth && !$thumbHeight) {
		return copy($filePath, $thumbPath);
	}

	// Try to use Imagemagick
	exec("convert $filePath -thumbnail \"{$thumbWidth}x{$thumbHeight}\>\" -quality THUMB_QUALITY $thumbPath", $output, $result);
	if (0 === $result) {
		return true;
	}

	// There's nothing to do if gd isn't installed
	if (!extension_loaded('gd')) {
		return false;
	}

	$imageAspect = $imageWidth / $imageHeight;
	if (!$thumbWidth) {
		$thumbWidth = (integer) ($thumbHeight * $imageAspect);
	} elseif (!$thumbHeight) {
		$thumbHeight = (integer) ($thumbWidth / $imageAspect);
	}
	$thumbAspect = $thumbWidth / $thumbHeight;

	if ($thumbAspect < $imageAspect) {
		$srcX = (integer) (($imageWidth / $thumbAspect - $imageHeight) / 2);
		$srcY = 0;
		$srcW = (integer) ($imageHeight * $thumbAspect);
		$srcH = $imageHeight;
	} else {
		$srcX = 0;
		$srcW = $imageWidth;
		$srcH = (integer) ($imageWidth / $thumbAspect);
		$srcY = (integer) (($imageHeight - $srcH) / 2);
	}

	$thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
	switch ($imageParams[2]) {
		case IMAGETYPE_GIF:
			$image = imagecreatefromgif($filePath);
			break;
		case IMAGETYPE_JPEG:
			$image = imagecreatefromjpeg($filePath);
			break;
		case IMAGETYPE_PNG:
			$image = imagecreatefrompng($filePath);
			break;
		default:
			return false;
			break;
	}
	imagecopyresampled($thumb, $image, 0, 0, $srcX, $srcY, $thumbWidth, $thumbHeight, $srcW, $srcH);
	imagedestroy($image);

	imagejpeg($thumb, $thumbPath, THUMB_QUALITY);
	imagedestroy($thumb);
}

/**
 * Recursively un-quotes a quoted variable
 *
 * @param  mixed $var
 * @return mixed
 */
function stripslashes_recursive($var)
{
	if (is_array($var)) {
		$unquoted = array();
		foreach ($var as $key => $value) {
			$unquoted[$key] = stripslashes_recursive($value);
		}
		return $unquoted;
	} elseif (is_scalar($var)) {
		return stripslashes($var);
	} else {
		return $var;
	}
}

/**
 * Makes bounds for list columns (recursive)
 *
 * @param  integer $total
 * @param  integer $numOfColumns
 * @param  integer $lastBound
 * @return array
 */
function makeListBounds($total, $numOfColumns, $lastBound = -1)
{
	if ($numOfColumns > 0) {
		$perColumn = (integer) ceil($total / $numOfColumns);

		$result = makeListBounds($total - $perColumn, $numOfColumns - 1, $lastBound + $perColumn);
		array_unshift($result, $lastBound + $perColumn);
		return $result;
	} else {
		return array();
	}
}

if (!ini_get('date.timezone')) {
	ini_set('date.timezone', 'UTC');
}

umask(0000);

error_reporting(0);
//error_reporting(E_ALL);

// Disable magic quotes
if (version_compare('5.3.0', phpversion()) > 0) {
	set_magic_quotes_runtime(0);
}
if (get_magic_quotes_gpc()) {
	$_GET = stripslashes_recursive($_GET);
	$_REQUEST = stripslashes_recursive($_REQUEST);
}

// Read content in directory
$content = array();
if ($handle = opendir(DIRNAME)) {
	while (false !== ($file = readdir($handle))) {
		if (substr($file, 0, 1) != '.'
			&& is_file(DIRNAME . $file)
			&& CURRENT_FILENAME != $file
			&& substr($file, 0, strlen(THUMB_PREFIX)) != THUMB_PREFIX
			&& isAllowedFile($file)
		) {
			$content[] = $file;
		}
	}
	closedir($handle);
}
usort($content, 'sortContent');

// Route the request
if (!empty($_GET)) {
	if (count($_GET) == 1 && '' == reset($_GET) && !isset($_GET['ext'])) {
		$fileName = urldecode($_SERVER['QUERY_STRING']);
		$file = fileFactory($fileName, $content);
		if (!in_array($fileName, $content) || !$file->isDimensional) {
			header('Location: ?');
		}
	} elseif (isset($_GET['thumb'])) {
		if (!in_array($_GET['thumb'], $content)) {
			header('HTTP/1.1 404 Not Found');
			exit(0);
		}
		$fullPath = DIRNAME . $_GET['thumb'];
		$thumbPath = DIRNAME . 'thumbs/' . getThumbName($_GET['thumb']);
		if (!file_exists($thumbPath)) {
			makeThumb($fullPath, $thumbPath);
		}
		header('Content-type: image/jpeg');
		if (file_exists($thumbPath)) {
			header('Location: thumbs/' . getThumbName($_GET['thumb']));
			// header('Expires: ' . date('r', time() + 60 * 60 * 24 * 365)); // 1 year cache
			// $handle = fopen($thumbPath, 'rb');
			// echo fread($handle, filesize($thumbPath));
			// fclose($handle);
		} else {
			$string = '/9j/4AAQSkZJRgABAgAAZABkAAD/7AARRHVja3kAAQAEAAAAWgAA/+4ADkFkb2JlAGTAAAAAAf/bAIQAAQEBAQEBAQEBAQIBAQECAgIBAQICAgICAgICAgMCAwMDAwIDAwQEBAQEAwUFBQUFBQcHBwcHCAgICAgICAgICAEBAQECAgIFAwMFBwUEBQcICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgI/8AAEQgAQABAAwERAAIRAQMRAf/EAIcAAAIBBAMBAAAAAAAAAAAAAAAHBgMECAkBAgoFAQEAAwEBAAAAAAAAAAAAAAAAAQIDBQQQAAECBAQDBQYGAgMAAAAAAAECAxEhBAUAMRIGQQcIYSITFAlRcaEyUhaBkbFCIxXwCnIkJREBAQEAAQQDAQAAAAAAAAAAAAECUREDBBQxEhMy/9oADAMBAAIRAxEAPwD36lU/0GAwh6rOrrkz0XcjtzdR3UFU3z7Gt24BZqlVkTVVlS25U3By20obp0VLCQj+IaykiJirji2c23orrUk6tXVs/wBiL0vtwKCqe8czGVqVJKrdeGs+P8N2IGeNfW0yvkZNa0+th6bt/SlVPvXf7ETBIWxutBhAGP8AFXq/DE+rtX28JTTesP0WUd6tDO0uat9cprj4rT+3r1ZdwVTLjpaLrak1byKl9sqUgNyK0xV8hzC+NuE8vFbROTHPDl9z62JYeYfLq8f2Vg3FTCqo0uANVCG9amla0RMNC0KQsgkJWlSI6kqAws6PRL1N0Lj7/ZD8cvb2cOOIS7hUcBRcUITy45kQJhwmQTwzJ7MB50/9jLvelFzFBMzzGssYmMxuapjlKI48BkMb+P8A0x7/APLwR8vmIrZl7MdGObpnXsFiDbMvZjSMNGTerl/UP0FyB0qtbzNQlQzBYcS6CPcU4a+EY+Xp69Pzrh6QenbpZTsPqB6irPyo3jY9y353bVtL7rt5o6eoebrE1DVNSMVSw2t5xZ0uNltyYUlQiMcrvZv2djsan1bkelXrb6eurOmvtFyd5u2LmZedooaVfV2hb7aV0z6yhqp8pWhNSwFqTBTTgKguQUtEFqxssby9WZSFR+MZjMZ5fE5DIYhK2eVCJjCGok6tOQ7x1cIfuVwEhPAa8es/pf5d9YXIiycj+bSKp/l1deYAuO6LXRuronq5mzVdzubdL4rRDjKHVtJSrQQsIjApUQoWzqyq6zLGGdB6LvpnWsIFD0v01PohpI3NvVRl/wArwcX/AH3ypexjhMqP0nvT/t4Ao+ntpgJyhuPeB/W6nE+xvlX1scK9b6U/QPcEKbrOQDb6FghQO493iRlwuow9jfJPGxws6/0peiO7VbtZduUqrk68T4pduVxKjEx+cOhf5KxT9NL/AJZMjp36HuRHSXv3ee9uQtoq9j/er2xWrrt/z79ZSNLotxvW919pVxL7rRqKa7OtOkOQCRBABUrVGt2rTMjbQychlkIQ08IgaeEsk8BMzxVZbPkxgkGMUhIABVqE0gJVLVxSDJPzKwCMubYfs+3kp0qB3PelI06lAgi7TClTIJ/cZrM8hgOfIH6cAeQP04A8gfpwB5A/TgI5VMLFwuDaUqizWbIUgJ0k6nN1au6lWav4YiPdENRyGAyYp1AhJECkiRBKkkE5gmZSTxM1meWAo1IBSqMCkiCgQpSSlRyIEykngJuGWWATbiC5S7fSuKidzX0nUoKJUP7UKJ0yUoSCiO6mSE8cBIfKj6cAeVH04A8qPpwB5UfTgIbU04F/voKU6SNmaooKgR9w1ZgrTMgkDuCazAZRwD7YjGZMSVaiSCrUJKiUy1cFEST8qcBy+MzGY1GOrScoKOoZSkpXASTPAKFlo+Bt8fKPuW9AjSEAAIu2kQ4AD5UcBNUzgJj4HbgDwO3AHgduAPA7cBD61CU32tT+9Z21PXpME3WrWZ/tAAMVZgZTOAcDKYQEIQgANOnISGnhAfKngJmeAqOJJyzlCQjETEAZRHAZDM4BT0repvbsAJblvsCImRF2yKuBPEzUZ5DATvwT2/DAHgnt+GAPBPb8MAeCe34YCF19MfuVJMdL7liCYAHvM1NweGeZHDgMzgGm2mQyhwzIgT7TmCeOaj2YCqpMYgiXEf5w/XAIy432gs612uvdrrZeLJeLhX07gsF5ulM+1WmqUhSVW9rSseHVd4pX3VApIwFknmhSpJC6q7VJjBPhcud8H9GliJ98szgLxvmG1UtkJF81Ed1bexd1MGZhH/t05Hu9uA6O3y5vArp/vF3USQ2zZrXTgADL/wBGlSRDiSZZTOApG87saQA1tjfFwVLvhrYzSvyffZAjwHATOA+jY3N0Xm8WtNXsa92Rhiobfut9vlTtvSaenYqA20w3YKupUpRceHzoSCInVIJIOwD88B//2Q==';
			echo base64_decode($string);
		}
		exit(0);
		break;
	}
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Expires: ' . date('r'));

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
	<head>
		<title>indexr</title>
		<meta http-equiv="Content-Type" content="text/html;charset=utf-8"/>
		<style type="text/css">
			* { margin: 0; padding: 0; outline:0; }
			body { background: black; color: #999; font: 12px/18px "Lucida Grande", Candara, Tahoma; margin: 18px 36px;}
			span { color: #333;}
			h1, h2, h3, h4 { font-weight: normal; line-height:110%; color: #ffa600;}
			h2 { font-size: 18px;}
			a { text-decoration: none; color: #ffa600; }
			a:visited { color: #bb8816; }
			.pagemenu a:visited { color: #ffa600;}
			a:hover { color: white;}
			a.span { color: #333;}
			img { border: 9px solid white; }
			td { vertical-align: top; }
			table table td { padding: 0 18px 18px 0; }
			.thumb { text-align: right; }
			.thumb img { border: 4px solid #ccc;}
			.thumb img:hover { border: 4px solid white;}
			.thumbtext { padding: 4px 0px 0px 0px; }
			.imagetext, .pagemenu { padding: 0px 0px 9px 9px; }
			.indexr { margin: 18px 0px 0px 0px; }
			p { margin: 0 0 9px 0; }
			form { }
		</style>
	</head>
	<body>
<?php

if (isset($fileName)):
	/*-- File viewer --*/

	$file = fileFactory($fileName, $content);
?>
		<p class="pagemenu">
			<a href="?#<?php echo $file->name; ?>" title="Back to list">&laquo; back</a>&nbsp;
			<a href="?<?php echo $file->previousFile; ?>" title="<?php echo $file->previousFile; ?>">&lsaquo; prev</a>
			<?php echo $file->num; ?>/<?php echo count($content); ?>
			<a href="?<?php echo $file->nextFile; ?>" title="<?php echo $file->nextFile; ?>">next &rsaquo;</a>
		</p>
		<p><a href="?#<?php echo $file->name; ?>"><img src="<?php echo $file->name; ?>" alt="<?php echo $file->name; ?>" title="Back to list"/></a></p>
		<div class="imagetext">
			<h2>

				<a href="?#<?php echo $file->name; ?>" title="Back to list"><?php echo $file->name; ?></a>

			</h2>
			<?php echo normalizeDate($file->date); ?><br/>
				<?php echo normalizeSize($file->size); ?><br/>
				<?php if ($file->isDimensional): ?>
					<?php echo $file->width; ?> x <?php echo $file->height; ?><br/>
				<?php endif; ?>
		</div>
<?php else:
	/*-- Directory lister --*/
	$files = array();
	$extensions = array();

	$selectedExtension = isset($_GET['ext']) && !empty($_GET['ext']) ? $_GET['ext'] : false;

	foreach ($content as $fileName) {
		$file = fileFactory($fileName, $content);
		if (!$selectedExtension || ($selectedExtension && $file->extension == $selectedExtension)) {
			$files[] = $file;
		}
		if ($file->extension) {
			if (!isset($extensions[$file->extension])) {
				$extensions[$file->extension] = 0;
			}
			$extensions[$file->extension]++;
		}
	}

	$sorts = array(
		'na' => 'return strcmp(strtolower($file1->name), strtolower($file2->name));',
		'nd' => 'return -strcmp(strtolower($file1->name), strtolower($file2->name));',
		'da' => '$d1 = (integer) $file1->date; $d2 = (integer) $file2->date; return ($d1 == $d2 ? 0 : ($d1 > $d2 ? 1 : -1));',
		'dd' => '$d1 = (integer) $file1->date; $d2 = (integer) $file2->date; return -($d1 == $d2 ? 0 : ($d1 > $d2 ? 1 : -1));',
		'ea' => 'return strcmp(strtolower($file1->extension), strtolower($file2->extension));',
		'ed' => 'return -strcmp(strtolower($file1->extension), strtolower($file2->extension));',
	);
	if (isset($_GET['sort']) && isset($sorts[$_GET['sort']])) {
		usort($files, create_function('$file1,$file2', $sorts[$_GET['sort']]));
	}

	ksort($extensions);
?>
		<form action="" method="get" class="pagemenu">
			<?php if (count($extensions)): ?>
						Filter by extension:
						<select name="ext" onchange="this.form.submit();">
							<option value="">----</option>
							<?php foreach ($extensions as $extension => $count): ?>
								<option value="<?= htmlspecialchars($extension) ?>" <?= (($selectedExtension == $extension) ? 'selected="selected"' : '') ?>>.<?= htmlspecialchars($extension) ?> (<?= $count . (1 == $count ? ' file' : ' files') ?>)</option>
							<?php endforeach; ?>
						</select>
						<input type="submit" value="Apply" id="filterSubmitButton" />
			<?php endif; ?>
			|
			Filename
			<a href="?sort=na" title="Sort by filename ascending">&#9650;</a>
			<a href="?sort=nd" title="Sort by filename descending">&#9660;</a>
			|
			Date
			<a href="?sort=da" title="Sort by date ascending">&#9650;</a>
			<a href="?sort=dd" title="Sort by date descending">&#9660;</a>
			|
			Extension
			<a href="?sort=ea" title="Sort by entension ascending">&#9650;</a>
			<a href="?sort=ed" title="Sort by entension descending">&#9660;</a>
		</form>

		<script type="text/javascript">
			//<![CDATA[
			document.getElementById('filterSubmitButton').style.display = 'none';
			//]]>
		</script>

		<?php
			$total = count($files);
			$i = 0;

			$bounds = makeListBounds($total, NUMBER_OF_COLUMNS);
		?>
		<table width="100%">
			<tr>
				<td width="<?php echo round(100 / NUMBER_OF_COLUMNS) ?>%">
					<?php foreach ($files as $file): ?>
						<?php if (0 == $i): ?>
							<table>
						<?php endif; ?>

							<tr>
								<td class="thumb">
									<a name="<?php echo $file->name; ?>"/>
									<?php if ($file->thumbnail): ?>
										<a href="<?php echo $file->isDimensional ? '?' . urlencode($file->name) : $file->name; ?>">
											<img src="<?php echo $file->thumbnail; ?>" alt="<?php echo $file->name; ?>"/>
										</a>
									<?php endif; ?>
								</td>

								<td class="thumbtext">
									<h2>
										<a href="<?php echo $file->isDimensional ? '?' . urlencode($file->name) : $file->name; ?>">
											<?php echo $file->name; ?>
										</a>
									</h2>
									<p>
									<?php echo normalizeDate($file->date); ?><br/>
										<?php echo normalizeSize($file->size); ?><br/>
										<?php if ($file->isDimensional): ?>
											<?php echo $file->width; ?> x <?php echo $file->height; ?>
										<?php endif; ?>
									</p>
								</td>
							</tr>
						<?php if (in_array($i, $bounds)): ?>
					</table>
				</td>
				<td width="<?php echo round(100 / NUMBER_OF_COLUMNS) ?>%">
					<table>
						<?php endif; ?>

						<?php $i++; ?>

						<?php if ($i == $total): ?>
							</table>
						<?php endif; ?>

					<?php endforeach; ?>
				</td>
			</tr>
		</table>
	<?php endif; ?>
		<p class="imagetext indexr">
			 <?php echo count($content); ?> files in folder
		</p>
		<p class="pagemenu">
			<a href="http://imagevuex.com/blog/indexr" title="Indexr Home">indexr <span>1.2</span></a>
		</p>
	</body>
</html>
