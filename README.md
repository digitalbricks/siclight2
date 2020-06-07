![SIC Logo](img/sic-logo.svg)
# Site Info Center LIGHT 2
**Getting information about sites CMS and PHP versions with one click.** 

_Site Info Center LIGHT (SIC) version 2 is an almost complete rewrite of [SIC version 1](https://github.com/digitalbricks/siclight), now using **Vue.js** for the frontend logic – wich is way faster (and easier to read) than the jQuery-driven version 1. In the new version, all the PHP-sided data processing is combined into a single PHP class with some essential methods exposed via a REST-like API to the Vue.js frontend. I recommend using version 2 – while version 1 will still do its job._


## Whats the purpose?
If you are a web design / development agency or a freelancer you might be familiar with this scenario: 

You have a bunch of sites with a couple of different content management systems and different server configurations under your care. Maybe hosted on different shared servers, managed by your client himself. And you never know exactly which CMS version and wich PHP version is currently running on each site without having to maintain an internal list. Sounds familiar?

**Site Info Center LIGHT (SIC) fetches all that information for you with just one click. You only have to configure your sites once in SIC and every time you click SIC's refresh button you get the current version of the CMS and PHP versions in use.**


## Requirements
**SIC** and the required **[SIC Satellite](https://github.com/digitalbricks/sic-satellite)** (more details in short) are written in PHP.  SIC, the user interface or frontend, uses **CURL** to communicate with the satellite, so your (local) server running SIC must have CURL installed - which is the case in most environments, especially if you local server is driven by XAMPP or MAMP.

**IMPORTANT NOTE: The current version of SIC is intended to be used on a PHP capable server in your local (!) network – your local development server for example – because it has no login / protection / user management that prevents foreign users from seeing sensible sites information. Hence the addition “LIGHT” in the project name. You may use SIC on a remote server and protect it with HTTP BASIC AUTH - but I would not recommend that.**


## How  does it work
**The system is made of two parts:**

* **SIC** (the repo you are watching)

    The part to be placed onto you local development server where you can reach it with you browser. It provides the user interface and the functionality for fetching information from the sites satellites.

* **SIC satellite** ([to be found here](https://github.com/digitalbricks/sic-satellite))

    This is a small PHP script you have to place in the root folder of the sites to be monitored. The satellite answers the request of the SIC with information about the CMS and PHP versions currently in use. The satellite in this project comes with a handful functions for getting version info from CMS I was using or I am still using but can be extended with further functions for the CMS you use. More about this later.

In order to prevent the **SIC satellite** to answer all requests and blasting informations into the wild, we are using a **shared secret** which has to be configured with the site in **SIC** and also has to be placed in the **SIC satellite** script.

If you hit the refresh button on the **SIC** user interface, SIC will call the **SIC satellite**, telling him wich CMS it should search for version information (wich function the satellite should run for getting the CMS version) and providing the shared secret. After the satellite has answered, the received information are displayed in the SIC and also stored in a CSV file in `/history` folder. **Yes, CSV**. There is no need for a database and you could import the CSV files into a spreadsheet tool if you want. SIC also provides a button for **bulk updating** all configured sites and displaying the **version history** of each site with one single click.

There will be also a `_summary-latest.csv` created in `/history` folder when you do a bulk update ("Refresh All" button). This file will contain all results of the latest sites information bulk update. You will see an according notification when the refresh queue is finished with a download button.


## Currently supported CMS
As Site Information Center LIGHT is only responsible for displaying and storing of retrieved information, the SIC satellite controls / limits the supported CMS. You will find an always up to date list of supported systems in the repository of the **[SIC Satellite](https://github.com/digitalbricks/sic-satellite)**. And of course you can add new functions to the satellite if you want.


## Configuration: SIC
After downloading the project, you will find a file `sites-config.NEW.php` in the root folder. Just rename it to `sites-config.php` and configure all your sites using the syntax sample provided in the file:

```php
$sites = array( 
    "example.com" => array(                                     // human readable title of the site to monitor
        "url"       => "https://www.example.com/satellite.php", // full URL of the satellite script
        "sys"       => "PROCESSWIRE",                           // system identifier, the satellite has a function for
        "secret"    => "T0tallY5ecret",                         // the shared secret of the site, HAVE TO match the one in the satellite
        "inact"     => false                                    // set to "true" if the site should not longer monitored but you want access to the history
    ),
    "another-site.com" => array(                                     
        "url"       => "https://www.another-site.com/obscured-filename.php", 
        "sys"       => "WORDPRESS",                                  
        "secret"    => "Y0uN3v3RKn0w",                         
        "inact"     => true                                    
    )
);  

```

If you are done, place all the folder downloaded files and folders (including your `sites-config.php`) on your local server where you can reach it now and in the future. Try reaching SIC with your local URL in the browser - if a list of your configured sites is shown, everything is fine.


## Configuration: Satellite 
Place a copy of the `satellite.php` (to be found in the [SIC satellite repository](https://github.com/digitalbricks/sic-satellite)) in the root directory of all your configured sites via FTP.  Update the `$sat_secret` in the satellite to the one you configured for the corresponding site in SIC (don’t use the same secret across all your sites!) and make sure the satellite has a function for your CMS (if not, read the section “Add further CMS functions to satellite” in the [SIC satellite README](https://github.com/digitalbricks/sic-satellite)).  You are done.

Try hitting the refresh button next to a site in SIC or the _Refresh All_ button on top right and check if the SIC gets information from the satellite(s).


## Upgrading Site Info Center LIGHT
1. Download the newest version from GitHub
2. Remove file `sites-config.NEW.php` and folder `/history` from the **just downloaded** `sic`-folder (you really don't want to overwrite that file and folder on your local server because they contain your configuration and the version history)
3. Copy the remaining files and folder to the SIC folder on your local server, overwrite old files and folders

This is all. But a previous backup is **always** a great idea.


## Migration from version 1.x to version 2.x
1. Make a complete backup of your old installation (just for the case)
2. Delete all files and folders from version 1 **except `sites-config.php` and folder `/history`**
3. Download the newest version from GitHub
4. Remove file `sites-config.NEW.php` and folder `/history` from the download (version 2)
6. Copy the downloaded and prepared version 2 files and folders to the place where your version 1 was

(Essentially: Just keep `sites-config.php` and `/history`, don't care about all other files and folders.)

Maybe you will need to refresh your browser cache when first opening the new version, as your browser may have still JS and CSS files from version 1 in cache.

