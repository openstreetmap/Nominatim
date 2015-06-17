#!/bin/bash


## During 'vagrant provision' this script runs as root and the current
## directory is '/root'
USERNAME=vagrant

### 
### maybe create ubuntu user
### 

# if [[ ! `id -u $USERNAME` ]]; then
#   useradd $USERNAME --create-home --shell /bin/bash
#   
#   # give sudo power
#   echo "$USERNAME ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/99-$USERNAME-user
#   chmod 0440 /etc/sudoers.d/99-$USERNAME-user
#   service sudo restart
#   
#   # add basic .profile
#   cp -r .ssh .profile .bashrc /home/$USERNAME/
#   chown -R $USERNAME /home/$USERNAME/.*
#   chgrp -R $USERNAME /home/$USERNAME/.*
#   
#   # now ideally login as $USERNAME and continue
#   su $USERNAME -l
# fi


sudo apt-get update -qq
sudo apt-get upgrade -y
# sudo apt-get install -y git-core screen
sudo apt-get install -y build-essential libxml2-dev libgeos-dev libpq-dev libbz2-dev \
                        libtool automake libproj-dev libboost-dev  libboost-system-dev \
                        libboost-filesystem-dev libboost-thread-dev
sudo apt-get autoremove -y

# get arrow-keys working in terminal (e.g. editing in vi)
echo 'stty sane' >> ~/.bash_profile
echo 'export TERM=linux' >> ~/.bash_profile
source ~/.bash_profile


###
### PostgreSQL 9.3 + PostGIS 2.1
###

sudo apt-get install -y postgresql-9.3-postgis-2.1 postgresql-contrib-9.3 postgresql-server-dev-9.3 
# already included: proj-bin libgeos-dev

# make sure OS-authenticated users (e.g. $USERNAME) can access
sudo sed -i "s/ident/trust/" /etc/postgresql/9.3/main/pg_hba.conf
sudo sed -i "s/md5/trust/"   /etc/postgresql/9.3/main/pg_hba.conf
sudo sed -i "s/peer/trust/"  /etc/postgresql/9.3/main/pg_hba.conf
sudo /etc/init.d/postgresql restart

# creates the role
sudo -u postgres createuser -s $USERNAME



###
### PHP for frontend
###
sudo apt-get install -y php5 php5-pgsql php-pear 
sudo pear install DB


# get rid of some warning
# where is the ini file? 'php --ini'
echo "date.timezone = 'Etc/UTC'" | sudo tee /etc/php5/cli/conf.d/99-timezone.ini > /dev/null



###
### Nominatim
###
sudo apt-get install -y libprotobuf-c0-dev protobuf-c-compiler \
                        libgeos-c1 libgeos++-dev \
                        lua5.2 liblua5.2-dev

# git clone --recursive https://github.com/twain47/Nominatim.git


# now ideally login as $USERNAME and continue
su $USERNAME -l
pwd
ls -la /home/vagrant
cd /home/vagrant/Nominatim

# cd ~/Nominatim
./autogen.sh
./configure
make
chmod +x ./
chmod +x ./module


# IP=`curl -s http://bot.whatismyipaddress.com`
IP=localhost
echo "<?php
   // General settings
   @define('CONST_Database_DSN', 'pgsql://@/nominatim');
   // Paths
   @define('CONST_Postgresql_Version', '9.3');
   @define('CONST_Postgis_Version', '2.1');
   // Website settings
   @define('CONST_Website_BaseURL', 'http://$IP:8089/nominatim/');
" > settings/local.php







###
### Setup Apache/website
###

createuser -SDR www-data

echo '
Listen 8089
<VirtualHost *:8089>
    # DirectoryIndex index.html
    # ErrorDocument 403 /index.html

    DocumentRoot "/var/www/"
 
    <Directory "/var/www/nominatim/">
      Options FollowSymLinks MultiViews
      AddType text/html   .php     
    </Directory>
</VirtualHost>
' | sudo tee /etc/apache2/sites-enabled/nominatim.conf > /dev/null


sudo apache2ctl graceful


sudo mkdir -m 755 /var/www/nominatim
sudo chown $USERNAME /var/www/nominatim
./utils/setup.php --threads 1 --create-website /var/www/nominatim


# if you get 'permission denied for relation word', then try
# GRANT usage ON SCHEMA public TO "www-data";
# GRANT SELECT ON ALL TABLES IN SCHEMA public TO "www-data";

##
## Test suite (Python)
## https://github.com/twain47/Nominatim/tree/master/tests
##
sudo apt-get install -y python-dev python-pip python-Levenshtein tidy
sudo pip install lettuce nose pytidylib haversine psycopg2 shapely

##
## Test suite (PHP)
## https://github.com/twain47/Nominatim/tree/master/tests-php
##
wget --no-clobber -q https://phar.phpunit.de/phpunit.phar
chmod +x phpunit.phar
sudo mv phpunit.phar /usr/local/bin/phpunit


