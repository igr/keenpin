<?php

/**
 * Task list.
 */

require_once "Task.php";

class TodoList implements Countable
{
    public static $lineSeparator = "\n";
    
    protected $tasks = array();

    public function __construct(array $tasks = null) {
        if (!is_null($tasks)) {
            $this->addTasks($tasks);
        }
    }

    /**
     * Adds single task. Task can be either a string or
     * a Task object.
     */
    public function addTask($task) {
        if (!($task instanceof $task)) {
            $task = new Task((string) $task);
        }
        $this->tasks[] = $task;
    }

    /**
     * Adds array of tasks.
     */
    public function addTasks(array $tasks) {
        foreach ($tasks as $task) {
            $this->addTask($task);
        }
    }
    
    /**
     * Parses tasks from a newline separated string
     * @param string $taskFile A newline-separated list of tasks.
     */
    public function parseTasks($taskFile) {
        foreach (explode(self::$lineSeparator, $taskFile) as $line) {
            $line = trim($line);
            if (strlen($line) > 0) {
                $this->addTask($line);
            }
        }
    }

    /**
     * Returns tasks list.
     */
    public function getTasks() {
        return $this->tasks;
    }

    public function __toString() {
        $file = "";
        foreach ($this->tasks as $task) {
            $file .= $task . self::$lineSeparator;
        }
        return trim($file);
    }

    /**
     * Returns total number of tasks.
     */
    public function count() {
        return count($this->tasks);
    }

    /**
     * Returns total number of open tasks.
     */
    public function countOpen() {
        $count = 0;
        foreach ($this->tasks as $task) {
            if ($task->isOpen()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Returns total number of closed tasks.
     */
    public function countCompleted() {
        $count = 0;
        foreach ($this->tasks as $task) {
            if ($task->isCompleted()) {
                $count++;
            }
        }
        return $count;
    }
}