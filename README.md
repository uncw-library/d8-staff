### Background

There are three containers:  apache-drupal, mysql, and phpmyadmin.  Apache listens at port 80 & connects to /var/www/html/web inside the apache-drupal container.

The dev box runs on your local computer.

Content/configuration are changed on the live server.  They are saved in the database and in drupal-sync.

Code is changed on the dev box.  We can QA our changes locally.

### What's in the git repo

Just enough to build our site.
 - Dockerfile for installing OS-level programs (php, drush, composer, ldap, ssmtp)
 - php.ini & settings.php
 - the apache config file
 - our custom modules & themes
 - drupal-sync files
 - but not user-uploaded files

## Production

#### Creating site from scratch

    - The drupal is fully formed in the docker image
        - Upgrading to a new image will overwrite the drupal sync folder
        - So, do a drupal config sync export before upgrading, if you want to keep the previous sync settings
    - The only permanent folders (shared volumes) in drupal are:
        - ../private   (private uploaded files)
        - ./sites/default/files   (public uploaded files)
    - The mysql & its backup each have a permanent folder (shared volume)
    - Use drupal-sync to share config
    - Use phpmyadmin export to share database

###### Making a Rancher service

In Rancher, create a new stack using:
  
  - Name: d8staff
  - docker-compose.yml: this repo's docker-compose-rancher.yml  (Change the default passwords and drupal hash salt in that file beforehand.)
  - racher-compose.yml: this repo's rancher-compose.yml

Create two load-balancer entries: the phpmyadmin and the nginx.

  - Public  HTTPS  {url.libapps.uncw.edu} 443 _ d8staff/webapp 80
  - Public  HTTPS  {url.libapps.uncw.edu} 443 _ d8staff/pma 80

Get the sqldump into the mysql database.  (not settled yet on the best way.  The first round used rsync sqldump to server, move sqldump into mysql container, enter mysql container, then commandline mysql -u root -p < sqldump, but that was a bit of a hack.

###### Upgrading a drupal image

If you wish to keep the current drupal sync settings, do a drupal config sync export from the Admin menu.  You'll use this exported file to do a drupal config sync import after the upgrade.

Build & tweak the d8-staff using docker-compose on the git repo.  See the above sections for details.

When the docker-compose dev box is good, do a `docker build --no-cache -t libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base ./drupal8docker`  and a `docker push libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base`

Then pull the image into Rancher, with an 'Upgrade service'.

###### Maintaining the production db:

Reasoning:  We'll want one gold-standard database.  Ultimately, that's the production machine.  Folks adding content can add to the production site & it will go directly to the production database.  The developers who want to use that data will need to sqldump that database for local use.  The db sidecar makes automatic sqldumps to /home/randall/volumes/backups/Backups/d8-staff.  if you need the database for a dev box, put a copy of the sqldump at ./db_autoimport/d8-staff_sandbox_db.sql.

###### Troubleshooting a failed mysql inport on production

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


## Dev box

### How to create a dev box

1) Clone this repo to your local computer

```
env GIT_SSL_NO_VERIFY=true git clone https://libapps-admin.uncw.edu/randall-dev/d8-staff.git
cd d8-staff
git checkout -b {your_branch}
```

2) Get the latest drupal-sync from production

 - in Drupal web interface > Configuration > Configuration synchronization > Export
 - delete the files in your computer's ./d8-staff/drupal8docker/sync
 - extract the downloaded file into that folder.


3) Get the database dump

- For now, the latest datbase dump is on the Team files tab, but when production is live you'll need to export it from the production server, using phpmyadmin or however.
- Place that {dumpfile name}.sql at ./db_autoimport --- with no other files in that folder.

4) Spin up the dev box:

```
docker-compose up -d
docker-compose logs -f
```

   wait until the db container logs say "MySQL init process done. Ready for start up."  Exit the log screen with `Ctrl-C`.  Or keep the log screen open & use a second terminal.  Your choice.

then spruce up the files to make drupal happy:

```
docker-compose exec webapp chown -R www-data:www-data /drupal_sync /var/www/html/web/modules/custom /var/www/html/web/themes/custom /etc/apache2/sites-enabled/000-default.conf
docker-compose exec webapp drush cache-rebuild
docker-compose exec webapp drush updatedb
docker-compose exec webapp drush config-import
```

See the app at http://localhost:8112
See the phpmyadmin at http://localhost:8113

Some bug requires you go to the web interface > Appearances > Staff Subtheme > Save Configuration.  Otherwise the colors don't apply.

Revise the app via the Drupal interface or via a text editor.  The repo & container folders are linked.


This stops the dev box:

```
ctrl-C
-and/or-
docker-compose stop 
```

6) After you are happy with the changes:

 - git commit your branch
 - docker build & push the new base image as described below
 - update rancher

#### How to clear the cache

⋅⋅⋅It's a good habit -- resolves most problems.

```
docker-compose exec webapp drush cache-rebuild
```

   or use the drupal web interface

### How to make a new base image

You can always use the latest 'drupal8-base' image from gitlab.  Docker-compose & rancher pull from it automatically.

But sometimes you need to update that image:

 - anytime you've finished a feature/bug-fix on the dev box
 - anytime you need to update Drupal version or some dependency

```
docker build --no-cache -t libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base ./drupal8docker 
```

### pushing a new image to production

```
docker push libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base
```

Then Rancher upgrade & ?how to refresh drupal via the web interface?

#### wiping the dev box and starting clean:

```
docker-compose down
docker volume rm d8-staff_db_data
docker build --no-cache -t libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base ./drupal8docker
docker-compose up
```

#### exporting a local database

Use the phpmyadmin at :8113, or run on a command line:

```
docker-compose exec webapp drush sql-dump --result-file=/docker-entrypoint-initdb.d/{some filename}.sql
# look for the file in ./db_autoimport/
```

#### add/remove a drupal module

With the repo on your local computer:

```
** add or remove `composer require module:4.3` to the drupal8docker/Dockerfile **
docker-compose down
docker volume rm d8-staff_drupal_data
docker build --no-cache -t libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base ./drupal8docker
docker-compose up -d
docker-compose exec webapp chown -R www-data:www-data /drupal_sync /var/www/html/web/modules/custom /var/www/html/web/themes/contrib /etc/apache2/sites-enabled/000-default.conf
docker-compose exec webapp drush cache-rebuild
docker-compose exec webapp drush updatedb
docker-compose exec webapp drush config-import

** see site at localhost:8112 **
```

When you're happy: git commit & push, and docker build & push.

#### editing a theme file

Revise the files in .drupal8docker/themes.  The folder links to the dev box's /drupal_app/web/themes/contrib folder.

and clearing the cache:

```
docker-compose exec webapp drush cache-rebuild
```

#### editing a module

Revise the files in ./drupal8docker/modules/custom.  The folder matches the container's /drupal_app/web/modules/custom folder.

You may have to docker build again, depending how deep the change was.

#### sharing drupal config changes

drupal_sync is drupal8's way of sharing drupal config changes.
The drupal sync files are at ./drupal_sync
You can use the Drupal web interface, or on a dev box:

  - exporting drupal_sync

    `docker-compose exec webapp drush config-export`

    or use the drupal web interface

  - importing drupal_sync
  
    `docker-compose exec webapp drush config-import`

    or use the drupal web interface