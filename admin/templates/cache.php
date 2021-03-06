<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}
?>

<form method="post" class="gravatar-cache-form">
  <input type="hidden" name="OGC_options[nonce]" value="<?php echo wp_create_nonce("OGC"); ?>"/>
  <input type="hidden" name="OGC_options[cache]" value="1"/>
  <table class="wp-list-table widefat fixed">
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row">
        <?php _e("Caching", "OGC"); ?>
      </th>
      <td>
        <fieldset>
          <label><input type='radio' name='OGC_options[activated]' value='1' <?php checked(1, $this->activated); ?>> <?php _e("Caching On", "OGC"); ?></label><br />
          <label><input type='radio' name='OGC_options[activated]' value='0' <?php checked(0, $this->activated); ?>> <?php _e("Caching Off", "OGC"); ?></label>
        </fieldset>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("This option allows you to enable or disable the Gravatars cache locally. It has the same effect as disabling the plugin.", "OGC"); ?>
      </td>
    </tr>
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row">
        <?php _e("Avatars cache directory", "OGC"); ?>
      </th>
      <td>
        <?php echo ABSPATH; ?><input type="text" name="OGC_options[directory]" value="<?php echo $this->cacheDirectory; ?>" size="30"/>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("This option allows you to specify where cached files will be saved. Avatar images will be stored in this directory.", "OGC"); ?>
      </td>
    </tr>
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row">
        <?php _e("Refresh gravatars cache every", "OGC"); ?>
      </th>
      <td>
        <label><input type="text" name="OGC_options[expireTime]" value="<?php echo $this->expireTime; ?>" size="2"/> <?php $this->expireTime <2 ? _e("day", "OGC") : _e("days", "OGC"); ?></label>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("This option allows you to specify how many days the avatars are cached. After these days an avatar update will be made, if it has been modified by the owner at gravatar.com. This option accepts an integer value starting at 1.", "OGC"); ?>
      </td>
    </tr>
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row">
        <?php _e("Search for outdated avatars every", "OGC"); ?>
      </th>
      <td>
        <label><input type="text" name="OGC_options[searchExpiredTime]" value="<?php echo $this->searchExpiredTime; ?>" size="2"/> <?php $this->searchExpiredTime <2 ? _e("minute", "OGC") : _e("minutes", "OGC"); ?></label>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("This option allows you to specify an interval in minutes in which it is checked whether users have updated their gravatar. This check is made in the background by WordPress cron. This option accepts an integer value starting at 1.", "OGC"); ?>
      </td>
    </tr>
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row">
        <?php _e("How many users check at a time", "OGC"); ?>
      </th>
      <td>
        <input type="text" name="OGC_options[maxUpdateEachTime]" value="<?php echo $this->maxUpdateEachTime; ?>" size="2"/>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("This option allows you to specify how many users should be scanned to verify that your gravatar has changed. This option is important, because if the number is too high, it can consume lots of resources. A good setup will depend on the number of avatars that are cached as well as the server resources. Try it out, starting with a small number.", "OGC"); ?>
      </td>
    </tr>
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row"><?php _e("In learning mode", "OGC"); ?></th>
      <td>
          <input type='checkbox' name='OGC_options[learningMode]' value='1' <?php checked(1, $this->learningMode); ?>>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("When the learning mode option is enabled the plugin only captures the sizes of the avatars used on your site. This will reduce the bandwidth used in communication with gravatar.com. The processing of the avatars will be faster because we have in advance the captured sizes. Leave this option active for a while, and visit your site wherever avatars appear to speed up the process of collecting sizes.", "OGC"); ?>
      </td>
    </tr>
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row">
        <?php _e("Avatars sizes used", "OGC"); ?>
      </th>
      <td>
        <label><input type="text" name="OGC_options[avatarUsedSizes]" value="<?php echo implode(", ", $this->avatarUsedSizes); ?>"/></label>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("This field allows you to add avatars sizes used on your site and contains a list of used comma-separated sizes. If you know all the sizes used, you do not need to activate the learning mode. However, this field will always be updated if a new size is used.", "OGC"); ?>
      </td>
    </tr>
  </table>
  <p class="submit">
    <button type="submit" name="updateOptions" id="submit" class="button button-primary"><?php _e("Save Changes"); ?></button>
    <button class="button" name="clearCache" <?php echo disabled($this->activated, 1, true) ?>><?php _e("Clear Cache", "OGC"); ?>
        <span class="clear-count"><?php echo '('.$fileCacheInfo['images'] .' '.($fileCacheInfo['images'] <2 ? __("image", "OGC") : __("images", "OGC")).' / '.$fileCacheInfo['usedSpace'].')' ?></span>
  </button>
  </p>
</form>
<table class="widefat fixed">
  <tr valign="top">
    <th rowspan="4"><img alt="" src="<?php echo $this->getLogo() ?>" class="avatar avatar-96 photo" width="96" height="96" /></th>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary" colspan="2">
      <?php _e("Contact Us", "OGC"); ?>
    </th>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary">
      <?php _e("WebSite", "OGC") ?>:</th>
    <td class="column-columnname"><a href="https://www.ncdc.pt">https://www.ncdc.pt</a></td>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary">
      <?php _e("E-mail", "OGC") ?>:</th>
    <td class="column-columnname"><a title="Mail To miguel@ncdc.pt" href="mailto:miguel@ncdc.pt">miguel@ncdc.pt</a></td>
  </tr>
</table>
