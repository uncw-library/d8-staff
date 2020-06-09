### Build new drupal8 base image

`cd drupal8docker`

##### create the image on your computer

`docker build -t {path to your registry}/drupal8:{some tag} .`

##### push the image to the repo

`docker push {path to your registry}/drupal8:{some tag}`
