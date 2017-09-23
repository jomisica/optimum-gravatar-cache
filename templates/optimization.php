<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}
?>

<form method="post" class="gravatar-cache-form" enctype="multipart/form-data">
  <table class="widefat fixed">
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row"><label for="wp_cache_status"><?php _e('Optimize avatars', 'OGC'); ?></label></th>
      <td>
        <fieldset>
          <label><input type='checkbox' name='OGC_options[optimizeAvatars]' value='1' <?php checked(1, $this->optimizeAvatars); ?>></label><br />
        </fieldset>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e('The optimization of avatars is done through an online service "resmush.it". This plugin needs to communicate with this service in order to optimize the avatars. It is not mandatory to use the optimized avatars. However when optimizing the avatars these will be loaded faster by the site visitor and provide a better experience.', 'OGC'); ?>
      </td>
    </tr>
  </table>
  <p class="submit">
    <button type="submit" name="updateOptions" id="submit" class="button button-primary"><?php _e('Save Changes', 'OGC'); ?></button>
  </p>
</form>
<table class="widefat fixed">
  <tr valign="top">
    <th rowspan="4"><img alt="" src="<?php echo $this->getLogo() ?>" class="avatar avatar-96 photo" width="96" height="96" /></th>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary" colspan="2">
      <?php _e('Contact Us', 'OGC'); ?>
    </th>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary">
      <?php _e('WebSite', 'OGC') ?>:</th>
    <td class="column-columnname"><a href="https://www.ncdc.pt">https://www.ncdc.pt</a></td>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary">
      <?php _e('E-mail', 'OGC') ?>:</th>
    <td class="column-columnname"><a title="Mail To miguel@ncdc.pt" href="mailto:miguel@ncdc.pt">miguel@ncdc.pt</a></td>
  </tr>
</table>
