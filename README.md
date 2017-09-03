# Optimum Gravatar Cache
WordPress **Optimum Gravatar Cache** has been written by José Miguel Silva Caldeira <miguel@ncdc.pt>.

## Description:
WordPress **Optimum Gravatar Cache** is a plugin for allowing you to cache the gravatars locally. In this way it is possible to use the gravatars in a more efficient way.

The plugin intends the following:
* Work with the gravatars locally, cache;
* Reduce the number of requests per page, thus reducing the total time required to load all files. This is achieved because most users do not have a custom gravatar, and for these only a file needs to be downloaded;
* Optimize all gravatars by reducing their size and again the transfer time.

These are for now the strengths.

The plugin is able to use the WordPress gravatars as well as the BuddyPress plugin gravatars.

## Pros and Cons
Whoever develops in general and for Wordpress in question knows that this one consumes quite a few resources.
Any improvements that can be made through plugins will have consequences, good and bad.

### Pros
* This plugin tries to minimize the number of files needed to display the same gravatar. This way the total time to load all the files is smaller.
* All images of gravatars are optimized internally by reducing their size but yet showing the same information. This will also help in the total time required to load all files.
* File names are reduced through base conversion, again with the idea of ​​saving a few more bytes.
* It is possible to choose between the extension of the gravatars by default, being possible (jpg / png / gif). In this way adapting to the needs of each one. Because each of these file types have their advantages and disadvantages.
* It is possible to considerably reduce the processing of either this plugin or all of them using a general wordpress cache. Such as WP Super Cache.
* Optimization is done in the background, allowing the user to better control the resources spent on image optimization.
* Updating the gravatars is also done in the background again allowing some control on the part of the user.
* You can not associate the name of the gravatar with a particular user.

### Cons
* It will consume some more processor and memory resources to be processed.
* It will consume more bandwidth because the files will be transferred from the local server.
* Since image optimization uses an external service, it will consume more bandwidth.

## Installation
Currently the plugin is not found in wordpress repositories.
In order to install it you can use one of the following method or another that meets your needs.

### Clone the project
You can clone the repository to the wordpress plugins directory.

```Bash
$ cd /to/your/wordpress/plugin/directory
$ git clone https://github.com/jomisica/optimum-gravatar-cache.git
```

### Through ZIP file

```Bash
$ cd /to/your/wordpress/plugin/directory
$ wget https://github.com/jomisica/optimum-gravatar-cache/archive/master.zip
$ unzip master.zip
$ rm master.zip
```

## Settings
Below we can see the ScreenShot of the plugin configuration page.

![Settings ScreenShot](media/settings-page.png?raw=true "Settings ScreenShot")

## Differences using and not using the plugin
Below we can see the ScreenShot that shows the difference of files loaded using the plugin and when it is not being used.

![Differences using and not using the plugin](media/compare.png?raw=true "Differences using and not using the plugin")

This test was done in an actual article of my blog, you can confirm by yourselves in the following link.

https://www.ncdc.pt/2014/11/07/como-ter-acesso-total-ao-router-technicolor-tg784n-v3-da-meo/

## Problem/BUGS report:
If you find any bugs or problems just mail me José Miguel Silva Caldeira <miguel@ncdc.pt>
