<?php
// How it works:
// User enters URL and replace a number (whole num or part of num) with a char '|' (pronounced 'pipe').
// Then he enters start and end numbers. After that this code will generate image URLs,
// which differences will be the replaced number. It will be incremented in range from $start to $end.
// For example URL: http://www.example.com/img_20180519.jpg
// can be changed to http://www.example.com/img_2018|19.jpg
// Then setting start to 8 and end to 10 will generate:
// http://www.example.com/img_2018819.jpg
// http://www.example.com/img_2018919.jpg
// http://www.example.com/img_20181019.jpg
// Now in case the first 2 url are not as expected,
// the user can tick 'leading zeros' checkbox before that to get:
// http://www.example.com/img_20180819.jpg
// http://www.example.com/img_20180919.jpg
// http://www.example.com/img_20181019.jpg
// If $end < $start, It will be decremented in range from $end to $start.
//
// Update 1:
// When there is no placeholder ('|'), code is trying to replace last number in URL with '|'.
// I call this 'lazy mode' and it's have moderate success.
//
// Expected input through POST:
// url : url should have a number (up to 6 digits), which is replaced with '|'
// start : index of first picture (including) used in the filename or filepath. By default is 0,
// but usually you will want to use 1. From 0 to 999999.
// end : index of last picture (including) used in the filename or filepath. From 0 to 999999.

$dataReady = 0; // show input form (and submit btn) or show generated gallery (after submit is pressed)
$start = null; // int  filename index of the first generated img URL. ex: start=13 => url.com/image13.jpg
$end = null; // int  filename index of the last generated img URL.
$biggest = null; // int  after comparing $start and $end, store larger one from them
$url = null; //string array  $url[0] is substring before the |, $url[1] is the substring after |.
$digits = null; //int  how manny digits is $biggest number - needed for some 'lazy mode' calc
$minDigitsLength = null; //int  usually same as $digits - needed for format string with leading zeroes

// validating 'start' - expecting to be integer from 0 to 999999
if (isset($_POST['start'])) {
    $startStrLen = strlen($_POST['start']);
    if ($startStrLen == 0) {
        $start = 0;
    } elseif ($startStrLen > 6) { //max number should not exceed 999999
        throw new Exception('Too large start number');
    } else { //validating it's not negative and it's integer
        for ($i = 0; $i < $startStrLen; $i++) {
            $char = $_POST['start'][$i];
            if ($char < '0' OR '9' < $char) {
                throw new Exception('Invalid start number');
            }
        }
        $start = $_POST['start'] + 0;
    }
}

// validating 'end' - expecting to be integer from 0 to 999999
if (isset($_POST['end']) AND isset($start)) {
    $endStrLen = strlen($_POST['end']);
    if ($endStrLen == 0) {
        $end = 0;
    } elseif ($endStrLen > 6) { //max number should not exceed 999999
        throw new Exception('Too large end number');
    } else { //validating it's not negative and it's integer
        for ($i = 0; $i < $endStrLen; $i++) {
            $char = $_POST['end'][$i];
            if ($char < '0' OR '9' < $char) {
                throw new Exception('Invalid end number');
            }
        }
        $end = $_POST['end'] + 0;
        $biggest = ($start < $end) ? $end : $start;
        $digits = countDigits($biggest);
    }
}

// validating 'zeros' - do we want 7 to printed as 07, 007, 0007, ... (depends from $biggest) or not
if (isset($end)) {
    if (!isset($_POST['zeros'])) {
        $minDigitsLength = 0;
    } else if ($_POST['zeros'] != 'on') {
        $minDigitsLength = 0;
    } else {
        $minDigitsLength = $digits;
    }
}

// checking 'url' for placeholder char ('|' pipe char)
if (isset($_POST['url']) AND (strlen($_POST['url']) > 0) AND isset($minDigitsLength)) {
    $numberOfSpecialChar = substr_count($_POST['url'], '|');
    if ($numberOfSpecialChar > 1) {
        throw new Exception('Invalid URL input - more than 1 placeholder');
    } elseif ($numberOfSpecialChar == 1) {
        $url = explode('|', trim($_POST['url']));
    } else { //($numberOfSpecialChar == 0) - There is no placeholder entered aka lazy mode :)
        //
        //using regex search for the last 1 to 6 consecutive digits forming a number
        $subject = trim($_POST['url']);
        $pattern = '/(.*?)(\d{1,6})(\D*$)/';
        $hasMatched = preg_match($pattern, $subject, $matches);
        if ($hasMatched == 0) {
            //throw new Exception('Unusable URL - there is no numbers in it');
            //$url = null;
        } else {
            // $matches[0] - full match not used
            // $matches[1] - first half of the URL
            // $matches[2] - number which all or last digits will be used as placeholder
            // $matches[3] - second half of the URL
            $numlen = strlen($matches[2]);
            if ($numlen >= $digits) {
                //example: .../img749.jpg -> 749, but we want from 0 to 20
                // so url becomes .../img7|.jpg, generating img700.jpg to img720.jpg
                $splitnum = '/(\d*)(\d{' . $digits . '})$/';
                if (preg_match($splitnum, $matches[2], $matches2) == 0) {
                    throw new Exception('');
                }
                // $matches2[0] - full match not used
                // $matches2[1] - first part of the number (can be empty)
                // $matches2[2] - last part of the number (at least 1 char) will be used as placeholder
                $url[0] = $matches[1] . $matches2[1];
                $url[1] = $matches[3];
                $minDigitsLength = $digits;
            } else {
                //example: .../img749.jpg -> 749, but we want from 950 to 1030
                // so url becomes .../img|.jpg, generating img950.jpg to img1030.jpg
                $url[0] = $matches[1];
                $url[1] = $matches[3];
                $minDigitsLength = $numlen;
            }
        }
    }
}

if (isset($url)) {
    $urlArr = array();
    if ($start <= $end) {  //generate from "oldest" to the "newest" picture
        for ($i = $start; $i <= $end; $i++) {
            $u = $url[0];
            $u .= str_pad($i, $minDigitsLength, '0', STR_PAD_LEFT);
            $u .= $url[1];
            //echo $u;
            $urlArr[count($urlArr)] = $u;
        } //unset($i);
    } else {  //generate from "newest" to the "oldest" picture
        for ($i = $start; $i >= $end; $i--) {
            $u = $url[0];
            $u .= str_pad($i, $minDigitsLength, '0', STR_PAD_LEFT);
            $u .= $url[1];
            //echo $u;
            $urlArr[count($urlArr)] = $u;
        } //unset($i);
    }

    //making cool html page title from URL (by replacing spec chars with underscore '_')
    $galleryName = '';
    $urlLowerChar = str_split(strtolower($_POST['url']), 1);
    foreach ($urlLowerChar as $value) {
        if ($value == '|') {
            $galleryName .= 'x';
        } else if (('a' <= $value && $value <= 'z') || ('0' <= $value && $value <= '9')) {
            $galleryName .= $value;
        } else if (strlen($galleryName) > 0) {
            if ($galleryName[strlen($galleryName) - 1] != '_') {
                $galleryName .= '_';
            }
        }
    }

    $dataReady = 1;
}

//ugly implementation, still I think it's faster than strlen($number);
function countDigits($number)
{
    if ($number < 10) {
        $digits = 1;
    } else if ($number < 100) {
        $digits = 2;
    } else if ($number < 1000) {
        $digits = 3;
    } else if ($number < 10000) {
        $digits = 4;
    } else if ($number < 100000) {
        $digits = 5;
    } else if ($number < 1000000) {
        $digits = 6;
    } else if ($number < 10000000) {
        $digits = 7;
    } else {
        $digits = -1;
        throw new Exception('Large numbers Not implemented');
    }
    return $digits;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <?php if ($dataReady): ?>
        <title><?= $galleryName; ?></title>
        <style>
            #img {
                width: auto;
                max-width: 100%;
                height: auto;
            }
        </style>
    <?php else: ?>
        <title>BruteForce URL</title>
    <?php endif; ?>
</head>
<body>

<?php if ($dataReady): ?>
    <table width="100%" border="0" cellspacing="10" cellpadding="1">
        <?php for ($u = 0; $u < count($urlArr); $u++) : ?>
            <tr>
                <td>
                    <img id="img" src="<?= $urlArr[$u]; ?>" alt="<?= $urlArr[$u]; ?>">
                </td>
            </tr>
        <?php endfor; ?>
    </table>
<?php else: ?>
    <form method="post" action=".">
        URL: <input type="text" name="url" size="100" placeholder="http://www.example.com/|.jpg" required>
        <br>
        from: <input type="number" name="start" min="0" max="999999" size="1" step="1" value="0">
        to: <input type="number" name="end" min="0" max="999999" size="1" step="1" value="99">
        leading zeros <input type="checkbox" name="zeros">
        <br>
        <input type="submit" value="Generate">
    </form>
<?php endif; ?>

</body>
</html>
