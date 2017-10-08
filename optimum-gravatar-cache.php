<?php
/*
Plugin Name: Optimum Gravatar Cache
Author: José Miguel Silva Caldeira
Version: 0.9.2
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
    protected $maxOptimizeEachTime;
    protected $defaultAvatar;
    protected $customAvatarExt;
    protected $learningMode;
    protected $resolved;
    protected $avatarUsedSizes;
    protected $avatarRating;
    protected $optimizeAvatars;
    protected static $curl;

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
        $this->expiryTime=$this->options['expiryTime'];
        $this->activated=$this->options['activated'];
        $this->searchExpiredTime=$this->options['searchExpiredTime'];
        $this->maxUpdateEachTime=$this->options['maxUpdateEachTime'];
        $this->optimizeAvatars=$this->options['optimizeAvatars'];
        $this->defaultAvatar=$this->options['defaultAvatar'];
        $this->customAvatarExt=$this->options['customAvatarExt'];
        $this->learningMode=$this->options['learningMode'];
        $this->maxOptimizeEachTime=$this->options['maxOptimizeEachTime'];

        if ($this->permissionsToRun() && $this->optionsToRun() && $this->activated) {
            add_filter('get_avatar', array( $this,'getCachedAvatar' ), 1, 5);
            add_filter('bp_core_fetch_avatar', array( $this,'getBPressCachedAvatar' ), 1, 9);
            if (!$this->learningMode) {
                add_filter('cron_schedules', array( $this, 'schedules'));
                $this->setCronEvent();
                add_action('OGC_CronEvent', array( $this, 'updateCache'));
            }
        }

        if (is_admin() && in_array('administrator', wp_get_current_user()->roles)) {
            load_plugin_textdomain('OGC', false, basename(dirname(__FILE__)) . '/languages/');
            register_activation_hook(__FILE__, array( $this, 'activate' ));
            register_deactivation_hook(__FILE__, array( $this, 'deactivate' ));
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
    }

    public function addSettingsLink($links)
    {
        $settingsLink = '<a href="options-general.php?page='.basename(__FILE__).'">' . __('Settings', 'OGC') . '</a>';
        array_push($links, $settingsLink);
        return $links;
    }

    public function addProjectLinks($links, $file)
    {
        if (strpos(__FILE__, dirname($file)) !== false) {
            $newLinks = array(
                    'discussionGroup' => '<a href="//www.ncdc.pt/groups/wordpress-optimum-gravatar-cache" target="_blank">'.__('Discussion Group', 'OGC').'</a>',
                    'gitHub' => '<a href="//github.com/jomisica/optimum-gravatar-cache" target="_blank">'.__('GitHub Project', 'OGC').'</a>'
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
        if (isset($_POST['OGC_options']['cache'])) {
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
            $this->maxOptimizeEachTime=intval($_POST['OGC_options']['maxOptimizeEachTime']);
        }

        $this->options['cache_directory']=$this->cacheDirectory;
        $this->options['expiryTime']=$this->expiryTime;
        $this->options['activated']=$this->activated;
        $this->options['searchExpiredTime']=$this->searchExpiredTime;
        $this->options['maxUpdateEachTime']=$this->maxUpdateEachTime;
        $this->options['maxOptimizeEachTime']=$this->maxOptimizeEachTime;
        $this->options['optimizeAvatars']=$this->optimizeAvatars;
        $this->options['learningMode']=$this->learningMode;

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
        }

        update_option('OGC_avatarUsedSizes', $this->avatarUsedSizes);
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
                          "message" => __('Could not save avatar.', 'OGC'),
                          "args"=>array()
                        );
                    } else {
                        return $options;
                    }
                } else {
                    $this->errorMessages[]=array(
                          "type" => "error notice",
                          "message" => __('Could not save avatar.', 'OGC'),
                          "args"=>array()
                        );
                }
            } else {
                $this->errorMessages[]=array(
                    "type" => "error notice",
                    "message" => __('The file type is not supported.', 'OGC'),
                    "args"=>array()
                  );
            }
          break;
          case UPLOAD_ERR_INI_SIZE:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __('The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'OGC'),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_FORM_SIZE:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.', 'OGC'),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_PARTIAL:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __('The uploaded file was only partially uploaded.', 'OGC'),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_NO_TMP_DIR:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __('Missing a temporary folder.', 'OGC'),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_CANT_WRITE:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __('Failed to write file to disk.', 'OGC'),
                      "args"=>array()
                    );
          break;
          case UPLOAD_ERR_EXTENSION:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __('File upload stopped by extension.', 'OGC'),
                      "args"=>array()
                    );
          break;
          default:
              $this->errorMessages[]=array(
                      "type" => "error notice",
                      "message" => __('Unknown upload error.', 'OGC'),
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
                  "message" => __('Could not create cache directory "%s". Please set read/write/execute (755) permissions for "%s" and/or correct owner.', 'OGC'),
                  "args"=>array(ABSPATH.$this->cacheDirectory,dirname(ABSPATH.$this->cacheDirectory))
                );
                $error=true;
            }
            if ((!is_writable(ABSPATH.$this->cacheDirectory) || !is_executable(ABSPATH.$this->cacheDirectory)) && !chmod(ABSPATH.$this->cacheDirectory, 0755)) {
                $this->errorMessages[]=array(
                  "type" => "error notice",
                  "message" => __('Please set read/write/execute (755) permissions for "%s" and/or correct owner.', 'OGC'),
                  "args"=>array(ABSPATH.$this->cacheDirectory)
                );
                $error=true;
            }
            if (!mkdir(ABSPATH."{$this->cacheDirectory}tmp", 0755, true) && !is_dir(ABSPATH."{$this->cacheDirectory}tmp")) {
                $this->errorMessages[]=array(
                  "type" => "error notice",
                  "message" => __('Could not create cache directory "%s". Please set read/write/execute (755) permissions for "%s" and/or correct owner.', 'OGC'),
                  "args"=>array(ABSPATH."{$this->cacheDirectory}tmp",dirname(ABSPATH."{$this->cacheDirectory}tmp"))
                );
                $error=true;
            }
            if ((!is_writable(ABSPATH."{$this->cacheDirectory}tmp") || !is_executable(ABSPATH."{$this->cacheDirectory}tmp")) && !chmod(ABSPATH."{$this->cacheDirectory}tmp", 0755)) {
                $this->errorMessages[]=array(
                  "type" => "error notice",
                  "message" => __('Please set read/write/execute (755) permissions for "%s" and/or correct owner.', 'OGC'),
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
          "message" => __('<b>Refresh gravatars cache every:</b> This option accepts an integer value from 1.', 'OGC'),
          "args"=>array()
      );
            $error=true;
        }
        if ($this->searchExpiredTime < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => __('<b>Search for outdated avatars every:</b> This option accepts an integer value from 1.', 'OGC'),
          "args"=>array()
      );
            $error=true;
        }
        if ($this->maxUpdateEachTime < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => __('<b>How many users check at a time:</b> This option accepts an integer value from 1.', 'OGC'),
          "args"=>array()
      );
            $error=true;
        }
        if ($this->maxOptimizeEachTime < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => __('<b>How many avatars to optimize each time:</b> This option accepts an integer value from 1.', 'OGC'),
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
				  `id` int(10) UNSIGNED NOT NULL auto_increment,
				  `email` varchar(255) NOT NULL,
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
                    'expiryTime' => 10,
                    'cache_directory' => 'cache/avatar/',
                    'searchExpiredTime'=> 1,
                    'maxUpdateEachTime' => 3,
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
            'message' => __('The plugin has been activated but needs to be configured to work. Enter the configuration page through the menu. You need at least specify the directory where the gravatars will be saved.', 'OGC'),
            'args'=>array()
          );
        }

        wp_schedule_event(time(), 'OGC_job', 'OGC_CronEvent');

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

    public function updateAndResizeAllSizes($hash)
    {
        global $wpdb;

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
        $sql = "SELECT DISTINCT(`hash`), `def`, `lastModified`, `lastCheck` FROM `{$this->cacheTableName}` WHERE lastCheck < {$time} ORDER BY `lastCheck` ASC LIMIT {$this->maxUpdateEachTime}";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results) {
            foreach ($results as $user) {
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
                        continue;
                    }
                    $this->updateAndResizeAllSizes($user->hash);
                } elseif ($gravatarStatus->status == 200 && $user->def == 1) {
                    $this->updateAndResizeAllSizes($user->hash);
                }
            }
        }
        if ($this->optimizeAvatars) {
            $this->optimizeCache();
        }
        flock($fp, LOCK_UN);
    }

    public function optimizeCache()
    {
        global $wpdb;
        $sql = "SELECT `id`, `size`, `ext`  FROM `{$this->cacheTableName}` WHERE (optimized='0' AND def='0') ORDER BY lastCheck DESC LIMIT {$this->maxOptimizeEachTime}";
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

    protected function resizeFromLargerCached($largerCached, $hash, $email, $size)
    {
        global $wpdb;

        $result=$wpdb->insert(
          $this->cacheTableName,
            array(
              'email' => $email,
              'hash' => $hash,
              'optimized' => '0',
              'size' => $size,
              'ext' => $largerCached->ext,
              'lastCheck' => $largerCached->lastCheck,
              'lastModified' => $largerCached->lastModified,
              'def' => '0'
            ),
            array('%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s')
          );

        if ($result) {
            $sourceB35Id=base_convert($largerCached->id, 10, 35);
            $destB35Id=base_convert($this->getIdByHashAndSize($hash, $size), 10, 35);
            $img = wp_get_image_editor(ABSPATH."{$this->cacheDirectory}{$sourceB35Id}.{$largerCached->ext}");
            if (! is_wp_error($img)) {
                $resize = $img->set_quality(100);
                $resize = $img->resize($size, $size, true);
                $resize = $img->save(ABSPATH."{$this->cacheDirectory}{$destB35Id}.{$largerCached->ext}");
            } else {
                return false;
            }
            return "<img alt src='/{$this->cacheDirectory}{$destB35Id}.{$largerCached->ext}' class='avatar avatar-{$size}' width='{$size}' height='{$size}' />";
        }
        return false;
    }

    protected function cacheNewAvatarSize($smallerCached, $hash, $email, $size)
    {
        global $wpdb;

        $result=$wpdb->insert(
          $this->cacheTableName,
            array(
              'email' => $email,
              'hash' => $hash,
              'optimized' => '0',
              'size' => $size,
              'ext' => $smallerCached->ext,
              'lastCheck' => $smallerCached->lastCheck,
              'lastModified' => $smallerCached->lastModified,
              'def' => '0'
            ),
            array('%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s')
          );

        if ($result) {
            $b35Id=base_convert($this->getIdByHashAndSize($hash, $size), 10, 35);
            $options = $hash.'?s='.$size.'&r='.$this->avatarRating.'&d=404';
            $gravatar=$this->getGravatarOnline($options);
            if ($gravatar->status == 200) {
                if (file_put_contents(ABSPATH."{$this->cacheDirectory}{$b35Id}.{$gravatar->ext}", $gravatar->content)) {
                    return "<img alt src='/{$this->cacheDirectory}{$b35Id}.{$gravatar->ext}' class='avatar avatar-{$size}' width='{$size}' height='{$size}' />";
                }
            }
        }
        return $this->tryDefaultAvatar($source, $size);
    }

    protected function cacheNewDefaultSize($largerCached, $hash, $email, $size)
    {
        global $wpdb;

        $result=$wpdb->insert(
          $this->cacheTableName,
            array(
              'email' => $email,
              'hash' => $hash,
              'optimized' => '0',
              'size' => $size,
              'ext' => $largerCached->ext,
              'lastCheck' => $largerCached->lastCheck,
              'lastModified' => $largerCached->lastModified,
              'def' => '1'
            ),
            array('%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s')
          );
        return $this->buildDefaultAvatar($size);
    }

    protected function updateResolved()
    {
        update_option('OGC_resolved', $this->resolved +=1);
    }

    protected function isFirstAvatartoUser($hash)
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

    public function creatDefaultUsedSizesToUser($hash, $email, $size)
    {
        global $wpdb;

        $this->updateAvatarUsedSizes($size);

        foreach ($this->avatarUsedSizes as $usedSize) {
            $result=$wpdb->insert(
            $this->cacheTableName,
            array(
              'email' => $email,
              'hash' => $hash,
              'optimized' => '0',
              'size' => $usedSize,
              'ext' => $this->customAvatarExt,
              'lastCheck' => 0,
              'lastModified' => 0,
              'def' => '1'
            ),
              array('%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s')
            );
        }

        return;
    }

    public function getCachedAvatar($source, $idOrEmail, $size, $default, $alt)
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
                return $this->buildDefaultAvatar($size);
            }
        }

        if ($this->isFirstAvatartoUser($mailHash)) {
            $this->creatDefaultUsedSizesToUser($mailHash, $email, $size);
            return $this->tryDefaultAvatar($source, $size);
        }

        $this->updateAvatarUsedSizes($size);

        $largerCached=$this->isThereAnyLargerCached($mailHash, $size);
        if ($largerCached) {
            if ($largerCached->def==0) {
                $resizedFromLarger=$this->resizeFromLargerCached($largerCached, $mailHash, $email, $size);
                if ($resizedFromLarger) {
                    return $resizedFromLarger;
                }
            } else {
                return $this->cacheNewDefaultSize($largerCached, $mailHash, $email, $size);
            }
        } else {
            $smallerCached=$this->isThereAnySmallerCached($mailHash, $size);
            if ($smallerCached) {
                if ($smallerCached->def==0) {
                    return $this->cacheNewAvatarSize($smallerCached, $mailHash, $email, $size);
                } else {
                    return $this->cacheNewDefaultSize($smallerCached, $mailHash, $email, $size);
                }
            }
        }
        return $this->tryDefaultAvatar($source, $size);
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
        rsort($sizes);


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
                                "message" =>  __('Could not delete file "%s" check permissions.', 'OGC'),
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

require_once(ABSPATH.'wp-includes/pluggable.php');
$OGC = new OGC();
