<?php
    $relationship = $vars['relationship'];
    $reverse = $vars['reverse'];
    $widget = $vars['widget'];

    $org = $relationship->get_container_entity();
    $subject = $relationship->get_subject_organization();
    
    echo "<p>";
    echo sprintf(__('email:salutation', $subject->language), escape($subject->name));
    echo "</p>";    
    
    $tr = array(
        '{name}' => escape($org->name), 
        '{subject}' => escape($subject->name), 
        '{type}' => $relationship->msg('header', $subject->language)
    );
    
    echo "<p>";
    echo strtr(__('network:notify_added_info', $subject->language), $tr);    
    echo "<br />";

    $widget_url = abs_url($widget->get_url());

    echo "<a href='{$widget_url}'>{$widget_url}</a>";
    echo "</p>";
    
    if (!$reverse)
    {    
        $reverse_type = Relationship::get_reverse_type($relationship->type);        
        $tr['{type}'] = Relationship::msg_for_type($reverse_type, 'header', $subject->language);
        
        echo "<p>";
        echo strtr(__('network:notify_added_instructions', $subject->language), $tr);        
        echo "<br />";
        
        $subjectWidget = Widget_Network::get_or_new_for_entity($subject);

        $url = secure_url("{$subjectWidget->get_edit_url()}?action=add_relationship&type={$reverse_type}&org_guid={$org->guid}&username={$subject->username}");
        echo "<a href='{$url}'>{$url}</a>";
        echo "</p>";        
    }
