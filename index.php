<?php

$startTime = microtime(true); // Start timer for page render

// Define paths
$articleDirectory = __DIR__ . '/articles';
$templateDirectory = __DIR__ . '/templates';
$articleTemplateDirectory = $templateDirectory . '/article';
$styleDirectory = __DIR__ . '/styles';
$indexFile = $templateDirectory . '/index/1.txt';
$defaultTemplate = '1.txt';
$defaultStyle = '1.txt';
$membersDirectory = __DIR__ . '/members';
$aboutDirectory = $templateDirectory . '/about';
$emailsDirectory = $templateDirectory . '/email';

$queryParams = $_GET; // Get all the query parameters from the URL
$articleParam = isset($queryParams['article']) ? trim($queryParams['article']) : null;
$showHidden = isset($queryParams['showall']);

function space($n) { return str_repeat(' ', $n); }
function paramKey($key) { global $queryParams; return isset($queryParams[$key]); }
function paramVal($key, $default = null) { global $queryParams; return $queryParams[$key] ? $queryParams[$key] : $default; }
function loadFile($path) { return file_exists($path) ? file_get_contents($path) : null; }

function getArticlePaths($dir) {
    global $showHidden;
    $articles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $fileinfo) {
        if ($fileinfo->isFile() && $fileinfo->getExtension() == 'txt' && ($showHidden || $fileinfo->getFilename()[0] != '.')) {
            $articles[] = $fileinfo->getPathname();
        }
    }

    // Sort the articles by date and file number (ascending order)
    usort($articles, function($a, $b) {
        $aDate = getArticleDateFromPath($a);
        $bDate = getArticleDateFromPath($b);

        // Compare dates first (ascending order)
        if ($aDate != $bDate) {
            return strcmp($aDate, $bDate); // Ascending order (oldest first)
        }

        // If dates are the same, compare the file number (ascending order)
        $aNumber = getArticleNumberFromPath($a);
        $bNumber = getArticleNumberFromPath($b);

        return $aNumber - $bNumber; // Ascending order (smallest number first)
    });

    return $articles;
}

function getArticleDateFromPath($path) {
    // Assuming the path is in the format /articles/YYYY/MM/DD/#.txt
    // Example: /articles/2024/10/06/1.txt
    preg_match('/\/articles\/(\d{4})\/(\d{2})\/(\d{2})\//', $path, $matches);
    if ($matches) {
        return sprintf('%s-%s-%s', $matches[1], $matches[2], $matches[3]); // YYYY-MM-DD
    }
    return '';
}

function getArticleNumberFromPath($path) {
    // Assuming the file is named like 1.txt, 2.txt, etc.
    preg_match('/\/(\d+)\.txt$/', $path, $matches);
    return isset($matches[1]) ? (int) $matches[1] : 0;
}

function getArticleMetadata($path) {
    //$content = file_get_contents($path);

    // More efficient than reading entire file
    $handle = fopen($path, 'r');
    $firstLines = '';
    if ($handle) {
        $lineCount = 0;
        while (($line = fgets($handle)) !== false && $lineCount < 5) {
						$firstLines .= $line;
            $lineCount++;
        }
        fclose($handle);
    } else {
        echo "Error opening file ($path).";
    }
		$content = $firstLines;
    
    preg_match('/TITLE=(.+)/', $content, $titleMatch);
    preg_match('/DATETIME=<time datetime="(\d{4}-\d{2}-\d{2})">/', $content, $dateMatch);
    
    return [
        'title' => isset($titleMatch[1]) ? trim($titleMatch[1]) : 'Untitled',
        'date' => isset($dateMatch[1]) ? $dateMatch[1] : '',
        'path' => $path
    ];
}

function groupArticlesByMonth($articles) {
    $grouped = [];
    foreach ($articles as $article) {
        $metadata = getArticleMetadata($article);
        $date = $metadata['date'];
        $monthYear = date('F Y', strtotime($date)); // Convert date to "Month Year" format

        if (!isset($grouped[$monthYear])) {
            $grouped[$monthYear] = [];
        }

        $grouped[$monthYear][] = $metadata;
    }
    return $grouped;
}

function renderIndex($message = '') {
    global $articleDirectory, $indexFile, $showHidden;

    $articles = getArticlePaths($articleDirectory); // Get paths for all articles
    $groupedArticles = groupArticlesByMonth($articles);

    $templateContent = loadFile($indexFile);
 
    if ($templateContent) {
        $templateContent = preg_replace('/<!-- \(MESSAGE\) -->\s*/', $message ? "<div style='color: orange; text-align: center; font-family: verdana; font-size: 1em; padding: 5px;'>&#9888; $message &#9888;</div>\n\n" : '', $templateContent);

        $html = ''; $indent = 8;
        foreach ($groupedArticles as $monthYear => $articles) {
            $html = $html . "\n" . space($indent) . "<h3>$monthYear</h3>\n";

            $html = $html . space($indent) . "<ul>\n";
            for ($i = count($articles) - 1; $i >= 0; $i--) {
                $article = $articles[$i];
                $relativePath = str_replace([$articleDirectory, '.txt'], '', $article['path']);

                $url = '/?article=' . str_replace('/', '-', trim($relativePath, '/\\'));
                $url = str_replace('\\', '-', $url); // Needed for Windows localhost mode

                $hidden = $showHidden && preg_match('/-\.\d+$/', $url);

                $html .= space($indent + 4) . '<li' . ($hidden ? ' style="background-color: #eee;"' : '') . '><a href="' . $url . '">';
                $html .= '<span class="publish-date">(' . date('M d', strtotime($article['date'])) . ')</span> | ';
                $html .= htmlspecialchars($article['title']);
                $html .= "</a></li>\n";
            }
						$html .= space($indent) . '</ul>';
        }
				
				echo str_replace(space($indent) . '<!-- (ARTICLE LINKS) -->', $html, $templateContent);
    }
		else {
        echo "Error: Index file not found.";
    }
}

function echoln($html) { echo $html . "\n"; }
function convertImages($html) { return paramKey('text') ? preg_replace('/\s+src\s*=\s*\"data:[^"]+"/', /*' src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR42mNgYGAAAAAEAAHI6uv5AAAAAElFTkSuQmCC"'*/' src="placeholder.png"', $html) : $html; }
//function getMemberPortrait($id) { global $membersDirectory; return convertImages(loadFile($membersDirectory . '/' . $id . '/portrait.txt')); }
function getMemberPortrait($id)
{
    global $membersDirectory;
    $info = getMemberInfo($id);
    return convertImages(str_replace('<img src', '<img alt="' . $info['name'] . ', ' . $info['title'] . '" src', loadFile($membersDirectory . '/' . $id . '/portrait.txt')));
}

function removeBoldAndItalics($html) { return $html ? str_replace(['<strong>', '</strong>', '<em>', '</em>'], null, $html) : $html; }

function getMemberInfo($id)
{
    global $membersDirectory;

    $info = loadFile($membersDirectory . '/' . $id . '/info.txt') or die('Unable to read member info file');

    $fields = [];
    preg_match('/NAME=(.+)/', $info, $matches);
    $fields['name'] = isset($matches[1]) ? trim($matches[1]) : 'Name not specified';
    preg_match('/TITLE=(.+)/', $info, $matches);
    $fields['title'] = isset($matches[1]) ? trim($matches[1]) : 'Title not specified';

    $fields['role'] = null; $fields['bio'] = null;
    $blocks = preg_split('/\[/', $info);
    for ($i = 1; $i < count($blocks); $i++) if (substr($blocks[$i], 0, 5) == 'ROLE]') $fields['role'] = trim(substr($blocks[$i], 5)); elseif (substr($blocks[$i], 0, 4) == 'BIO]') $fields['bio'] = trim(substr($blocks[$i], 4));
    
    if (!$fields['role']) $fields['role'] = $fields['bio'] ? $fields['bio'] : 'Role not specified';
    if (!$fields['bio']) $fields['bio'] = $fields['role'] ? $fields['role'] : 'Bio not specified';
		
		$fields['role'] = removeBoldAndItalics($fields['role']);

    return $fields;
}

function renderArticle($articlePath) {
    global $articleTemplateDirectory, $styleDirectory, $membersDirectory, $defaultTemplate, $defaultStyle;

    $articleContent = loadFile($articlePath);
    if (!$articleContent) {
        return false; // Article not found
    }

    // Parse article fields (TEMPLATE, STYLE, DATETIME, TITLE, AUTHOR, CONTRIBUTORS, CONTENT)
    $fields = [];
    preg_match('/TEMPLATE=(.+)/', $articleContent, $matches);
    $fields['template'] = isset($matches[1]) ? trim($matches[1]) . '.txt' : $defaultTemplate;

    preg_match('/STYLE=(.+)/', $articleContent, $matches);
    $fields['style'] = isset($matches[1]) ? trim($matches[1]) . '.txt' : $defaultStyle;

    preg_match('/DATETIME=(.+)/', $articleContent, $matches);
    $fields['datetime'] = isset($matches[1]) ? trim($matches[1]) : '';

    preg_match('/TITLE=(.+)/', $articleContent, $matches);
    $fields['title'] = isset($matches[1]) ? trim($matches[1]) : 'Untitled Article';

    preg_match('/AUTHOR=(.+)/', $articleContent, $matches);
    $fields['author'] = isset($matches[1]) ? trim($matches[1]) : '';

    preg_match('/CONTRIBUTORS=(.+)/', $articleContent, $matches);
    $fields['contributors'] = isset($matches[1]) ? trim($matches[1]) : '';

    // Extract content after [CONTENT]
    $fields['content'] = trim(preg_split('/\[CONTENT\]\s*/', $articleContent)[1] ?? '');

    // Load the template and style
    $templateContent = loadFile($articleTemplateDirectory . '/' . $fields['template']);
    $styleContent = trim(loadFile($styleDirectory . '/' . $fields['style']));

    // Load the author bio
    $authorId = $fields['author'];
    //$authorPortrait = loadFile($membersDirectory . '/' . $authorId . '/portrait.txt');
    $authorPortrait = getMemberPortrait($authorId);
    $authorLinkedPortrait = $authorPortrait ? "<a class=\"proud-link\" href=\"/?about#m$authorId\">" . $authorPortrait . '</a>' : '';
    $authorInfo = getMemberInfo($authorId);
    //$authorBio = '<div class="author-bio">' . $authorInfo['bio'] . '</div>';
    //$authorBio = '<div class="author-bio">' . '<div class="author-info-header-container" style="margin-top: 15px;"><h3 style="margin: 0; Xbackground-color: orange;">' . $authorInfo['name'] . '</h3><div class="gear-icon"><h3>&#9881;</h3><div class="tooltip"><h2><strong>Generative Models:</strong></h2><span class="contributors"><!-- (CONTRIBUTORS) --></span></div></div></div><h4 style="margin: 0; margin-top: 3px; color: #555;">' . $authorInfo['title'] . '</h4>' . $authorInfo['bio'] . '</div>';
    $indent = 12;
    $authorBio = '<div class="author-bio">' . "\n" . space($indent + 4) . '<div class="author-info-header-container">' . "\n" . space($indent + 8) . '<div></div>' . "\n" . space($indent + 8) . '<div class="gear-icon">' . "\n" . space($indent + 12) . '<h3>&#9881;</h3>' . "\n" . space($indent + 12) . '<div class="popup">' . "\n" . space($indent + 16) . '<h2><strong>Generative Models:</strong></h2>' . "\n" . space($indent + 16) . '<span class="contributors"><!-- (CONTRIBUTORS) --></span>' . "\n" . space($indent + 12) . '</div>' . "\n" . space($indent + 8) . '</div>' . "\n" . space($indent + 4) . '</div>' . "\n" . space($indent + 4) . '<h3 style="margin: 0; margin-top: -15px;">' . $authorInfo['name'] . '</h3>' . "\n" . space($indent + 4) . '<h4 style="margin: 0; margin-top: 3px; margin-bottom: 16px; color: #666;">' . $authorInfo['title'] . '</h4>' . "\n" . space($indent + 4) . $authorInfo['bio'] . "\n" . space($indent) . '</div>';

    $authorInfoContent = trim($authorLinkedPortrait . "\n" . space($indent) . $authorBio);

    // Substitute placeholders in the template
    $output = str_replace(
        [
            '<!-- (TITLE) -->',
            '<!-- (STYLE) -->',
            '<!-- (DATETIME) -->',
            '<!-- (CONTENT) -->',
            '<!-- (AUTHOR) -->',
            '<!-- (CONTRIBUTORS) -->',
        ],
        [
            htmlspecialchars($fields['title']),
            $styleContent,
            $fields['datetime'],
            $fields['content'],
            $authorInfoContent,
            $fields['contributors'],
        ],
        $templateContent
    );

    echo convertImages($output);
    return true;
}

function checkForUnrecognizedParams($recognizedParams, $queryParams) {
    foreach ($queryParams as $key => $value) {
        if (!in_array($key, $recognizedParams)) {
            renderIndex("Invalid parameter \"{$key}\"");
            return true;
        }
    }
}

if (!checkForUnrecognizedParams(['about', 'article', 'showall', 'text', 'email', 'rnf'], $queryParams))
{
    // Add "ErrorDocument 404 /index.php?rnf" to .htaccess file and restart Apache web server for this to work (Nginx uses "server { error_page 404 /index.php?rnf; }")
    if (paramKey('rnf')) { renderIndex('The resource you requested does not exist'); }
    elseif (paramKey('about')) { include($aboutDirectory . '/1.txt'); }
    elseif (paramKey('email')) { echo file_get_contents($emailsDirectory . '/' . paramVal('email', 1) . '.html'); }
    elseif ($articleParam)
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}-\.?\d+$/', $articleParam)) // Check if the article specifier has correct format
        {
            $articlePath = $articleDirectory . '/' . str_replace('-', '/', $articleParam) . '.txt';
            if (!renderArticle($articlePath)) renderIndex("The article \"$articleParam\" does not exist");
        }
        else { renderIndex("Invalid article specifier \"$articleParam\" expected format is \"YYYY-MM-DD-#\""); }
    }
    else { renderIndex(); }
}

//$serverSoftware = $_SERVER['SERVER_SOFTWARE'];
//$serverSoftware = (stripos($serverSoftware, 'apache') !== false ? 'Apache' : (stripos($serverSoftware, 'nginx') !== false ? 'Nginx' : '{Unknown}'));

//$timestamp = date('Y-m-d @ H:i:s T') . date_default_timezone_get();
$timestamp = date('Y-m-d H:i:s');

$utcTimezone = new DateTimeZone(date_default_timezone_get());
$estTimezone = new DateTimeZone('America/Toronto');
$date = new DateTime($timestamp, $utcTimezone);
$date->setTimezone($estTimezone);
$timestamp = $date->format('Y-m-d @ H:i T');

$elapsed = number_format((microtime(true) - $startTime) * 1000, 1); // End timer for page render
//echo "\n\n<!-- Rendered in $elapsed ms by $serverSoftware Web Server & PHP " . phpversion() . " on $timestamp -->";

$serverSoftware = $_SERVER['SERVER_SOFTWARE'];
echo "\n\n<!-- Rendered in $elapsed ms on $timestamp | $serverSoftware -->";
?>