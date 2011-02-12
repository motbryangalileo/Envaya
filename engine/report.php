<?php

class Report extends Entity
{
    static $table_name = 'reports';
    static $table_attributes = array(
        'report_guid' => 0,
        'status' => 0,
    );      
    private $fields = null;    
    private $field_defs = null;
   
    function get_date_text()
    {
        return friendly_time($this->time_created);
    }
    
    function get_title()
    {
        return $this->get_report_definition()->get_title();
    }
    
    function get_field_definitions()
    {
        if ($this->field_defs == null)
        {
            $this->field_defs = $this->get_handler()->get_field_definitions($this);
        }
        return $this->field_defs;
    }
    
    function get_field_definition($field_name)
    {
        $field_defs = $this->get_field_definitions();
        return @$field_defs[$field_name];
    }
    
    function get_status_text()
    {
        switch ($this->status)
        {
            case ReportStatus::Blank:   return __('report:status_blank');
            case ReportStatus::Draft:   return __('report:status_draft');
            case ReportStatus::Submitted: return __('report:status_submitted');
            case ReportStatus::Approved: return __('report:status_approved');
            default: return __('report:status_unknown');
        }    
    }
    
    function get_url()
    {
        return $this->get_container_entity()->get_url()."/report/".$this->guid;
    }

    function get_edit_url()
    {
        return $this->get_url()."/edit";
    }
    
    function get_report_definition()
    {
        return get_entity($this->report_guid);
    }
    
    function get_handler()
    {
        return $this->get_report_definition()->get_handler();
    }

    function can_edit()
    {
        return parent::can_edit() && ($this->status < ReportStatus::Submitted || Session::isadminloggedin());
    }
    
    function render_view()
    {
        return $this->get_handler()->view($this);
    }
    
    function render_edit()
    {
        return $this->get_handler()->edit($this);
    }
    
    function &get_fields()
    {
        if ($this->fields == null)
        {
            $fields = array();
            foreach (ReportField::query()->where('report_guid = ?', $this->guid)->filter() as $field)
            {
                $fields[$field->name] = $field;
            }
            $this->fields = $fields;
        }
        return $this->fields;
    }   
    
    function get_field($name)
    {
        $fields = &$this->get_fields();
        if (!isset($fields[$name]))
        {
            $field = new ReportField();
            $field->name = $name;
            $field->set_report($this);            
            $fields[$name] = $field;
        }
        
        return $fields[$name];
    }
    
    function save()
    {
        parent::save();
    
        foreach ($this->get_fields() as $name => $field)
        {
            if ($field->dirty)
            {
                $field->save();
            }
        }
        
        $org = $this->get_container_entity();
        $reportsWidget = $org->get_widget_by_name('reports');
        if (!$reportsWidget->is_active())
        {
            $reportsWidget->enable();
            $reportsWidget->save();            
        }
    }

    function can_manage()
    {
        $report_def = $this->get_report_definition();
        return Session::isadminloggedin() || Session::get_loggedin_userid() == $report_def->container_guid;
    }
    
    function post_feed_items()
    {
        if (FeedItem::query()->where('subject_guid = ?', $this->guid)->count() == 0)
        {
            $org = $this->get_container_entity();   
            post_feed_items($org, 'report', $this);
        }
    }                    
}