<?php

class Controller_Post extends Controller_Profile
{
    protected $post;

    function before()
    {
        parent::before();

        $postId = $this->request->param('id');

        if ($postId == 'new')
        {
            $this->request->action = 'new';
            return;
        }

        $post = get_entity($postId);
        $org = $this->org;
        if ($post && $post->container_guid == $org->guid && $post->getSubtype() == T_blog)
        {
            $this->post = $post;
            return;
        }
        else
        {
            $this->use_public_layout();
            $this->org_page_not_found();
        }
    }

    function action_index()
    {
        $org = $this->org;
        $post = $this->post;

        $this->use_public_layout();

        if ($post->canEdit())
        {
            add_submenu_item(__("widget:edit"), "{$post->getUrl()}/edit", 'edit');
        }

        $title = __('widget:news');

        if (!$org->canView())
        {
            $org->showCantViewMessage();
            $body = '';
        }
        else
        {
            $body = $this->org_view_body($title, elgg_view("org/blogPost", array('entity'=> $post)));
        }

        $this->page_draw($title,$body);
    }

    function action_edit()
    {
        $this->require_editor();
        $post = $this->post;

        $title = __('blog:editpost');

        $cancelUrl = get_input('from') ?: $post->getUrl();

        add_submenu_item(__("canceledit"), $cancelUrl, 'edit');

        $org = $post->getContainerEntity();
        $area1 = elgg_view("org/editPost", array('entity' => $post));
        $body = elgg_view_layout("one_column_padded", elgg_view_title($title), $area1);

        $this->page_draw($title,$body);
    }

    function action_save()
    {
        $this->require_editor();
        $this->validate_security_token();
        $post = $this->post;
        $org = $this->org;

        $body = get_input('blogbody');

        if (get_input('delete'))
        {
            $org = $post->getContainerEntity();
            $post->disable();
            $post->save();
            system_message(__('blog:delete:success'));
            forward($org->getURL()."/news");
        }
        else if (empty($body))
        {
            register_error(__("blog:blank"));
            forward_to_referrer();
        }
        else
        {
            $post->setContent($body, true);
            $post->save();

            system_message(__("blog:updated"));
            forward($post->getUrl());
        }
    }

    function action_new()
    {
        $this->require_editor();
        $this->validate_security_token();

        $body = get_input('blogbody');
        $org = $this->org;

        if (empty($body))
        {
            register_error(__("blog:blank"));
            forward_to_referrer();
        }
        else
        {
            $uuid = get_input('uuid');

            $duplicates = NewsUpdate::queryByMetadata('uuid', $uuid)->where('container_guid=?',$org->guid)->filter();
            if (!sizeof($duplicates))
            {
                $post = new NewsUpdate();
                $post->owner_guid = get_loggedin_userid();
                $post->container_guid = $org->guid;
                $post->setContent($body, true);
                $post->uuid = $uuid;
                $post->save();

                system_message(__("blog:posted"));
            }
            else
            {
                $post = $duplicates[0];
            }

            forward($post->getURL());
        }
    }

    function action_preview()
    {
        $this->request->headers['Content-type'] = 'text/javascript';
        $this->request->response = json_encode($this->post->jsProperties());
    }

    function action_prev()
    {
        $this->redirect_delta(-1);
    }

    function action_next()
    {
        $this->redirect_delta(1);
    }

    function redirect_delta($delta)
    {
        $post = $this->post;

        $op = ($delta > 0) ? ">" : "<";
        $order = ($delta > 0) ? "asc" : "desc";

        $selectWhere = "SELECT * from entities WHERE type='object' AND enabled='yes' AND subtype=? AND container_guid=?";

        $entity = entity_row_to_elggstar(get_data_row("$selectWhere AND guid $op ? ORDER BY guid $order LIMIT 1",
            array(T_blog, $post->container_guid, $post->guid)
        ));
        if ($entity)
        {
            forward($entity->getURL());
        }

        $entity = entity_row_to_elggstar(get_data_row("$selectWhere ORDER BY guid $order LIMIT 1",
            array(T_blog, $post->container_guid)
        ));

        if ($entity)
        {
            forward($entity->getURL());
        }
    }
}