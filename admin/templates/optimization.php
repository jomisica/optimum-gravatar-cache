<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}
?>

<form method="post" class="gravatar-cache-form" enctype="multipart/form-data">
  <input type="hidden" name="OGC_options[nonce]" value="<?php echo wp_create_nonce("OGC"); ?>"/>
  <input type="hidden" name="OGC_options[optimization]" value="1"/>
  <table class="widefat fixed">
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row"><?php _e("Optimize avatars", "OGC"); ?></th>
      <td>
        <input type='checkbox' name='OGC_options[optimizeAvatars]' value='1' <?php checked(1, $this->optimizeAvatars); ?>>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("The optimization of avatars is done through an online service 'resmush.it'. This plugin needs to communicate with this service in order to optimize the avatars. It is not mandatory to use the optimized avatars. However when optimizing the avatars these will be loaded faster by the site visitor and provide a better experience.", "OGC"); ?>
      </td>
    </tr>
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row">
        <?php _e("How many avatars to optimize each time", "OGC"); ?>
      </th>
      <td>
      <input type="text" name="OGC_options[maxOptimizeEachTime]" value="<?php echo $this->maxOptimizeEachTime; ?>" size="2"/>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("This option allows you to specify how many avatars will be optimized each time. This option is important, because if the number is too high, it can consume lots of resources. A good setting will depend on the number of avatars that are cached as well as the server resources. Try it out, starting with a small number.", "OGC"); ?>
      </td>
    </tr>
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row"><?php _e("Precompress .SVG Files", "OGC"); ?></th>
      <td>
        <input type='checkbox' name='OGC_options[precompress]' value='1' <?php checked(1, $this->precompress); ?>>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("Precompress .SVG files, pre compress the avatars that are used as default avatar on the site. In this way it is possible to save resources because the .SVG files are compressed only once, when they are configured and are saved. It is no longer necessary for the server to compress the files every time they are sent.", "OGC"); ?>
      </td>
    </tr>
  </table>
  <p class="submit">
    <button type="submit" name="updateOptions" id="submit" class="button button-primary"><?php _e("Save Changes", "OGC"); ?></button>
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
