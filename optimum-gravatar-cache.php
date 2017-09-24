<?php
/*
Plugin Name: Optimum Gravatar Cache
Author: José Miguel Silva Caldeira
Version: 0.0.1
Description: It cache the gravatars locally, reducing the total number of requests per post. This will speed up the loading of the site and consequently improve the user experience.
Author URI: https://www.ncdc.pt/members/admin
Text Domain: OGC
*/

if (!defined('ABSPATH')) {
    exit;
}

class OGC
{
    protected $options;
    protected $mimeTypes=array("image/jpeg" => "jpg","image/png" => "png","image/gif" => "gif", "image/svg+xml"=>"svg");
    protected $pluginName = 'Optimum Gravatar Cache';
    protected $cacheDirectory;
    protected $expiryTime;
    protected $activated;
    protected $errorMessages=array();
    protected $cacheTableName;
    protected $validExtention=array("jpg","png","gif","svg");
    protected $searchExpiredTime;
    protected $maxUpdateEachTime;
    protected $defaultAvatar;
    protected $customAvatarExt;

    public function __construct()
    {
        global $wpdb;

        $this->cacheTableName=$wpdb->prefix."optimum_gravatar_cache";
        $this->resolved=get_option('OGC_resolved');
        $this->options = get_option('OGC_options');
        $this->cacheDirectory = $this->options['cache_directory'];
        $this->expiryTime=$this->options['expiryTime'];
        $this->activated=$this->options['activated'];
        $this->searchExpiredTime=$this->options['searchExpiredTime'];
        $this->maxUpdateEachTime=$this->options['maxUpdateEachTime'];
        $this->optimizeAvatars=$this->options['optimizeAvatars'];
        $this->defaultAvatar=$this->options['defaultAvatar'];
        $this->customAvatarExt=$this->options['customAvatarExt'];

        if ($this->permissionsToRun() && $this->optionsToRun() && $this->activated) {
            add_filter('get_avatar', array( $this,'getCachedAvatar' ), 1, 5);
            add_filter('bp_core_fetch_avatar', array( $this,'getBPressCachedAvatar' ), 1, 9);
            add_filter('cron_schedules', array( $this, 'schedules'));
        }

        if (is_admin()) {
            load_plugin_textdomain('OGC', false, basename(dirname(__FILE__)) . '/languages/');
            register_activation_hook(__FILE__, array( $this, 'activate' ));
            register_deactivation_hook(__FILE__, array( $this, 'deactivate' ));
            // register_uninstall_hook(__FILE__, array( $this, 'uninstall' )); //next
            add_action('admin_menu', array( $this,'add_admin_menu'));
            add_action('admin_enqueue_scripts', array( $this, 'adminScripts'));
            add_action('admin_notices', array( $this, 'adminPermissionsNotices'));
            add_filter("plugin_action_links_".plugin_basename(__FILE__), array( $this, 'addSettingsLink'));
            add_filter('plugin_row_meta', array( $this, 'addProjectLinks'), 10, 2);

            if (isset($_POST['clearCache'])) {
                $this->clearCache();
            }

            if (isset($_POST['updateOptions'])) {
                $this->updateOptions();
            }
        }

        if ($this->permissionsToRun() && $this->optionsToRun() && $this->activated) {
            add_action('OGC_CronEvent', array( $this, 'updateCache'));
        }
    }

    public function addSettingsLink($links)
    {
        $settingsLink = '<a href="options-general.php?page='.basename(__FILE__).'">' . __('Settings') . '</a>';
        array_push($links, $settingsLink);
        return $links;
    }

    public function addProjectLinks($links, $file)
    {
        if (strpos(__FILE__, dirname($file)) !== false) {
            $newLinks = array(
                    'discussionGroup' => '<a href="//www.ncdc.pt/groups/wordpress-optimum-gravatar-cache" target="_blank">'.__("Discussion Group", 'OGC').'</a>',
                    'gitHub' => '<a href="//github.com/jomisica/optimum-gravatar-cache" target="_blank">'.__("GitHub Project", 'OGC').'</a>'
                    );
            $links = array_merge($links, $newLinks);
        }
        return $links;
    }

    public function schedules($schedules)
    {
        $schedules["OGC_job"] = array(
            // 'interval' => $this->searchExpiredTime*60*60,
            'interval' => 60,

            'display' => 'OGC job'
          );
        return $schedules;
    }

    // public function loadTextdomain()
    // {
    //     load_plugin_textdomain('OGC', false, basename(dirname(__FILE__)) . '/languages/');
    // }

    protected function setCronEvent()
    {
        wp_schedule_event(time(), 'OGC_job', 'OGC_CronEvent');
    }

    protected function clearCronEvent()
    {
        remove_action('OGC_CronEvent', 'updateCache');
        wp_clear_scheduled_hook('OGC_CronEvent');
    }

    public function getDefaultAvatar()
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

    public function getLogo()
    {
        return plugins_url('/media/logo.svg', __FILE__);
    }

    public function cleanDefaultAvatars()
    {
        global $wpdb;

        foreach (glob(ABSPATH.$this->cacheDirectory."0*") as $filename) {
            unlink($filename);
        }

        $wpdb->query("DELETE FROM `{$this->cacheTableName}` WHERE def='1'");
    }

    protected function updateOptions()
    {
        if (isset($_POST['OGC_options']['directory'])) {
            $this->cacheDirectory=trim($_POST['OGC_options']['directory'], '/') . '/';
        }
        if (isset($_POST['OGC_options']['activated'])) {
            $this->activated=intval($_POST['OGC_options']['activated']);
        }
        if (isset($_POST['OGC_options']['expiryTime'])) {
            $this->expiryTime=intval($_POST['OGC_options']['expiryTime']);
        }
        if (isset($_POST['OGC_options']['searchExpiredTime'])) {
            $this->searchExpiredTime=intval($_POST['OGC_options']['searchExpiredTime']);
        }
        if (isset($_POST['OGC_options']['maxUpdateEachTime'])) {
            $this->maxUpdateEachTime=intval($_POST['OGC_options']['maxUpdateEachTime']);
        }
        if (isset($_POST['OGC_options']['optimizeAvatars'])) {
            $this->optimizeAvatars=intval($_POST['OGC_options']['optimizeAvatars']);
        }

        $this->options['cache_directory']=$this->cacheDirectory;
        $this->options['expiryTime']=$this->expiryTime;
        $this->options['activated']=$this->activated;
        $this->options['searchExpiredTime']=$this->searchExpiredTime;
        $this->options['maxUpdateEachTime']=$this->maxUpdateEachTime;
        $this->options['optimizeAvatars']=$this->optimizeAvatars;

        $errorPermissions=$this->adminPermissionsToRun();
        $errorOptions=$this->adminOptionsToRun();

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

        if ($needCleanCache==true) {
            $this->cleanDefaultAvatars();
        }

        if ($errorPermissions || $errorOptions) {
            $this->options['activated']=0;
            $this->activated=0;
        } else {
            $this->options['messages']="";
            $this->clearCronEvent();
            $this->setCronEvent();
        }
        // var_dump($this->options);

        update_option('OGC_options', $this->options);
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
                if (move_uploaded_file($_FILES["file"]["tmp_name"], dirname(__FILE__) . '/avatar/custom.'.$this->mimeTypes[$_FILES["file"]["type"]])) {
                    $options=array(
                      'default'=>false,
                      'ext'=>pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION),
                    );

                    if (!copy(dirname(__FILE__) . '/avatar/custom.'.$ext, ABSPATH.$this->cacheDirectory.'0.svg')) {
                        $this->errorMessages[]=array(
                          "type" => "error notice",
                          "message" => __("Could not save avatar.", 'OGC'),
                          "args"=>array()
                        );
                    } else {
                        return $options;
                    }
                } else {
                    $this->errorMessages[]=array(
                          "type" => "error notice",
                          "message" => __("Could not save avatar.", 'OGC'),
                          "args"=>array()
                        );
                }
            } else {
                $this->errorMessages[]=array(
                    "type" => "error notice",
                    "message" => __("The file type is not supported.", 'OGC'),
                    "args"=>array()
                  );
            }
          break;
          case UPLOAD_ERR_INI_SIZE:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("The uploaded file exceeds the upload_max_filesize directive in php.ini.", 'OGC'),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_FORM_SIZE:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.", 'OGC'),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_PARTIAL:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("The uploaded file was only partially uploaded.", 'OGC'),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_NO_TMP_DIR:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("Missing a temporary folder.", 'OGC'),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_CANT_WRITE:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("Failed to write file to disk.", 'OGC'),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_EXTENSION:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("File upload stopped by extension.", 'OGC'),
                      "args"=>array()
                    );
          break;
          default:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __("Unknown upload error.", 'OGC'),
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
                  "message" => __("Could not create cache directory '%s'. Please set read/write/execute (755) permissions for '%s' and/or correct owner.", 'OGC'),
                  "args"=>array(ABSPATH.$this->cacheDirectory,dirname(ABSPATH.$this->cacheDirectory))
                );
                $error=true;
            }
            if ((!is_writable(ABSPATH.$this->cacheDirectory) || !is_executable(ABSPATH.$this->cacheDirectory)) && !chmod(ABSPATH.$this->cacheDirectory, 0755)) {
                $this->errorMessages[]=array(
                  "type" => "error notice",
                  "message" => __("Please set read/write/execute (755) permissions for '%s' and/or correct owner.", 'OGC'),
                  "args"=>array(ABSPATH.$this->cacheDirectory)
                );
                $error=true;
            }
            if (!mkdir(ABSPATH."{$this->cacheDirectory}tmp", 0755, true) && !is_dir(ABSPATH."{$this->cacheDirectory}tmp")) {
                $this->errorMessages[]=array(
                  "type" => "error notice",
                  "message" => __("Could not create cache directory '%s'. Please set read/write/execute (755) permissions for '%s' and/or correct owner.", 'OGC'),
                  "args"=>array(ABSPATH."{$this->cacheDirectory}tmp",dirname(ABSPATH."{$this->cacheDirectory}tmp"))
                );
                $error=true;
            }
            if ((!is_writable(ABSPATH."{$this->cacheDirectory}tmp") || !is_executable(ABSPATH."{$this->cacheDirectory}tmp")) && !chmod(ABSPATH."{$this->cacheDirectory}tmp", 0755)) {
                $this->errorMessages[]=array(
                  "type" => "error notice",
                  "message" => __("Please set read/write/execute (755) permissions for '%s' and/or correct owner.", 'OGC'),
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

        if ($this->expiryTime < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => __("<b>Refresh gravatars cache every:</b> This option accepts an integer value from 1.", 'OGC'),
          "args"=>array()
      );
            $error=true;
        }
        if ($this->searchExpiredTime < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => __("<b>Search for outdated gravatars every:</b> This option accepts an integer value from 1.", 'OGC'),
          "args"=>array()
      );
            $error=true;
        }
        if ($this->maxUpdateEachTime < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => __("<b>How many gravatars to upgrade or optimize each time:</b> This option accepts an integer value from 1.", 'OGC'),
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
        if ($this->expiryTime < 1) {
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

    public function getBPressCachedAvatar($content, $params, $id)
    {
        if (is_array($params) && $params['object'] == 'user') {
            return $this->getCachedAvatar($content, $params['item_id'], $params['width'], null, null);
        }
        return $content;
    }

    // Activate plugin and update default option
    public function activate()
    {
        global $wpdb;

        $wpdb->query("CREATE TABLE IF NOT EXISTS `{$this->cacheTableName}` (
				  `id` int(11) NOT NULL auto_increment,
				  `email` varchar(255) NOT NULL,
				  `hash` char(32) NOT NULL,
				  `optimized` enum('0','1') NOT NULL,
				  `size` int(5) NOT NULL,
				  `ext` enum('svg','jpg','png','gif') NOT NULL,
				  `lastCheck` int(11) NOT NULL,
				  `lastModified` int(11) NOT NULL,
				  `def` enum('0','1') NOT NULL,
          ADD PRIMARY KEY (`id`),
          ADD KEY `hash` (`hash`),
          ADD KEY `size` (`size`)
				)");

        $resolved=get_option('OGC_resolved');
        if ($resolved == false) {
            $resolved=0;
        }

        $default_options=get_option('OGC_options');
        if ($default_options == false) {
            $default_options = array(
                    'activated'   => 0,
                    'expiryTime' => 10,
                    'cache_directory' => 'cache/avatar/',
                    'searchExpiredTime'=> 1,
                    'maxUpdateEachTime' => 3,
                    'messages' => array(),
                    'defaultAvatar'=> true,
                    'customAvatarExt'=>  'svg',
                    'optimizeAvatars'=> 1
            );
        }

        if (!$this->permissionsToRun() || !$this->optionsToRun()) {
            $default_options['messages'][] = array(
            'type' => "notice notice-info",
            'message' => __("The plugin has been activated but needs to be configured to work. \
            Enter the configuration page through the menu. You need at least specify the directory \
            where the gravatars will be saved.", 'OGC'),
            'args'=>array()
          );
        } else {
            $this->setCronEvent();
        }
        update_option('OGC_resolved', $resolved);
        update_option('OGC_options', $default_options);
    }

    // Deactivate plugin
    public function deactivate()
    {
        $this->clearCronEvent();
    }

    protected function getGravatarOnline($option)
    {
        $properties = new stdClass();
        $curl = curl_init("http://www.gravatar.com/avatar/{$option}");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_FILETIME, true);

        $response    = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $properties->status = $httpCode;

        if ($httpCode == 200) {
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $mime  = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
            $properties->ext = $this->mimeTypes[$mime];
            $properties->lastModified = curl_getinfo($curl, CURLINFO_FILETIME);
            $properties->content = substr($response, $headerSize);
        }
        curl_close($curl);
        return $properties;
    }

    protected function getGravatarStatusOnline($options)
    {
        $properties = new stdClass();
        $headers=get_headers("https://www.gravatar.com/avatar/{$options}", 1);
        $lastModified=(int)strtotime($headers['Last-Modified']);
        $status=explode(' ', $headers['0']);
        $status=(int)$status[1];

        if ($status != 200 && $status != 404) {
            return false;
        }
        $properties->status = $status;
        $properties->lastModified = $lastModified;
        $properties->ext = $this->mimeTypes[$headers['Content-Type']];

        return $properties;
    }

    public function updateAndResizeUserAllAvatars($hash)
    {
        global $wpdb;

        // avatars sizes used
        $sql = "SELECT DISTINCT(`size`), id FROM `{$this->cacheTableName}` WHERE `hash` = '$hash' ORDER BY `size` DESC";
        $sizeResults = $wpdb->get_results($sql, OBJECT);
        $sizes = array();
        foreach ($sizeResults as $avatarSize) {
            $sizes[] = $avatarSize->size;
        }
        $maxValue = max($sizes);
        $options = "{$hash}?s={$maxValue}&r=G&d=404";
        $newGravatar=$this->getGravatarOnline($options);
        if ($newGravatar->status == 200) {
            file_put_contents(ABSPATH."{$this->cacheDirectory}tmp/{$hash}.{$newGravatar->ext}", $newGravatar->content);
            $img = wp_get_image_editor(ABSPATH."{$this->cacheDirectory}tmp/{$hash}.{$newGravatar->ext}");
            if (! is_wp_error($img)) {
                $sizes_array = array();
                foreach ($sizeResults as $size) {
                    $sizes_array[] =array('width' => $size->size, 'height' => $size->size, 'crop' => true);
                }
                $resize = $img->set_quality(100);
                $resize = $img->multi_resize($sizes_array);
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
            }
            $lastCheck = time();
            $wpdb->query("UPDATE `{$this->cacheTableName}` SET `optimized`='0', `lastCheck`={$lastCheck}, `def`='0', `ext`='{$newGravatar->ext}', `lastModified`={$newGravatar->lastModified} WHERE `hash`='{$hash}'");
            return true;
        }
        return false;
    }

    public function updateCache()
    {
        global $wpdb;
        $fp = fopen(ABSPATH."{$this->cacheDirectory}lock", 'w+');
        if (!flock($fp, LOCK_EX|LOCK_NB)) {
            return;
        }

        $time=time()-$this->expiryTime * 86400;//86400 1 day
        $sql = "SELECT DISTINCT(`hash`), `def`, `lastModified` FROM `{$this->cacheTableName}` WHERE lastCheck < {$time} ORDER BY `lastCheck` ASC LIMIT {$this->maxUpdateEachTime}";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results) {
            foreach ($results as $user) {
                $maxValue=$this->getUserAvatarCacheMaxSize($user->hash);
                $lastCheck = time();
                $options = "{$user->hash}?s={$maxValue}&r=G&d=404";
                $gravatarStatus=$this->getGravatarStatusOnline($options);
                if ($gravatarStatus->status == 404 && $user->def == 1) {
                    $wpdb->query("UPDATE `{$this->cacheTableName}` SET `lastCheck`={$lastCheck} WHERE `hash`='{$user->hash}'");
                    continue;
                } elseif ($gravatarStatus->status == 404 && $user->def == 0) {
                    $wpdb->query("UPDATE `{$this->cacheTableName}` SET `optimized`='0', `lastCheck`={$lastCheck}, `def`='1', `ext`='{$this->customAvatarExt}', `lastModified`=0 WHERE `hash`='{$user->hash}'");
                    continue;
                } elseif ($gravatarStatus->status == 200 && $user->def == 0) {
                    if ($user->lastModified == $gravatarStatus->lastModified) {
                        continue;
                    }
                } elseif ($gravatarStatus->status == 200 && $user->def == 1) {
                }
                $this->updateAndResizeUserAllAvatars($user->hash);
            }
        }
        if ($this->optimizeAvatars) {
            $this->optimizeCache();
        }
        // sleep(3600);
        flock($fp, LOCK_UN);
    }

    protected function sendResmushRequest($options)
    {
        $properties = new stdClass();
        $properties->error = false;
        $curl = curl_init("http://api.resmush.it/ws.php?img=".$options);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_FILETIME, true);
        $response    = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode == 200) {
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $data=json_decode(substr($response, $headerSize));

            if (isset($data->error)) {
                $properties->error = true;
            } else {
                $properties->optimizedURL = $data->dest;
            }
        } else {
            $properties->error = true;
        }

        curl_close($curl);
        return $properties;
    }

    protected function getOptimizedGravatar($url)
    {
        $properties = new stdClass();
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_FILETIME, true);

        $response    = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $properties->status = $httpCode;

        if ($httpCode == 200) {
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $mime   = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
            $properties->ext = $this->mimeTypes[$mime];
            $properties->lastModified = curl_getinfo($curl, CURLINFO_FILETIME);
            $properties->content = substr($response, $headerSize);
        }
        curl_close($curl);
        return $properties;
    }

    public function optimizeCache()
    {
        global $wpdb;
        // return;
        file_put_contents(ABSPATH."cache/avatar/test.txt", __LINE__." - "." optimizeCache1\n", FILE_APPEND);
        $sql = "SELECT `id`, `size`, `ext`  FROM `{$this->cacheTableName}` WHERE (optimized='0' AND def='0') ORDER BY id LIMIT {$this->maxUpdateEachTime}";
        $results = $wpdb->get_results($sql, OBJECT);
        file_put_contents(ABSPATH."/cache/avatar/test.txt", __LINE__." - ".json_encode($results)." Necessita atualisar2\n", FILE_APPEND);
        if ($results) {
            foreach ($results as $gravatar) {
                // $b35Id=dechex($gravatar -> id);
                $b35Id=base_convert($gravatar -> id, 10, 35);

                // file_put_contents(ABSPATH."/cache/avatar/test.txt", __LINE__." - ".json_encode($gravatar)." Necessita atualisar3\n", FILE_APPEND);
                file_put_contents(ABSPATH."/cache/avatar/test.txt", json_encode(ABSPATH.$this->cacheDirectory.$b35Id.'-'.$gravatar ->size.'.'.$gravatar -> ext)." Necessita atualisar\n", FILE_APPEND);
                // if (!file_exists(ABSPATH."{$this->cacheDirectory}{$b35Id}-{$gravatar ->size}.{$gravatar -> ext}")) {
                  if (!file_exists(ABSPATH."{$this->cacheDirectory}{$b35Id}.{$gravatar -> ext}")) {

                    // file_put_contents(ABSPATH."/cache/avatar/test.txt", json_encode($gravatar)." Necessita atualisar\n", FILE_APPEND);
                    // file_put_contents(ABSPATH."/cache/avatar/test.txt", json_encode(ABSPATH.$this->cacheDirectory.$b35Id.'-'.$gravatar ->size.'.'.$gravatar -> ext)." Necessita atualisar\n", FILE_APPEND);
                    continue;
                  }
                // file_put_contents(ABSPATH."/cache/avatar/test.txt", __LINE__." - ".json_encode($gravatar)." Necessita atualisar4\n", FILE_APPEND);
                // $options=site_url()."/{$this->cacheDirectory}{$b35Id}-{$gravatar -> size}.{$gravatar -> ext}";
                // $options=site_url()."/{$this->cacheDirectory}{$b35Id}.{$gravatar -> ext}";
                $options=site_url()."/{$this->cacheDirectory}11p.jpg";

                file_put_contents(ABSPATH."cache/avatar/test.txt", __LINE__." - ".json_encode($options)."\n\n", FILE_APPEND);

                $optimizedGravatarRequest=$this->sendResmushRequest($options);
                file_put_contents(ABSPATH."cache/avatar/test.txt", __LINE__." - ".json_encode($optimizedGravatarRequest)."- optimizeCache5\n\n", FILE_APPEND);

                if (!$optimizedGravatarRequest->error) {
                    $optimizedGravatar=$this->getOptimizedGravatar($optimizedGravatarRequest->optimizedURL);
                    if ($optimizedGravatar->status == 200) {
                        file_put_contents(ABSPATH."cache/avatar/test.txt", __LINE__." - ".json_encode($optimizedGravatar->status)."- optimizeCache6\n\n", FILE_APPEND);
                        if (file_put_contents(ABSPATH."{$this->cacheDirectory}{$b35Id}.{$gravatar -> ext}", $optimizedGravatar->content)) {
                            file_put_contents(ABSPATH."cache/avatar/test.txt", __LINE__." - ".json_encode(ABSPATH."{$this->cacheDirectory}{$b35Id}.{$gravatar -> ext}")."- optimizeCache7\n\n", FILE_APPEND);

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

    protected function buildDefaultAvatar($size)
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
        return "<img alt src='{$avatarURL}' class='avatar avatar-{$size}' width='{$size}' height='{$size}' />";
    }

    protected function tryDefaultAvatar($source, $size)
    {
        $avatarTag=$this->buildDefaultAvatar($size);
        if ($avatarTag) {
            return $avatarTag;
        } else {
            return $source;
        }
    }

    protected function getUserAvatarCacheMaxSize($hash)
    {
        global $wpdb;

        $sql = "SELECT DISTINCT(`size`) FROM `{$this->cacheTableName}` WHERE `hash` = '$hash' ORDER BY `size` DESC LIMIT 1";
        $sizeResults = $wpdb->get_results($sql, OBJECT);
        if ($sizeResults[0]) {
            return $sizeResults[0]->size;
        } else {
            return false;
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

    protected function isThereALargerOnCache($hash, $size)
    {
        global $wpdb;
        $sql = "SELECT id FROM `{$this->cacheTableName}` WHERE `hash` = '{$hash}' AND `size` > $size LIMIT 1";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results[0]) {
            return true;
        }
        return false;
    }

    protected function resizeFromBiggerOnCache($hash, $size, $b35Id)
    {
        global $wpdb;
        $sql = "SELECT id, ext FROM `{$this->cacheTableName}` WHERE `hash` = '{$hash}' AND `size` > $size LIMIT 1";
        $results = $wpdb->get_results($sql, OBJECT);

        if ($results[0]) {
            $sourceB35Id=base_convert($results[0]->id, 10, 35);
            $img = wp_get_image_editor(ABSPATH."{$this->cacheDirectory}{$sourceB35Id}.{$results[0]->ext}");
            if (! is_wp_error($img)) {
                $resize = $img->set_quality(100);
                $resize = $img->resize($size, $size, true);
                $resize = $img->save(ABSPATH."{$this->cacheDirectory}{$b35Id}.{$results[0]->ext}");
            } else {
                return false;
            }

            return true;
        }
        return false;
    }

    protected function updateResolved()
    {
        update_option('OGC_resolved', $this->resolved +=1);
    }

    public function getCachedAvatar($source, $idOrEmail, $size, $default, $alt)
    {
        global $wpdb;

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
            // return ""; //$source;
        }
        $this->updateResolved();
        $email=strtolower(trim($email));
        $lastCheck = time();
        $mailHash=md5($email);

        $sql = $wpdb->prepare("SELECT `id`, `hash`,`ext`,`def` FROM `{$this->cacheTableName}` WHERE `hash` = %s AND `size` = %d LIMIT 1", $mailHash, $size);
        $results = $wpdb->get_results($sql, OBJECT);

        if ($results[0] -> id) {
            if ($results[0] -> def == 0) {
                $b35Id=base_convert($results[0] -> id, 10, 35);
                if (file_exists(ABSPATH.$this->cacheDirectory.$b35Id.'.'.$results[0] -> ext)) {
                    return "<img alt src='/{$this->cacheDirectory}{$b35Id}.{$results[0]->ext}' \
                        class='avatar avatar-{$size}' width='{$size}' height='{$size}' />";
                } else {
                    $wpdb->query("DELETE FROM `{$this->cacheTableName}` WHERE `id`={$results[0] -> id}");
                }
            } elseif ($results[0] -> def == 1) {
                if ($this->customAvatarExt =="svg") {
                    $avatarFile = ABSPATH.$this->cacheDirectory.'0.svg';
                    $avatarURL="/{$this->cacheDirectory}0.svg";
                } else {
                    $sizeId=base_convert($size, 10, 35);
                    $avatarFile = ABSPATH."{$this->cacheDirectory}0$sizeId.{$this->customAvatarExt}";
                    $avatarURL="/{$this->cacheDirectory}0$sizeId.{$this->customAvatarExt}";
                }
                if (file_exists($avatarFile)) {
                    return "<img alt src='$avatarURL' class='avatar avatar-{$size}' width='{$size}' height='{$size}' />";
                } else {
                    $wpdb->query("DELETE FROM `{$this->cacheTableName}` WHERE `id`={$results[0] -> id}");
                }
            }
        }



        $options = $mailHash.'?s='.$size.'&r=G&d=404';
        $gravatar=$this->getGravatarStatusOnline($options);

        if ($gravatar->status == 200) {
            $result=$wpdb->insert(
                $this->cacheTableName,
                array(
                  'email' => $email,
                  'hash' => $mailHash,
                  'optimized' => '0',
                  'size' => $size,
                  'ext' => $gravatar->ext,
                  'lastCheck' => $lastCheck,
                  'lastModified' => $gravatar->lastModified,
                  'def' => '0'
                ),
                array('%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s')
            );

            if (!$result) {
                return $this->tryDefaultAvatar($source, $size);
            }

            $b35Id=base_convert($this->getIdByHashAndSize($mailHash, $size), 10, 35);
            if (!$b35Id) {
                return $this->tryDefaultAvatar($source, $size);
            }

            $allAvatarsHaveTheSameDate = $this->allAvatarsHaveTheSameModifiedDate($mailHash);

            if ($allAvatarsHaveTheSameDate) {
                if ($this->isThereALargerOnCache($mailHash, $size)) {
                    if (!$this->resizeFromBiggerOnCache($mailHash, $size, $b35Id)) {
                        return $this->tryDefaultAvatar($source, $size);
                    }
                } else {
                    $gravatar=$this->getGravatarOnline($options);
                    if (!file_put_contents(ABSPATH.$this->cacheDirectory.$b35Id.'.'.$gravatar->ext, $gravatar->content)) {
                        return $this->tryDefaultAvatar($source, $size);
                    }
                }
            } else {
                $this->updateAndResizeUserAllAvatars($mailHash);
            }
            return "<img alt src='/{$this->cacheDirectory}{$b35Id}.{$gravatar->ext}' class='avatar avatar-{$size}' width='{$size}' height='{$size}' />";
        } elseif ($gravatar->status == 404) {
            $avatarTag=$this->buildDefaultAvatar($size);
            if ($avatarTag) {
                $result=$wpdb->insert(
                $this->cacheTableName,
                array(
                  'email' => $email,
                  'hash' => $mailHash,
                  'optimized' => '0',
                  'size' => $size,
                  'ext' => $this->customAvatarExt,
                  'lastCheck' => $lastCheck,
                  'lastModified' => 0,
                  'def' => '1'
                ),
                  array('%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s')
                );

                if (!$result) {
                    return $avatarTag;
                }
                $allAvatarsHaveTheSameDate = $this->allAvatarsHaveTheSameModifiedDate($mailHash);
                if (!$allAvatarsHaveTheSameDate) {
                    $wpdb->query("UPDATE `{$this->cacheTableName}` SET `optimized`='0', `lastCheck`={$lastCheck}, `def`='1', `ext`='{$this->customAvatarExt}', `lastModified`=0 WHERE `hash`='{$mailHash}'");
                }
                return $avatarTag;
            } else {
                return $source;
            }
        } else {
            return $source;
        }
    }

    public function add_admin_menu()
    {
        add_options_page('Optimum Gravatar Cache ', $this->pluginName, 'manage_options', basename(__FILE__), array( $this,'settingsViewPage' ));
    }

    public function getDBStats()
    {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT count(id) as num FROM `{$this->cacheTableName}`");
        $total = $wpdb->get_results($sql, OBJECT);

        $sql = $wpdb->prepare("SELECT count( DISTINCT(hash) ) as num FROM `{$this->cacheTableName}` WHERE def='1' ");
        $default = $wpdb->get_results($sql, OBJECT);

        $sql = $wpdb->prepare("SELECT count( DISTINCT(hash) ) as num FROM `{$this->cacheTableName}` WHERE def='0' ");
        $custom = $wpdb->get_results($sql, OBJECT);

        $sql = "SELECT DISTINCT(`size`) FROM `{$this->cacheTableName}`";
        $sizesRresults = $wpdb->get_results($sql, OBJECT);

        $sizes=array();
        foreach ($sizesRresults as $index => $contents) {
            $sizes[]=$contents->size;
        }

        if ($total && $default && $custom) {
            return array( 'sizes' => implode(', ', $sizes), 'total' => $total[0]->num, 'default' => $default[0]->num, 'custom' => $custom[0]->num);
        }
        return;
    }

    public function settingsViewPage()
    {
        echo '<div class="wrap"><h2>'.$this->pluginName.'</h2>';

        if (isset($_GET['tab'])) {
            $current = $_GET['tab'];
        } else {
            $current = 'cache';
        }

        $tabs = array( 'cache' => __('Cache', 'OGC'), 'defaultAvatar' => __('Default avatar', 'OGC'), 'optimization' => __('Optimization', 'OGC'), 'stats' => __('Stats', 'OGC'));
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
              require "templates/cache.php";
            break;
            case 'defaultAvatar':
              require "templates/default-avatar.php";
            break;
            case 'optimization':
              require "templates/optimization.php";
              break;
            case 'stats':
              $dbCacheInfo = $this->getDBStats();
              $fileCacheInfo = $this->getCacheDetails();
              require "templates/stats.php";
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
                "message" => __('Unable to clear data from the table.', 'OGC')
            );
            return false;
        }

        if (!$wpdb->query("ALTER TABLE `{$this->cacheTableName}` AUTO_INCREMENT = 1")) {
            $this->errorMessages[]=array(
                "type" => "error notice",
                "message" => __('Unable to reset AUTO_INDEX from the table.', 'OGC')
            );
            return false;
        }

        if (is_dir(ABSPATH.$this->cacheDirectory)) {
            if ($opendir = opendir(ABSPATH.$this->cacheDirectory)) {
                while (($file = readdir($opendir)) !== false) {
                    if (filetype(ABSPATH.$this->cacheDirectory . $file) == 'file') {
                        if (!unlink(ABSPATH.$this->cacheDirectory . $file)) {
                            $this->errorMessages[]=array(
                                "type" => "error notice",
                                "message" =>  __("Could not delete file '%s' check permissions.", 'OGC'),
                                "args"=>array($file)
                            );
                        }
                    }
                }
                closedir($opendir);
            }
        }
    }

    protected function getCacheDetails()
    {
        if (is_dir(ABSPATH.$this->cacheDirectory)) {
            $fileList = glob(ABSPATH.$this->cacheDirectory.'/*.{'.implode(",", array_values($this->mimeTypes)).'}', GLOB_BRACE);
            $mimeTypesList=array();
            $size = 0;
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
            wp_enqueue_script('ogc-main-script', plugins_url('/js/main.js', __FILE__), array('jquery'));
            wp_enqueue_style('ogc-main-style', plugins_url('/css/style.css', __FILE__));
        }
    }
}

$OGC = new OGC();
