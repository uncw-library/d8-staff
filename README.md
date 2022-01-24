## Background

### There are three containers:  apache+drupal, mysql, and phpmyadmin.

    The dev box runs on the local computer.
    Content/configuration are changed on the prod server.
    Code is changed on the dev box.
    Dev speed/ease-of-use and reliablility are defining features.  

### What's in the git repo

Just enough to build our site.
 - Dockerfile for installing OS-level programs (php, drush, composer, ldap, ssmtp)
 - the apache config file
 - php.ini & our additions to drupal's settings.php
 - our custom modules & themes
 - drupal-sync files
 - but not user-uploaded files, passwords, or db data.

# How to revise the site or upgrade versions

### 1) Clone this repo to your local computer

```
env GIT_SSL_NO_VERIFY=true git clone https://libapps-admin.uncw.edu/randall-dev/d8-staff.git
cd d8-staff
```

### 2) Get the production drupal-sync

- in Drupal web interface > Configuration > Configuration synchronization > Export
- on your local computer, delete all the .yml files in ./d8-staff/drupal8docker/sync/
- unzip the downloaded file into that folder.

### 3) Get the production database

(This would be better if we had small dummy database that had sample data, but was similar to the production database.)

- Use the production phpmyadmin to export the db.
- Place that {dumpfile name}.sql at ./db_autoimport --- next to add_drupaluser.sql.  These should be the only two files in ./db_autoimport.  All sqldumps in this folder get autoimported into the dev box's mysql.
- Remove the old db docker volume with `docker volume rm d8-staff_db_data`

### 4) Spin up the dev box (pick slow or fast way)

#### a. Slow Way ( updates all modules and OS-level programs ) , or

```
docker build --no-cache -t libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base --platform linux/x86_64/v8 ./drupal8docker
docker-compose up --build -d
docker-compose logs -f
```

#### b. Fast Way ( does not update modules & programs )

```
docker pull libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base
docker-compose up --build -d
docker-compose logs -f
```


Wait until the logs say "MySQL init process done. Ready for start up."  You may exit the log screen with `Ctrl-C`, or keep the log screen open & use a second terminal.  Your choice.

### 5)...then spruce up the files to make the dev box happy

```
docker-compose exec webapp chown -R www-data:www-data /drupal_sync /var/www/html/web/modules/custom /var/www/html/web/themes/custom /etc/apache2/sites-enabled/000-default.conf
docker-compose exec webapp drush cache-rebuild
docker-compose exec webapp drush updatedb
docker-compose exec webapp drush config-import
```

    See the app at http://localhost:8112
    See the phpmyadmin at http://localhost:8113

### 6) Revise the app via the web interface or a text editor

Any changes in this repo's ./drupal8docker are live updated in the running container.  Normal drupal cache-rebuild, etc practices still work via the web interface.

### 7) After you are happy with your changes

Stop the dev box: `Ctrl-C` and `docker-compose down`

### 8) If you made a config change, then export it to ./drupal8docker/sync

```
docker-compose exec webapp drush config-export
```

### 9) Fast bake a new image

```
docker build -t libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base --platform linux/x86_64/v8 ./drupal8docker
docker push libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base
```

- git commit and git push
- upgrade the service in rancher
- open the site page: /admin/reports/status & run the cron job or db update script if it recommends.


If the theme does not show on production, the hack is to enter the site's admin panel > Appearances > Staff Subtheme > Settings > "Save Configuration" button.

-------------------------

# Misc Dev box tools:

### How to clear the cache

⋅It's a good habit -- resolves most problems.

`docker-compose exec webapp drush cache-rebuild`  or use the drupal web interface

### Wiping the entire dev box and starting clean:

```
docker-compose down
docker volume rm d8-staff_db_data
docker build --no-cache -t libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base ./drupal8docker
docker-compose up
```

### Add/Remove a drupal module from the image

Before step 4) "Spin up the dev box":  Revise ./drupal8docker/Dockerfile, lines with "composer require ..."  Then continue with 4a) "Slow Way".

### Editing a theme file

While in step 6) "Revise the app via the web interface or a text editor":  Revise the files in .drupal8docker/themes.  The folder links to the dev box's /drupal_app/web/themes/contrib folder.

and clear cache frequently: `docker-compose exec webapp drush cache-rebuild` or via web interface.

### Editing a module

While in step 6) "Revise the app via the web interface or a text editor":  Revise the files in ./drupal8docker/modules/custom.  The folder matches the container's /drupal_app/web/modules/custom folder.

You may have to jump back to 4b "Fast way", depending how deep the change was.

----------------------
# Creating a drupal on Rancher from scratch

## Making a Rancher service from scratch

On libapps server, create a folders:
Permissions 0644  33:33  /home/randall/volumes/d8-staff/private
Permissions 0644  33:33  /home/randall/volumes/d8-staff/files
Permissions 0644  {anything}:{anything}  /home/randall/volumes/d8-staff/db_autoimport

Put a .sql database dump and this repo's ./db_autoimport/add_drupaluser.sql file into the server's db_autoimport folder.  It will autopopulate the mysql with those .sql dumps.  Note:  this db autoimport only happens if the mysql has no data, i.e. a clean install.  This is what we want, only import db dump if the db is empty.

In Rancher, create a new stack using:
  
  - Name: d8staff
  - docker-compose.yml: this repo's docker-compose-rancher.yml  (Change the default passwords and drupal hash salt in that file beforehand.)
  - racher-compose.yml: this repo's rancher-compose.yml

Create two load-balancer entries: the phpmyadmin and the nginx.

  - Public  HTTPS  {url.libapps.uncw.edu} 443 _ d8staff/webapp 80
  - Public  HTTPS  {url.libapps.uncw.edu} 443 _ d8staff/pma 80

See the live site at https://{url.libapps.uncw.edu} 

# Random notes

### Troubleshooting a failed mysql inport on production

If the mysql import failed & you want to delete the whole database data:
you can stop the Stack in Rancher,
delete the folder at /home/randall/volumes/d8staff/mysql
revise the sqldump at /home/randall/volumes/d8staff/db_autoimport
restart the stack.
The mysql rancher logs will read "MySQL init process done. Ready for start up." if the database succeeded in importing.

### Sharing drupal config changes

drupal_sync is drupal8's way of sharing drupal config changes.
The drupal sync files are at ./drupal_sync
You can use the Drupal web interface, or on a dev box:

  - exporting drupal_sync: `docker-compose exec webapp drush config-export`

    or use the drupal web interface

  - importing drupal_sync: `docker-compose exec webapp drush config-import`

    or use the drupal web interface


