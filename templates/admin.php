<div class="wrap gravatar-cache">
    <div id="icon-options-general" class="icon32"></div>
    <h2><?php _e($this->pluginName); ?></h2>
    <form method="post" class="gravatar-cache-form">
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="wp_cache_status"><?php _e('Caching', 'OGC'); ?></label></th>
                <td>
                    <fieldset>
                        <label><input type='radio' name='OGC_options[active]' value='1' <?php checked(1, $this->cacheActive); ?>> <?php _e('Caching On', 'OGC'); ?></label><br />
                        <label><input type='radio' name='OGC_options[active]' value='0' <?php checked(0, $this->cacheActive); ?>> <?php _e('Caching Off', 'OGC'); ?></label><br />
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2"  class="description">
                    <small><?php _e('This option allows you to enable or disable the Gravatars cache locally. It has the same effect as disabling the plugin.', 'OGC'); ?></small>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <?php _e('Gravatars cache directory', 'OGC'); ?>
                </th>
                <td>
                    <label><?php echo ABSPATH; ?><input type="text" name="OGC_options[directory]" value="<?php echo $this->cacheDirectory; ?>" size="30"/></label>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2"  class="description">
                    <small><?php _e('This option allows you to specify where cached files will be saved. Gravatar images will be stored in this directory.', 'OGC'); ?></small>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <?php _e('Refresh gravatars cache every', 'OGC'); ?>
                </th>
                <td>
                    <label><input type="text" name="OGC_options[refresh]" value="<?php echo $this->refreshCache; ?>" size="2"/> <?php echo _n('day', 'days', $this->refreshCache, 'OGC'); ?></label>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2"  class="description">
                    <small><?php _e('This option allows you to specify how many days, the Gravatars are cached. After these days will be made an update of the gravatar. This option accepts an integer value from 1.', 'OGC'); ?></small>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <?php _e('Default gravatar extention', 'OGC'); ?>
                </th>
                <td>
                    <label>
                      <select name="OGC_options[extention]">
                        <option value="0" <?php selected($this->defaultExtentionId, 0); ?>><?php echo $this->validExtention[0]; ?></option>
                          <option value="1" <?php selected($this->defaultExtentionId, 1); ?>><?php echo $this->validExtention[1]; ?></option>
                          <option value="2" <?php selected($this->defaultExtentionId, 2); ?>><?php echo $this->validExtention[2]; ?></option>
                      </select>
                    </label>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2"  class="description">
                    <small><?php _e('This option allows you to choose the default extension type to use for non-customized gravatars.', 'OGC'); ?></small>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">
                    <?php _e('Search for outdated gravatars every', 'OGC'); ?>
                </th>
                <td>
                    <label><input type="text" name="OGC_options[search]" value="<?php echo $this->searchCache; ?>" size="2"/> <?php echo _n('hour', 'hours', $this->searchCache, 'OGC'); ?></label>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2"  class="description">
                    <small><?php _e('This option allows you to specify an interval in hours in which the existence of outdated gravats is checked. This check is made in the background by wordpress cron.', 'OGC'); ?></small>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <?php _e('How many gravatars to upgrade or optimize each time', 'OGC'); ?>
                </th>
                <td>
                    <label><input type="text" name="OGC_options[howMany]" value="<?php echo $this->howManyUpdate; ?>" size="2"/></label>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2"  class="description">
                    <small><?php _e('This option allows you to specify how many gravatars are updated or optimized each time.', 'OGC'); ?></small>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" name="updateOptions" id="submit" class="button button-primary"><?php _e('Save Changes'); ?></button>
            <button class="button" name="clearCache" <?php echo disabled($this->cacheActive, 1, true) ?>><?php _e('Clear Cache', 'OGC'); ?>
                <span class="cache count-<?php echo $cacheInfo['amount']; ?> ">
                    <span class="clear-count"><?php echo '('.$cacheInfo['images'] .' '._n('image', 'images', $cacheInfo['images'], 'OGC').' / '.$cacheInfo['usedSpace'].')' ?></span>
                </span>
            </button>
        </p>
    </form>
	<div  class="postbox">
		<h2><?php _e('Contact Us', 'OGC'); ?></h2>
		<div class="inside">
			<b><?php _e('WebSite', 'OGC') ?>:</b> <a href="https://www.ncdc.pt">https://www.ncdc.pt</a><br>
			<b><?php _e('E-mail', 'OGC') ?>:</b> <a title="Mail To miguel@ncdc.pt" href="mailto:miguel@ncdc.pt">miguel@ncdc.pt</a>
		</div>
	</div>
</div>
