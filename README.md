A Drupal8 build

```
env GIT_SSL_NO_VERIFY=true git clone https://libapps-admin.uncw.edu/randall-dev/d8-staff.git
cd d8-staff
```

## building a new drupal8base image

skip this unless updating the version of drupal8 or its dependencies.

```
docker build -t libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base ./drupal8docker
```


## Production

#### Creating new site from scratch

###### Putting our site's unique files onto the server

```
SSH to the libapps or libapps-staff server as user randall
cd /home/randall/volumes/
env GIT_SSL_NO_VERIFY=true git clone {this repo's gitlab url}
sudo chown randall:uncw-randall /home/randall/volumes/d8-staff
cd d8-staff
git checkout {"master" for production, "staging" for staging, "dev" for development}
copy the base sqldump to /home/randall/volumes/d8-staff/db_autoimport/
```

###### Making a Rancher service

In Rancher, create a new stack with:
  
  - Name: {sitename}
  - docker-compose.yml: this repo's docker-compose-production.yml  (Change the default passwords in this file beforehand.)
  - racher-compose.yml: this repo's rancher-compose.yml

Create two load-balancer entries: the phpmyadmin and the nginx.

  - Public  HTTPS  {url.libapps.uncw.edu} 443 _ {container_name} 80

Copy the url you just created.

Paste that url into the "server_name" line in /home/randall/volumes/d8-staff/config/nginx/default.conf
(This file is mirrored within the container at /etc/nginx/conf.d/default.conf.)
Restart the nginx service.

###### Troubleshooting failed mysql inport

```
If the mysql import failed & you want to delete the whole database data:
you can stop the Stack in Rancher,
delete the folder at /home/randall/volumes/d8-staff/mysql
revise the sqldump at /home/randall/volumes/d8-staff/db_autoimport
restart the stack.
The mysql logs will read "MySQL init process done. Ready for start up." if the database succeeded in importing.

Note:
The d8-mysql folder is the binary files of our d8-staff database.  It is gitignored.
The db_autoimport folder is holds sqldumps.  It is also gitignored.
```

#### Updating the config/theme/module from another computer

Merge those changes from that computer to the intended branch of this repo ('master' for production site, 'staging' for staging site, 'dev' for dev sites)
SSH to /home/randall/volumes/{sitename}/{repo_name}
git pull origin

(include notes for config import, container rebuild, whatevers necessary to effect the change)


## Dev box

1) change the passwords in the file ".env"  [passwords can be unique to each instance]
2) copy d8-staff_sandbox_db.sql to ./db_autoimport/
3) then:

```
docker-compose up -d
docker-compose logs -f
```

   wait until the db container logs say "MySQL init process done. Ready for start up."  Exit log screen with `Ctrl-C`.  Or keep the log screen open & use a second terminal.  Your choice.

Fix the file permissions, import the config changes, and refresh the drupal cache with:

```
docker-compose exec webapp chown -R www-data:www-data /drupal_sync /drupal_app/web/modules /drupal_app/web/themes
docker-compose exec webapp drush config-import -y
docker-compose exec webapp drush cache-rebuild
```

See the app at http://localhost:8111
(the app will error out until the database finishes loading.)

See the phpmyadmin at http://localhost:5112


Stopping the dev box with:

```
ctrl-C
-and/or-
docker-compose stop 
```
#### adding a 3rd party module

```
** add "module_name": "^version" to "require" section of ./drupal8docker/config/drupal/composer.json **
docker-compose down
docker volume rm d8-staff_drupal_data
docker build -t libapps-admin.uncw.edu:8000/randall-dev/d8-staff/drupal8base ./drupal8docker
docker-compose up -d
docker-compose exec webapp chown -R www-data:www-data /drupal_sync /drupal_app/web/modules /drupal_app/web/themes
docker-compose exec webapp drush config-import -y
docker-compose exec webapp drush cache-rebuild
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

Revise the files in ./modules.  It is the home to our custom modules.  The folder syncs to the container's /drupal_app/web/modules

#### sharing drupal config changes

drupal_sync is drupal8's way of sharing drupal config changes amongst developers.
You can also do this through the frontend, but that breaks version control.

The drupal sync files are at ./drupal_sync

#### exporting drupal_sync

`docker-compose exec webapp drush config-export -y`

#### importing drupal_sync:

`docker-composer exec webapp drush config-import -y`

#### rebuilding the drupal cache

`docker-compose exec webapp drush cache-rebuild`

⋅⋅⋅It's a good habit -- resolves most problems.

#### managing the production db:

Reasoning:  We'll want one gold-standard database.  Ultimately, that's the production machine.  Folks adding content can add to the production site & it will go directly to the production database.  The developers need an sqldump of that database for local use.  Maybe we'll keep a "most recent" database dump on some shared drive or somewhere.  Maybe that "most recent" sqldump is slimmed down or has dummy data.  If it's dummy data, we might can git commit it in this repo.  We'll figure that out.  Either way, get a copy the sqldump to ./db_autoimport.

#### wiping the containers and starting clean:

```
docker-compose down
docker volume rm lilting_seahawk_db_data lilting_seahawk_drupal_data
docker-compose up --build
```

#### if you need to export a local database

1) `docker-compose exec webapp drush sql-dump --result-file=/docker-entrypoint-initdb.d/{some filename}.sql`
1) look for the file in ./db_autoimport/

#### some docker commands

docker 

    ps    {show active containers}

        -a      {show all containers}

    volume

        ls      {list}

        rm      {removes specific volume}

                - this will destroy the container's persistent data

docker-compose

    up      {start the containers}

        -d  {in detached mode}
        --build {make the docker image if it doesn't yet exist}

    stop    {halt the containers but keeps them intact}

    down    {halt & remove the containers,
             preserves volumes and images}

    exec (container_name) echo 'hello world' {or other program from inside the container}

    logs     {show logs}

        -f  {follow the log tail}
