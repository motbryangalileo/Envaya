<?php

/*
 * The Request class provides utility functions that provide information 
 * about the currently executing request.
 *
 * This class is just a convenience wrapper for the PHP built-in superglobal 
 * variable $_SERVER, so all methods are static.
 */
class Request
{            
    static function get_client_ip()
    {
        // ignore $_SERVER['HTTP_X_FORWARDED_FOR'] and $_SERVER['HTTP_CLIENT_IP'] since they can be easily spoofed
    
        return @$_SERVER['REMOTE_ADDR'];
    }
    
    static function get_uri()
    {
        return @$_SERVER['PATH_INFO'];
    }
    
    static function get_user_agent()
    {
        return @$_SERVER['HTTP_USER_AGENT'];
    }    
    
    static function get_host()
    {
        return @$_SERVER['HTTP_HOST'] ?: Config::get('domain');
    }
    
    static function get_protocol()
    {
        return static::is_secure() ? 'https' : 'http';
    }
    
    static function get_query()
    {
        $query_string = @$_SERVER['QUERY_STRING'];
        $connector = ($query_string) ? "?" : "";        
        return "{$connector}{$query_string}";   
    }
    
    private static $full_original_url = null;
    
    static function full_original_url()
    {
        if (!isset(static::$full_original_url))
        {    
            $protocol = static::get_protocol();
            $host = static::get_host();        
            $path = static::get_uri();
            $query = static::get_query();
            static::$full_original_url = "{$protocol}://{$host}{$path}{$query}";
        }
        return static::$full_original_url;
    }
    
    static function is_post()
    {
        return @$_SERVER['REQUEST_METHOD'] == "POST";
    }   
    
    static function get_method()
    {
        return @$_SERVER['REQUEST_METHOD'];
    }
    
    private static $secure = null;
    
    static function is_secure()
    {
        if (!isset(static::$secure))
        {    
            if (!empty($_SERVER['HTTPS']) AND filter_var($_SERVER['HTTPS'], FILTER_VALIDATE_BOOLEAN))
            {
                static::$secure = true;
            }        
            else
            {
                static::$secure = false;
            }
        }
        return static::$secure;
    }   

    static function is_mobile_browser()
    {
        if (isset($_SERVER['BROWSER_TYPE'])) // from custom nginx variable (allowing nginx to cache mobile/desktop versions of page)
        {
            return $_SERVER['BROWSER_TYPE'] == 'mobile';
        }
    
        $useragent = @$_SERVER['HTTP_USER_AGENT'];

        // from http://detectmobilebrowsers.com/ (4/14/2012)
        if (preg_match('/android.+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)
         || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i',substr($useragent,0,4)))
        {
            return true;
        }
        else
        {
            return false;
        }
    }        
    
    // note this doesn't work with current nginx caching -- would need to bump regex up to nginx
    
    static $is_bot = null;    
    
    static function is_bot()
    {
        $useragent = @$_SERVER['HTTP_USER_AGENT'];
    
        if (!isset(static::$is_bot))
        {    
            if (preg_match('#[Bb]ot|[Ss]pider|[Cc]rawler|[Ss]earch|Slurp|ScoutJet|Funnelback|Yandex|Mediapartners|ia_archiver|Teoma|Ezooms|facebookexternalhit|Sogou|BPImageWalker|Filecrop|(Microsoft URL Control)#', $useragent))
            {
                static::$is_bot = true;
            }
            else
            {
                static::$is_bot = false;
            }
        }
        return static::$is_bot;
    }
}
