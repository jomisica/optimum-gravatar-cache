=== Optimum Gravatar Cache ===
Contributors: jomisica
Author URI: https://www.ncdc.pt/members/admin
Donate link: https://www.ncdc.pt/members/admin
Tags: gravatar-image, gravatar, gravatar-cache, avatar-optimization, avatar
Requires PHP: 5.3
Requires MySQL at least: 5.0.95
Requires at least: 4.7
Tested up to: 4.9.5
Stable tag: 1.1.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

It cache the gravatars locally, reducing the total number of requests per post. This will speed up the loading of the site and consequently improve the user experience.

== Description ==

WordPress **Optimum Gravatar Cache** is a plugin for allowing you to cache the gravatars locally. In this way it is possible to use the gravatars in a more efficient way.

The plugin intends the following:
* Work with the gravatars locally, cache;
* Reduce the number of requests per page, thus reducing the total time required to load all files. This is achieved because most users do not have a custom gravatar, and for these only a file needs to be downloaded;
* Optimize all gravatars by reducing their size and again the transfer time.

These are for now the strengths.

If you use the plugin, add your rating, so I can have some feedback.

== Languages ==

1. English
2. Portuguese

==Dependencies==

This plugin depends on the following PHP modules:

*  php-gd
*  php-curl

== Installation ==

Install like any other plugin, directly from your plugins page. Go to the plugin settings page at Settings-> Optimum Gravatar Cache, configure for your needs and enable caching.

= After Installed =

Once installed it is necessary to configure the server so that it can serve the avatars so that they are reassessed every time they are used.

In the case of Apache, an .htaccess file is automatically created in the cache directory. In the case of NGinx it is necessary to add the following settings manually.


location ~* ^/cache/avatar/.*\.(jpg|png|gif|svg)$ {
  allow all;
  gzip_static  on;
  etag off;
  expires off;
  add_header Cache-Control "max-age=0";
}
location ~* ^/cache/avatar/.*$ {
        deny all;
}

Caution: Copy the text directly from the readme.txt file because invalid characters appear here.
You must adjust the cache directory accordingly.


== Screenshots ==

1. In this screenshot we can see the options to configure the cache.
2. In this screenshot, we can see the options that allow you to configure the default avatar.
3. In this screenshot we can see the optimization options.
4. In this screenshot we can see some about the use of the plugin.
5. In this screenshot we can see a comparison of the files that are downloaded when the plugin is in use and when it is not.

== Upgrade Notice ==

= 1.1.1 =

* Modify message for translation

= 1.1.0 =

* I removed the email column from the table because it was only needed for debugging purposes.
* I added a new action so that when a user is deleted all associated avatars are removed from the cache. This is important for reasons of cache space as well as for adapting to RGPD, which the user has the right to be forgotten.

= 1.0.9 =

* Compatibility check with wordpress version 4.9.5.

= 1.0.8 =

* Compatibility check with wordpress version 4.9.4.

= 1.0.7 =

* Compatibility check with wordpress version 4.9.3.

= 1.0.6 =

* Compatibility check with wordpress version 4.9.3.

= 1.0.5 =

* Compatibility check with wordpress version 4.9.2.

= 1.0.4 =

* Modified the bp_core_fetch_avatar_url filter to be added only when ajax requests are made. This way preventing duplicate processing of avatars.

= 1.0.3 =

* Now the new sizes are added if they appear in the background. In order to anticipate the actions of the users so that it is avoided to solve the gravatars at the moment.
* It was resolved how to add a new avatar of greater dimension than the existing ones in cache. It is slow because the avatar is resolved at the moment, however it is always shown the same avatar regardless of size. This is important when using avatars of different sizes at the same time, as is the case with buddypress.
* Now it is possible to also handle bp_core_fetch_avatar_url filter. So the avatars that are part of the buddypress automatic popup menu suggestions already use the cache.

= 1.0.2 =

* Added option to precompress .SVG files.
* When uninstalling the plugin, it is cleaned by removing the options used by it, removing the used table as well as the cache directory.
* Added verification of the PHP modules needed for the correct operation of the plugin.
* Solve a problem in the updateCache function in order to update the last check of a gravatar even when it has not changed.
* Repairing the Portuguese translation in the .po file.

= 1.0.1 =

* Now the default avatars are already optimized.

= 1.0 =

* First realese

== Changelog ==

= 1.1.1 =

* Modify message for translation

= 1.1.0 =

* I removed the email column from the table because it was only needed for debugging purposes.
* I added a new action so that when a user is deleted all associated avatars are removed from the cache. This is important for reasons of cache space as well as for adapting to RGPD, which the user has the right to be forgotten.

= 1.0.9 =

* Compatibility check with wordpress version 4.9.5.

= 1.0.8 =

* Compatibility check with wordpress version 4.9.4.

= 1.0.7 =

* Compatibility check with wordpress version 4.9.3.

= 1.0.6 =

* Compatibility check with wordpress version 4.9.3.

= 1.0.5 =

* Compatibility check with wordpress version 4.9.2.

= 1.0.4 =

* Modified the bp_core_fetch_avatar_url filter to be added only when ajax requests are made. This way preventing duplicate processing of avatars.

= 1.0.3 =

* Now the new sizes are added if they appear in the background. In order to anticipate the actions of the users so that it is avoided to solve the gravatars at the moment.
* It was resolved how to add a new avatar of greater dimension than the existing ones in cache. It is slow because the avatar is resolved at the moment, however it is always shown the same avatar regardless of size. This is important when using avatars of different sizes at the same time, as is the case with buddypress.
* Now it is possible to also handle bp_core_fetch_avatar_url filter. So the avatars that are part of the buddypress automatic popup menu suggestions already use the cache.

= 1.0.2 =

* Added option to precompress .SVG files.
* When uninstalling the plugin, it is cleaned by removing the options used by it, removing the used table as well as the cache directory.
* Added verification of the PHP modules needed for the correct operation of the plugin.
* Solve a problem in the updateCache function in order to update the last check of a gravatar even when it has not changed.
* Repairing the Portuguese translation in the .po file.

= 1.0.1 =

* Now the default avatars are already optimized.

= 1.0 =

* First realese
