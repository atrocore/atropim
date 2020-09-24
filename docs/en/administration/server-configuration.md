## Server Configurations
TreoPIM can be installed only on **Unix-based** Systems with configured Apache, Nginx, or IIS server.

#### PHP Requirements
Requires **PHP 7.1 or above** (with pdo_mysql, openssl, json, zip, gd, mbstring, xml, curl,exif extensions)

php.ini settings:
```
max_execution_time = 180
max_input_time = 180
memory_limit = 256M
post_max_size = 50M
upload_max_filesize = 50M
```

#### Database Requirements
Supports MySQL version 5.5.3 or greater. These are no special peculiarities.

#### Required Permissions
The files and directories should have the following permissions:
* ``/data``, ``/custom``, ``/client/custom`` – should be writable all files, directories and subdirectories (664 for files, 775 for directories, including all subdirectories and files);
* ``/application``, ``/client`` – should be writable the current directory (775 for the current directory, 644 for files, 755 for directories and subdirectories);
* All other files and directories should be readable (644 for files, 755 for directories).

To set the permissions go to project root and execute these commands in the terminal:
```
find . -type d -exec chmod 755 {} + && find . -type f -exec chmod 644 {} +;
find data custom client/custom -type d -exec chmod 775 {} + && find data custom client/custom -type f -exec chmod 664 {} +;
chmod 775 application/Espo/Modules client/modules;
```
All files should be owned and group-owned by the webserver process. It can be “www-data”, “apache”, “www”, etc.

To set the owner and group-owner go to project root and execute these command in the terminal:
```
chown -R <OWNER>:<GROUP-OWNER> .;
```

#### Configure crontab
1. Make cron handler file executable:
   ```
   chmod +x bin/cron.sh 
   ```  
2. Open crontab:
   ```
   crontab -e
   ```   
3. Configure crontab:
   ```
   * * * * * cd /var/www/my-treopim-project; ./bin/cron.sh process-treopim-1 /usr/bin/php 
   ```
   - **/var/www/my-treopim-project** - path to project root
   - **process-treopim-1** - an unique id of process. You should use different process id if you have few TreoPIM project in one server
   - **/usr/bin/php** - PHP7.1 or above

#### Configuration instructions based on your server
* [Apache server configuration](apache-server-configuration.md)
* [Nginx server configuration](nginx-server-configuration.md)