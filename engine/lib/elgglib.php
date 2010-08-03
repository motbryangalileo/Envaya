<?php

    /**
     * Elgg library
     * Contains important functionality core to Elgg
     *
     * @package Elgg
     * @subpackage Core
     * @author Curverider Ltd
     * @link http://elgg.org/
     */

    /**
     * Provides auto-loading support of Kohana classes, as well as transparent
     * extension of classes that have a _Core suffix.
     *
     * Class names are converted to file names by making the class name
     * lowercase and converting underscores to slashes:
     *
     *     // Loads engine/my/class/name.php
     *     auto_load('My_Class_Name');
     *
     * @param   string   class name
     * @return  boolean
     */
    function auto_load($class)
    {
        error_log("$class");
    
        // Transform the class name into a path
        $file = str_replace('_', '/', strtolower($class));

        global $CONFIG;

        $path = $CONFIG->path."engine/$file.php";

        if (is_file($path))
        {
            require $path;
            return TRUE;
        }

        return FALSE;
    }

    function url_with_param($url, $param, $value)
    {
        $url = parse_url($url);
        parse_str(@$url['query'],$query);
        $query[$param] = $value;

        $prefix = @$url['scheme'] ? $url['scheme']."://".$url['host'] : '';

        return $prefix.$url['path']."?".http_build_query($query);
    }

    function sanitize_html($html, $options = null)
    {
        require_once(dirname(dirname(__DIR__)).'/vendors/htmlpurifier/library/HTMLPurifier.auto.php');
        global $CONFIG;

        if (!$options)
        {
            $options = array();
        }
        $options['Cache.SerializerPath'] = $CONFIG->dataroot;
        $options['AutoFormat.Linkify'] = true;

        $purifier = new HTMLPurifier($options);
        return $purifier->purify( $html );
    }

    function escape($val)
    {
        return htmlentities($val, ENT_QUOTES, 'UTF-8');
    }

    function get_snippet($content, $maxLength = 100)
    {
        if ($content)
        {
            $cacheKey = "snippet_".md5($content)."_$maxLength";
            $cache = get_cache();
            $snippet = $cache->get($cacheKey);
            if (!$snippet)
            {
                $content = preg_replace('/<img[^>]+>/i', '', $content);
                $content = preg_replace('/<\/(p|h1|h2|h3)>/i', '</$1> <br />', $content);

                $tooLong = strlen($content) > $maxLength;
                // todo: multi-byte support
                if ($tooLong)
                {
                    $shortStr = substr($content, 0, $maxLength);

                    $lastSpace = strrpos($shortStr, ' ');
                    if ($lastSpace && $lastSpace > $maxLength / 2)
                    {
                        $shortStr = substr($shortStr, 0, $lastSpace);
                    }

                    $content = $shortStr;
                }
                $content = sanitize_html($content, array('HTML.AllowedElements' => 'a,em,strong,br','AutoFormat.RemoveEmpty' => true));
                $content = preg_replace('/(<br \/>\s*)+/', ' &ndash; ', $content);
                $content = preg_replace('/&ndash;\s*$/', '', $content);
                $content = preg_replace('/^\s*&ndash;/', '', $content);

                if ($tooLong)
                {
                    $content = $content."...";
                }
                $snippet = $content;
                $cache->set($cacheKey, $snippet);
            }

            return $snippet;
        }
        return '';
    }

    function isPearError($res)
    {
        return is_a($res, 'PEAR_Error');
    }

    function forward_to_referrer()
    {
        forward($_SERVER['HTTP_REFERER']);
    }

    function not_found()
    {
        $title = __('page:notfound');
        $body = elgg_view_layout('one_column_padded', elgg_view_title($title), __('page:notfound:details')."<br/><br/><br/>");
        header("HTTP/1.1 404 Not Found");
        echo page_draw($title, $body);
        exit;
    }

    function restore_input($name, $value)
    {
        $prevInput = Session::get('input');
        if ($prevInput)
        {
            if (isset($prevInput[$name]))
            {
                $val = $prevInput[$name];
                unset($prevInput[$name]);
                Session::set('input', $prevInput);
                return $val;
            }
        }
        return $value;
    }

    function sanitize_image_size($size)
    {
        $size = strtolower($size);
        if (!in_array($size, array('large','medium','small','tiny','master','topbar')))
        {
            return "medium";
        }
        return $size;
    }

    function yes_no_options()
    {
        return array(
            'yes' => __('option:yes'),
            'no' => __('option:no'),
        );
    }

    function get_first_key($arr)
    {
        reset($arr);
        $pair = each($arr);
        $res = $pair[0];
        reset($arr);
        return $res;
    }    
    
    /**
     * Adds messages to the session so they'll be carried over, and forwards the browser.
     * Returns false if headers have already been sent and the browser cannot be moved.
     *
     * @param string $location URL to forward to browser to
     * @return nothing|false
     */

    function forward($location = "/")
    {
        global $CONFIG;
        if (!headers_sent())
        {
            if ($location && $location[0] == '/')
            {
                $location = substr($location, 1);
            }

            if ((substr_count($location, 'http://') == 0) && (substr_count($location, 'https://') == 0))
            {
                $location = $CONFIG->url . $location;
            }

            save_system_messages();

            header("Location: {$location}");
            exit;
        }
        return false;
    }

    /**
     * Templating and visual functionality
     */

    $CURRENT_SYSTEM_VIEWTYPE = "";

    /**
     * Override the view mode detection for the elgg view system.
     *
     * This function will force any further views to be rendered using $viewtype. Remember to call elgg_set_viewtype() with
     * no parameters to reset.
     *
     * @param string $viewtype The view type, e.g. 'rss', or 'default'.
     * @return bool
     */
    function elgg_set_viewtype($viewtype = "")
    {
        global $CURRENT_SYSTEM_VIEWTYPE;

        $CURRENT_SYSTEM_VIEWTYPE = $viewtype;

        return true;
    }

    /**
     * Return the current view type used by the elgg view system.
     *
     * By default, this function will return a value based on the default for your system or from the command line
     * view parameter. However, you may force a given view type by calling elgg_set_viewtype()
     *
     * @return string The view.
     */
    function elgg_get_viewtype()
    {
        global $CURRENT_SYSTEM_VIEWTYPE;

        return $CURRENT_SYSTEM_VIEWTYPE ?: get_input('view') ?: 'default';
    }

    /**
     * Handles templating views
     *
     * @see set_template_handler
     *
     * @param string $view The name and location of the view to use
     * @param array $vars Any variables that the view requires, passed as an array
     * @return string The HTML content
     */
        function elgg_view($view, $vars = null)
        {

            global $CONFIG;

            // basic checking for bad paths
            if (strpos($view, '..') !== false)
            {
                return false;
            }

            if (empty($vars))
            {
                $vars = array();
            }

            $vars['user'] = get_loggedin_user();
            $vars['config'] = $CONFIG;
            $vars['url'] = $CONFIG->url;
            $viewtype = elgg_get_viewtype();
            $viewDir = dirname(dirname(__DIR__)) . "/views/";
            $viewFile = $viewDir . "{$viewtype}/{$view}.php";

            $exists = file_exists($viewFile);

            ob_start();

            if ($exists && include_view($viewFile, $vars))
            {
                // success
            }
            else if (@$CONFIG->debug)
            {
                error_log(" [This view ({$view}) could not be included] ");
            }

            return ob_get_clean();
        }

        function include_view($viewFile, $vars)
        {
            return include $viewFile;
        }

    /**
     * Returns whether the specified view exists
     *
     * @param string $view The view name
     * @param string $viewtype If set, forces the viewtype
     * @return true|false Depending on success
     */
        function elgg_view_exists($view, $viewtype = '')
        {
            if (empty($viewtype))
                $viewtype = elgg_get_viewtype();

            return file_exists(dirname(dirname(__DIR__)) . "/views/{$viewtype}/{$view}.php");

        }

    /**
     * When given an entity, views it intelligently.
     *
     * Expects a view to exist called entity-type/subtype, or for the entity to have a parameter
     * 'view' which lists a different view to display.  In both cases, elgg_view will be called with
     * array('entity' => $entity) as its parameters, and therefore this is what the view should expect
     * to receive.
     *
     * @param ElggEntity $entity The entity to display
     * @param boolean $full Determines whether or not to display the full version of an object, or a smaller version for use in aggregators etc
     * @param boolean $bypass If set to true, elgg_view will bypass any specified alternative template handler; by default, it will hand off to this if requested (see set_template_handler)
     * @param boolean $debug If set to true, the viewer will complain if it can't find a view
     * @return string HTML (etc) to display
     */
        function elgg_view_entity(ElggEntity $entity, $full = false, $bypass = true, $debug = false) {

            // No point continuing if entity is null.
            if (!$entity) return '';

            $classes = array(
                'ElggUser' => 'user',
                'ElggObject' => 'object',
            );

            $entity_class = get_class($entity);

            if (isset($classes[$entity_class])) {
                $entity_type = $classes[$entity_class];
            } else {
                foreach($classes as $class => $type) {
                    if ($entity instanceof $class) {
                        $entity_type = $type;
                        break;
                    }
                }
            }
            if (!isset($entity_class)) return false;

            $subtype = $entity->getSubtypeName();
            if (empty($subtype)) { $subtype = $entity_type; }

            $contents = '';
            if (elgg_view_exists("{$entity_type}/{$subtype}")) {
                $contents = elgg_view("{$entity_type}/{$subtype}",array(
                                                                    'entity' => $entity,
                                                                    'full' => $full
                                                                    ), $bypass, $debug);
            }
            if (empty($contents)) {
                $contents = elgg_view("{$entity_type}/default",array(
                                                                'entity' => $entity,
                                                                'full' => $full
                                                                ), $bypass, $debug);
            }
            return $contents;
        }


    /**
     * Returns a view of a list of entities, plus navigation. It is intended that this function
     * be called from other wrapper functions.
     *
     * @see list_entities
     * @see list_user_objects
     * @see list_user_friends_objects
     * @see list_entities_from_metadata
     * @see list_entities_from_metadata_multi
     * @see list_entities_from_relationships
     * @see list_site_members
     *
     * @param array $entities List of entities
     * @param int $count The total number of entities across all pages
     * @param int $offset The current indexing offset
     * @param int $limit The number of entities to display per page
     * @param true|false $fullview Whether or not to display the full view (default: true)
     * @param true|false $viewtypetoggle Whether or not to allow users to toggle to gallery view
     * @param bool $pagination Whether pagination is offered.
     * @return string The list of entities
     */
        function elgg_view_entity_list($entities, $count, $offset, $limit, $fullview = true, $viewtypetoggle = true, $pagination = true) {

            $count = (int) $count;
            $offset = (int) $offset;
            $limit = (int) $limit;

            $context = get_context();

            return elgg_view('search/entity_list',array(
                'entities' => $entities,
                'count' => $count,
                'offset' => $offset,
                'limit' => $limit,
                'baseurl' => $_SERVER['REQUEST_URI'],
                'fullview' => $fullview,
                'context' => $context,
                'viewtypetoggle' => $viewtypetoggle,
                'pagination' => $pagination
              ));
        }

    /**
     * Displays an internal layout for the use of a plugin canvas.
     * Takes a variable number of parameters, which are made available
     * in the views as $vars['area1'] .. $vars['areaN'].
     *
     * @param string $layout The name of the views in canvas/layouts/.
     * @return string The layout
     */
        function elgg_view_layout($layout) {

            $arg = 1;
            $param_array = array();
            while ($arg < func_num_args()) {
                $param_array['area' . $arg] = func_get_arg($arg);
                $arg++;
            }
            if (elgg_view_exists("canvas/layouts/{$layout}")) {
                return elgg_view("canvas/layouts/{$layout}",$param_array);
            } else {
                return elgg_view("canvas/default",$param_array);
            }

        }

    /**
     * Returns a view for the page title
     *
     * @param string $title The page title
     * @param string $submenu Should a submenu be displayed? (default false, use not recommended)
     * @return string The HTML (etc)
     */
        function elgg_view_title($title, $args = null)
        {
            $title = elgg_view('page_elements/title', array('title' => $title, 'args' => $args));
            return $title;
        }


        function endswith( $str, $sub )
        {
            return substr($str, strlen($str) - strlen($sub)) == $sub ;
        }

        function rewrite_to_current_domain($url)
        {
            return Request::instance()->rewrite_to_current_domain($url);
        }

    /**
     * Adds an item to the submenu
     *
     * @param string $label The human-readable label
     * @param string $link The URL of the submenu item
     * @param boolean $onclick Used to provide a JS popup to confirm delete
     */
        function add_submenu_item($label, $link, $group = 'topnav', $onclick = false) {

            global $CONFIG;
            if (!isset($CONFIG->submenu)) $CONFIG->submenu = array();
            if (!isset($CONFIG->submenu[$group])) $CONFIG->submenu[$group] = array();
            $item = new stdClass;
            $item->value = $link;
            $item->name = $label;
            $item->onclick = $onclick;
            $CONFIG->submenu[$group][] = $item;

        }

        function get_submenu_group($groupname, $itemTemplate = 'canvas_header/submenu_template', $groupTemplate = 'canvas_header/submenu_group')
        {
            global $CONFIG;
            if (!isset($CONFIG->submenu))
            {
                return '';
            }

            $submenu_register = $CONFIG->submenu;
            if (!isset($submenu_register[$groupname]))
            {
                return '';
            }

            $submenu = array();
            $submenu_register_group = $CONFIG->submenu[$groupname];

            $parsedUrl = parse_url($_SERVER['REQUEST_URI']);

            foreach($submenu_register_group as $key => $item)
            {
                $selected = endswith($item->value, $parsedUrl['path']);

                $submenu[] = elgg_view($itemTemplate,
                    array(
                            'href' => $item->value,
                            'label' => $item->name,
                            'onclick' => $item->onclick,
                            'selected' => $selected,
                        ));
            }

            return elgg_view($groupTemplate, array(
                'submenu' => $submenu,
                'group_name' => $groupname
            ));
        }

    /**
     * Wrapper function to display search listings.
     *
     * @param string $icon The icon for the listing
     * @param string $info Any information that needs to be displayed.
     * @return string The HTML (etc) representing the listing
     */
        function elgg_view_listing($icon, $info) {
            return elgg_view('search/listing',array('icon' => $icon, 'info' => $info));
        }

    /**
     * Returns a representation of a full 'page' (which might be an HTML page, RSS file, etc, depending on the current view)
     *
     * @param unknown_type $title
     * @param unknown_type $body
     * @return unknown
     */
        function page_draw($title, $body, $preBody = "")
        {
            return elgg_view('pageshells/pageshell', array(
                    'title' => $title,
                    'body' => $body,
                    'preBody' => $preBody,
                    'sysmessages' => system_messages(null,"")
                  )
            );
        }

    /**
     * Displays a UNIX timestamp in a friendly way (eg "less than a minute ago")
     *
     * @param int $time A UNIX epoch timestamp
     * @return string The friendly time
     */
        function friendly_time($time) {

            $diff = time() - ((int) $time);
            if ($diff < 60) {
                return __("friendlytime:justnow");
            } else if ($diff < 3600) {
                $diff = round($diff / 60);
                if ($diff == 0) $diff = 1;
                if ($diff > 1)
                    return sprintf(__("friendlytime:minutes"),$diff);
                return sprintf(__("friendlytime:minutes:singular"),$diff);
            } else if ($diff < 86400) {
                $diff = round($diff / 3600);
                if ($diff == 0) $diff = 1;
                if ($diff > 1)
                    return sprintf(__("friendlytime:hours"),$diff);
                return sprintf(__("friendlytime:hours:singular"),$diff);
            } else if ($diff < 604800) {
                $diff = round($diff / 86400);
                if ($diff == 0) $diff = 1;
                if ($diff > 1)
                    return sprintf(__("friendlytime:days"),$diff);
                return sprintf(__("friendlytime:days:singular"),$diff);
            } else {
                $date = getdate($time);
                $now = getdate();

                $month = __("date:month:{$date['mon']}");
                $dateText = sprintf(__("date:withmonth"), $month, $date['mday']);

                if ($now['year'] != $date['year'])
                {
                    return sprintf(__("date:withyear"), $dateText, $date['year']);
                }
                else
                {
                    return $dateText;
                }
            }


        }

    /**
     * Library loading and handling
     */

    /**
     * Loads library files on start
     *
     * @param string $directory Full path to the directory to start with
     * @param string $file_exceptions A list of filenames (with no paths) you don't ever want to include
     * @param string $file_list A list of files that you know already you want to include
     * @return array Array of full filenames
     */
        function get_library_files($directory, $file_exceptions = array(), $file_list = array()) {

            if ($handle = opendir($directory))
            {
                while ($file = readdir($handle))
                {
                    if (endswith($file, '.php') && !in_array($file,$file_exceptions))
                    {
                        $file_list[] = $directory . "/" . $file;
                    }
                }
            }

            return $file_list;

        }

    /**
     * Message register handling
     * If no parameter is given, the function returns the array of messages so far and empties it.
     * Otherwise, any message or array of messages is added.
     *
     * @param string|array $message Optionally, a single message or array of messages to add
     * @param string $register By default, "errors". This allows for different types of messages, eg errors.
     * @return true|false|array Either the array of messages, or a response regarding whether the message addition was successful
     */

    function system_messages($message = "", $register = "messages")
    {
        static $allMessages;

        if (!isset($allMessages))
        {
            $messages = Session::get('messages');
            if ($messages)
            {
                $allMessages = $messages;
                Session::set('messages', null);
            }
            else
            {
                $allMessages = array();
            }
        }

        if (!isset($allMessages[$register]) && !$register)
        {
            $allMessages[$register] = array();
        }

        if (!empty($message))
        {
            $allMessages[$register][] = $message;
            return true;
        }
        else
        {
            if ($register)
            {
                $res = $allMessages[$register];
                unset($allMessages[$register]);
                return $res;
            }
            else
            {
                $res = $allMessages;
                $allMessages = null;
                return $res;
            }
        }
    }

    function save_system_messages()
    {
        $messages = system_messages('', '');

        if ($messages)
        {
            Session::set('messages', $messages);
        }
    }


    /**
     * An alias for system_messages($message) to handle standard user information messages
     *
     * @param string|array $message Message or messages to add
     * @return true|false Success response
     */
        function system_message($message) {
            return system_messages($message, "messages");
        }

    /**
     * An alias for system_messages($message) to handle error messages
     *
     * @param string|array $message Error or errors to add
     * @return true|false Success response
     */
        function register_error($error) {
            return system_messages($error, "errors");
        }

    /**
     * Event register
     * Adds functions to the register for a particular event, but also calls all functions registered to an event when required
     *
     * Event handler functions must be of the form:
     *
     *      event_handler_function($event, $object_type, $object);
     *
     * And must return true or false depending on success.  A false will halt the event in its tracks and no more functions will be called.
     *
     * You can then simply register them using the following function. Optionally, this can be called with a priority nominally from 0 to 1000, where functions with lower priority values are called first (note that priorities CANNOT be negative):
     *
     *      register_elgg_event_handler($event, $object_type, $function_name [, $priority = 500]);
     *
     * Note that you can also use 'all' in place of both the event and object type.
     *
     * To trigger an event properly, you should always use:
     *
     *      trigger_elgg_event($event, $object_type [, $object]);
     *
     * Where $object is optional, and represents the $object_type the event concerns. This will return true if successful, or false if it fails.
     *
     * @param string $event The type of event (eg 'init', 'update', 'delete')
     * @param string $object_type The type of object (eg 'system', 'blog', 'user')
     * @param string $function The name of the function that will handle the event
     * @param int $priority A priority to add new event handlers at. Lower numbers will be called first (default 500)
     * @param boolean $call Set to true to call the event rather than add to it (default false)
     * @param mixed $object Optionally, the object the event is being performed on (eg a user)
     * @return true|false Depending on success
     */

        function events($event = "", $object_type = "", $function = "", $priority = 500, $call = false, $object = null) {

            global $CONFIG;

            if (!isset($CONFIG->events)) {
                $CONFIG->events = array();
            } else if (!isset($CONFIG->events[$event]) && !empty($event)) {
                $CONFIG->events[$event] = array();
            } else if (!isset($CONFIG->events[$event][$object_type]) && !empty($event) && !empty($object_type)) {
                $CONFIG->events[$event][$object_type] = array();
            }

            if (!$call) {

                if (!empty($event) && !empty($object_type) && is_callable($function)) {
                    $priority = (int) $priority;
                    if ($priority < 0) $priority = 0;
                    while (isset($CONFIG->events[$event][$object_type][$priority])) {
                        $priority++;
                    }
                    $CONFIG->events[$event][$object_type][$priority] = $function;
                    ksort($CONFIG->events[$event][$object_type]);
                    return true;
                } else {
                    return false;
                }

            } else {

                $return = true;
                if (!empty($CONFIG->events[$event][$object_type]) && is_array($CONFIG->events[$event][$object_type])) {
                    foreach($CONFIG->events[$event][$object_type] as $eventfunction) {
                      if ($eventfunction($event, $object_type, $object) === false) {
                            return false;
                            //$return = false;
                            //break;
                        }
                    }
                }

                if (!empty($CONFIG->events['all'][$object_type]) && is_array($CONFIG->events['all'][$object_type])) {
                    foreach($CONFIG->events['all'][$object_type] as $eventfunction) {
                        if ($eventfunction($event, $object_type, $object) === false) {
                            return false;
                            //$return = false;
                            //break;
                        }
                    }
                }

                if (!empty($CONFIG->events[$event]['all']) && is_array($CONFIG->events[$event]['all'])) {
                    foreach($CONFIG->events[$event]['all'] as $eventfunction) {
                        if ($eventfunction($event, $object_type, $object) === false) {
                            return false;
                            //$return = false;
                            //break;
                        }
                    }
                }

                if (!empty($CONFIG->events['all']['all']) && is_array($CONFIG->events['all']['all'])) {
                    foreach($CONFIG->events['all']['all'] as $eventfunction) {
                        if ($eventfunction($event, $object_type, $object) === false) {
                            return false;
                            //$return = false;
                            //break;
                        }
                    }
                }
                return $return;

            }

            return false;

        }

    /**
     * Alias function for events, that registers a function to a particular kind of event
     *
     * @param string $event The event type
     * @param string $object_type The object type
     * @param string $function The function name
     * @return true|false Depending on success
     */
        function register_elgg_event_handler($event, $object_type, $function, $priority = 500) {
            return events($event, $object_type, $function, $priority);
        }

    /**
     * Alias function for events, that triggers a particular kind of event
     *
     * @param string $event The event type
     * @param string $object_type The object type
     * @param string $function The function name
     * @return true|false Depending on success
     */
        function trigger_elgg_event($event, $object_type, $object = null) {
            $return = true;
            $return1 = events($event, $object_type, "", null, true, $object);
            if (!is_null($return1)) $return = $return1;
            return $return;
        }

    /**
     * Register a function to a plugin hook for a particular entity type, with a given priority.
     *
     * eg if you want the function "export_user" to be called when the hook "export" for "user" entities
     * is run, use:
     *
     *      register_plugin_hook("export", "user", "export_user");
     *
     * "all" is a valid value for both $hook and $entity_type. "none" is a valid value for $entity_type.
     *
     * The export_user function would then be defined as:
     *
     *      function export_user($hook, $entity_type, $returnvalue, $params);
     *
     * Where $returnvalue is the return value returned by the last function returned by the hook, and
     * $params is an array containing a set of parameters (or nothing).
     *
     * @param string $hook The name of the hook
     * @param string $entity_type The name of the type of entity (eg "user", "object" etc)
     * @param string $function The name of a valid function to be run
     * @param string $priority The priority - 0 is first, 1000 last, default is 500
     * @return true|false Depending on success
     */
        function register_plugin_hook($hook, $entity_type, $function, $priority = 500) {
            global $CONFIG;

            if (!isset($CONFIG->hooks)) {
                $CONFIG->hooks = array();
            } else if (!isset($CONFIG->hooks[$hook]) && !empty($hook)) {
                $CONFIG->hooks[$hook] = array();
            } else if (!isset($CONFIG->hooks[$hook][$entity_type]) && !empty($entity_type)) {
                $CONFIG->hooks[$hook][$entity_type] = array();
            }

            if (!empty($hook) && !empty($entity_type) && is_callable($function)) {
                $priority = (int) $priority;
                if ($priority < 0) $priority = 0;
                while (isset($CONFIG->hooks[$hook][$entity_type][$priority])) {
                    $priority++;
                }
                $CONFIG->hooks[$hook][$entity_type][$priority] = $function;
                ksort($CONFIG->hooks[$hook][$entity_type]);
                return true;
            } else {
                return false;
            }

        }

    /**
     * Triggers a plugin hook, with various parameters as an array. For example, to provide
     * a 'foo' hook that concerns an entity of type 'bar', with a parameter called 'param1'
     * with value 'value1', that by default returns true, you'd call:
     *
     * trigger_plugin_hook('foo', 'bar', array('param1' => 'value1'), true);
     *
     * @see register_plugin_hook
     * @param string $hook The name of the hook to trigger
     * @param string $entity_type The name of the entity type to trigger it for (or "all", or "none")
     * @param array $params Any parameters. It's good practice to name the keys, i.e. by using array('name' => 'value', 'name2' => 'value2')
     * @param mixed $returnvalue An initial return value
     * @return mixed|null The cumulative return value for the plugin hook functions
     */
        function trigger_plugin_hook($hook, $entity_type, $params = null, $returnvalue = null) {
            global $CONFIG;

            if (!empty($CONFIG->hooks[$hook][$entity_type]) && is_array($CONFIG->hooks[$hook][$entity_type])) {
                foreach($CONFIG->hooks[$hook][$entity_type] as $hookfunction) {

                    $temp_return_value = $hookfunction($hook, $entity_type, $returnvalue, $params);
                    if (!is_null($temp_return_value)) $returnvalue = $temp_return_value;
                }
            }

            if (!empty($CONFIG->hooks['all'][$entity_type]) && is_array($CONFIG->hooks['all'][$entity_type])) {
                foreach($CONFIG->hooks['all'][$entity_type] as $hookfunction) {

                    $temp_return_value = $hookfunction($hook, $entity_type, $returnvalue, $params);
                    if (!is_null($temp_return_value)) $returnvalue = $temp_return_value;
                }
            }

            if (!empty($CONFIG->hooks[$hook]['all']) && is_array($CONFIG->hooks[$hook]['all'])) {
                foreach($CONFIG->hooks[$hook]['all'] as $hookfunction) {

                    $temp_return_value = $hookfunction($hook, $entity_type, $returnvalue, $params);
                    if (!is_null($temp_return_value)) $returnvalue = $temp_return_value;
                }
            }

            if (!empty($CONFIG->hooks['all']['all']) && is_array($CONFIG->hooks['all']['all'])) {
                foreach($CONFIG->hooks['all']['all'] as $hookfunction) {

                    $temp_return_value = $hookfunction($hook, $entity_type, $returnvalue, $params);
                    if (!is_null($temp_return_value)) $returnvalue = $temp_return_value;
                }
            }

            return $returnvalue;
        }

    /**
     * Error handling
     */

    /**
     * PHP Error handler function.
     * This function acts as a wrapper to catch and report PHP error messages.
     *
     * @see http://www.php.net/set-error-handler
     * @param int $errno The level of the error raised
     * @param string $errmsg The error message
     * @param string $filename The filename the error was raised in
     * @param int $linenum The line number the error was raised at
     * @param array $vars An array that points to the active symbol table at the point that the error occurred
     */
        function __elgg_php_error_handler($errno, $errmsg, $filename, $linenum, $vars)
        {
            $error = date("Y-m-d H:i:s (T)") . ": \"" . $errmsg . "\" in file " . $filename . " (line " . $linenum . ")";

            switch ($errno) {
                case E_USER_ERROR:
                        error_log("ERROR: " . $error);
                        register_error("ERROR: " . $error);

                        // Since this is a fatal error, we want to stop any further execution but do so gracefully.
                        throw new Exception($error);
                    break;

                case E_WARNING :
                case E_USER_WARNING :
                        if (error_reporting() != 0)
                        {
                            error_log("WARNING: " . $error);
                        }
                    break;

                default:
                    global $CONFIG;
                    if (isset($CONFIG->debug) && error_reporting() != 0)
                    {
                        error_log("DEBUG: " . $error);
                    }
            }

            return true;
        }

    /**
     * Custom exception handler.
     * This function catches any thrown exceptions and handles them appropriately.
     *
     * @see http://www.php.net/set-exception-handler
     * @param Exception $exception The exception being handled
     */

        function __elgg_php_exception_handler($exception) {

            error_log("*** FATAL EXCEPTION *** : " . $exception);
            ob_end_clean(); // Wipe any existing output buffer
            $body = elgg_view("messages/exceptions/exception",array('object' => $exception));
            echo page_draw(__('exception:title'), $body);


            global $CONFIG;
            if ($CONFIG->error_emails_enabled)
            {
                $lastErrorEmailTimeFile = "{$CONFIG->dataroot}last_error_time";
                $lastErrorEmailTime = (int)file_get_contents($lastErrorEmailTimeFile);
                $curTime = time();

                if ($curTime - $lastErrorEmailTime > 60)
                {
                    file_put_contents($lastErrorEmailTimeFile, "$curTime", LOCK_EX);

                    $class = get_class($exception);
                    $ex = print_r($exception, true);
                    $server = print_r($_SERVER, true);

                    send_admin_mail("$class: {$_SERVER['REQUEST_URI']}", "
Exception:
==========
$ex



_SERVER:
=======
$server
                ", null, true);
                }
            }
        }

    /**
     * Data lists
     */

    $DATALIST_CACHE = null;

    /**
     * Get the value of a particular piece of data in the datalist
     *
     * @param string $name The name of the datalist
     * @return string|false Depending on success
     */
        function datalist_get($name)
        {
            //var_dump(debug_backtrace());

            global $DATALIST_CACHE;

            if (!is_array($DATALIST_CACHE))
            {
                $cache = get_cache();

                $DATALIST_CACHE = $cache->get('datalist');

                if (!is_array($DATALIST_CACHE))
                {
                    $DATALIST_CACHE = array();

                    $result = get_data("SELECT * from datalists");
                    if ($result)
                    {
                        foreach ($result as $row)
                        {
                            $DATALIST_CACHE[$row->name] = $row->value;
                        }
                    }

                    $cache->set('datalist', $DATALIST_CACHE);
                }
            }

            return @$DATALIST_CACHE[$name];
        }

    /**
     * Sets the value for a system-wide piece of data (overwriting a previous value if it exists)
     *
     * @param string $name The name of the datalist
     * @param string $value The new value
     * @return true
     */
        function datalist_set($name, $value)
        {
            global $DATALIST_CACHE;

            insert_data("INSERT into datalists set name = ?, value = ? ON DUPLICATE KEY UPDATE value = ?",
                array($name, $value, $value)
            );

            $DATALIST_CACHE[$name] = $value;

            get_cache()->set('datalist', $DATALIST_CACHE);

            return true;
        }

    /**
     * Get the full URL of the current page.
     *
     * @return string The URL
     */
    function full_url()
    {
        $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
        $protocol = substr(strtolower($_SERVER["SERVER_PROTOCOL"]), 0, strpos(strtolower($_SERVER["SERVER_PROTOCOL"]), "/")) . $s;
        $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);
        return $protocol . "://" . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
    }

    /**
     * This function is a shutdown hook registered on startup which does nothing more than trigger a
     * shutdown event when the script is shutting down, but before database connections have been dropped etc.
     *
     */
    function __elgg_shutdown_hook()
    {
        global $CONFIG, $START_MICROTIME;

        trigger_elgg_event('shutdown', 'system');

        if ($CONFIG->debug)
        {
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            error_log("Page {$uri} generated in ".(float)(microtime(true)-$START_MICROTIME)." seconds");
        }
    }

    function elgg_init()
    {
        register_shutdown_function('__elgg_shutdown_hook');
    }


    register_elgg_event_handler('init','system','elgg_init');
