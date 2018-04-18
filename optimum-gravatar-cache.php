<?php
/*
Plugin Name: Optimum Gravatar Cache
Plugin URI:   https://www.ncdc.pt/groups/wordpress-optimum-gravatar-cache/
Version: 1.1.1
Author: José Miguel Silva Caldeira
License:      GPL3
License URI:  https://www.gnu.org/licenses/gpl-3.0.html
Description: It cache the gravatars locally, reducing the total number of requests per post. This will speed up the loading of the site and consequently improve the user experience.
Author URI: https://www.ncdc.pt/members/admin
Text Domain: OGC
Domain Path:  /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

class OGC
{
    protected $options;
    protected $phpModulesRequired=array("curl","gd");
    protected $mimeTypes=array("image/jpeg" => "jpg","image/png" => "png","image/gif" => "gif", "image/svg+xml"=>"svg");
    protected $pluginName = 'Optimum Gravatar Cache';
    protected $cacheDirectory;
    protected $expireTime;
    protected $activated;
    protected $errorMessages=array();
    protected $cacheTableName;
    protected $searchExpiredTime;
    protected $maxUpdateEachTime;
    protected $maxOptimizeEachTime;
    protected $precompress;
    protected $defaultAvatar;
    protected $customAvatarExt;
    protected $learningMode;
    protected $resolved;
    protected $avatarUsedSizes;
    protected $avatarRating;
    protected $optimizeAvatars;
    protected $curl;

    public function __construct()
    {
        global $wpdb;
        $this->curl=false;
        $this->cacheTableName=$wpdb->prefix."optimum_gravatar_cache";
        $this->resolved=get_option('OGC_resolved');
        $this->options = get_option('OGC_options');
        $this->avatarUsedSizes = get_option('OGC_avatarUsedSizes');
        $this->avatarRating=get_option('avatar_rating');
        $this->cacheDirectory = $this->options['cache_directory'];
        $this->expireTime=$this->options['expireTime'];
        $this->activated=$this->options['activated'];
        $this->searchExpiredTime=$this->options['searchExpiredTime'];
        $this->maxUpdateEachTime=$this->options['maxUpdateEachTime'];
        $this->optimizeAvatars=$this->options['optimizeAvatars'];
        $this->defaultAvatar=$this->options['defaultAvatar'];
        $this->precompress=$this->options['precompress'];
        $this->customAvatarExt=$this->options['customAvatarExt'];
        $this->learningMode=$this->options['learningMode'];
        $this->maxOptimizeEachTime=$this->options['maxOptimizeEachTime'];

        add_action('delete_user', array( $this,'deleteUserAvatarsCache' ), 1, 2);

        if ($this->permissionsToRun() && $this->optionsToRun() && $this->phpModulesRequired() && $this->activated) {
            add_filter('get_avatar', array( $this,'getCachedAvatar' ), 5, 6);
            add_filter('bp_core_fetch_avatar', array( $this,'getBPressCachedAvatar' ), 5, 3);
            add_filter('bp_core_fetch_avatar_url', array( $this,'getBPressCachedAvatarURL' ), 5, 3);

            if (!$this->learningMode) {
                add_filter('cron_schedules', array( $this, 'schedules'));
                $this->setCronEvent();
                add_action('OGC_CronEvent', array( $this, 'updateCache'));
            }
        }

        if (is_admin() && in_array('administrator', wp_get_current_user()->roles)) {
            load_plugin_textdomain("OGC", false, basename(dirname(__FILE__)) . '/languages/');
            register_activation_hook(__FILE__, array( $this, 'activate' ));
            register_deactivation_hook(__FILE__, array( $this, 'deactivate' ));
            add_action('admin_menu', array( $this,'addAdminMenu'));
            add_action('admin_enqueue_scripts', array( $this, 'adminScripts'));
            add_action('admin_notices', array( $this, 'adminPermissionsNotices'));
            add_filter("plugin_action_links_".plugin_basename(__FILE__), array( $this, 'addSettingsLink'));
            add_filter('plugin_row_meta', array( $this, 'addProjectLinks'), 10, 2);

            if (!$this->adminPhpModulesRequired()) {
                $this->activated=0;
            }

            if (isset($_POST['clearCache'])) {
                $this->clearCache();
            }

            if (isset($_POST['updateOptions'])) {
                $this->updateOptions();
            }
        }
    }

    protected function phpModulesRequired()
    {
        $total=0;
        foreach ($this->phpModulesRequired as $module) {
            if (extension_loaded($module)) {
                $total++;
            }
        }
        if ($total==count($this->phpModulesRequired)) {
            return true;
        }
        return false;
    }

    protected function adminPhpModulesRequired()
    {
        if (!$this->phpModulesRequired()) {
            $this->errorMessages[]=array(
            'type' => 'error notice',
            'message' => __("This list (%s) of PHP modules is required.", "OGC"),
            'args'=>array(implode(', ', $this->phpModulesRequired))
          );
            return false;
        }
        return true;
    }


    public function addSettingsLink($links)
    {
        $settingsLink = '<a href="options-general.php?page='.basename(__FILE__).'">' . __("Settings", "OGC") . '</a>';
        array_push($links, $settingsLink);
        return $links;
    }

    public function addProjectLinks($links, $file)
    {
        if (strpos(__FILE__, dirname($file)) !== false) {
            $newLinks = array(
                    'discussionGroup' => '<a href="//www.ncdc.pt/groups/wordpress-optimum-gravatar-cache" target="_blank">'.__("Discussion Group", "OGC").'</a>',
                    'gitHub' => '<a href="//github.com/jomisica/optimum-gravatar-cache" target="_blank">'.__("GitHub Project", "OGC").'</a>'
                    );
            $links = array_merge($links, $newLinks);
        }
        return $links;
    }

    public function schedules($schedules)
    {
        $schedules["OGC_job"] = array(
            'interval' => $this->searchExpiredTime*60,
            'display' => $pluginName.' cron job'
          );
        return $schedules;
    }

    protected function setCronEvent()
    {
        if (! wp_next_scheduled('OGC_CronEvent')) {
            wp_schedule_event(time(), 'OGC_job', 'OGC_CronEvent');
        }
    }

    protected function getDefaultAvatar()
    {
        if ($this->customAvatarExt=='svg') {
            if ($this->defaultAvatar) {
                return plugins_url('/avatar/default.svg', __FILE__);
            } else {
                return plugins_url('/avatar/custom.svg', __FILE__);
            }
        } else {
            return plugins_url('/avatar/custom.'.$this->customAvatarExt, __FILE__);
        }
    }

    protected function getLogo()
    {
        return plugins_url('/admin/images/logo.svg', __FILE__);
    }

    protected function deleteOldDefaultAvatars()
    {
        global $wpdb;

        $wpdb->query("UPDATE `{$this->cacheTableName}` SET `ext`='{$this->customAvatarExt}' WHERE def='1'");

        foreach (glob(ABSPATH.$this->cacheDirectory."0*") as $fileName) {
            unlink($fileName);
        }
    }
    protected function optimizeDefaultAvatars()
    {
        if ($this->customAvatarExt =="svg") {
            return;
        }

        foreach (glob(ABSPATH.$this->cacheDirectory."0*.{$this->customAvatarExt}") as $avatarFile) {
            $optimezedAvatarStat=$avatarFile.".O";
            $avatarBaseName=basename($avatarFile);
            if (!file_exists($optimezedAvatarStat)) {
                $options=site_url()."/{$this->cacheDirectory}{$avatarBaseName}";
                $optimizedDefaultAvatarRequest=$this->sendResmushRequest($options);
                if (!$optimizedDefaultAvatarRequest->error) {
                    $optimizedDefaultAvatar=$this->getOptimizedGravatar($optimizedDefaultAvatarRequest->optimizedURL);
                    if ($optimizedDefaultAvatar->status == 200) {
                        if (file_put_contents($avatarFile, $optimizedDefaultAvatar->content)) {
                            touch($avatarFile.".O");
                        }
                    }
                }
            }
        }
    }
    protected function validateCacheDirectory($path)
    {
        $systemDirectoriesConstants=array(
          ABSPATH,
          WP_CONTENT_DIR,
        );
        $systemDirectories=array(
          ABSPATH."wp-admin",
          ABSPATH."wp-includes",
          WPINC,
          WP_LANG_DIR,
          WP_TEMP_DIR,
          WPMU_PLUGIN_DIR,
          WP_ADMIN_DIR,
          WP_PLUGIN_DIR,
        );
        foreach ($systemDirectoriesConstants as $systemDirectory) {
            if (ABSPATH.$path == $systemDirectory) {
                $this->errorMessages[]=array(
                  "type" => "error notice",
                  "message" => __("It is not possible to use the default wordpress directory '%s' as the avatars cache directory. However it is possible to create a directory within it.<br>For example: '%s'<br><br>This is to avoid crashes when cleaning the cache or uninstalling the plugin.", "OGC"),
                  "args"=>array($systemDirectory, $systemDirectory."avatar-cache")
                );
                return false;
            }
        }
        foreach ($systemDirectories as $systemDirectory) {
            if (ABSPATH.$path == $systemDirectory || strpos(ABSPATH.$path, $systemDirectory) === 0) {
                $this->errorMessages[]=array(
                  "type" => "error notice",
                  "message" => __("It is not possible to use the default wordpress directory '%s' as the avatars cache directory. Create the directory in the webserver root or inside the '%s' directory.<br><br>This is to avoid crashes when cleaning the cache or uninstalling the plugin.", "OGC"),
                  "args"=>array($systemDirectory, WP_CONTENT_DIR)
                );
                return false;
            }
        }

        return true;
    }

    protected function updateOptions()
    {
        if (!wp_verify_nonce($_POST['OGC_options']['nonce'], "OGC")) {
            return;
        }
        if (isset($_POST['OGC_options']['cache'])) {
            if (isset($_POST['OGC_options']['directory'])) {
                $cacheDirectory=trim($_POST['OGC_options']['directory'], '/');
            }
            if (isset($_POST['OGC_options']['activated'])) {
                $this->activated=intval($_POST['OGC_options']['activated']);
            }
            if (isset($_POST['OGC_options']['expireTime'])) {
                $this->expireTime=intval($_POST['OGC_options']['expireTime']);
            }
            if (isset($_POST['OGC_options']['searchExpiredTime'])) {
                $this->searchExpiredTime=intval($_POST['OGC_options']['searchExpiredTime']);
            }
            if (isset($_POST['OGC_options']['maxUpdateEachTime'])) {
                $this->maxUpdateEachTime=intval($_POST['OGC_options']['maxUpdateEachTime']);
            }
            if (isset($_POST['OGC_options']['learningMode'])) {
                $this->learningMode=intval($_POST['OGC_options']['learningMode']);
            } else {
                $this->learningMode=0;
            }
            if (isset($_POST['OGC_options']['avatarUsedSizes'])) {
                $this->avatarUsedSizes=array_map('trim', explode(',', $_POST['OGC_options']['avatarUsedSizes']));
                rsort($this->avatarUsedSizes);
            }
        }

        if (isset($_POST['OGC_options']['optimization'])) {
            if (isset($_POST['OGC_options']['optimizeAvatars'])) {
                $this->optimizeAvatars=intval($_POST['OGC_options']['optimizeAvatars']);
            } else {
                $this->optimizeAvatars=0;
            }
            if (isset($_POST['OGC_options']['precompress'])) {
                $this->precompress=intval($_POST['OGC_options']['precompress']);
                $this->compressDefaultAvatar();
            } else {
                $this->precompress=0;
                $this->deleteCompressDefaultAvatar();
            }
            $this->maxOptimizeEachTime=intval($_POST['OGC_options']['maxOptimizeEachTime']);
        }

        if (isset($_POST['OGC_options']['cache'])) {
            if (!$this->validateCacheDirectory($cacheDirectory)) {
                $errorcacheDirectory=true;
                $this->cacheDirectory=$this->options['cache_directory'];
            } else {
                $this->cacheDirectory=$cacheDirectory."/";
                $this->options['cache_directory']=$this->cacheDirectory;
            }
        } else {
            $this->cacheDirectory=$this->options['cache_directory'];
        }
        $this->options['expireTime']=$this->expireTime;
        $this->options['activated']=$this->activated;
        $this->options['searchExpiredTime']=$this->searchExpiredTime;
        $this->options['maxUpdateEachTime']=$this->maxUpdateEachTime;
        $this->options['maxOptimizeEachTime']=$this->maxOptimizeEachTime;
        $this->options['optimizeAvatars']=$this->optimizeAvatars;
        $this->options['learningMode']=$this->learningMode;
        $this->options['precompress']=$this->precompress;

        $errorPermissions=$this->adminPermissionsToRun();
        $errorOptions=$this->adminOptionsToRun();

        if (!$errorcacheDirectory && isset($_POST['OGC_options']['default-avatar'])) {
            $avatar=$this->adminSaveCustomAvatar();
            if ($avatar) {
                $this->options['defaultAvatar']=$avatar['default'];
                $this->options['customAvatarExt']=$avatar['ext'];
                $needCleanCache=true;
            }
            if (intval($_POST['OGC_options']['resetDefaultAvatar'])) {
                $this->options['defaultAvatar']=true;
                $this->options['customAvatarExt']="svg";
                $needCleanCache=true;
            }
            $this->defaultAvatar=$this->options['defaultAvatar'];
            $this->customAvatarExt=$this->options['customAvatarExt'];
        }

        if ($needCleanCache==true) {
            $this->deleteOldDefaultAvatars();
        }

        if (!$this->phpModulesRequired()) {
            $errorPhpModules=true;
        }

        if ($errorPermissions || $errorOptions || $errorPhpModules || $errorcacheDirectory) {
            $this->options['activated']=0;
            $this->activated=0;
        } else {
            $this->options['messages']="";
            $this->copyApacheHtaccess();
        }

        update_option('OGC_avatarUsedSizes', $this->avatarUsedSizes);
        update_option('OGC_options', $this->options);
    }

    protected function copyApacheHtaccess()
    {
        if (!copy(dirname(__FILE__) . '/apache/htaccess', ABSPATH.$this->cacheDirectory.'.htaccess')) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => __("It was not possible to copy the apache '%s' configuration file to the '%s' cache directory.", "OGC"),
          "args"=>array(dirname(__FILE__) . '/apache/htaccess', ABSPATH.$this->cacheDirectory.'.htaccess')
        );
        }
    }

    public function adminSaveCustomAvatar()
    {
        if (!isset($_FILES['file'])) {
            return false;
        }
        if ($_FILES['file']['error'] == UPLOAD_ERR_NO_FILE) {
            return false;
        }

        switch ($_FILES['file']['error']) {
          case UPLOAD_ERR_OK:
            $ext=pathinfo(strtolower($_FILES["file"]["name"]), PATHINFO_EXTENSION);
            if (array_key_exists($_FILES["file"]["type"], $this->mimeTypes) && in_array($ext, $this->mimeTypes)) {
                if (move_uploaded_file($_FILES["file"]["tmp_name"], dirname(__FILE__) . '/avatar/custom.'.$ext)) {
                    $options=array(
                      'default'=>false,
                      'ext'=>$ext,
                    );
                    return $options;
                } else {
                    $this->errorMessages[]=array(
                          "type" => "error notice",
                          "message" => __("Could not save avatar.", "OGC"),
                          "args"=>array()
                        );
                }
            } else {
                $this->errorMessages[]=array(
                    "type" => "error notice",
                    "message" => __("The file type is not supported.", "OGC"),
                    "args"=>array()
                  );
            }
          break;
          case UPLOAD_ERR_INI_SIZE:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("The uploaded file exceeds the upload_max_filesize directive in php.ini.", "OGC"),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_FORM_SIZE:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.", "OGC"),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_PARTIAL:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("The uploaded file was only partially uploaded.", "OGC"),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_NO_TMP_DIR:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("Missing a temporary folder.", "OGC"),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_CANT_WRITE:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("Failed to write file to disk.", "OGC"),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_EXTENSION:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("File upload stopped by extension.", "OGC"),
                      "args"=>array()
                    );
          break;
          default:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("Unknown upload error.", "OGC"),
                      "args"=>array()
                    );
          break;
      }

        return false;
    }

    public function adminPermissionsToRun()
    {
        $error=false;
        if (!$this->permissionsToRun()) {
            if (!mkdir(ABSPATH.$this->cacheDirectory, 0755, true) && !is_dir(ABSPATH.$this->cacheDirectory)) {
                $this->errorMessages[]=array(
                  "type" => "error notice",
                  "message" => __("Could not create cache directory '%s'. Please set read/write/execute (755) permissions for '%s' and/or correct owner.", "OGC"),
                  "args"=>array(ABSPATH.$this->cacheDirectory,dirname(ABSPATH.$this->cacheDirectory))
                );
                $error=true;
            }
            if ((!is_writable(ABSPATH.$this->cacheDirectory) || !is_executable(ABSPATH.$this->cacheDirectory)) && !chmod(ABSPATH.$this->cacheDirectory, 0755)) {
                $this->errorMessages[]=array(
                  "type" => "error notice",
                  "message" => __("Please set read/write/execute (755) permissions for '%s' and/or correct owner.", "OGC"),
                  "args"=>array(ABSPATH.$this->cacheDirectory)
                );
                $error=true;
            }
            if (!mkdir(ABSPATH."{$this->cacheDirectory}tmp", 0755, true) && !is_dir(ABSPATH."{$this->cacheDirectory}tmp")) {
                $this->errorMessages[]=array(
                  "type" => "error notice",
                  "message" => __("Could not create cache directory '%s'. Please set read/write/execute (755) permissions for '%s' and/or correct owner.", "OGC"),
                  "args"=>array(ABSPATH."{$this->cacheDirectory}tmp",dirname(ABSPATH."{$this->cacheDirectory}tmp"))
                );
                $error=true;
            }
            if ((!is_writable(ABSPATH."{$this->cacheDirectory}tmp") || !is_executable(ABSPATH."{$this->cacheDirectory}tmp")) && !chmod(ABSPATH."{$this->cacheDirectory}tmp", 0755)) {
                $this->errorMessages[]=array(
                  "type" => "error notice",
                  "message" => __("Please set read/write/execute (755) permissions for '%s' and/or correct owner.", "OGC"),
                  "args"=>array(ABSPATH."{$this->cacheDirectory}tmp")
                );
                $error=true;
            }
        }
        return $error;
    }

    protected function adminOptionsToRun()
    {
        $error=false;

        if ($this->expireTime < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => __("<b>Refresh gravatars cache every:</b> This option accepts an integer value from 1.", "OGC"),
          "args"=>array()
      );
            $error=true;
        }
        if ($this->searchExpiredTime < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => __("<b>Search for outdated avatars every:</b> This option accepts an integer value from 1.", "OGC"),
          "args"=>array()
      );
            $error=true;
        }
        if ($this->maxUpdateEachTime < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => __("<b>How many users check at a time:</b> This option accepts an integer value from 1.", "OGC"),
          "args"=>array()
      );
            $error=true;
        }
        if ($this->maxOptimizeEachTime < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => __("<b>How many avatars to optimize each time:</b> This option accepts an integer value from 1.", "OGC"),
          "args"=>array()
      );
            $error=true;
        }

        return $error;
    }

    protected function permissionsToRun()
    {
        if (!is_dir(ABSPATH.$this->cacheDirectory)) {
            return false;
        }
        if (!is_writable(ABSPATH.$this->cacheDirectory)) {
            return false;
        }
        if (!is_executable(ABSPATH.$this->cacheDirectory)) {
            return false;
        }
        if (!is_dir(ABSPATH."{$this->cacheDirectory}tmp")) {
            return false;
        }
        if (!is_writable(ABSPATH."{$this->cacheDirectory}tmp")) {
            return false;
        }
        if (!is_executable(ABSPATH."{$this->cacheDirectory}tmp")) {
            return false;
        }
        return true;
    }

    protected function optionsToRun()
    {
        $error=true;
        if ($this->expireTime < 1) {
            $error=false;
        }
        if ($this->searchExpiredTime < 1) {
            $error=false;
        }
        if ($this->maxUpdateEachTime < 1) {
            $error=false;
        }
        return $error;
    }

    public function adminPermissionsNotices()
    {
        if ($this->options['messages']) {
            $this->errorMessages = array_merge($this->options['messages'], $this->errorMessages);
        }

        foreach ($this->errorMessages as $index => $contents) {
            echo "<div class=\"{$contents['type']}\"><p><b>{$this->pluginName}</b></p><p>".vsprintf($contents['message'], $contents['args'])."</p></div>";
        }
    }

    public function getBPressCachedAvatarURL($url, $params)
    {
        if ($params['object'] == 'user' && $params['class'] == 'avatar') {
            return $this->getCachedAvatar($url, $params['item_id'], $params['width'], null, $params['alt'], false);
            // return $this->getCachedAvatar($url, $params['item_id'], 26, null, $params['alt'], false);
        }
        return $url;
    }

    public function getBPressCachedAvatar($content, $params, $id)
    {
        if (is_array($params) && $params['object'] == 'user') {
            return $this->getCachedAvatar($content, $params['item_id'], $params['width'], null, $params['alt']);
        }
        return $content;
    }

    // Activate plugin and update default option
    public function activate()
    {
        global $wpdb;

        $wpdb->query("CREATE TABLE IF NOT EXISTS `{$this->cacheTableName}` (
				  `id` int(10) UNSIGNED NOT NULL auto_increment,
				  `hash` char(32) NOT NULL,
				  `optimized` enum('0','1') NOT NULL,
				  `size` smallint(5) UNSIGNED NOT NULL,
				  `ext` enum('svg','jpg','png','gif') NOT NULL,
				  `lastCheck` int(10) UNSIGNED NOT NULL,
				  `lastModified` int(10) UNSIGNED NOT NULL,
				  `def` enum('0','1') NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unicSize` (`hash`,`size`),
          KEY `optimizedAvatar` (`optimized`,`def`),
          KEY `getIdByHashAndSize` (`hash`,`size`) USING BTREE,
          KEY `DistinctDefault` (`def`) USING BTREE,
          KEY `lastCheck` (`lastCheck`)
				)");

        $avatarUsedSizes=get_option('OGC_avatarUsedSizes');
        if ($avatarUsedSizes == false) {
            $avatarUsedSizes=array(96, 64, 50, 32, 26, 20);
        }

        $resolved=get_option('OGC_resolved');
        if ($resolved == false) {
            $resolved=0;
        }

        $default_options=get_option('OGC_options');
        if ($default_options == false) {
            $default_options = array(
                    'activated'   => 0,
                    'expireTime' => 10,
                    'cache_directory' => 'cache/avatar/',
                    'searchExpiredTime'=> 5,
                    'maxUpdateEachTime' => 10,
                    'maxOptimizeEachTime' => 10,
                    'precompress' => 1,
                    'messages' => array(),
                    'defaultAvatar'=> true,
                    'customAvatarExt'=>  'svg',
                    'optimizeAvatars'=> 1,
                    'learningMode' => 1
            );
        }

        if (!$this->permissionsToRun() || !$this->optionsToRun()) {
            $default_options['messages'][] = array(
            'type' => "notice notice-info",
            'message' => __("The plugin has been activated but needs to be configured to work. Enter the configuration page through the menu. You need at least specify the directory where the gravatars will be saved.", "OGC"),
            'args'=>array()
          );
        }

        $this->adminPhpModulesRequired();

        wp_schedule_event(time(), 'OGC_job', 'OGC_CronEvent');

        update_option('OGC_avatarUsedSizes', $avatarUsedSizes);
        update_option('OGC_resolved', $resolved);
        update_option('OGC_options', $default_options);
    }

    // Deactivate plugin
    public function deactivate()
    {
        wp_clear_scheduled_hook('OGC_CronEvent');
    }

    protected function getResource($url, $nobody)
    {
        $properties = new stdClass();
        if ($this->curl === false) {
            $this->curl = curl_init();
        }
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 600);
        curl_setopt($this->curl, CURLOPT_USERAGENT, "Optimum Gravatar Cache at https://www.ncdc.pt");
        curl_setopt($this->curl, CURLOPT_HEADER, true);
        curl_setopt($this->curl, CURLOPT_NOBODY, $nobody);
        curl_setopt($this->curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($this->curl, CURLOPT_FILETIME, true);

        $response    = curl_exec($this->curl);
        $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        $properties->status = $httpCode;

        if ($httpCode == 200) {
            $properties->ext = $this->mimeTypes[curl_getinfo($this->curl, CURLINFO_CONTENT_TYPE)];
            $properties->lastModified = curl_getinfo($this->curl, CURLINFO_FILETIME);
            $properties->content = substr($response, curl_getinfo($this->curl, CURLINFO_HEADER_SIZE));
        }
        return $properties;
    }
    protected function getGravatarOnline($options)
    {
        return $this->getResource("https://www.gravatar.com/avatar/{$options}", false);
    }
    protected function getGravatarStatusOnline($options)
    {
        return $this->getResource("https://www.gravatar.com/avatar/{$options}", true);
    }

    protected function sendResmushRequest($options)
    {
        $properties=$this->getResource("http://api.resmush.it/ws.php?img={$options}", false);
        if ($properties->status == 200) {
            $data=json_decode($properties->content);
            if (isset($data->error)) {
                $properties->error = true;
            } else {
                $properties->optimizedURL = $data->dest;
            }
        } else {
            $properties->error = true;
        }
        return $properties;
    }

    protected function getOptimizedGravatar($url)
    {
        return $this->getResource($url, false);
    }

    protected function updateAndResizeAllSizes($hash)
    {
        global $wpdb;

        // avatars sizes used
        $sql = "SELECT DISTINCT(`size`), id FROM `{$this->cacheTableName}` WHERE `hash` = '$hash' ORDER BY `size` DESC";
        $sizeResults = $wpdb->get_results($sql, OBJECT);
        $maxValue = max($this->avatarUsedSizes);
        $options = "{$hash}?s={$maxValue}&r={$this->avatarRating}&d=404";
        $newGravatar=$this->getGravatarOnline($options);

        if ($newGravatar->status == 200) {
            file_put_contents(ABSPATH."{$this->cacheDirectory}tmp/{$hash}.{$newGravatar->ext}", $newGravatar->content);
            $img = wp_get_image_editor(ABSPATH."{$this->cacheDirectory}tmp/{$hash}.{$newGravatar->ext}");
            if (! is_wp_error($img)) {
                $avatarsOptions = array();
                foreach ($this->avatarUsedSizes as $size) {
                    $avatarsOptions[] =array('width' => $size, 'height' => $size, 'crop' => true);
                }

                $resize = $img->set_quality(100);
                $resize = $img->multi_resize($avatarsOptions);
                if (!$resize) {
                    return false;
                }
            } else {
                return false;
            }

            rename(ABSPATH."{$this->cacheDirectory}tmp/{$hash}.{$newGravatar->ext}", ABSPATH."{$this->cacheDirectory}tmp/{$hash}-{$maxValue}x{$maxValue}.{$newGravatar->ext}");
            foreach ($sizeResults as $size) {
                $avatarId=base_convert($size -> id, 10, 35);
                rename(ABSPATH."{$this->cacheDirectory}tmp/{$hash}-{$size->size}x{$size->size}.{$newGravatar->ext}", ABSPATH."{$this->cacheDirectory}{$avatarId}.{$newGravatar->ext}");
                touch(ABSPATH."{$this->cacheDirectory}{$avatarId}.{$newGravatar->ext}", $newGravatar->lastModified);
            }
            $lastCheck = time();
            $wpdb->query("UPDATE `{$this->cacheTableName}` SET `optimized`='0', `lastCheck`={$lastCheck}, `def`='0', `ext`='{$newGravatar->ext}', `lastModified`={$newGravatar->lastModified} WHERE `hash`='{$hash}'");
            return true;
        }
        return false;
    }


    protected function getUserAvatarCachedSizes($hash)
    {
        global $wpdb;

        $sql = "SELECT `size` FROM `{$this->cacheTableName}` WHERE `hash` = '$hash' ORDER BY `size` DESC";
        $sizeResults = $wpdb->get_results($sql, OBJECT);
        if ($sizeResults[0]) {
            $sizes=array();
            foreach ($sizeResults as $size) {
                $sizes[]=$size->size;
            }
            return $sizes;
        } else {
            return false;
        }
    }

    protected function addMissingAvatarsSizes($user)
    {
        global $wpdb;

        $sizes=$this->getUserAvatarCachedSizes($user->hash);
        $missingSizes = array_diff($this->avatarUsedSizes, $sizes);

        if (count($missingSizes)) {
            $wpdb->query("UPDATE `{$this->cacheTableName}` SET `lastCheck`=0,  `lastModified`=0 WHERE `hash`='{$user->hash}'");

            foreach ($missingSizes as $size) {
                $result=$wpdb->insert(
                  $this->cacheTableName,
                  array(
                    'hash' => $user->hash,
                    'optimized' => '0',
                    'size' => $size,
                    'ext' => $user->ext,
                    'lastCheck' => 0,
                    'lastModified' => 0,
                    'def' => $user->def
                  ),
                  array('%s', '%s', '%d', '%s', '%d', '%d', '%s')
                );
            }
        }
    }

    public function updateCache()
    {
        global $wpdb;
        $fp = fopen(ABSPATH."{$this->cacheDirectory}lock", 'w+');
        if (!flock($fp, LOCK_EX|LOCK_NB)) {
            return;
        }
        $time=time()-$this->expireTime * 86400;//86400 1 day
        $sql = "SELECT DISTINCT(`hash`), `def`, `lastModified`, `lastCheck`, `ext` FROM `{$this->cacheTableName}` WHERE lastCheck < {$time} ORDER BY `lastCheck` ASC LIMIT {$this->maxUpdateEachTime}";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results) {
            foreach ($results as $user) {
                $this->addMissingAvatarsSizes($user);
                $maxValue=max($this->avatarUsedSizes);
                $lastCheck = time();
                $options = "{$user->hash}?s={$maxValue}&r={$this->avatarRating}&d=404";
                $gravatarStatus=$this->getGravatarStatusOnline($options);
                if ($gravatarStatus->status == 404 && $user->def == 1) {
                    $wpdb->query("UPDATE `{$this->cacheTableName}` SET `lastCheck`={$lastCheck} WHERE `hash`='{$user->hash}'");
                    continue;
                } elseif ($gravatarStatus->status == 404 && $user->def == 0) {
                    $wpdb->query("UPDATE `{$this->cacheTableName}` SET `optimized`='0', `lastCheck`={$lastCheck}, `def`='1', `ext`='{$this->customAvatarExt}', `lastModified`=0 WHERE `hash`='{$user->hash}'");
                    continue;
                } elseif ($gravatarStatus->status == 200 && $user->def == 0) {
                    if ($user->lastModified == $gravatarStatus->lastModified) {
                        $wpdb->query("UPDATE `{$this->cacheTableName}` SET `lastCheck`={$lastCheck} WHERE `hash`='{$user->hash}'");
                        continue;
                    }
                    $this->updateAndResizeAllSizes($user->hash);
                    continue;
                } elseif ($gravatarStatus->status == 200 && $user->def == 1) {
                    $this->updateAndResizeAllSizes($user->hash);
                }
            }
        }
        if ($this->optimizeAvatars) {
            $this->optimizeDefaultAvatars();
            $this->optimizeCache();
        }
        flock($fp, LOCK_UN);
    }

    protected function optimizeCache()
    {
        global $wpdb;
        $sql = "SELECT `id`, `size`, `ext`, `lastModified` FROM `{$this->cacheTableName}` WHERE (optimized='0' AND def='0') ORDER BY lastCheck DESC LIMIT {$this->maxOptimizeEachTime}";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results) {
            foreach ($results as $gravatar) {
                $b35Id=base_convert($gravatar -> id, 10, 35);
                if (!file_exists(ABSPATH."{$this->cacheDirectory}{$b35Id}.{$gravatar -> ext}")) {
                    continue;
                }
                $options=site_url()."/{$this->cacheDirectory}{$b35Id}.{$gravatar -> ext}";
                $optimizedGravatarRequest=$this->sendResmushRequest($options);
                if (!$optimizedGravatarRequest->error) {
                    $optimizedGravatar=$this->getOptimizedGravatar($optimizedGravatarRequest->optimizedURL);
                    if ($optimizedGravatar->status == 200) {
                        if (file_put_contents(ABSPATH."{$this->cacheDirectory}{$b35Id}.{$gravatar -> ext}", $optimizedGravatar->content)) {
                            touch(ABSPATH."{$this->cacheDirectory}{$b35Id}.{$gravatar -> ext}", $gravatar->lastModified);
                            $wpdb->query("UPDATE `{$this->cacheTableName}` SET optimized='1' WHERE id={$gravatar->id}");
                        }
                    }
                }
            }
        }
    }

    protected function getIdByHashAndSize($hash, $size)
    {
        global $wpdb;
        $sql = "SELECT `id` FROM `{$this->cacheTableName}` where hash='{$hash}' AND size={$size}";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results[0] -> id) {
            return $results[0] -> id;
        }
        return false;
    }

    protected function compressDefaultAvatar()
    {
        if ($this->precompress) {
            if ($this->customAvatarExt=='svg') {
                $avatarFile = ABSPATH.$this->cacheDirectory.'0.svg';
                if (file_exists($avatarFile)) {
                    $theOutput = gzencode(file_get_contents($avatarFile), 9);
                    file_put_contents($avatarFile.".gz", $theOutput);
                    touch($avatarFile.".gz", filemtime($avatarFile));
                }
            }
        }
    }

    protected function deleteCompressDefaultAvatar()
    {
        $avatarFile = ABSPATH.$this->cacheDirectory.'0.svg.gz';
        if (file_exists($avatarFile)) {
            unlink($avatarFile);
        }
    }

    protected function buildDefaultAvatar($size, $html)
    {
        if ($this->customAvatarExt =="svg") {
            $avatarFile = ABSPATH.$this->cacheDirectory.'0.svg';
            $avatarURL="/{$this->cacheDirectory}0.svg";
        } else {
            $sizeId=base_convert($size, 10, 35);
            $avatarFile = ABSPATH."{$this->cacheDirectory}0$sizeId.{$this->customAvatarExt}";
            $avatarURL="/{$this->cacheDirectory}0$sizeId.{$this->customAvatarExt}";
        }

        if (!file_exists($avatarFile)) {
            if ($this->customAvatarExt=='svg') {
                if ($this->defaultAvatar) {
                    if (!copy(dirname(__FILE__) . '/avatar/default.svg', $avatarFile)) {
                        return false;
                    }
                } else {
                    if (!copy(dirname(__FILE__) . '/avatar/custom.svg', $avatarFile)) {
                        return false;
                    }
                }
                $this->compressDefaultAvatar();
            } else {
                $avatar = wp_get_image_editor(dirname(__FILE__) . '/avatar/custom.'.$this->customAvatarExt);
                if (! is_wp_error($avatar)) {
                    $avatar->set_quality(100);
                    $avatar->resize($size, $size, true);
                    $avatar->save($avatarFile);
                } else {
                    return false;
                }
            }
        }
        if ($html) {
            return "<img alt src='{$avatarURL}' class='avatar avatar-{$size} photo' width='{$size}' height='{$size}' />";
        } else {
            return "{$avatarURL}";
        }
    }

    protected function tryDefaultAvatar($source, $size, $html)
    {
        $avatarTag=$this->buildDefaultAvatar($size, $html);
        if ($avatarTag) {
            return $avatarTag;
        } else {
            return $source;
        }
    }

    protected function allAvatarsHaveTheSameModifiedDate($hash)
    {
        global $wpdb;

        $sql = "SELECT DISTINCT(`lastModified`) FROM `{$this->cacheTableName}` WHERE `hash` = '{$hash}'";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results[0] && count($results) == 1) {
            return true;
        }
        return false;
    }

    protected function isThereAnyLargerCached($hash, $size)
    {
        global $wpdb;
        $sql = "SELECT id, ext, lastModified, lastCheck, def, size FROM `{$this->cacheTableName}` WHERE `hash` = '{$hash}' ORDER BY `size` DESC LIMIT 1";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results[0] && $results[0]->size > $size) {
            return $results[0];
        }
        return false;
    }

    protected function isThereAnySmallerCached($hash, $size)
    {
        global $wpdb;
        $sql = "SELECT id, ext, lastModified, lastCheck, def, size FROM `{$this->cacheTableName}` WHERE `hash` = '{$hash}' ORDER BY `size` ASC LIMIT 1";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results[0]) {
            return $results[0];
        }
        return false;
    }

    protected function resizeFromLargerCached($largerCached, $hash, $size, $html)
    {
        global $wpdb;

        $result=$wpdb->insert(
          $this->cacheTableName,
            array(
              'hash' => $hash,
              'optimized' => '0',
              'size' => $size,
              'ext' => $largerCached->ext,
              'lastCheck' => $largerCached->lastCheck,
              'lastModified' => $largerCached->lastModified,
              'def' => '0'
            ),
            array('%s', '%s', '%d', '%s', '%d', '%d', '%s')
          );

        if ($result) {
            $sourceB35Id=base_convert($largerCached->id, 10, 35);
            $destB35Id=base_convert($this->getIdByHashAndSize($hash, $size), 10, 35);
            $img = wp_get_image_editor(ABSPATH."{$this->cacheDirectory}{$sourceB35Id}.{$largerCached->ext}");
            if (! is_wp_error($img)) {
                $resize = $img->set_quality(100);
                $resize = $img->resize($size, $size, true);
                $resize = $img->save(ABSPATH."{$this->cacheDirectory}{$destB35Id}.{$largerCached->ext}");
                touch(ABSPATH."{$this->cacheDirectory}{$destB35Id}.{$largerCached->ext}", $largerCached->lastModified);
            } else {
                return false;
            }
            if ($html) {
                return "<img alt src='/{$this->cacheDirectory}{$destB35Id}.{$largerCached->ext}' class='avatar avatar-{$size} photo' width='{$size}' height='{$size}' />";
            } else {
                return "/{$this->cacheDirectory}{$destB35Id}.{$largerCached->ext}";
            }
        }
        return false;
    }

    protected function getUserAvatarSize($hash, $size)
    {
        global $wpdb;
        $sql = "SELECT id, ext FROM `{$this->cacheTableName}` WHERE `hash` = '{$hash}' AND `size` = '{$size}' LIMIT 1";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results[0]) {
            return $results[0];
        }
        return false;
    }

    protected function cacheNewAvatarSize($smallerCached, $hash, $size, $html)
    {
        global $wpdb;

        $result=$wpdb->insert(
          $this->cacheTableName,
            array(
              'hash' => $hash,
              'optimized' => '0',
              'size' => $size,
              'ext' => $smallerCached->ext,
              'lastCheck' => $smallerCached->lastCheck,
              'lastModified' => $smallerCached->lastModified,
              'def' => '0'
            ),
            array('%s', '%s', '%d', '%s', '%d', '%d', '%s')
          );

        if ($this->updateAndResizeAllSizes($hash)) {
            $avatar=$this->getUserAvatarSize($hash, $size);
            $b35Id=base_convert($avatar->id, 10, 35);
            if ($html) {
                return "<img alt src='/{$this->cacheDirectory}{$b35Id}.{$avatar->ext}' class='avatar avatar-{$size} photo' width='{$size}' height='{$size}' />";
            } else {
                return "/{$this->cacheDirectory}{$b35Id}.{$avatar->ext}";
            }
        }

        return $this->tryDefaultAvatar($source, $size, $html);
    }

    protected function cacheNewDefaultSize($largerCached, $hash, $size, $html)
    {
        global $wpdb;

        $result=$wpdb->insert(
          $this->cacheTableName,
            array(
              'hash' => $hash,
              'optimized' => '0',
              'size' => $size,
              'ext' => $largerCached->ext,
              'lastCheck' => $largerCached->lastCheck,
              'lastModified' => $largerCached->lastModified,
              'def' => '1'
            ),
            array('%s', '%s', '%d', '%s', '%d', '%d', '%s')
          );
        return $this->buildDefaultAvatar($size, $html);
    }

    protected function updateResolved()
    {
        update_option('OGC_resolved', $this->resolved++);
    }

    protected function isFirstAvatarToUser($hash)
    {
        global $wpdb;
        $sql = "SELECT COUNT(id) as num FROM `{$this->cacheTableName}` WHERE `hash`='{$hash}'";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results[0] && $results[0]->num == 0) {
            return true;
        }
        return false;
    }


    protected function updateAvatarUsedSizes($size)
    {
        if (!in_array($size, $this->avatarUsedSizes)) {
            $this->avatarUsedSizes[]=$size;
            rsort($this->avatarUsedSizes);
            update_option('OGC_avatarUsedSizes', $this->avatarUsedSizes);
        }

        return true;
    }

    protected function creatDefaultUsedSizesToUser($hash, $size)
    {
        global $wpdb;

        $this->updateAvatarUsedSizes($size);

        foreach ($this->avatarUsedSizes as $usedSize) {
            $result=$wpdb->insert(
            $this->cacheTableName,
            array(
              'hash' => $hash,
              'optimized' => '0',
              'size' => $usedSize,
              'ext' => $this->customAvatarExt,
              'lastCheck' => 0,
              'lastModified' => 0,
              'def' => '1'
            ),
              array('%s', '%s', '%d', '%s', '%d', '%d', '%s')
            );
        }
        return;
    }

    public function getCachedAvatar($source, $idOrEmail, $size, $default, $alt, $html=true)
    {
        global $wpdb;

        if ($this->learningMode) {
            $this->updateAvatarUsedSizes($size);
            return $source;
        }

        if (strpos($source, 'gravatar.com') === false) {
            return $source;
        }

        $email=false;
        $user=false;

        if (is_numeric($idOrEmail)) {
            $id = (int) $idOrEmail;
            $user = get_userdata($id);
            $email=$user->user_email;
        } elseif (is_object($idOrEmail)) {
            if (!empty($idOrEmail->user_id)) {
                $id = (int) $idOrEmail->user_id;
                $user = get_userdata($id);
                $email=$user->user_email;
            } elseif (!empty($idOrEmail->comment_author_email)) {
                $email=$idOrEmail->comment_author_email;
            }
        } else {
            $user = get_user_by_email($idOrEmail);
            $email=$user->user_email;
        }

        if (!$email) {
            // return $source;
        }

        $this->updateResolved();
        $email=strtolower(trim($email));
        $lastCheck = time();
        $mailHash=md5($email);

        $sql = $wpdb->prepare("SELECT `id`, `hash`,`ext`,`def` FROM `{$this->cacheTableName}` WHERE `hash` = '%s' AND `size` = %d LIMIT 1", $mailHash, $size);
        $results = $wpdb->get_results($sql, OBJECT);

        if ($results[0] -> id) {
            if ($results[0] -> def == 0) {
                $b35Id=base_convert($results[0] -> id, 10, 35);
                if (file_exists(ABSPATH.$this->cacheDirectory.$b35Id.'.'.$results[0] -> ext)) {
                    if ($html) {
                        return "<img alt src='/{$this->cacheDirectory}{$b35Id}.{$results[0]->ext}' class='avatar avatar-{$size} photo' width='{$size}' height='{$size}' />";
                    } else {
                        return "/{$this->cacheDirectory}{$b35Id}.{$results[0]->ext}";
                    }
                } else {
                    $wpdb->query("DELETE FROM `{$this->cacheTableName}` WHERE `id`={$results[0] -> id}");
                }
            } elseif ($results[0] -> def == 1) {
                return $this->buildDefaultAvatar($size, $html);
            }
        }

        if ($this->isFirstAvatarToUser($mailHash)) {
            $this->creatDefaultUsedSizesToUser($mailHash, $size);
            return $this->tryDefaultAvatar($source, $size, $html);
        }

        $this->updateAvatarUsedSizes($size);

        $largerCached=$this->isThereAnyLargerCached($mailHash, $size);
        if ($largerCached) {
            if ($largerCached->def==0) {
                $resizedFromLarger=$this->resizeFromLargerCached($largerCached, $mailHash, $size, $html);
                if ($resizedFromLarger) {
                    return $resizedFromLarger;
                }
            } else {
                return $this->cacheNewDefaultSize($largerCached, $mailHash, $size, $html);
            }
        } else {
            $smallerCached=$this->isThereAnySmallerCached($mailHash, $size);
            if ($smallerCached) {
                if ($smallerCached->def==0) {
                    return $this->cacheNewAvatarSize($smallerCached, $mailHash, $size, $html);
                } else {
                    return $this->cacheNewDefaultSize($smallerCached, $mailHash, $size, $html);
                }
            }
        }
        return $this->tryDefaultAvatar($source, $size, $html);
    }

    public function addAdminMenu()
    {
        add_options_page('Optimum Gravatar Cache ', $this->pluginName, 'manage_options', basename(__FILE__), array( $this,'settingsViewPage' ));
    }

    protected function getDBStats()
    {
        global $wpdb;

        $sql = "SELECT count(id) as num FROM `{$this->cacheTableName}`";
        $total = $wpdb->get_results($sql, OBJECT);

        $sql = "SELECT count( DISTINCT(hash) ) as num FROM `{$this->cacheTableName}` WHERE def='1'";
        $default = $wpdb->get_results($sql, OBJECT);

        $sql = "SELECT count( DISTINCT(hash) ) as num FROM `{$this->cacheTableName}` WHERE def='0'";
        $custom = $wpdb->get_results($sql, OBJECT);

        if ($total && $default && $custom) {
            return array( 'sizes' => implode(', ', $this->avatarUsedSizes), 'total' => $total[0]->num, 'default' => $default[0]->num, 'custom' => $custom[0]->num);
        }
        return array();
    }

    public function settingsViewPage()
    {
        echo '<div class="wrap"><h2>'.$this->pluginName.'</h2>';

        if (isset($_GET['tab'])) {
            $current = $_GET['tab'];
        } else {
            $current = 'cache';
        }

        $tabs = array( 'cache' => __("Cache", "OGC"), 'defaultAvatar' => __("Default avatar", "OGC"), 'optimization' => __("Optimization", "OGC"), 'stats' => __("Stats", "OGC"));
        $links = array();
        foreach ($tabs as $tab => $name) {
            if ($tab == $current) {
                $links[] = "<a class='nav-tab nav-tab-active' href='?page=".basename(__FILE__)."&tab=$tab'>$name</a>";
            } else {
                $links[] = "<a class='nav-tab' href='?page=".basename(__FILE__)."&tab=$tab'>$name</a>";
            }
        }
        echo '<h2>';
        foreach ($links as $link) {
            echo $link;
        }
        echo '</h2>';

        switch ($current) {
            case 'cache':
              $fileCacheInfo = $this->getCacheDetails();
              require "admin/templates/cache.php";
            break;
            case 'defaultAvatar':
              require "admin/templates/default-avatar.php";
            break;
            case 'optimization':
              require "admin/templates/optimization.php";
              break;
            case 'stats':
              $dbCacheInfo = $this->getDBStats();
              $fileCacheInfo = $this->getCacheDetails();
              require "admin/templates/stats.php";
            break;
        }
        echo "</div>";
    }

    protected function clearCache()
    {
        global $wpdb;
        if (!$wpdb->query("TRUNCATE TABLE `{$this->cacheTableName}`")) {
            $this->errorMessages[]=array(
                "type" => "error notice",
                "message" => __("Unable to clear data from the table.", "OGC")
            );
            return false;
        }

        if (!$wpdb->query("ALTER TABLE `{$this->cacheTableName}` AUTO_INCREMENT = 1")) {
            $this->errorMessages[]=array(
                "type" => "error notice",
                "message" => __("Unable to reset AUTO_INDEX from the table.", "OGC")
            );
            return false;
        }

        if (is_dir(ABSPATH.$this->cacheDirectory)) {
            $fileList = glob(ABSPATH.$this->cacheDirectory.'*.{'.implode(",", array_values($this->mimeTypes)).',O,svg.gz}', GLOB_BRACE);
            foreach ($fileList as $file) {
                if (!unlink($file)) {
                    $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" =>  __("Could not delete file '%s' check permissions.", "OGC"),
                      "args"=>array($file)
                    );
                }
            }
        }
    }

    protected function getCacheDetails()
    {
        $fileList=array();
        $size = 0;
        $mimeTypesList=array();
        if ($this->cacheDirectory != "" && is_dir(ABSPATH.$this->cacheDirectory)) {
            $fileList = glob(ABSPATH.$this->cacheDirectory.'/*.{'.implode(",", array_values($this->mimeTypes)).'}', GLOB_BRACE);
            foreach ($fileList as $file) {
                $mimeTypes=mime_content_type($file);
                if (!in_array($mimeTypes, $mimeTypesList)) {
                    $mimeTypesList[]=$mimeTypes;
                }
                $size  += filesize($file);
            }
        }
        return array( 'images' => count($fileList), 'usedSpace' => $this->sizeToByte($size), 'typesUsed' => implode(", ", $mimeTypesList) );
    }

    protected function sizeToByte($size)
    {
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        return number_format_i18n($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    public function adminScripts()
    {
        $current_page = get_current_screen()->base;
        if ($current_page == 'settings_page_'.basename(dirname(__FILE__))) {
            wp_enqueue_script('ogc-main-script', plugins_url('/admin/js/main.js', __FILE__), array('jquery'));
            wp_enqueue_style('ogc-main-style', plugins_url('/admin/css/style.css', __FILE__));
        }
    }

    protected function getUserAvatarsCached($hash)
    {
        global $wpdb;

        $sql = "SELECT `id`, `ext` FROM `{$this->cacheTableName}` WHERE `hash` = '$hash' AND `def`='0'";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results[0]) {
            return $results;
        } else {
            return false;
        }
    }

    public function deleteUserAvatarsCache($user_id)
    {
        global $wpdb;
        $userData = get_userdata($user_id);
        $mailHASH=md5($userData->user_email);

        $users=$this->getUserAvatarsCached($mailHASH);
        if ($users) {
            foreach ($users as $user) {
                $b35Id=base_convert($user -> id, 10, 35);
                if (file_exists(ABSPATH.$this->cacheDirectory.$b35Id.'.'.$user -> ext)) {
                    unlink(ABSPATH.$this->cacheDirectory.$b35Id.'.'.$user -> ext);
                }
            }
        }

        $wpdb->query("DELETE FROM `{$this->cacheTableName}` WHERE `hash`='{$mailHASH}'");
    }
}

require_once(ABSPATH.'wp-includes/pluggable.php');
$OGC = new OGC();
