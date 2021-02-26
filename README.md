![treopim_h80](_assets/atropim_logo_color_340_64px.png)

## What Is AtroPIM?

![anne](_assets/atropim-banner.png)

AtroPIM is an [open-source product information management system (PIM)](https://atropim.com), which is based on [AtroCore](https://github.com/atrocore/atrocore) software platform. AtroPIM (as well as AtroCore) is distributed under GPLv3 License and is free. It has a lot of features right out-of-the-box and thus is an excellent tool for cost-effective and timely application development.

AtroPIM is a single page application (SPA) with an API-centric and service-oriented architecture (SOA). It has a flexible data model based on entities, entity attributes and relations of all kinds among them. AtroPIM allows you to gather and store all your product content in one place, enrich it and spread it to several channels like own online shop, amazon, eBay, online shops of your distributors, on a tablet or mobile application. AtroPIM will help you to structure and organize all your flexible data and get rid of excel mess. 

## What Are the Advantages of AtroPIM?

- Many out-of-the-box features;
- Free – 100% open source, licensed under GPLv3;
- REST API;
- Service-oriented architecture (SOA);
- Responsive and user friendly UI;
- Configurable (entities, relations, layouts, labels, navigation, dashboards);
- Extensible with modules;
- Includes the advantages of [AtroCore](https://github.com/atrocore/atrocore).

## How Does It Work?

AtroPIM is an effective solution for storing, processing and managing your product information. All product data are extracted from different sources, classified, translated and enriched. AtroPIM helps you publish the relevant data on different sales channels with minimum efforts. 

![unctions_banne](_assets/how_it_works_scheme_en.png)

## Features

AtroPIM comes with a lot of features directly out of the box, including:

- associations;
- measuring units;
- channels;
- catalogs;
- category trees and categories;
- product families;
- product series;
- products;
- attribute groups and attributes;
- product and category images (available only when the [AtroDAM module](https://atrodam.com) is also installed to your AtroPIM system);
- assets (available only when the [AtroDAM module](https://atrodam.com) is also installed to your AtroPIM system);  
- portals;
- and much more.

Want to know more about AtroPIM functions and its advantages for you? Please, visit [our website](http://atropim.com). Find out more about the AtroDAM module [here](https://atrodam.com).

## Technology

AtroPIM is based on EspoCRM and uses PHP7, backbone.js, composer and some Zend Framework 3 libraries.

![Technology_schem](_assets/technologie-scheme-eng.png)

Want to know more about AtroPIM technology? Please, visit [our website](http://atropim.com/technology)!

## Integrations

AtroPIM has a REST API and can be integrated with any third-party system, channel or marketplace. 

We offer following integrations:

- Data synchronisation with over 1000 Channels via channable.com
- ODOO
- SAP Business One,
- Microsoft Dynamics NAV
- Magento 2
- Magento 1
- Shopware 5
- OXID eShop 6
- and others.

Please, [ask](https://atropim.com/contact), if you want to know more.

## Documentation

- Documentation for users is available [here](https://github.com/atrocore/atropim-docs/tree/master/en/user-guide).
- Documentation for administrators is available [here](https://github.com/atrocore/atropim-docs/tree/master/en/administration).
- Documentation for developers is currently not available.

### Requirements

* Unix-based system. Ubuntu is recommended.
* PHP 7.1 or above (with pdo_mysql, openssl, json, zip, gd, mbstring, xml, curl, exif extensions).
* MySQL 5.5.3 or above.

### Configuration Instructions Based on Your Server

* [Apache server configuration](https://github.com/atrocore/atropim-docs/blob/master/en/administration/apache-server-configuration.md)
* [Nginx server configuration](https://github.com/atrocore/atropim-docs/blob/master/en/administration/nginx-server-configuration.md)

### Installation

> The Installation guide is based on **Ubuntu**. Of course, you can use any Unix-based system, but make sure that your OS supports the following commands.<br/>

To create your new AtroPIM application, first make sure you are using PHP 7.1 or above and have [Composer](https://getcomposer.org/download/) installed.

1. Create your new project by running one of the following commands.

   If you don't need the demo data, run:
   ```
   composer create-project atrocore/skeleton-pim-no-demo my-atropim-project
   ```
   If you need the demo data, run:
    ```
   composer create-project atrocore/skeleton-pim my-atropim-project
   ```   
2. Change recursively the user and group ownership for project files: 
   ```
   chown -R webserver_user:webserver_user my-atropim-project/
   ```
   >**webserver_user** – depends on your webserver and can be one of the following: www, www-data, apache, etc.   

3. Change the permissions for project files: 
   ```
    find . -type d -exec chmod 755 {} + && find . -type f -exec chmod 644 {} +;
    find data custom -type d -exec chmod 775 {} + && find data custom -type f -exec chmod 664 {}
   ```

4. Configure the crontab as described below.

   4.1. Run the following command:
      ```
      crontab -e -u webserver_user
      ```
   4.2. Add the following configuration:
      ```
      * * * * * /usr/bin/php /var/www/my-atropim-project/index.php cron
      ```      

5. Install AtroPIM following the installation wizard in the web interface. Go to http://YOUR_PROJECT/
     
## License

AtroPIM is published under the GNU GPLv3 [license](LICENSE.txt).

## Support

- For support please contact us - visit [AtroPIM Website](http://atropim.com).
