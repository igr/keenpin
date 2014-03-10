<?php
/**
 * keenpin library.
 */

require_once "config.php";
require_once "TodoList.php";

### ----------------------------------------------------------------- system

function redirect($target) {
    header("Location: " . $target);
}

function accessDenied() {
    header('HTTP/1.0 404 Not Found');
    include("404.php");
    exit();
}


### ----------------------------------------------------------------- utils

/**
 * Generates password hashes.
 */
function generateHash($plainText, $salt = null) {
    if ($salt === null) {
        $salt = substr(md5(uniqid(rand(), true)), 0, 32);
    } else {
        $salt = substr($salt, 0, 32);
    }
	
    return $salt . sha1($salt . $plainText);
}

/**
 * Checks if password match.
 */
function checkListPassword($template, $password, $passwordFile) {

    $pwd = file_get_contents($passwordFile);
    $hash = generateHash($password, $template);

    return $hash === $pwd;
}



### ----------------------------------------------------------------- tasklist

/**
 * Writes task list file.
 */
function writeTaskList($content) {
    file_put_contents("todo.txt", $content);
}


/**
 * Generates html pages from template.
 * $tplName : template name
 * $page : desired page (ie : index.html, edit.html)
 */
function generateKeenPin($tplName, $page) {
 
    // load tasks
    $content = file_get_contents('todo.txt');
    $taskList = new TodoList;
    $taskList->parseTasks($content);

    // load template regions
	$listIni = parse_ini_file('./tpl/'.$tplName.'/list.txt');
    $html = file_get_contents('./tpl/'.$tplName.'/_template.html');

    // global vars
    $count = $taskList->count();
    $countOpen = $taskList->countOpen();
    $countCompleted = $taskList->countCompleted();
    $done = round($countCompleted * 100 / $count, 2);

	// start replacing templated variables
    $html = str_replace("\${keenpin.update}", "update.php", $html);
	$html = str_replace("\${keenpin.edit}", "edit.php", $html);
	$html = str_replace("\${keenpin.index}", "index.php", $html);
    $html = str_replace("\${list.tplName}", htmlspecialchars($tplName), $html);
	$html = str_replace("\${list.tplDir}", htmlspecialchars('./tpl/'.$tplName), $html);
    $html = str_replace("\${list.raw}", htmlspecialchars($content), $html);
    $html = str_replace("\${list.total}", $count, $html);
    $html = str_replace("\${list.totalOpen}", $countOpen, $html);
    $html = str_replace("\${list.totalCompleted}", $countCompleted, $html);
    $html = str_replace("\${list.donePercent}", $done, $html);
	
    foreach($listIni as $key => $value) {
        $html = str_replace("\${list." . $key . "}", $value, $html);
    }

	// parse task list 
	$html = parseTaskLists($taskList, $html);
	
	// parse If blocks
    $html = parseIfBlocks($html);

    // collect blocks
    $blocks = array();
    $ndx = 0;
    while (true) {
        $ndx = strpos($html, "[kp:page=", $ndx);
        if ($ndx == false) {
            break;
        }
        $ndx1 = $ndx + 9;
        $ndx2 = strpos($html, "]", $ndx1);
        $ndx3 = strpos($html, "[/kp:page]", $ndx1);
        $name = substr($html, $ndx + 9, $ndx2 - $ndx1);
        $block = substr($html, $ndx2 + 1, $ndx3 - $ndx2 - 1);

        $blocks[] = array($name, $ndx, $block);
        $html = substr($html, 0, $ndx) . substr($html, $ndx3 + 11);
    }

    // generate desired pages
    foreach ($blocks as $block) {
        $name = $block[0];
        $ndx = $block[1];
		
		if ($name == $page) {
			$output = substr($html, 0, $ndx) . $block[2] . substr($html, $ndx);
		}
    }
	
	return $output;
}


function parseTaskLists($taskList, $html) {
    $tagStart = "[kp:taskList]";
    $tagEnd = "[/kp:taskList]";

    while(true) {
        $posStart = strpos($html, $tagStart);
        if ($posStart == false) {
            break;
        }

        $posEnd = strpos($html, $tagEnd, $posStart);

        $taskTemplate = substr($html, $posStart + strlen($tagStart), $posEnd - $posStart - strlen($tagStart));
        $prefix = substr($html, 0, $posStart);
        $suffix = substr($html, $posEnd + strlen($tagEnd));

        // generate

        $html = $prefix;
        $total = $taskList->count();
        $tasks = $taskList->getTasks();

        for ($i = 0; $i < $total; $i++) {
            $t = $tasks[$i];

            // task variables
            $taskHtml = $taskTemplate;

            $taskHtml = parseTaskTokens($t, $taskHtml);

            $taskHtml = str_replace("\${t.index}", $i, $taskHtml);
            $taskHtml = str_replace("\${t.number}", $i + 1, $taskHtml);
            $taskHtml = str_replace("\${t.task}", htmlspecialchars($t->getTask()), $taskHtml);
            $taskHtml = str_replace("\${t.raw}", htmlspecialchars($t->getRawTask()), $taskHtml);
            $taskHtml = str_replace("\${t.priority}", $t->getPriority(), $taskHtml);
            $taskHtml = str_replace("\${t.hasPriority}", ($t->hasPriority() ? 'true' : 'false'), $taskHtml);
            $taskHtml = str_replace("\${t.open}", ($t->isOpen() ? 'true' : 'false'), $taskHtml);
            $taskHtml = str_replace("\${t.completed}", ($t->isCompleted() ? 'true' : 'false'), $taskHtml);
            $taskHtml = str_replace("\${t.hasCreatedDate}", ($t->hasCreatedDate() ? 'true' : 'false'), $taskHtml);
            $taskHtml = str_replace("\${t.createdDate}", $t->getCreatedDateString(), $taskHtml);
            $taskHtml = str_replace("\${t.age.days}", $t->age(), $taskHtml);
            $taskHtml = str_replace("\${t.hasCompletedDate}", ($t->hasCompletedDate() ? 'true' : 'false'), $taskHtml);
            $taskHtml = str_replace("\${t.completedDate}", $t->getCompletedDateString(), $taskHtml);

            $html .= $taskHtml;
        }

        $html .= $suffix;
    }
    return $html;
}

/**
 * Parse task tokens.
 */
function parseTaskTokens($t, $taskHtml) {
    $tagStart = "[kp:task]";
    $tagEnd = "[/kp:task]";

    while (true) {
        $posStart = strpos($taskHtml, $tagStart);
        if ($posStart == false) {
            break;
        }

        $posEnd = strpos($taskHtml, $tagEnd, $posStart);
        $prefix = substr($taskHtml, 0, $posStart);
        $suffix = substr($taskHtml, $posEnd + strlen($tagEnd));

        $tagText = extractTagContent($taskHtml, "[kp:task:txt]", "[/kp:task:txt]");
        $tagContext = extractTagContent($taskHtml, "[kp:task:ctx]", "[/kp:task:ctx]");
        $tagProject = extractTagContent($taskHtml, "[kp:task:prj]", "[/kp:task:prj]");

        $taskHtml = "";

        foreach ($t->getChunks() as $chunk) {
            $char = substr($chunk, 0, 1);
            if ($char === '@') {
                $taskHtml .= str_replace("\${t.task.context}", htmlspecialchars(substr($chunk, 1)), $tagContext);
            } else if ($char === '+') {
                $taskHtml .= str_replace("\${t.task.project}", htmlspecialchars(substr($chunk, 1)), $tagProject);
            } else {
                $taskHtml .= str_replace("\${t.task.text}", htmlspecialchars($chunk), $tagText);
            }
        }

        $taskHtml = $prefix . $taskHtml . $suffix;
    }

    return $taskHtml;
}

function extractTagContent($text, $tagStart, $tagEnd) {
    $posStart = strpos($text, $tagStart);
    if ($posStart == false) {
        return null;
    }
    $posEnd = strpos($text, $tagEnd, $posStart);
    return substr($text, $posStart + strlen($tagStart), $posEnd - $posStart - strlen($tagStart));
}

/**
 * Parses IF blocks.
 */
function parseIfBlocks($html) {
    $tagStart = "[kp:if ";
    $tagEnd = "[/kp:if]";

    while (true) {
        $posStart = strpos($html, $tagStart);
        if ($posStart == false) {
            break;
        }
        $posEnd = strpos($html, $tagEnd, $posStart);

        while (true) {

            // check for nested if blocks
            $pos2 = strpos($html, $tagStart, $posStart + strlen($tagStart));

            if ($pos2 == false) {
                break;
            }
            if ($pos2 > $posEnd) {
                break;
            }
            // inner detected
            $posStart = $pos2;
        }

        $prefix = substr($html, 0, $posStart);
        $suffix = substr($html, $posEnd + strlen($tagEnd));

        $posEnd2 = strpos($html, "]", $posStart);

        $expression = substr($html, $posStart + strlen($tagStart), $posEnd2 - $posStart - strlen($tagStart));

        $inner = "";
        $bool = evalExpression($expression);

        if ($bool) {
            $inner = substr($html, $posEnd2 + 1, $posEnd - $posEnd2 - 1);
        }

        $html = $prefix . $inner . $suffix;
    }
    return $html;
}

/**
 * Evaluate expression in a safe way.
 */
function evalExpression($expression) {
    $result = false;
    $expression = trim($expression);
    if ($expression == '') {
        return false;
    }
    $expression = str_replace("(", "", $expression);
    $expression = str_replace(")", "", $expression);
    $expression = str_replace("[", "", $expression);
    $expression = str_replace("]", "", $expression);
    $expression = str_replace("$", "", $expression);
    $expression = "\$result = (" . $expression . ") ? true : false;";
    eval($expression);
    return $result;
}
?>
