# Optimum Gravatar Cache
WordPress **Optimum Gravatar Cache** has been written by José Miguel Silva Caldeira <miguel@ncdc.pt>.

## Description:
WordPress **Optimum Gravatar Cache** is a plugin for allowing you to cache the gravatars locally. In this way it is possible to use the gravatars in a more efficient way.

The plugin intends the following:
* Work with the gravatars locally, cache;
* Reduce the number of requests per page, thus reducing the total time required to load all files. This is achieved because most users do not have a custom gravatar, and for these only a file needs to be downloaded;
* Optimize all gravatars by reducing their size and again the transfer time.

These are for now the strengths.

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
