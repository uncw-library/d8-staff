A Drupal8 build

### Background

Apache and drupal are in one container.  Mysql is in another.  Phpmyadmin is along for user convenience.  Apache listens at port 80 & connects to /var/www/html/web inside the container.

The apache/drupal container is based off the dockerfile available at the url listed on line 1 of that dockerfile.

The apache/drupal image contains the last known set of: 

 - apache config file
 - drupal sync files
 - contrib modules
 - uncw custom modules
 - uncw theme revision
 - php.ini
 - settings.php

We expose these files, since revising them allows all the imaginable updates we may choose to make.  We use composer to install drupal core, as drupal9 is expected to require composer for install.  3rd party modules are named in the composer.json and float upward to the most recent version.

The images are agnostic of platform and work equally well on rancher1.x, docker-compose or kubernetes.

A dev box is provided, for creating new code or testing changes.

Production can be updated by upgrading to the newly-made image.

Content stays in the production db.  Config changes feedback to this repo via drupal config export.

### cloning this repo

```
env GIT_SSL_NO_VERIFY=true git clone https://libapps-admin.uncw.edu/randall-dev/d8-staff.git
cd d8-staff
```

### building a new drupal8base image

A developer role.  this is only necessary for baking changes into an image.  Mainly before pushing a new image to the docker repo & then to production.

```
docker build -t libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base ./drupal8docker 
```

### pushing to production

```
docker push libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base
```

## Production

#### Creating new site from scratch

###### Putting this repo onto the server

```
SSH to the libapps or libapps-staff server as user randall
cd /home/randall/volumes/
env GIT_SSL_NO_VERIFY=true git clone https://libapps-admin.uncw.edu/randall-dev/d8-staff.git d8staff
cd d8staff
git checkout {"master" for production, "staging" for staging, "dev" for development}
copy the base sqldump to /home/randall/volumes/d8staff/db_autoimport/
sudo chown -R randall:uncw-randall /home/randall/volumes/d8staff
```

###### Making a Rancher service

In Rancher, create a new stack using:
  
  - Name: d8staff
  - docker-compose.yml: this repo's docker-compose-rancher.yml  (Change the default passwords in that file beforehand.)
  - racher-compose.yml: this repo's rancher-compose.yml

Create two load-balancer entries: the phpmyadmin and the nginx.

  - Public  HTTPS  {url.libapps.uncw.edu} 443 _ d8staff/webapp 80
  - Public  HTTPS  {url.libapps.uncw.edu} 443 _ d8staff/pma 80

Copy the webapp url you just created. 

Paste that url into the "server_name" line in /home/randall/volumes/d8staff/drupal8docker/config/apache/000-default.conf
(This file is mirrored within the container at /etc/apache2/sites-enabled/000-default.conf.)
Restart the apache/drupal container.

###### Maintaining the production db:

Reasoning:  We'll want one gold-standard database.  Ultimately, that's the production machine.  Folks adding content can add to the production site & it will go directly to the production database.  The developers who want to use that data will need to sqldump that database for local use.  The db sidecar makes automatic sqldumps to /home/randall/volumes/backups/Backups/d8staff.  Maybe we'll keep a slimmed down version or one that has dummy data.  If it's dummy data, we might can git commit it in this repo.  We'll figure that out.  Either way, if you need the database for a dev box, put a copy of the sqldump at ./db_autoimport/d8-staff_sandbox_db.sql.

###### Troubleshooting failed mysql inport

```
If the mysql import failed & you want to delete the whole database data:
you can stop the Stack in Rancher,
delete the folder at /home/randall/volumes/d8staff/mysql
revise the sqldump at /home/randall/volumes/d8staff/db_autoimport
restart the stack.
The mysql logs will read "MySQL init process done. Ready for start up." if the database succeeded in importing.

Note:
The d8-mysql folder is the binary files of our d8staff database.  It is gitignored.
The db_autoimport folder is holds sqldumps.  It is also gitignored.
```

#### Updating the config/theme/module from another computer

Export the changes using the drupal config export page, or via drush config-export.  The export page creates a zip of config files. Drush exports the files to /drupal_sync.  A dev will those changes to the intended branch of this repo ('master' for production site, 'staging' for staging site, 'dev' for dev sites)  The next docker build or docker-compose will use the new config settings.

## Dev box

1) copy d8-staff_sandbox_db.sql to ./db_autoimport/
2) then:

```
docker-compose up -d
docker-compose logs -f
```

   wait until the db container logs say "MySQL init process done. Ready for start up."  Exit the log screen with `Ctrl-C`.  Or keep the log screen open & use a second terminal.  Your choice.

3) then:

```
docker-compose exec webapp chown -R www-data:www-data /drupal_sync /var/www/html/web/modules/custom /var/www/html/web/themes/custom /etc/apache2/sites-enabled/000-default.conf /var/www/html/composer.json
docker-compose exec webapp drush cache-rebuild
docker-compose exec webapp drush updatedb
docker-compose exec webapp drush config-import

```

See the app at http://localhost:8112
See the phpmyadmin at http://localhost:8113


Stopping the dev box with:

```
ctrl-C
-and/or-
docker-compose stop 
```

#### adding a 3rd party module

```
** add "name": "^version" to the "require" section of ./drupal8docker/config/drupal/composer.json **
docker-compose down
docker volume rm d8-staff_drupal_data
docker build --no-cache -t libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base:apache ./drupal8docker
docker-compose up -d
docker-compose exec webapp chown -R www-data:www-data /drupal_sync /var/www/html/web/modules/custom /var/www/html/web/themes/contrib /etc/apache2/sites-enabled/000-default.conf /var/www/html/composer.json
docker-compose exec webapp drush cache-rebuild
docker-compose exec webapp drush updatedb
docker-compose exec webapp drush config-import

** see site at localhost:8112 **
```

#### removing a 3rd party module

```
** remove the ./modules/contrib/{module name} folder **
** then, same steps as adding a 3rd party module **
```

#### editing a theme file

Revise the files in ./themes.  The folder is synced to the container's /drupal_app/web/themes/contrib

You may have to `docker-compose restart` to clear the cache.

#### editing a module

Revise the files in ./modules/custom.  It is the home to our custom modules.  The folder syncs to the container's /drupal_app/web/modules

#### sharing drupal config changes

drupal_sync is drupal8's way of sharing drupal config changes amongst developers.
You can also do this through the frontend, but that breaks version control.

The drupal sync files are at ./drupal_sync

#### exporting drupal_sync

`docker-compose exec webapp drush config-export`

#### importing drupal_sync:

`docker-composer exec webapp drush config-import`

#### rebuilding the drupal cache

`docker-compose exec webapp drush cache-rebuild`

⋅⋅⋅It's a good habit -- resolves most problems.

#### wiping the containers and starting clean:

```
docker-compose down
docker volume rm d8-staff_drupal_data d8-staff_db_data
docker build --no-cache -t libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base:apache ./drupal8docker
docker-compose up --build
```

#### if you need to export a local database

1) `docker-compose exec webapp drush sql-dump --result-file=/docker-entrypoint-initdb.d/{some filename}.sql`
1) look for the file in ./db_autoimport/
