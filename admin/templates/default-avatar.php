<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}
?>

<form method="post" class="gravatar-cache-form" enctype="multipart/form-data">
  <input type="hidden" name="OGC_options[nonce]" value="<?php echo wp_create_nonce("OGC"); ?>"/>
  <input type="hidden" name="OGC_options[default-avatar]" value="1"/>
  <table class="widefat fixed">
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row">
        <?php _e("Default avatar", "OGC"); ?>
      </th>
      <td>
        <img id="default-avatar" alt="" src="<?php echo $this->getDefaultAvatar() ?>" class="avatar avatar-96 photo" width="96" height="96" />
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("The default avatar is used whenever the user does not have a custom gravatar. It should be configured with an image that fits your site.", "OGC"); ?>
      </td>
    </tr>
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row">
        <?php _e("Upload custom image", "OGC"); ?>
      </th>
      <td>
        <input type='file' id="upload" name='file' accept='image/jpeg, image/png, image/gif, image/svg+xml'>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("This image will be used as the default avatar. Accepts the following image types (.SVG, .PNG, .JPG, .GIF). The image should have the minimum dimensions of the largest avatar used on your site, so you will not lose quality when it is resized. Except in case of type .SVG.", "OGC"); ?>
      </td>
    </tr>
    <tr valign="top">
      <th class="column-columnname column-primary" scope="row"><?php _e("Reset avatar by default", "OGC"); ?></th>
      <td>
          <input type='checkbox' name='OGC_options[resetDefaultAvatar]' value='1'>
      </td>
    </tr>
    <tr valign="top">
      <td colspan="2" class="description">
        <?php _e("Resetting the avatar by default allows you to use the avatar provided by the plugin when installed. This way removing any previous customization.", "OGC"); ?>
      </td>
    </tr>
  </table>
  <p class="submit">
    <button type="submit" name="updateOptions" id="submit" class="button button-primary"><?php _e("Save Changes"); ?></button>
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
