<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}
?>

<table class="widefat fixed" cellspacing="0" border=0 width="600">
  <thead>
    <th class="column-columnname column-primary" scope="col" colspan="2">
      <?php _e('Caching', 'OGC'); ?>
    </th>
  </thead>
  <tr valign="top">
    <th class="column-columnname column-primary" scope="row">
      <?php _e('Sizes used', 'OGC'); ?>
    </th>
    <td class="column-columnname">
      <?php echo $dbCacheInfo['sizes']?>
    </td>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary" scope="row">
      <?php _e('MimeTypes Used', 'OGC'); ?>
    </th>
    <td class="column-columnname">
      <?php echo $fileCacheInfo['typesUsed']?>
    </td>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary" scope="row">
      <?php _e('Cached avatars', 'OGC'); ?>
    </th>
    <td class="column-columnname">
      <?php echo $dbCacheInfo['total']?>
    </td>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary" scope="row">
      <?php _e('How many users use a custom avatar', 'OGC'); ?>
    </th>
    <td class="column-columnname">
      <?php echo $dbCacheInfo['custom']?>
    </td>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary" scope="row">
      <?php _e('How many users do not have a custom avatar', 'OGC'); ?>
    </th>
    <td class="column-columnname">
      <?php echo $dbCacheInfo['default']?>
    </td>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary" scope="row">
      <?php _e('How many avatars have been resolved', 'OGC'); ?>
    </th>
    <td class="column-columnname">
      <?php echo $this->resolved; ?>
    </td>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary" scope="row">
      <?php _e('Avatars on disk', 'OGC'); ?></th>
    <td class="column-columnname">
      <?php echo $fileCacheInfo['images']?>
    </td>
  </tr>
  <tr valign="top">
    <th class="column-columnname column-primary" scope="row">
      <?php _e('Space used by avatars', 'OGC'); ?>
    </th>
    <td class="column-columnname">
      <?php echo $fileCacheInfo['usedSpace']?>
    </td>
  </tr>
</table>
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
