<?php
// Define paths
$articleDirectory = __DIR__ . '/articles';
$templateDirectory = __DIR__ . '/templates';
$articleTemplateDirectory = $templateDirectory . '/article';
$styleDirectory = __DIR__ . '/styles';
$indexFile = $templateDirectory . '/index/1.txt';
$defaultTemplate = '1.txt';
$defaultStyle = '1.txt';
$authorDirectory = __DIR__ . '/authors';

// Get all the query parameters from the URL
$queryParams = $_GET;

// Get the 'article' parameter from the URL
$articleParam = isset($queryParams['article']) ? trim($queryParams['article']) : null;

function loadFile($path) {
    return file_exists($path) ? file_get_contents($path) : null;
}

/*function getArticles($dir) {
    $articles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $fileinfo) {
        if ($fileinfo->isFile() && $fileinfo->getExtension() == 'txt' && substr($fileinfo->getFilename(), 0, 1) != '.') {
            $articles[] = $fileinfo->getPathname();
        }
    }
    return $articles;
}*/

/*function getArticles($dir) {
    $articles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $fileinfo) {
        if ($fileinfo->isFile() && $fileinfo->getExtension() == 'txt' && substr($fileinfo->getFilename(), 0, 1) != '.') {
            $articles[] = $fileinfo->getPathname();
        }
    }

    // Sort the articles by date and file number
    usort($articles, function($a, $b) {
        $aDate = getArticleDateFromPath($a);
        $bDate = getArticleDateFromPath($b);

        // Compare dates first (descending order)
        if ($aDate != $bDate) {
            return strcmp($bDate, $aDate); // Reverse order (latest first)
        }

        // If dates are the same, compare the file number (descending order)
        $aNumber = getArticleNumberFromPath($a);
        $bNumber = getArticleNumberFromPath($b);

        return $bNumber - $aNumber; // Reverse order (largest number first)
    });

    return $articles;
}*/

function getArticles($dir) {
    $articles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $fileinfo) {
        if ($fileinfo->isFile() && $fileinfo->getExtension() == 'txt' && substr($fileinfo->getFilename(), 0, 1) != '.') {
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

    // Reverse the sorted array to get reverse chronological order (newest first)
    return array_reverse($articles);
}

// Helper function to extract the date from the article path
function getArticleDateFromPath($path) {
    // Assuming the path is in the format /articles/YYYY/MM/DD/#.txt
    // Example: /articles/2024/10/06/1.txt
    preg_match('/\/articles\/(\d{4})\/(\d{2})\/(\d{2})\//', $path, $matches);
    if ($matches) {
        return sprintf('%s-%s-%s', $matches[1], $matches[2], $matches[3]); // YYYY-MM-DD
    }
    return '';
}

// Helper function to extract the file number from the article path
function getArticleNumberFromPath($path) {
    // Assuming the file is named like 1.txt, 2.txt, etc.
    preg_match('/\/(\d+)\.txt$/', $path, $matches);
    return isset($matches[1]) ? (int) $matches[1] : 0;
}

function getArticleMetadata($path) {
    $content = file_get_contents($path);
    
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
    global $articleDirectory, $indexFile;

    // Get all articles
    $articles = getArticles($articleDirectory);
    $groupedArticles = groupArticlesByMonth($articles);

    // Load the template
    $templateContent = loadFile($indexFile);
 
    if ($templateContent) {
        if ($message) {
            echo "<div style='color: orange; background-color: black; text-align: center; font-family: verdana; font-size: 1em; padding: 5px;'>&#9888; $message &#9888;</div>";
        }

        $html = '';
        foreach ($groupedArticles as $monthYear => $articles) {
            $html = $html . "\n    <h3>$monthYear</h3>\n";

            $html = $html . "    <ul>\n";
						for ($i = count($articles) - 1; $i >= 0; $i--) {
                $article = $articles[$i];
                $relativePath = str_replace([$articleDirectory, '.txt'], '', $article['path']);

                $url = '/?article=' . str_replace('/', '-', trim($relativePath, '/\\'));
                $url = str_replace('\\', '-', $url); // Needed for Windows localhost mode

                $html .= '        <li><a href="' . $url . '">';
                $html .= '<span class="publish-date">(' . date('M d', strtotime($article['date'])) . ')</span> | ';
                $html .= htmlspecialchars($article['title']);
                $html .= "</a></li>\n";
            }
						$html .= '    </ul>';
        }
				
				echo str_replace('    <!-- (ARTICLE LINKS) -->', $html, $templateContent);
    }
		else {
        echo "Error: Index file not found.";
    }
}

function renderArticle($articlePath) {
    global $articleTemplateDirectory, $styleDirectory, $authorDirectory, $defaultTemplate, $defaultStyle;

    // Load the article content
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
    $fields['content'] = preg_split('/\[CONTENT\]/', $articleContent)[1] ?? '';

    // Load the template and style
    $templateContent = loadFile($articleTemplateDirectory . '/' . $fields['template']);
    $styleContent = loadFile($styleDirectory . '/' . $fields['style']);

    // Load the author bio
    $authorBio = loadFile($authorDirectory . '/' . $fields['author'] . '/bio.txt');

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
            $authorBio,
            $fields['contributors'],
        ],
        $templateContent
    );

    echo $output;
    return true;
}

function checkForUnrecognizedParams($recognizedParams, $queryParams) {
    foreach ($queryParams as $key => $value) {
        if (!in_array($key, $recognizedParams)) {
            renderIndex("Invalid parameter \"{$key}\"");
            exit();
        }
    }
}

// Recognized parameters
$recognizedParams = ['article'];

checkForUnrecognizedParams($recognizedParams, $queryParams);

// Determine what to serve
if ($articleParam) {
    // Check if the article specifier has correct format
		if (preg_match('/^\d{4}-\d{2}-\d{2}-\.?\d+$/', $articleParam)) {
				$articlePath = $articleDirectory . '/' . str_replace('-', '/', $articleParam) . '.txt';
        if (!renderArticle($articlePath)) {
            // Article not found, render index with error message
						renderIndex("The article \"$articleParam\" does not exist");
        }
    } else {
        renderIndex("Invalid article specifier \"$articleParam\" expected format is \"YYYY-MM-DD-#\""); // Please prepend and append a "caution" symbol to the message
    }
} else {
    // No parameters, render the index
    renderIndex();
}
?>