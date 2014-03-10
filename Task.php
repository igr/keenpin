<?php

/**
 * Task is defined by a single line of a "t o d o . t x t".
 */

class Task
{
    protected $rawTask;     // raw task string, as passed to the ctor
    protected $task;        // task sans priority and completion marker/date
    
    protected $completed = false;   // flag for completed tasks
    protected $completedDate;       // completion date
    
    protected $priority;            // single-char uppercase priority, if found
    protected $createdDate;         // task creation date
    
    public $projects = array();     // list of project names (case-sensitive)
    public $contexts = array();     // list of context names (case-sensitive)

    protected $chunks = array();    // chunks

    protected $metadata = array();  // map of meta data, from the task, experimental

    /**
     * Creates a new task from a raw line. Expects non empty lines.
     */
    public function __construct($task) {
        date_default_timezone_set("UTC");   // todo see php.ini

        $task = trim($task);
        $this->rawTask = $task;
        
        $result = $this->parseCompleted($task);
        $result = $this->parsePriority($result);
        $result = $this->parseCreatedDate($result);

        $this->task = $result;
        $this->parseChunks($result);

        $this->parseMetadata($result);
    }
    
    /**
     * Returns number of days between creation date and
     * provided date, or current date.
     */
    public function age($endDate = null) {
        if ($this->hasCreatedDate() == false) {
            return null;
        }

        date_default_timezone_set("UTC");

        $end = $this->completedDate;
        if ($this->hasCompletedDate() == false) {
            if (!is_null($endDate)) {
                if (!($endDate instanceof DateTime)) {
                    $endDate = new DateTime($endDate);
                }
                $end = $endDate;
            } else {
                $end = new DateTime("now");
            }
        }

        $days = ($end->format("U") - $this->createdDate->format("U")) / 86400;
        return abs(ceil($days));
    }
    
    /**
     * Adds an array of projects to the list.
     * Prevents duplication in the array.
     */
    public function addProjects(array $projects) {
        $projects = array_map("trim", $projects);
        $this->projects = array_unique(array_merge($this->projects, $projects));
    }
    
    /**
     * Adds an array of contexts to the list.
     * Prevents duplication in the array.
     */
    public function addContexts(array $contexts) {
        $contexts = array_map("trim", $contexts);
        $this->contexts = array_unique(array_merge($this->contexts, $contexts));
    }
    
    /**
     * Access meta-properties, as held by key:value metadata in the task.
     * @param string $name The name of the meta-property.
     * @return string|null Value if property found, or null.
     */
    public function __get($name) {
        return isset($this->metadata[$name]) ? $this->metadata[$name] : null;
    }
    
    /**
     * Check for existence of a meta-property.
     * @param string $name The name of the meta-property.
     * @return boolean Whether the property is contained in the task.
     */
    public function __isset($name) {
        return isset($this->metadata[$name]);
    }
    
    /**
     * Rebuilds the task string.
     */
    public function __toString() {
        $task = "";
        if ($this->completed) {
            $task .= sprintf("x %s ", $this->getCreatedDateString());
        }
        
        if (isset($this->priority)) {
            $task .= sprintf("(%s) ", strtoupper($this->priority));
        }
        
        if (isset($this->createdDate)) {
            $task .= sprintf("%s ", $this->getCreatedDateString());
        }
        
        $task .= $this->task;
        return $task;
    }

    ###-------------------------------------------------------------------------------------

    public function isOpen() {
        return $this->completed != true;
    }

    public function isCompleted() {
        return $this->completed;
    }

    public function hasCompletedDate() {
        return $this->isCompleted() && isset($this->completedDate);
    }
    
    public function getCompletedDate() {
        return $this->isCompleted() && isset($this->completedDate) ? $this->completedDate : null;
    }

    public function getCompletedDateString() {
        return $this->isCompleted() && isset($this->completedDate) ? $this->completedDate->format("Y-m-d") : null;
    }

    public function getCreatedDate() {
        return isset($this->createdDate) ? $this->createdDate : null;
    }

    public function getCreatedDateString() {
        return isset($this->createdDate) ? $this->createdDate->format("Y-m-d") : null;
    }

    public function hasCreatedDate() {
        return isset($this->createdDate) ? true : null;
    }
    
    /**
     * Returns the remainder of the task, sans completed marker,
     * creation date and priority.
     */
    public function getTask() {
        return $this->task;
    }

    public function getRawTask() {
        return $this->rawTask;
    }
    
    public function getPriority() {
        return $this->priority;
    }

    public function hasPriority() {
        return ($this->priority != null) ? true : false;
    }

    public function getChunks() {
        return $this->chunks;
    }

    ###-------------------------------------------------------------------------------------
    
    /**
     * Looks for a "x " marker, optionally followed by a date (YYYY-MM-DD).
     */
    protected function parseCompleted($input) {
        $pattern = "/^(x) (\d{4}-\d{2}-\d{2}) /";

        $matchCount = preg_match($pattern, $input, $matches);
        if ($matchCount == 0) {
            if (substr($input, 0, 2) == "x ") {
                $this->completed = true;
                return substr($input, 2);
            }
        } else if ($matchCount == 1) {
            try {
                $this->completedDate = new DateTime($matches[2]);
            } catch (Exception $e) {
                return $input;
            }
            
            $this->completed = true;
            return substr($input, strlen($matches[0]));
        }
        return $input;
    }
    
    /**
     * Resolves priority marker - an uppercase letter in parentheses.
     */
    protected function parsePriority($input) {
        $pattern = "/^\(([A-Z])\) /";

        if (preg_match($pattern, $input, $matches) == 1) {
            $this->priority = $matches[1];
            return substr($input, strlen($matches[0]));
        }
        return $input;
    }
    
    /**
     * Find a creation date (after a priority marker).
     */
    protected function parseCreatedDate($input) {
        $pattern = "/^(\d{4}-\d{2}-\d{2}) /";
        if (preg_match($pattern, $input, $matches) == 1) {
            try {
                $this->createdDate = new DateTime($matches[1]);
            } catch (Exception $e) {
                return $input;
            }
            return substr($input, strlen($matches[0]));
        }
        return $input;
    }
    
    /**
     * Metadata can be held in the string in the format key:value.
     * This data can be accessed using __get() and __isset().
     */
    protected function parseMetadata($input) {
        // Match a word (alphanumeric+underscores), a colon, followed by
        // any non-whitespace character.
        $pattern = "/(?<=\s|^)(\w+):(\S+)(?=\s|$)/";
        if (preg_match_all($pattern, $input, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $this->metadata[$match[1]] = $match[2];
            }
        }
    }

    /**
     * Parse chunks of a task.
     */
    protected function parseChunks($input) {
        $words = preg_split("/[\s]+/", $input);
        $text = "";
        foreach ($words as $w) {
            $char = substr($w, 0, 1);
            if ($char === '+') {        // project
                if ($text != "") {
                    $this->chunks[] = $text;
                    $this->projects[] = substr($w, 1);
                    $text = "";
                }
                $this->chunks[] = $w;
            } else if ($char === '@') { // context
                if ($text != "") {
                    $this->chunks[] = $text;
                    $this->contexts[] = substr($w, 1);
                    $text = "";
                }
                $this->chunks[] = $w;
            } else {
                $text .= $w . " ";
            }
        }
        if ($text != "") {
            $this->chunks[] = $text;
        }
    }
}