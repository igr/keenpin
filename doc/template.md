# Make your own beautiful template

_keenpin_ template is a single HTML page (named `_template.html`) that contains various blocks and macros. Using the content of `todo.txt`, the template is processed and one or more HTML pages are generated.

Template may contain anything you like. If you are adventurous enough, you can add some javascript magic and even make list dynamic!

## blocks

Block is content between two keenpin tags. Each keenpin tag starts with `[kp:<name>` and ends with `[/kp:<name>]`. Here are the available blocks.

### `[kp:page=<file_name>]`

Since single template can generate many pages, page block defines content that will be part of the `<file_name>.html` page. Therefore, to generate at least one page (usually the `index.html`), you must have at least one page block in your template!

Example:

    ...
    [kp:page=index]
        the content that will be generated in index.html
    [/kp:page]
    ...


### `[kp:taskList]`

`taskList` block simply defines the content that will be iterated for each task from the `todo.txt` list. Inside `taskList` block several task-related macros are available.

Example:

    [kp:page=index]
        <ul>
            [kp:taskList]
                <li>${t.task}</li>
            [/kp:taskList]
        </ul>
    [/kp:page]


### `[kp:if <boolean_expression>]`

`if` block defines the content that will be included if boolean expression is `true`. You can use macros in the expression!

Example:

    [kp:if ${t.open}]
        <li>${t.task}</li>
    [/kp:if]

### `[kp:task]`

When iterating tasks with `taskList` block, you can display task content as a string (using a macro): including the contexts and projects, without prefix flags and dates. This is not good enough for templates that want to show more details. Use `task` block to reach task elements. It splits task content string into chunks of different types: text content, project and context. Each type is then rendered independently, allowing you to design projects and context names. The order of sub-blocks is not important.

Example:

    [kp:taskList]
        [kp:task]
            [kp:task:txt]
                ${t.task.text}
            [/kp:task:txt]
            [kp:task:ctx]
                <span class="ctx">${t.task.context}</span>
            [/kp:task:ctx]
            [kp:task:prj]
                <span class="prj">${t.task.project}</span>
            [/kp:task:prj]
        [/kp:task]
    [/kp:taskList]


## Macros

Macros are simple value placeholders that will be replaced with real value in the generated pages. There are two types of macros:

+ global macros, available on whole page, and
+ block-related macros, available in certain block.

Here is the macro list.

### global macros

+ `${list.name}` - list name
+ `${list.total}` - total number of tasks
+ `${list.totalOpen}` - number of open tasks
+ `${list.totalCompleted}` - number of completed tasks
+ `${list.donePercent}` - percentage of open tasks
+ `${list.raw}` - raw content of whole todo.txt. May be used for editable keepin templates.
+ `${list.*}` - all other custom macros defined in list.txt

### taskList block macros

+ `${t.index}` - zero-based index of current task
+ `${t.number}` - one-based ordinal number of task
+ `${t.task}` - task content, sans completed marker, creation date and priority
+ `${t.raw}` - complete task content
+ `${t.priority}` - task priority
+ `${t.hasPriority}` - set if task has priority
+ `${t.open}` - set if task is still open
+ `${t.completed}` - set if task is completed
+ `${t.hasCreatedDate}` - set if task has completed date
+ `${t.createdDate}` - tasks created date
+ `${t.age.days}` - task age, if task has created date
+ `${t.hasCompletedDate}` - set if task has competition date
+ `${t.completedDate}` - tasks competition date

### task block macros

+ `${t.task.context}` - task context
+ `${t.task.project}` - task project
+ `${t.task.text}` - any other task text chunk

## Updating

It's easy to update the list, just send the POST request to the value of `${keenpin.update}` macro, with following request parameters:

+ `tasks` - complete todo.txt raw content, optional
+ `password` - list password
+ `listName` - task list name

Example: 

    <form action="${keenpin.update}" method="POST">
        <textarea name="tasks">${list.raw}</textarea>
        <input type="password" name="password"></div>
        <input type="hidden" name="list" value="${list.name}">
        <input type="submit" value="Save">
    </form>

If `tasks` parameter is omitted, files will be just regenerated using existing version of `todo.txt` on the server!