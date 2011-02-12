<?php

class FeaturedPhoto extends Entity
{
    static $table_name = 'featured_photos';

    static $table_attributes = array(
        'user_guid' => 0,
        'image_url' => '',        
        'x_offset' => 0,
        'y_offset' => 0,
        'weight' => 1,
        'href' => '',
        'caption' => '',
        'org_name' => '',
        'language' => '',
        'active' => 1
    );

    static function json_cache_key()
    {
        return make_cache_key("featuredphoto:json");
    }    
    
    function save()
    {
        get_cache()->delete(static::json_cache_key());
        parent::save();
    }    
    
    function delete()
    {
        get_cache()->delete(static::json_cache_key());
        parent::delete();
    }        
    
    static function get_json_array()
    {
        return cache_result(function() {    
            return json_encode(array_map(
                function($p) { return $p->js_properties(); }, 
                FeaturedPhoto::query()->where('active=1')->filter()
            ));   
        }, static::json_cache_key());
    }
    
    public function js_properties()
    {
        return array(
            'url' => $this->image_url,
            'x' => $this->x_offset,
            'y' => $this->y_offset,
            'weight' => $this->weight,
            'href' => $this->href,
            'caption' => $this->caption,
            'org' => $this->org_name
        );
    }    
}