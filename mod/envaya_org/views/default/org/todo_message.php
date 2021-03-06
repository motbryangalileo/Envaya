<?php

$org = $vars['org'];    
if (!Session::get('hide_todo'))
{               
    $todoItems = array();
    $doneItems = array();    
   
    foreach (TodoItem::get_list($org) as $item)
    {
        if ($item->done)
        {
            $doneItems[] = $item;
        }
        else
        {
            $todoItems[] = $item;
        }
    }
    
?>
<?php 
if (sizeof($todoItems))
{
    echo view('js/messages'); 
    echo view('js/xhr');
?>
<script type='text/javascript'>
function hideTodo()
{
    hideMessages('todo_container');
    fetchJson("/org/hide_todo", function(){});
}
</script>
<div class='todo_container' id='todo_container'>
<div class='good_messages'>
<a class='hideMessages' style='margin-right:0px;margin-top:-3px;' href='javascript:hideTodo()' onclick='ignoreDirty()'></a>
<?php
$messages = SessionMessages::get_register('messages');
if ($messages)
{

    foreach ($messages as $message)
    {
        echo "<p><strong>$message</strong></p>";
    }
}
?>
<table>
<tr>
<th><?php echo __('todo:todo') ?></th>
<th><?php echo __('todo:done') ?></th>
</tr>
<tr>
<td>
<ul class='todo_steps'>
<?php
foreach ($todoItems as $todoItem)
{
    echo "<li>{$todoItem->link}</li>";
}
?>
</ul>
</td>
<td>
<ul class='done_steps'>
<?php 
foreach ($doneItems as $doneItem)
{
    echo "<li>{$doneItem->link}</li>";
}
?>
</ul>
</td>
</tr>
</table>

</div>
</div>
<?php 
}
}
?>