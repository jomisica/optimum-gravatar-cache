<?php
/*
Plugin Name: Optimum Gravatar Cache
Author: José Miguel Silva Caldeira
Version: 0.0.1
Description: It cache the gravatars locally, reducing the total number of requests per post. This will speed up the loading of the site and consequently improve the user experience.
Author URI: https://www.ncdc.pt/members/admin
Text Domain: OGC
*/

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

class OGC
{
    protected $options;
    protected $mimeTypes=array("image/jpeg" => "jpg","image/jpg" => "jpg","image/png" => "png","image/gif" => "gif");
    protected $pluginName = 'Optimum Gravatar Cache';
    protected $cacheDirectory;
    protected $refreshCache;
    protected $cacheActive;
    protected $errorMessages=array();
    protected $cacheTableName;
    protected $validExtention=array("jpg","png","gif");
    protected $defaultExtentionId;
    protected $searchCache;
    protected $howManyUpdate;

    public function __construct()
    {
        global $wpdb;

        $this->cacheTableName=$wpdb->prefix."optimum_gravatar_cache";
        $this->options = get_option('OGC_options');
        $this->cacheDirectory = $this->options['cache_directory'];
        $this->refreshCache=$this->options['refresh'];
        $this->cacheActive=$this->options['active'];
        $this->defaultExtentionId=$this->options['extention'];
        $this->searchCache=$this->options['search'];
        $this->howManyUpdate=$this->options['howMany'];

        if ($this->permissionsToRun() && $this->optionsToRun() && $this->cacheActive) {
            add_filter('get_avatar', array( $this,'getCachedGravatar' ), 1, 5);
            add_filter('bp_core_fetch_avatar', array( $this,'getBPressCachedGravatar' ), 1, 9);
            add_filter('cron_schedules', array( $this, 'schedules'));
        }

        if (is_admin()) {
            add_action('plugins_loaded', array( $this, 'loadTextdomain' ));
            register_activation_hook(__FILE__, array( $this, 'activate' ));
            register_deactivation_hook(__FILE__, array( $this, 'deactivate' ));
            // register_uninstall_hook(__FILE__, array( $this, 'uninstall' )); //next
            add_action('admin_menu', array( $this,'add_admin_menu'));
            add_action('admin_enqueue_scripts', array( $this, 'clientScripts'));
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

        if ($this->permissionsToRun() && $this->optionsToRun() && $this->cacheActive) {
            add_action('OGC_CronEvent', array( $this, 'updateCache'));
            add_action('OGC_CronEvent', array( $this, 'optimizeCache'));
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
            'interval' => $this->searchCache*60*60,
            'display' => __('OGC job'));
        return $schedules;
    }

    public function loadTextdomain()
    {
        load_plugin_textdomain('OGC', false, basename(dirname(__FILE__)) . '/languages/');
    }

    protected function setCronEvent()
    {
        wp_schedule_event(time(), 'OGC_job', 'OGC_CronEvent');
    }

    protected function clearCronEvent()
    {
        remove_action('OGC_CronEvent', 'updateCache');
        remove_action('OGC_CronEvent', 'optimizeCache');
        wp_clear_scheduled_hook('OGC_CronEvent');
    }

    protected function updateOptions()
    {
        $oldExtentionId=$this->defaultExtentionId;
        $this->cacheDirectory=rtrim($_POST['OGC_options']['directory'], '/') . '/';
        $this->cacheActive=intval($_POST['OGC_options']['active']);
        $this->refreshCache=intval($_POST['OGC_options']['refresh']);
        $this->defaultExtentionId=intval($_POST['OGC_options']['extention']);
        $this->searchCache=intval($_POST['OGC_options']['search']);
        $this->howManyUpdate=intval($_POST['OGC_options']['howMany']);

        $this->options['cache_directory']=$this->cacheDirectory;
        $this->options['refresh']=$this->refreshCache;
        $this->options['active']=$this->cacheActive;
        $this->options['extention']=$this->defaultExtentionId;
        $this->options['search']=$this->searchCache;
        $this->options['howMany']=$this->howManyUpdate;

        $errorPermissions=$this->adminPermissionsToRun();
        $errorOptions=$this->adminOptionsToRun();

        if ($errorPermissions || $errorOptions) {
            $this->options['active']=0;
            $this->cacheActive=0;
        } else {
            $this->options['messages']="";
            $this->clearCronEvent();
            $this->setCronEvent();
        }

        update_option('OGC_options', $this->options);

        if ($oldExtentionId!=$this->defaultExtentionId) {
            $this->deleteOldExtention($this->validExtention[$oldExtentionId]);
        }
    }

    protected function deleteOldExtention($ext)
    {
        global $wpdb;

        $wpdb->delete($this->cacheTableName, array( 'ext' => $ext, 'def'=> 1), array( '%s', '%d' ));
    }

    protected function adminPermissionsToRun()
    {
        $error=false;
        if (!$this->permissionsToRun()) {
            if (!mkdir(ABSPATH.$this->cacheDirectory, 0755, true) && !is_dir(ABSPATH.$this->cacheDirectory)) {
                $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => "Could not create cache directory '%s'. Please set read/write/execute (755) permissions for '%s' and/or correct owner.",
          "args"=>array(ABSPATH.$this->cacheDirectory,dirname(ABSPATH.$this->cacheDirectory))
      );
                $error=true;
            }
            if ((!is_writable(ABSPATH.$this->cacheDirectory) || !is_executable(ABSPATH.$this->cacheDirectory)) && !chmod(ABSPATH.$this->cacheDirectory, 0755)) {
                $this->errorMessages[]=array(
            "type" => "error notice",
            "message" => "Please set read/write/execute (755) permissions for '%s' and/or correct owner.",
            "args"=>array(ABSPATH.$this->cacheDirectory)
        );
                $error=true;
            }
        }
        return $error;
    }

    protected function adminOptionsToRun()
    {
        $error=false;

        if ($this->refreshCache < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => "<b>Refresh gravatars cache every:</b> This option accepts an integer value from 1.",
          "args"=>array()
      );
            $error=true;
        }
        if ($this->searchCache < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => "<b>Search for outdated gravatars every:</b> This option accepts an integer value from 1.",
          "args"=>array()
      );
            $error=true;
        }
        if ($this->searchCache < 1) {
            $this->errorMessages[]=array(
          "type" => "error notice",
          "message" => "<b>How many gravatars to upgrade or optimize each time:</b> This option accepts an integer value from 1.",
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
        return true;
    }

    protected function optionsToRun()
    {
        $error=true;
        if ($this->refreshCache < 1) {
            $error=false;
        }
        if ($this->searchCache < 1) {
            $error=false;
        }
        if ($this->searchCache < 1) {
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
            echo "<div class=\"{$contents['type']}\"><p><b>{$this->pluginName}</b></p><p>".vsprintf(__($contents['message'], 'OGC'), $contents['args'])."</p></div>";
        }
    }

    public function getBPressCachedGravatar($content, $params, $id)
    {
        if (is_array($params) && $params['object'] == 'user') {
            return $this->getCachedGravatar($content, $params['item_id'], $params['width']);
        }
        return $content;
    }

    public function activate()
    {
        global $wpdb;

        $wpdb->query("CREATE TABLE IF NOT EXISTS `{$this->cacheTableName}` (
				  `id` int(11) NOT NULL auto_increment,
				  `email` varchar(255) NOT NULL,
				  `hash` varchar(45) NOT NULL,
				  `optimized` tinyint(1) NOT NULL,
				  `size` int(5) NOT NULL,
				  `ext` varchar(5) NOT NULL,
				  `lastCheck` int(11) NOT NULL,
				  `lastModified` int(11) NOT NULL,
				  `def` tinyint(1) NOT NULL,
				  PRIMARY KEY  (`id`)
				)");

        $default_options=get_option('OGC_options');

        if ($default_options == false) {
            $default_options = array(
                    'active'   => 0,
                    'refresh' => 10,
                    'cache_directory' => 'cache/gravatar/',
                    'extention' => 0,
                    'search'=> 1,
                    'howMany' => 3,
                    'messages' => array()
            );
        }

        if (!$this->permissionsToRun() || !$this->optionsToRun()) {
            $default_options['messages'][] = array(
            'type' => "notice notice-info",
            'message' => "The plugin has been activated but needs to be configured to work. Enter the configuration page through the menu. You need at least specify the directory where the gravatars will be saved.",
            'args'=>array()
          );
        } else {
            $this->setCronEvent();
        }
        update_option('OGC_options', $default_options);
    }

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

    public function updateCache()
    {
        global $wpdb;
        $time=time()-$this->refreshCache * 86400;//86400 1 day
        $sql = "SELECT `id`, `hash`, `size`, `lastModified` FROM `{$this->cacheTableName}` WHERE lastCheck < {$time} ORDER BY id LIMIT {$this->howManyUpdate}";
        $results = $wpdb->get_results($sql, OBJECT);

        if ($results) {
            foreach ($results as $gravatar) {
                $lastCheck = time();
                $options = "{$gravatar->hash}?s={$gravatar -> size}&r=G&d=404";
                $headers=get_headers("https://www.gravatar.com/avatar/{$options}", 1);
                $lastModified=(int)strtotime($headers['Last-Modified']);
                $status=explode(' ', $headers['0']);
                $headers['status']=$status[1];
                $status=$status[1];

                if ($status == 200) {
                    if ($gravatar->lastModified < $lastModified) {
                        $newGravatar=$this->getGravatarOnline($options);
                        if ($newGravatar->status == 200) {
                            // $gravatarId=dechex($gravatar -> id);
                            $gravatarId=base_convert($gravatar -> id, 10, 35);
                            file_put_contents(ABSPATH."{$this->cacheDirectory}{$gravatarId}-{$gravatar -> size}.{$newGravatar->ext}", $newGravatar->content);
                            $wpdb->query("UPDATE `{$this->cacheTableName}` SET optimized=0, ext='{$newGravatar->ext}', lastCheck={$lastCheck}, lastModified={$newGravatar->lastModified} WHERE id={$gravatar -> id}");
                        }
                    } else {
                        $wpdb->query("UPDATE `{$this->cacheTableName}` SET lastCheck={$lastCheck} WHERE id={$gravatar -> id}");
                    }
                }
            }
        }
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
        $sql = "SELECT `id`, `size`, `ext`  FROM `{$this->cacheTableName}` WHERE (optimized=0 AND def=0) ORDER BY id LIMIT {$this->howManyUpdate}";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results) {
            foreach ($results as $gravatar) {
                $gravatarId=base_convert($gravatar -> id, 10, 35);
                if (!file_exists(ABSPATH."{$this->cacheDirectory}{$gravatarId}-{$gravatar ->size}.{$gravatar -> ext}")) {
                    continue;
                }
                $options=site_url()."/{$this->cacheDirectory}{$gravatarId}-{$gravatar -> size}.{$gravatar -> ext}";
                $optimizedGravatarRequest=$this->sendResmushRequest($options);
                if (!$optimizedGravatarRequest->error) {
                    $optimizedGravatar=$this->getOptimizedGravatar($optimizedGravatarRequest->optimizedURL);
                    if ($optimizedGravatar->status == 200) {
                        if (file_put_contents(ABSPATH."{$this->cacheDirectory}{$gravatarId}-{$gravatar->size}.{$gravatar -> ext}", $optimizedGravatar->content)) {
                            $wpdb->query("UPDATE `{$this->cacheTableName}` SET optimized=1 WHERE id={$gravatar->id}");
                        }
                    }
                }
            }
        }
    }

    protected function getIdByEmail($email, $size)
    {
        global $wpdb;
        $sql = "SELECT `id` FROM `{$this->cacheTableName}` where email='{$email}' AND size={$size}";
        $results = $wpdb->get_results($sql, OBJECT);
        if ($results[0] -> id) {
            return $results[0] -> id;
        }
        return false;
    }

    public function getCachedGravatar($source, $idOrEmail, $size, $default, $alt)
    {
        global $wpdb;

        if (strpos($source, 'gravatar.com') === false) {
            return $source;
        }

        $email=false;

        $url = site_url();
        $url = str_replace(
                'https:', '', $url
        );
        $url = str_replace(
                'http:', '', $url
        );
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
            return $source;
        }

        $email=strtolower(trim($email));

        if ($email) {
            $sql = $wpdb->prepare("SELECT `id`, `hash`,`ext`,`def` FROM `{$this->cacheTableName}` WHERE `email` = %s AND `size` = %d LIMIT 1", $email, $size);
            $results = $wpdb->get_results($sql, OBJECT);
            if ($results[0] -> id && $results[0] -> def == 0) {
                $gravatarId=base_convert($results[0] -> id, 10, 35);

                if (file_exists(ABSPATH.$this->cacheDirectory.$gravatarId.'-'.$size.'.'.$results[0] -> ext)) {
                    return "<img alt src='{$url}/{$this->cacheDirectory}{$gravatarId}-{$size}.{$results[0]->ext}' class='avatar avatar-{$size}' width='{$size}' height='{$size}' />";
                } else {
                    $wpdb->query("DELETE FROM `{$this->cacheTableName}` WHERE id=".$results[0] -> id);
                }
            } elseif ($results[0] -> id && $results[0] -> def == 1 && $results[0] -> ext == $this->validExtention[$this->defaultExtentionId]) {
                if (file_exists(ABSPATH.$this->cacheDirectory.'0-'.$size.'.'.$results[0] -> ext)) {
                    return "<img alt src='{$url}/{$this->cacheDirectory}0-{$size}.{$results[0]->ext}' class='avatar avatar-{$size}' width='{$size}' height='{$size}' />";
                }
            }
        }

        $lastCheck = time();
        $mailHash=md5($email);

        $options = $mailHash.'?s='.$size.'&r=G&d=404';
        $gravatar=$this->getGravatarOnline($options);

        if ($gravatar->status == 200) {
            $result=$wpdb->insert(
                $this->cacheTableName,
                array(
                  'email' => $email,
                  'hash' => $mailHash,
                  'optimized' => 0,
                  'size' => $size,
                  'ext' => $gravatar->ext,
                  'lastCheck' => $lastCheck,
                  'lastModified' => $gravatar->lastModified,
                  'def' => 0
                ),
                array('%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d')
            );

            if (!$result) {
                return $source;
            }

            $gravatarId=base_convert($this->getIdByEmail($email, $size), 10, 35);
            if (!$gravatarId) {
                return $source;
            }

            if (!file_put_contents(ABSPATH.$this->cacheDirectory.$gravatarId.'-'.$size.'.'.$gravatar->ext, $gravatar->content)) {
                return $source;
            }

            $image_tag="<img alt src='{$url}/{$this->cacheDirectory}{$gravatarId}-{$size}.{$gravatar->ext}' class='avatar avatar-{$size}' width='{$size}' height='{$size}' />";
        } elseif ($gravatar->status == 404) {
            if (file_exists(ABSPATH.$this->cacheDirectory.'0-'.$size.'.'.$this->validExtention[$this->defaultExtentionId])) {
                $result=$wpdb->insert(
                    $this->cacheTableName,
                    array(
                      'email' => $email,
                      'hash' => $mailHash,
                      'optimized' => 0,
                      'size' => $size,
                      'ext' => $this->validExtention[$this->defaultExtentionId],
                      'lastCheck' => $lastCheck,
                      'lastModified' => 0,
                      'def' => 1
                    ),
                    array('%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d')
                );

                if ($result) {
                    $image_tag="<img alt src='{$url}/{$this->cacheDirectory}0-{$size}.{$this->validExtention[$this->defaultExtentionId]}' class='avatar avatar-{$size}' width='{$size}' height='{$size}' />";
                } else {
                    return $source;
                }
            } else {
                $options = $mailHash.'.'.$this->validExtention[$this->defaultExtentionId].'?s='.$size.'&r=G&d=mm';
                $defaultGravatar=$this->getGravatarOnline($options);
                if ($defaultGravatar->status == 200) {
                    $inser=$wpdb->insert(
                        $this->cacheTableName,
                        array(
                          'email' => $email,
                          'hash' => $mailHash,
                          'optimized' => 0,
                          'size' => $size,
                          'ext' => $this->validExtention[$this->defaultExtentionId],
                          'lastCheck' => $lastCheck,
                          'lastModified' => 0,
                          'def' => 1
                        ),
                        array('%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d')
                    );

                    if (!$result) {
                        return $source;
                    }

                    if (!file_exists(ABSPATH.$this->cacheDirectory.'0-'.$size.'.'.$this->validExtention[$this->defaultExtentionId])) {
                        if (!file_put_contents(ABSPATH.$this->cacheDirectory.'0-'.$size.'.'.$this->validExtention[$this->defaultExtentionId], $defaultGravatar->content)) {
                            return $source;
                        }
                        $options=site_url().'/'.$this->cacheDirectory.'0-'.$size.'.'.$this->validExtention[$this->defaultExtentionId];
                        $optimizedGravatarRequest=$this->sendResmushRequest($options);
                        if (!$optimizedGravatarRequest->error) {
                            $optimizedGravatar=$this->getOptimizedGravatar($optimizedGravatarRequest->optimizedURL);
                            if ($optimizedGravatar->status == 200) {
                                if (!file_put_contents(ABSPATH.$this->cacheDirectory.'0-'.$size.'.'.$this->validExtention[$this->defaultExtentionId], $optimizedGravatar->content)) {
                                    return $source;
                                }
                            } else {
                                return $source;
                            }
                        } else {
                            return $source;
                        }
                    }

                    $image_tag="<img alt src='{$url}/{$this->cacheDirectory}0-{$size}.{$this->validExtention[$this->defaultExtentionId]}' class='avatar avatar-{$size}' width='{$size}' height='{$size}' />";
                } else {
                    return $source;
                }
            }
        } else {
            return $source;
        }
        return $image_tag;
    }

    public function add_admin_menu()
    {
        add_options_page('Optimum Gravatar Cache ', $this->pluginName, 'manage_options', basename(__FILE__), array( $this,'settingsViewPage' ));
    }

    public function settingsViewPage()
    {
        $cacheInfo = $this->getCacheDetails();
        require "templates/admin.php";
    }

    protected function clearCache()
    {
        global $wpdb;
        if (!$wpdb->query("TRUNCATE TABLE `{$this->cacheTableName}`")) {
            $this->errorMessages[]=array(
                "type" => "error notice",
                "message" => 'Unable to clear data from the table.'
            );
            return false;
        }

        if (!$wpdb->query("ALTER TABLE `{$this->cacheTableName}` AUTO_INCREMENT = 1")) {
            $this->errorMessages[]=array(
                "type" => "error notice",
                "message" => 'Unable to reset AUTO_INDEX from the table.'
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
                                "message" =>  "Could not delete file '%s' check permissions.",
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
            $fileList = glob(ABSPATH.$this->cacheDirectory.'/*.{'.implode(",", $this->validExtention).'}', GLOB_BRACE);
            $size = 0;
            foreach ($fileList as $file) {
                $size  += filesize($file);
            }
        }
        return array( 'images' => count($fileList) , 'usedSpace' => $this->sizeToByte($size) );
    }

    protected function sizeToByte($size)
    {
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }

    public function clientScripts()
    {
        wp_enqueue_style('ogc-main-style', plugins_url('/css/style.css', __FILE__));
    }
}

$OGC = new OGC();
