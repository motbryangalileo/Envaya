<?php

/*
 * Minifies javascript and CSS files, copying static files to www/_media/
 * and saving cache information in build/.
 *
 * Usage:
 *  php make.php [clean|js|css|media|lib_cache|path_cache|all]
 */     

chdir(__DIR__);
 
require_once "scripts/cmdline.php";

class Build
{    
    static function all()
    {
        Build::build_cache();
        Build::path_cache();
        Build::media();
        Build::css();
        Build::js();
        Build::vendors();
    }
    
    /*
     * Removes all files generated by make.php
     */
    static function clean()
    {
        system('rm -rf build/*.php');
        system('rm -rf config/default_build.php');
        system('rm -rf build/path_cache');
        system('rm -rf www/_media/*');
    }
    
    /*
     * Downloads code for 3rd party libraries that can't be distributed with our source code
     * because their licensing is incompatible with ours.
     */
    static function vendors()
    {
        $sphinx_ver = "rel20";        
        $sphinx_api_filename = "sphinxapi.{$sphinx_ver}.php";
        $sphinx_api_path = __DIR__."/vendors/$sphinx_api_filename";
        if (!is_file($sphinx_api_path))
        {
            $sphinx_url = "http://sphinxsearch.googlecode.com/svn/branches/{$sphinx_ver}/api/sphinxapi.php";    
        
            $sphinx_api_php = file_get_contents($sphinx_url);
            static::write_file($sphinx_api_path, $sphinx_api_php);
        }
        static::write_file(__DIR__."/vendors/sphinxapi.php", 
            "<?php require_once __DIR__.'/$sphinx_api_filename';");
    }    
    
    static function build_cache() 
    {
        @unlink("build/cache.php");

        require_once "scripts/analysis/phpparentanalyzer.php";
        require_once "start.php";

        ob_start();
        echo "<?php class RealBuildCache extends BuildCache {\n";

        echo "function _get_lib_paths() { return array(".implode(',', array_map(
            function($p) { return var_export($p, true); }, Engine::get_lib_paths()
        ))."); }\n";

        $default_keys = array();
        
        foreach (Config::get('languages') as $lang)
        {
            $default_keys[$lang]["lang:$lang"] = __("lang:$lang", $lang);
        }
        
        echo "function _get_default_translations() { return ".var_export($default_keys, true)."; }\n";        
        
        $analyzer = new PHPParentAnalyzer();
        $analyzer->parse_dir(__DIR__);

        $parent_classes = $analyzer->parent_classes;
        $paths = $analyzer->paths;

        $parent_cache = array();

		ksort($paths);
		
        foreach ($paths as $cls => $path)
        {
            echo "function {$cls}(&\$a,&\$b,&\$c){";
            
            echo "\$a=".var_export($path, true).";";
            if (isset($parent_classes[$cls]))
            {
                $parent_cls = $parent_classes[$cls];
                if (isset($paths[$parent_cls]))
                {
                    $lparent = strtolower($parent_cls);
                    echo "\$b=".var_export($lparent, true).";";
                }
            }
            echo "\$c=".var_export(strtolower($cls), true).';';

            echo "}\n";
        }

        echo "}";
        $php = ob_get_clean();

        static::write_file("build/cache.php", $php);        
    }

    /*
     * Generates a cache of all the paths of files in the engine, themes, languages, and views
     * directories, which might be referenced via virtual paths in calls to Engine::get_real_path() .
     * This cache allows Engine::get_real_path() to work in O(1) time instead of O(num_modules) time.
     *
     * Also generates a cache of all the paths of files in the lib/ directory.
     *
     * Uses relative paths so the same cache files work in any root directory
     * and can be copied to different systems without needing to regenerate them.
     */    
    static function path_cache()
    {
        // remove previous build files before including start.php
        // so that Engine class doesn't use previous path cache when building the new path cache
        @unlink("build/path_cache.php");
        system('rm -rf build/path_cache');
    
        require_once "start.php";
                                
        $dir_paths = array(
            // allows us to test if the path cache actually works like it should
            'views/default/admin' => array(            
                'views/default/admin/path_cache_test.php' => 'build/path_cache_info.php' 
            )
        );        
        
        $virtual_dirs = array('themes', 'languages', 'views', 'config');
                        
        $modules = Config::get('modules');
        foreach ($modules as $module)
        {
            foreach ($virtual_dirs as $virtual_dir)
            {        
                static::add_paths_in_dir("mod/{$module}/", $virtual_dir, $dir_paths);
            }
        }       
        
        foreach ($virtual_dirs as $virtual_dir)
        {        
            static::add_paths_in_dir('', $virtual_dir, $dir_paths);
        }                
        
        static::add_nonexistent_view_paths($dir_paths);
        
        static::add_nonexistent_language_paths($dir_paths);
                
        // create a cache file for each virtual directory
        mkdir('build/path_cache'); 
        foreach ($dir_paths as $dir => $paths)
        {
            $cache_name = str_replace('/','__', $dir);
            static::write_file("build/path_cache/$cache_name.php", static::get_array_php($paths));
        }
        
        // list of commonly used virtual directories whose paths will be included in
        // build/path_cache.php, rather than needing to open a cache file for each directory
        $default_dirs = array(          
            'languages/en',
            'themes',
            'views/default',
            'views/default/home',            
            'views/default/js',
            'views/default/layouts',
            'views/default/page_elements',
            'views/default/input',
            'views/default/object',            
            'views/default/output',
            'views/default/translation',
            'views/default/messages',
        );
        
        $default_paths = array();
        foreach ($default_dirs as $default_dir)
        {
            if (isset($dir_paths[$default_dir]))
            {
                $default_paths = array_merge($default_paths, $dir_paths[$default_dir]);
            }
        }
        
        static::write_file("build/path_cache.php", static::get_array_php($default_paths));
                
        // create a file with cache information to display on the admin statistics page
        // (which also verifies that the path cache is basically working)
        $num_default_paths = sizeof($default_paths);
        $num_files = sizeof($dir_paths);
        static::write_file("build/path_cache_info.php", 
            "<div>The path cache is enabled. ($num_default_paths default paths + $num_files files)</div>");
    }

    /* 
     * Minifies all CSS files defined in each module's views/default/css/ directory, 
     * and copies to www/_media/css/.
     */       
    static function css($name = '*')
    {    
        require_once "start.php";
        
        $modules = static::module_glob();
        $css_paths = glob("{views/default/css/$name.php,mod/$modules/views/default/css/$name.php}", GLOB_BRACE);

        $output_dir = 'www/_media/css';
        
        if (!is_dir($output_dir))
        {
            mkdir($output_dir);
        }
        
        $build_config = static::get_build_config();
        
        foreach ($css_paths as $css_path)
        {
            $pathinfo = pathinfo($css_path);
            $filename = $pathinfo['filename'];
            $css_temp = "scripts/$filename.tmp.css";
            $raw_css = view("css/$filename");
            
            if (preg_match('/http(s)?:[^\s\)\"\']*/', $raw_css, $matches))
            {
                throw new Exception("Absolute URL {$matches[0]} found in $css_path. In order to work on both dev/production without recompiling, CSS files must not contain absolute paths.");
            }
            
            file_put_contents($css_temp, $raw_css);
            $content_hash = static::compress($css_temp, "$output_dir/$filename", 'css', self::Minify | self::Gzip | self::ContentHash);
            unlink($css_temp);
            
            $build_config["build:hash:css:$filename"] = $content_hash;
        }
        
        static::write_build_config($build_config);
    }

     
    /* 
     * Minifies Javascript in each module's js/ directory, and copie to www/_media/.
     */   
    static function js($name = '*')
    {    
        require_once "start.php";
        $modules = static::module_glob();
        
        static::js_minify_dir(".", $name);
        
        foreach (Config::get('modules') as $module)
        {            
            static::js_minify_dir("mod/$module", $name);
        }
    }
    
    /* 
     * Copies static files from each module's _media/ directory to www/_media/.
     */
    static function media()
    {
        require_once "start.php";
        
        static::system("rsync -rp --chmod=ugo=rwX _media/ www/_media/");
        
        foreach (Config::get('modules') as $module)
        {            
            if (is_dir("mod/$module/_media"))
            {
                static::system("rsync -rp --chmod=ugo=rwX mod/$module/_media/ www/_media/");
            }
        }
    }
    
    private static function module_glob()
    {
        return "{".implode(',',Config::get('modules'))."}";
    }    
    
    const Minify = 1;
    const Gzip = 2;
    const ContentHash = 4;
    
    private static function compress($srcFile, $destFileBase, $ext, $options)
    {
        $src = file_get_contents($srcFile);       
    
        $tmpFile = null;
    
        if ($options & self::Minify)
        {
            $tmpFile = "$destFileBase.tmp";
            system("java -jar vendors/yuicompressor-2.4.2.jar --type $ext -o ".escapeshellarg($tmpFile). " ".escapeshellarg($srcFile));            
            $compressed = file_get_contents($tmpFile);
        }
        else
        {        
            $compressed = $src;
        }
        
        if ($options & self::ContentHash)
        {
            $contentHash = self::get_content_hash($compressed);
            $destFile = "$destFileBase.$contentHash.$ext";
        }
        else
        {
            $destFile = "$destFileBase.$ext";
            $contentHash = null;
        }
        
        file_put_contents($destFile, $compressed);
        
        if ($tmpFile)
        {
            unlink($tmpFile);
        }
        
        $size_str = strlen($src)."  :  ".strlen($compressed);
        
        if ($options & self::Gzip)
        {
            $gzencoded = gzencode("\n$compressed", 9);        
            file_put_contents("$destFile.gz", $gzencoded);
            echo $size_str." : ".strlen($gzencoded)." : $destFile\n";  
        }
        else
        {
            echo "$size_str : $destFile\n";  
        }
        
        return $contentHash;
    }
        
    private static function add_nonexistent_language_paths(&$dir_paths)
    {
        $language = Language::get(Config::get('language'));
    
        $default_group = $language->get_group('default');
        
        $pseudo_groups = array('admin' => true);
        
        foreach ($default_group as $key => $tr)
        {
            @list($group_name, $etc) = explode(':', $key, 2);
            if ($etc)
            {
                $pseudo_groups[$group_name] = true;
            }
        }
        
        foreach ($pseudo_groups as $group_name => $v)
        {
            foreach (Language::all() as $language)
            {
                if (!$language->get_group_path($group_name))
                {
                    $code = $language->get_code();
                    $dir = "languages/{$code}";
                    $virtual_path = "$dir/{$code}_{$group_name}.php";            
                    $dir_paths[$dir][$virtual_path] = 0; 
                }
            }
        }
    }
    
    
    private static function add_nonexistent_view_paths(&$dir_paths)
    {
        // add cache entries for non-existent views in view types other than default
        // since the view() function will check if they exist before using the default view.
        
        $view_types = array();
        $default_views = array();

        foreach ($dir_paths as $dir => $paths)
        {
            if (preg_match('#^views/(\w+)#', $dir, $matches))
            {
                $view_type = $matches[1];
                if ($view_type == 'default')
                {            
                    foreach ($paths as $virtual_path => $real_path)
                    {
                        $default_views[] = substr($virtual_path, strlen('views/default/'));
                    }
                }
                else // collect names of all view types other than default
                {
                    $view_types[$view_type] = $view_type;
                }
            }
        }
                               
        foreach ($default_views as $view_path)
        {
            foreach ($view_types as $view_type)
            {            
                // is this view not defined for this view type?
                $virtual_path = "views/$view_type/$view_path";
                if (!Engine::filesystem_get_real_path($virtual_path)) 
                {
                    $dir = dirname($virtual_path);
                    
                    if (!isset($paths[$dir][$virtual_path]))
                    {
                        // 0 is sentinel for nonexistent keys in path cache
                        $dir_paths[$dir][$virtual_path] = 0; 
                    }                    
                }
            }
        }
    }
        
    static function write_file($filename, $contents)
    {
        echo strlen($contents) . " ".$filename."\n";
        file_put_contents($filename, $contents);            
    }

    private static function add_paths_in_dir($rel_base, $dir, &$paths)
    {
        $root = Engine::$root;
        $handle = @opendir("{$root}/{$rel_base}{$dir}");
        if ($handle)
        {
            while ($file = readdir($handle))
            {
                $virtual_path = "{$dir}/{$file}";
                $real_rel_path = "{$rel_base}{$virtual_path}";
                $real_path = "{$root}/{$real_rel_path}";

                if (preg_match('/\.php$/', $file))
                {
                    if (!isset($paths[$dir][$virtual_path]))
                    {
                        $paths[$dir][$virtual_path] = $real_rel_path;
                    }
                }
                if ($file[0] != '.' && is_dir($real_path))
                {
                    static::add_paths_in_dir($rel_base, $virtual_path, $paths);
                }
            }
        }
    }
    
    private static function get_array_php($arr)
    {
        return "<?php return ".var_export($arr, true).";";
    }
    
    private static function get_build_config()
    {
        $res = @include("config/default_build.php");
        
        return is_array($res) ? $res : array();
    }
    
    private static function write_build_config($build_config)
    {
        static::write_file("config/default_build.php", static::get_array_php($build_config));
    }
    
    /*
     * Returns a short hash code that can be used as part of a file's URL, to ensure that
     * browser/proxy caches are invalidated automatically whenever the content is changed.
     */
    private static function get_content_hash($content)
    {    
        // 16^10 hash values should be enough to avoid collisions within an Expires timeout of 1 year    
        // (not important to avoid intentional collisions)
        return substr(md5($content), 0, 10);            
    }
     
    public static function get_output($file)
    {
        ob_start();
        require $file;                
        return ob_get_clean();
    }
     
    private static function js_minify_dir($base, $name = '*', $dir = '')
    {    
        $js_src_files = glob("$base/js/{$dir}{$name}.{js,php}", GLOB_BRACE);
        foreach ($js_src_files as $js_src_file)
        {
            $basename = pathinfo($js_src_file,  PATHINFO_BASENAME);
            $filename = pathinfo($js_src_file,  PATHINFO_FILENAME);
            $extension = pathinfo($js_src_file,  PATHINFO_EXTENSION);
            
            $base_output_file = "www/_media/{$dir}{$filename}";
            
            $is_inline = ($dir == 'inline/'); // inline JS does not need content hashes because it is never loaded by URL
            $is_pre_minified = strpos($js_src_file, ".min.") !== false;
            
            if ($is_inline)
            {
                $options = self::Minify;
            }
            else if ($is_pre_minified)
            {
                $options = self::Gzip;
            }
            else
            {
                $options = self::Minify | self::Gzip | self::ContentHash;
            }            
            
            if ($extension == 'php')
            {
                $js_temp_file = "scripts/$basename.tmp.js";
                $raw_js = static::get_output($js_src_file);
                file_put_contents($js_temp_file, $raw_js);
                $content_hash = static::compress($js_temp_file, $base_output_file, 'js', $options);
                unlink($js_temp_file);
            }
            else
            {          
                $content_hash = static::compress($js_src_file, $base_output_file, 'js', $options);
            }
            
            if (isset($content_hash)) 
            {
                $build_config = static::get_build_config();
                $build_config["build:hash:js:$filename"] = $content_hash;
                static::write_build_config($build_config);
            }
        }
        
        $subdirs = glob("$base/js/{$dir}*", GLOB_ONLYDIR);
        foreach ($subdirs as $subdir)
        {
            $basename = pathinfo($subdir,  PATHINFO_BASENAME);
            if ($basename == 'src')
            {
                continue;
            }
        
            if (!is_dir("www/_media/{$dir}{$basename}"))
            {
                mkdir("www/_media/{$dir}{$basename}");
            }
            static::js_minify_dir($base, $name, "{$dir}{$basename}/");
        }
    }
    
    static function system($cmd)
    {
        echo "$cmd\n";
        return system($cmd);
    }    
}

$target = @$argv[1] ?: 'all';
$arg = @$argv[2];
if (method_exists('Build', $target))
{
    if ($arg)
    {
        Build::$target($arg);
    }
    else
    {
        Build::$target();
    }
}
else
{
    echo "Build::$target is not defined\n";
}
