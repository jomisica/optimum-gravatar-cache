=== Optimum Gravatar Cache ===
Contributors: jomisica
Author URI: https://www.ncdc.pt/members/admin
Tags: gravatar-image, gravatar, gravatar-cache, avatar-optimization, avatar
Requires at least: 4.8.2
Tested up to: 4.7
Stable tag: 1.0
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

The plugin is able to use the WordPress gravatars as well as the BuddyPress plugin gravatars.

== Languages ==
1. English
2. Hebrew

== Installation ==
Currently the plugin is not found in wordpress repositories.
In order to install it you can use one of the following method or another that meets your needs.

= Clone the project =
You can clone the repository to the wordpress plugins directory.

```Bash
$ cd /to/your/wordpress/plugin/directory
$ git clone https://github.com/jomisica/optimum-gravatar-cache.git
```

= Through ZIP file =
```Bash
$ cd /to/your/wordpress/plugin/directory
$ wget https://github.com/jomisica/optimum-gravatar-cache/archive/master.zip
$ unzip master.zip
$ rm master.zip
```

== Frequently Asked Questions ==

== How to donate or contribute? ==

== Screenshots ==
1. In this screenshot we can see the options to configure the cache.
2. In this screenshot, we can see the options that allow you to configure the default avatar.
3. In this screenshot we can see the optimization options.
4. In this screenshot we can see some about the use of the plugin.
5. In this screenshot we can see a comparison of the files that are downloaded when the plugin is in use and when it is not.

== Changelog ==
