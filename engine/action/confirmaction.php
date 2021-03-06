<?php

class Action_ConfirmAction extends Action
{    
    function before()
    {
        Permission_Public::require_any();
    }

    function render()
    {
        Input::validate_security_token(true);       
        
        $message = Input::get_string('message');
        $ok_url = Input::get_string('ok');
        $cancel_url = Input::get_string('cancel');

        // hack to handle RedirectException by going back to previous page
        if (Session::get('messages'))
        {
            $this->redirect($cancel_url);
        }        
        else
        {        
            $this->page_draw(array(
                'title' => $message,
                'content' => view("input/confirm_action", array('ok_url' => $ok_url, 'cancel_url' => $cancel_url))
            ));        
        }
    }
}    