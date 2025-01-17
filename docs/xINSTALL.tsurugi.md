# INSTALLATION INSTRUCTIONS
## for Tsurugi Linux
# 0/ Quick MISP Instance on Tsurugi Linux - Status

This has been tested by @SteveClement on 20190408

[Tsurugi can be found here.](https://tsurugi-linux.org/)

# 1/ Prepare Tsurugi with a MISP User
--------------------------------

# openssh-server

It seems there are issues with the **openssh-server** package, thus we need to reconfigure to re-create the keys. (Only if ssh is NOT working)

```bash
sudo update-rc.d -f ssh remove
sudo update-rc.d -f ssh defaults
sudo dpkg-reconfigure openssh-server
sudo systemctl restart ssh
```

If you installed Tsurugi to your disk, the locale is a little all over the place.
We assume **en_US** to be default unless you know what you're doing, go with that default.

```bash
sudo sed -i 's/ja_JP/en_US/g' /etc/default/locale
sudo sed -i 's/ja_JP.UTF/# ja_JP.UTF/g' /etc/locale.gen
sudo dpkg-reconfigure locales
```

To install MISP on Tsurugi copy paste this in your r00t shell:
```bash
wget -O /tmp/misp-tsurugi.sh https://raw.githubusercontent.com/MISP/MISP/2.4/INSTALL/xINSTALL.tsurugi.txt && bash /tmp/misp-tsurugi.sh
```

!!! warning
    Please read the installer script before randomly doing the above.
    The script is tested on a plain vanilla Tsurugi Linux Boot CD and installs quite a few dependencies.

```bash
# <snippet-begin 0_INSTALL-tsurugi.sh>
#!/usr/bin/env bash
#INSTALLATION INSTRUCTIONS
#------------------------- for Tsurugi Linux
#
#0/ Quick MISP Instance on Tsurugi Linux - Status
#---------------------------------------------
#
#1/ Prepare Tsurugi with a MISP User
#--------------------------------
# You will need a working OpenSSH server, reconfigure as follows:
# sudo update-rc.d -f ssh remove
# sudo update-rc.d -f ssh defaults
# sudo dpkg-reconfigure openssh-server
# sudo systemctl restart ssh
# If you installed tsurugi the locale is a little all over the place. I assume en_US to be default unless you know what you're doing.
# sudo sed -i 's/ja_JP/en_US/g' /etc/default/locale
# sudo sed -i 's/ja_JP.UTF/# ja_JP.UTF/g' /etc/locale.gen
# sudo dpkg-reconfigure locales
# To install MISP on Tsurugi copy paste this in your r00t shell:
# wget -O /tmp/misp-tsurugi.sh https://raw.githubusercontent.com/MISP/MISP/2.4/INSTALL/INSTALL.tsurugi.txt && bash /tmp/misp-tsurugi.sh
# /!\ Please read the installer script before randomly doing the above.
# The script is tested on a plain vanilla Tsurugi Linux Boot CD and installs quite a few dependencies.

MISP_USER='misp'
MISP_PASSWORD='Password1234'

function tsurugiOnRootR0ckz() {
  if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root"
   exit 1
  elif [[ $(id $MISP_USER >/dev/null; echo $?) -ne 0 ]]; then
    useradd -s /bin/bash -m -G adm,cdrom,sudo,dip,plugdev,www-data $MISP_USER
    echo $MISP_USER:$MISP_PASSWORD | chpasswd
  else
    echo "User ${MISP_USER} exists, skipping creation"
    adduser $MISP_USER www-data
  fi
}

function installMISPonTsurugi() {
  # MISP configuration variables
  PATH_TO_MISP='/var/www/MISP'
  MISP_BASEURL='https://misp.local'
  MISP_LIVE='1'
  CAKE="${PATH_TO_MISP}/app/Console/cake"

  # Database configuration
  DBHOST='localhost'
  DBNAME='misp'
  DBUSER_ADMIN='root'
  DBPASSWORD_ADMIN="$(openssl rand -hex 32)"
  DBUSER_MISP='misp'
  DBPASSWORD_MISP="$(openssl rand -hex 32)"

  # Webserver configuration
  FQDN='misp.local'

  # OpenSSL configuration
  OPENSSL_CN=$FQDN
  OPENSSL_C='LU'
  OPENSSL_ST='State'
  OPENSSL_L='Location'
  OPENSSL_O='Organization'
  OPENSSL_OU='Organizational Unit'
  OPENSSL_EMAILADDRESS='info@localhost'

  # GPG configuration
  GPG_REAL_NAME='Autogenerated Key'
  GPG_COMMENT='WARNING: MISP AutoGenerated Key consider this Key VOID!'
  GPG_EMAIL_ADDRESS='admin@admin.test'
  GPG_KEY_LENGTH='2048'
  GPG_PASSPHRASE='Password1234'

  # php.ini configuration
  upload_max_filesize=50M
  post_max_size=50M
  max_execution_time=300
  memory_limit=2048M
  session.sid_length=32
  session.use_strict_mode=1

  PHP_INI=/etc/php/7.0/apache2/php.ini

  # apt config
  export DEBIAN_FRONTEND=noninteractive

  # sudo config to run $LUSER commands
  SUDO="sudo -H -u ${MISP_USER}"
  SUDO_WWW="sudo -H -u www-data"

  echo "Admin (${DBUSER_ADMIN}) DB Password: ${DBPASSWORD_ADMIN}"
  echo "User  (${DBUSER_MISP}) DB Password: ${DBPASSWORD_MISP}"

  echo "-----------------------------------------------------------------------"
  echo "Disabling sleep etc…"
  gsettings set org.gnome.settings-daemon.plugins.power sleep-inactive-ac-timeout 0
  gsettings set org.gnome.settings-daemon.plugins.power sleep-inactive-battery-timeout 0
  gsettings set org.gnome.settings-daemon.plugins.power sleep-inactive-battery-type 'nothing'
  xset s 0 0
  xset dpms 0 0
  xset s off

  # Update all expired keys, needed for MongoDB key.
  apt-key list | \
  grep "expired: " | \
  sed -ne 's|pub .*/\([^ ]*\) .*|\1|gp' | \
  xargs -n1 sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys

  apt update
  apt install -qy etckeeper
  # Skip dist-upgrade for now, pulls in 500+ updated packages
  #sudo apt -y dist-upgrade
  git config --global user.email "root@tsurugi.lan"
  git config --global user.name "Root User"
  apt install -qy postfix

  apt install -qy \
  curl gcc git gnupg-agent make openssl redis-server zip libyara-dev python3-yara python3-redis python3-zmq \
  mariadb-client \
  mariadb-server \
  apache2 apache2-doc apache2-utils \
  libapache2-mod-php7.0 php7.0 php7.0-cli  php7.0-mbstring php-pear php7.0-dev php7.0-json php7.0-xml php7.0-mysql php7.0-opcache php7.0-readline php7.0-gd \
  python3-dev python3-pip libpq5 libjpeg-dev libfuzzy-dev ruby asciidoctor \
  libxml2-dev libxslt1-dev zlib1g-dev python3-setuptools expect

  apt install -qy haveged
  systemctl restart haveged

  systemctl restart mysql.service

  a2dismod status
  a2enmod ssl rewrite headers
  a2dissite 000-default
  a2ensite default-ssl

  pecl channel-update pecl.php.net

  yes '' |pecl install redis

  echo "extension=redis.so" | tee /etc/php/7.0/mods-available/redis.ini

  phpenmod redis

  # You can make Python 3 default, if you wish to.
  #update-alternatives --install /usr/bin/python python /usr/bin/python2.7 1
  #update-alternatives --install /usr/bin/python python /usr/bin/python3.5 2

  mkdir ${PATH_TO_MISP}
  chown www-data:www-data ${PATH_TO_MISP}
  cd ${PATH_TO_MISP}
  ${SUDO_WWW} git clone https://github.com/MISP/MISP.git ${PATH_TO_MISP}

  ${SUDO_WWW} git config core.filemode false

  cp -p /etc/lsb-release /etc/lsb-release.tmp
  sudo sed -i 's/TSURUGI/Ubuntu/g' /etc/lsb-release
  sudo sed -i 's/bamboo/xenial/g' /etc/lsb-release
  sudo add-apt-repository ppa:jonathonf/python-3.6 -y
  sudo apt-get update
  sudo apt-get install python3.6 python3.6-dev -y
  mv /etc/lsb-release.tmp /etc/lsb-release
  ${SUDO_WWW} virtualenv -p python3.6 ${PATH_TO_MISP}/venv

  cd ${PATH_TO_MISP}/app/files/scripts
  ${SUDO_WWW} git clone https://github.com/CybOXProject/python-cybox.git
  ${SUDO_WWW} git clone https://github.com/STIXProject/python-stix.git
  ${SUDO_WWW} git clone https://github.com/CybOXProject/mixbox.git

  mkdir /var/www/.cache
  chown www-data:www-data /var/www/.cache

  cd ${PATH_TO_MISP}/app/files/scripts/python-stix
  ${SUDO_WWW} ${PATH_TO_MISP}/venv/bin/pip install .

  cd ${PATH_TO_MISP}/app/files/scripts/python-cybox
  ${SUDO_WWW} ${PATH_TO_MISP}/venv/bin/pip install .

  cd ${PATH_TO_MISP}/app/files/scripts/mixbox
  ${SUDO_WWW} ${PATH_TO_MISP}/venv/bin/pip install .

  cd ${PATH_TO_MISP}
  ${SUDO_WWW} git submodule update --init --recursive
  # Make git ignore filesystem permission differences for submodules
  ${SUDO_WWW} git submodule foreach --recursive git config core.filemode false

  # install PyMISP
  cd ${PATH_TO_MISP}/PyMISP
  ${SUDO_WWW} ${PATH_TO_MISP}/venv/bin/pip install .

  cd ${PATH_TO_MISP}/app
  mkdir /var/www/.composer ; chown www-data:www-data /var/www/.composer
  ${SUDO_WWW} php composer.phar install --no-dev

  ${SUDO_WWW} cp -fa ${PATH_TO_MISP}/INSTALL/setup/config.php ${PATH_TO_MISP}/app/Plugin/CakeResque/Config/config.php

  chown -R www-data:www-data ${PATH_TO_MISP}
  chmod -R 750 ${PATH_TO_MISP}
  chmod -R g+ws ${PATH_TO_MISP}/app/tmp
  chmod -R g+ws ${PATH_TO_MISP}/app/files
  chmod -R g+ws ${PATH_TO_MISP}/app/files/scripts/tmp

  if [ ! -e /var/lib/mysql/misp/users.ibd ]; then
    echo "
      set timeout 10
      spawn mysql_secure_installation
      expect \"Enter current password for root (enter for none):\"
      send -- \"\r\"
      expect \"Set root password?\"
      send -- \"y\r\"
      expect \"New password:\"
      send -- \"${DBPASSWORD_ADMIN}\r\"
      expect \"Re-enter new password:\"
      send -- \"${DBPASSWORD_ADMIN}\r\"
      expect \"Remove anonymous users?\"
      send -- \"y\r\"
      expect \"Disallow root login remotely?\"
      send -- \"y\r\"
      expect \"Remove test database and access to it?\"
      send -- \"y\r\"
      expect \"Reload privilege tables now?\"
      send -- \"y\r\"
      expect eof" | expect -f -

    mysql -u $DBUSER_ADMIN -p$DBPASSWORD_ADMIN -e "create database $DBNAME;"
    mysql -u $DBUSER_ADMIN -p$DBPASSWORD_ADMIN -e "grant usage on *.* to $DBNAME@localhost identified by '$DBPASSWORD_MISP';"
    mysql -u $DBUSER_ADMIN -p$DBPASSWORD_ADMIN -e "grant all privileges on $DBNAME.* to '$DBUSER_MISP'@'localhost';"
    mysql -u $DBUSER_ADMIN -p$DBPASSWORD_ADMIN -e "flush privileges;"

    update-rc.d mysql enable
    update-rc.d apache2 enable
    update-rc.d redis-server enable

    ${SUDO_WWW} cat ${PATH_TO_MISP}/INSTALL/MYSQL.sql | mysql -u $DBUSER_MISP -p$DBPASSWORD_MISP $DBNAME

    echo "<?php
  class DATABASE_CONFIG {
          public \$default = array(
                  'datasource' => 'Database/Mysql',
                  //'datasource' => 'Database/Postgres',
                  'persistent' => false,
                  'host' => '$DBHOST',
                  'login' => '$DBUSER_MISP',
                  'port' => 3306, // MySQL & MariaDB
                  //'port' => 5432, // PostgreSQL
                  'password' => '$DBPASSWORD_MISP',
                  'database' => '$DBNAME',
                  'prefix' => '',
                  'encoding' => 'utf8',
          );
  }" | ${SUDO_WWW} tee ${PATH_TO_MISP}/app/Config/database.php
  else
    echo "There might be a database already existing here: /var/lib/mysql/misp/users.ibd"
    echo "Skipping any creations…"
    sleep 3
  fi

  openssl req -newkey rsa:4096 -days 365 -nodes -x509 \
  -subj "/C=${OPENSSL_C}/ST=${OPENSSL_ST}/L=${OPENSSL_L}/O=${OPENSSL_O}/OU=${OPENSSL_OU}/CN=${OPENSSL_CN}/emailAddress=${OPENSSL_EMAILADDRESS}" \
  -keyout /etc/ssl/private/misp.local.key -out /etc/ssl/private/misp.local.crt

  if [ ! -e /etc/rc.local ]
  then
      echo '#!/bin/sh -e' | tee -a /etc/rc.local
      echo 'exit 0' | tee -a /etc/rc.local
      chmod u+x /etc/rc.local
  fi

  cd /var/www
  mkdir misp-dashboard
  chown www-data:www-data misp-dashboard
  ${SUDO_WWW} git clone https://github.com/MISP/misp-dashboard.git
  cd misp-dashboard
  ${SUDO_WWW} ${PATH_TO_MISP}/venv/bin/pip install zmq redis
  /var/www/misp-dashboard/install_dependencies.sh
  sed -i "s/^host\ =\ localhost/host\ =\ 0.0.0.0/g" /var/www/misp-dashboard/config/config.cfg
  sed -i -e '$i \sudo -u www-data bash /var/www/misp-dashboard/start_all.sh\n' /etc/rc.local
  sed -i -e '$i \sudo -u misp /usr/local/src/viper/viper-web -p 8888 -H 0.0.0.0 &\n' /etc/rc.local
  sed -i -e '$i \git_dirs="/usr/local/src/misp-modules/ /var/www/misp-dashboard /usr/local/src/faup /usr/local/src/mail_to_misp /usr/local/src/misp-modules /usr/local/src/viper /var/www/misp-dashboard"\n' /etc/rc.local
  sed -i -e '$i \for d in $git_dirs; do\n' /etc/rc.local
  sed -i -e '$i \    echo "Updating ${d}"\n' /etc/rc.local
  sed -i -e '$i \    cd $d && sudo git pull &\n' /etc/rc.local
  sed -i -e '$i \done\n' /etc/rc.local
  ${SUDO_WWW} bash /var/www/misp-dashboard/start_all.sh

  apt install libapache2-mod-wsgi-py3 -y

  echo "<VirtualHost _default_:80>
          ServerAdmin admin@localhost.lu
          ServerName misp.local

          Redirect permanent / https://misp.local

          LogLevel warn
          ErrorLog /var/log/apache2/misp.local_error.log
          CustomLog /var/log/apache2/misp.local_access.log combined
          ServerSignature Off
  </VirtualHost>

  <VirtualHost _default_:443>
          ServerAdmin admin@localhost.lu
          ServerName misp.local
          DocumentRoot ${PATH_TO_MISP}/app/webroot

          <Directory ${PATH_TO_MISP}/app/webroot>
                  Options -Indexes
                  AllowOverride all
  		            Require all granted
                  Order allow,deny
                  allow from all
          </Directory>

          SSLEngine On
          SSLCertificateFile /etc/ssl/private/misp.local.crt
          SSLCertificateKeyFile /etc/ssl/private/misp.local.key
  #        SSLCertificateChainFile /etc/ssl/private/misp-chain.crt

          LogLevel warn
          ErrorLog /var/log/apache2/misp.local_error.log
          CustomLog /var/log/apache2/misp.local_access.log combined
          ServerSignature Off
          Header set X-Content-Type-Options nosniff
          Header set X-Frame-Options DENY
  </VirtualHost>" | tee /etc/apache2/sites-available/misp-ssl.conf

  echo "127.0.0.1 misp.local" | tee -a /etc/hosts

  echo "<VirtualHost *:8001>
      ServerAdmin admin@misp.local
      ServerName misp.local

      DocumentRoot /var/www/misp-dashboard

      WSGIDaemonProcess misp-dashboard \
         user=misp group=misp \
         python-home=/var/www/misp-dashboard/DASHENV \
         processes=1 \
         threads=15 \
         maximum-requests=5000 \
         listen-backlog=100 \
         queue-timeout=45 \
         socket-timeout=60 \
         connect-timeout=15 \
         request-timeout=60 \
         inactivity-timeout=0 \
         deadlock-timeout=60 \
         graceful-timeout=15 \
         shutdown-timeout=5 \
         send-buffer-size=0 \
         receive-buffer-size=0 \
         header-buffer-size=0

      WSGIScriptAlias / /var/www/misp-dashboard/misp-dashboard.wsgi

      <Directory /var/www/misp-dashboard>
          WSGIProcessGroup misp-dashboard
          WSGIApplicationGroup %{GLOBAL}
          Require all granted
      </Directory>

      LogLevel info
      ErrorLog /var/log/apache2/misp-dashboard.local_error.log
      CustomLog /var/log/apache2/misp-dashboard.local_access.log combined
      ServerSignature Off
  </VirtualHost>" | tee /etc/apache2/sites-available/misp-dashboard.conf

  a2dissite default-ssl
  a2ensite misp-ssl
  a2ensite misp-dashboard

  for key in upload_max_filesize post_max_size max_execution_time max_input_time memory_limit
  do
      sed -i "s/^\($key\).*/\1 = $(eval echo \${$key})/" $PHP_INI
  done
  sudo sed -i "s/^\(session.sid_length\).*/\1 = $(eval echo \${session0sid_length})/" $PHP_INI
  sudo sed -i "s/^\(session.use_strict_mode\).*/\1 = $(eval echo \${session0use_strict_mode})/" $PHP_INI

  systemctl restart apache2

  cp ${PATH_TO_MISP}/INSTALL/misp.logrotate /etc/logrotate.d/misp
  chmod 0640 /etc/logrotate.d/misp

  ${SUDO_WWW} cp -a ${PATH_TO_MISP}/app/Config/bootstrap.default.php ${PATH_TO_MISP}/app/Config/bootstrap.php
  ${SUDO_WWW} cp -a ${PATH_TO_MISP}/app/Config/core.default.php ${PATH_TO_MISP}/app/Config/core.php
  ${SUDO_WWW} cp -a ${PATH_TO_MISP}/app/Config/config.default.php ${PATH_TO_MISP}/app/Config/config.php

  chown -R www-data:www-data ${PATH_TO_MISP}/app/Config
  chmod -R 750 ${PATH_TO_MISP}/app/Config
  $CAKE Live $MISP_LIVE
  $CAKE Baseurl $MISP_BASEURL

  echo "%echo Generating a default key
      Key-Type: 1
      Key-Length: $GPG_KEY_LENGTH
      Subkey-Type: 1
      Name-Real: $GPG_REAL_NAME
      Name-Comment: $GPG_COMMENT
      Name-Email: $GPG_EMAIL_ADDRESS
      Expire-Date: 0
      Passphrase: $GPG_PASSPHRASE
      # Do a commit here, so that we can later print "done"
      %commit
  %echo done" > /tmp/gen-key-script

  ${SUDO_WWW} gpg --homedir ${PATH_TO_MISP}/.gnupg --batch --gen-key /tmp/gen-key-script

  ${SUDO_WWW} sh -c "gpg --homedir ${PATH_TO_MISP}/.gnupg --export --armor $GPG_EMAIL_ADDRESS" | ${SUDO_WWW} tee ${PATH_TO_MISP}/app/webroot/gpg.asc

  chmod +x ${PATH_TO_MISP}/app/Console/worker/start.sh

  $CAKE userInit -q
  $CAKE Admin updateDatabase

  AUTH_KEY=$(mysql -u $DBUSER_MISP -p$DBPASSWORD_MISP misp -e "SELECT authkey FROM users;" | tail -1)

  $CAKE Admin setSetting "MISP.python_bin" "${PATH_TO_MISP}/venv/bin/python"
  $CAKE Admin setSetting "Plugin.ZeroMQ_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_event_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_object_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_object_reference_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_attribute_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_sighting_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_user_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_organisation_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_port" 50000
  $CAKE Admin setSetting "Plugin.ZeroMQ_redis_host" "localhost"
  $CAKE Admin setSetting "Plugin.ZeroMQ_redis_port" 6379
  $CAKE Admin setSetting "Plugin.ZeroMQ_redis_database" 1
  $CAKE Admin setSetting "Plugin.ZeroMQ_redis_namespace" "mispq"
  $CAKE Admin setSetting "Plugin.ZeroMQ_include_attachments" false
  $CAKE Admin setSetting "Plugin.ZeroMQ_tag_notifications_enable" false
  $CAKE Admin setSetting "Plugin.ZeroMQ_audit_notifications_enable" false
  $CAKE Admin setSetting "GnuPG.email" "admin@admin.test"
  $CAKE Admin setSetting "GnuPG.homedir" "/var/www/MISP/.gnupg"
  $CAKE Admin setSetting "GnuPG.password" "Password1234"
  $CAKE Admin setSetting "MISP.host_org_id" 1
  $CAKE Admin setSetting "MISP.email" "info@admin.test"
  $CAKE Admin setSetting "MISP.disable_emailing" false
  $CAKE Admin setSetting "MISP.contact" "info@admin.test"
  $CAKE Admin setSetting "MISP.disablerestalert" true
  $CAKE Admin setSetting "MISP.showCorrelationsOnIndex" true
  $CAKE Admin setSetting "Plugin.Cortex_services_enable" false
  $CAKE Admin setSetting "Plugin.Cortex_services_url" "http://127.0.0.1"
  $CAKE Admin setSetting "Plugin.Cortex_services_port" 9000
  $CAKE Admin setSetting "Plugin.Cortex_timeout" 120
  $CAKE Admin setSetting "Plugin.Cortex_services_url" "http://127.0.0.1"
  $CAKE Admin setSetting "Plugin.Cortex_services_port" 9000
  $CAKE Admin setSetting "Plugin.Cortex_services_timeout" 120
  $CAKE Admin setSetting "Plugin.Cortex_services_authkey" ""
  $CAKE Admin setSetting "Plugin.Cortex_ssl_verify_peer" false
  $CAKE Admin setSetting "Plugin.Cortex_ssl_verify_host" false
  $CAKE Admin setSetting "Plugin.Cortex_ssl_allow_self_signed" true
  $CAKE Admin setSetting "Plugin.Sightings_policy" 0
  $CAKE Admin setSetting "Plugin.Sightings_anonymise" false
  $CAKE Admin setSetting "Plugin.Sightings_range" 365
  $CAKE Admin setSetting "Plugin.CustomAuth_disable_logout" false
  $CAKE Admin setSetting "Plugin.RPZ_policy" "DROP"
  $CAKE Admin setSetting "Plugin.RPZ_walled_garden" "127.0.0.1"
  $CAKE Admin setSetting "Plugin.RPZ_serial" "\$date00"
  $CAKE Admin setSetting "Plugin.RPZ_refresh" "2h"
  $CAKE Admin setSetting "Plugin.RPZ_retry" "30m"
  $CAKE Admin setSetting "Plugin.RPZ_expiry" "30d"
  $CAKE Admin setSetting "Plugin.RPZ_minimum_ttl" "1h"
  $CAKE Admin setSetting "Plugin.RPZ_ttl" "1w"
  $CAKE Admin setSetting "Plugin.RPZ_ns" "localhost."
  $CAKE Admin setSetting "Plugin.RPZ_ns_alt" ""
  $CAKE Admin setSetting "Plugin.RPZ_email" "root.localhost"
  $CAKE Admin setSetting "MISP.language" "eng"
  $CAKE Admin setSetting "MISP.proposals_block_attributes" false
  $CAKE Admin setSetting "MISP.redis_host" "127.0.0.1"
  $CAKE Admin setSetting "MISP.redis_port" 6379
  $CAKE Admin setSetting "MISP.redis_database" 13
  $CAKE Admin setSetting "MISP.redis_password" ""
  $CAKE Admin setSetting "MISP.ssdeep_correlation_threshold" 40
  $CAKE Admin setSetting "MISP.extended_alert_subject" false
  $CAKE Admin setSetting "MISP.default_event_threat_level" 4
  $CAKE Admin setSetting "MISP.newUserText" "Dear new MISP user,\\n\\nWe would hereby like to welcome you to the \$org MISP community.\\n\\n Use the credentials below to log into MISP at \$misp, where you will be prompted to manually change your password to something of your own choice.\\n\\nUsername: \$username\\nPassword: \$password\\n\\nIf you have any questions, don't hesitate to contact us at: \$contact.\\n\\nBest regards,\\nYour \$org MISP support team"
  $CAKE Admin setSetting "MISP.passwordResetText" "Dear MISP user,\\n\\nA password reset has been triggered for your account. Use the below provided temporary password to log into MISP at \$misp, where you will be prompted to manually change your password to something of your own choice.\\n\\nUsername: \$username\\nYour temporary password: \$password\\n\\nIf you have any questions, don't hesitate to contact us at: \$contact.\\n\\nBest regards,\\nYour \$org MISP support team"
  $CAKE Admin setSetting "MISP.enableEventBlacklisting" true
  $CAKE Admin setSetting "MISP.enableOrgBlacklisting" true
  $CAKE Admin setSetting "MISP.log_client_ip" false
  $CAKE Admin setSetting "MISP.log_auth" false
  $CAKE Admin setSetting "MISP.disableUserSelfManagement" false
  $CAKE Admin setSetting "MISP.block_event_alert" false
  $CAKE Admin setSetting "MISP.block_event_alert_tag" "no-alerts=\"true\""
  $CAKE Admin setSetting "MISP.block_old_event_alert" false
  $CAKE Admin setSetting "MISP.block_old_event_alert_age" ""
  $CAKE Admin setSetting "MISP.incoming_tags_disabled_by_default" false
  $CAKE Admin setSetting "MISP.footermidleft" "This is an autogenerated install"
  $CAKE Admin setSetting "MISP.footermidright" "Please configure accordingly and do not use in production"
  $CAKE Admin setSetting "MISP.welcome_text_top" "Autogenerated install, please configure and harden accordingly"
  $CAKE Admin setSetting "MISP.welcome_text_bottom" "Welcome to MISP on Tsurugi"
  $CAKE Admin setSetting "Security.password_policy_length" 12
  $CAKE Admin setSetting "Security.password_policy_complexity" '/^((?=.*\d)|(?=.*\W+))(?![\n])(?=.*[A-Z])(?=.*[a-z]).*$|.{16,}/'
  $CAKE Admin setSetting "Session.autoRegenerate" 0
  $CAKE Admin setSetting "Session.timeout" 600
  $CAKE Admin setSetting "Session.cookie_timeout" 3600
  $CAKE Live $MISP_LIVE
  $CAKE Admin updateGalaxies
  $CAKE Admin updateTaxonomies
  $CAKE Admin updateWarningLists
  $CAKE Admin updateNoticeLists
  $CAKE Admin updateObjectTemplates "31337"
  sed -i -e '$i \echo never > /sys/kernel/mm/transparent_hugepage/enabled\n' /etc/rc.local
  sed -i -e '$i \echo 1024 > /proc/sys/net/core/somaxconn\n' /etc/rc.local
  sed -i -e '$i \sysctl vm.overcommit_memory=1\n' /etc/rc.local
  sed -i -e '$i \sudo -u www-data bash /var/www/MISP/app/Console/worker/start.sh\n' /etc/rc.local
  sed -i -e '$i \sudo -u www-data /var/www/MISP/venv/bin/misp-modules -l 127.0.0.1 -s > /tmp/misp-modules_rc.local.log 2> /dev/null &\n' /etc/rc.local
  ${SUDO_WWW} bash ${PATH_TO_MISP}/app/Console/worker/start.sh
  cd /usr/local/src/
  git clone https://github.com/MISP/misp-modules.git
  cd misp-modules
  # pip3 install
  chown www-data .
  apt install libpq5 libjpeg-dev tesseract-ocr libpoppler-cpp-dev imagemagick libopencv-dev zbar-tools libzbar0 libzbar-dev libfuzzy-dev -y

  ${SUDO_WWW} ${PATH_TO_MISP}/venv/bin/pip install -I -r REQUIREMENTS
  ${SUDO_WWW} ${PATH_TO_MISP}/venv/bin/pip install -I .
  ${SUDO_WWW} ${PATH_TO_MISP}/venv/bin/pip install maec python-magic wand lief yara-python plyara
  ${SUDO_WWW} ${PATH_TO_MISP}/venv/bin/pip install git+https://github.com/kbandla/pydeep.git
  ${SUDO_WWW} ${PATH_TO_MISP}/venv/bin/pip install stix2
  gem install pygments.rb
  gem install asciidoctor-pdf --pre
  ${SUDO_WWW} ${PATH_TO_MISP}/venv/bin/misp-modules -l 127.0.0.1 -s &
  $CAKE Admin setSetting "Plugin.Enrichment_services_enable" true
  $CAKE Admin setSetting "Plugin.Enrichment_hover_enable" true
  $CAKE Admin setSetting "Plugin.Enrichment_timeout" 300
  $CAKE Admin setSetting "Plugin.Enrichment_hover_timeout" 150
  $CAKE Admin setSetting "Plugin.Enrichment_cve_enabled" true
  $CAKE Admin setSetting "Plugin.Enrichment_dns_enabled" true
  $CAKE Admin setSetting "Plugin.Enrichment_services_url" "http://127.0.0.1"
  $CAKE Admin setSetting "Plugin.Enrichment_services_port" 6666
  $CAKE Admin setSetting "Plugin.Import_services_enable" true
  $CAKE Admin setSetting "Plugin.Import_services_url" "http://127.0.0.1"
  $CAKE Admin setSetting "Plugin.Import_services_port" 6666
  $CAKE Admin setSetting "Plugin.Import_timeout" 300
  $CAKE Admin setSetting "Plugin.Import_ocr_enabled" true
  $CAKE Admin setSetting "Plugin.Import_csvimport_enabled" true
  $CAKE Admin setSetting "Plugin.Export_services_enable" true
  $CAKE Admin setSetting "Plugin.Export_services_url" "http://127.0.0.1"
  $CAKE Admin setSetting "Plugin.Export_services_port" 6666
  $CAKE Admin setSetting "Plugin.Export_timeout" 300
  $CAKE Admin setSetting "Plugin.Export_pdfexport_enabled" true
  cd /usr/local/src/
  apt-get install -y libssl-dev swig python3-ssdeep p7zip-full unrar-free sqlite python3-pyclamd exiftool radare2
  git clone https://github.com/viper-framework/viper.git
  chown -R $MISP_USER:$MISP_USER viper
  cd viper
  virtualenv -p python3.6 venv
  $SUDO git submodule update --init --recursive
  sed -i 's/yara-python==3.7.0//g' requirements-modules.txt
  ./venv/bin/pip install scrapy
  ./venv/bin/pip install -r requirements.txt
  sed -i '1 s/^.*$/\#!\/usr\/local\/src\/viper\/venv\/bin\/python/' viper-cli
  sed -i '1 s/^.*$/\#!\/usr\/local\/src\/viper\/venv\/bin\/python/' viper-web
  $SUDO /usr/local/src/viper/viper-cli -h > /dev/null
  $SUDO /usr/local/src/viper/viper-web -p 8888 -H 0.0.0.0 &
  echo 'PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games:/usr/local/src/viper:/var/www/MISP/app/Console"' |tee -a /etc/environment
  echo ". /etc/environment" >> /home/${MISP_USER}/.profile
  $SUDO sed -i "s/^misp_url\ =/misp_url\ =\ http:\/\/localhost/g" /home/${MISP_USER}/.viper/viper.conf
  $SUDO sed -i "s/^misp_key\ =/misp_key\ =\ $AUTH_KEY/g" /home/${MISP_USER}/.viper/viper.conf

  while [ "$(sqlite3 /home/${MISP_USER}/.viper/admin.db 'UPDATE auth_user SET password="pbkdf2_sha256$100000$iXgEJh8hz7Cf$vfdDAwLX8tko1t0M1TLTtGlxERkNnltUnMhbv56wK/U="'; echo $?)" -ne "0" ]; do
    # FIXME This might lead to a race condition, the while loop is sub-par
   chown $MISP_USER:$MISP_USER /home/${MISP_USER}/.viper/admin.db
    echo "Updating viper-web admin password, giving process time to start-up, sleeping 5, 4, 3,…"
    sleep 6
  done

  chown -R www-data:www-data ${PATH_TO_MISP}
  chmod -R 750 ${PATH_TO_MISP}
  chmod -R g+ws ${PATH_TO_MISP}/app/tmp
  chmod -R g+ws ${PATH_TO_MISP}/app/files
  chmod -R g+ws ${PATH_TO_MISP}/app/files/scripts/tmp

  cd /usr/local/src/

  apt-get install cmake libcaca-dev liblua5.3-dev -y
  git clone https://github.com/MISP/mail_to_misp.git
  git clone https://github.com/stricaud/faup.git faup
  git clone https://github.com/stricaud/gtcaca.git gtcaca
  chown -R ${MISP_USER}:${MISP_USER} faup mail_to_misp gtcaca
  cd gtcaca
  $SUDO_CMD mkdir -p build
  cd build
  $SUDO_CMD cmake .. && $SUDO_CMD make
  sudo make install
  cd ../../faup
  $SUDO_CMD mkdir -p build
  cd build
  $SUDO_CMD cmake .. && $SUDO_CMD make
  sudo make install
  sudo ldconfig
  cd ../../mail_to_misp
  $SUDO_CMD virtualenv -p python3 venv
  $SUDO_CMD ./venv/bin/pip install lief
  $SUDO_CMD ./venv/bin/pip install -r requirements.txt
  $SUDO_CMD cp mail_to_misp_config.py-example mail_to_misp_config.py
  ##$SUDO cp mail_to_misp_config.py-example mail_to_misp_config.py
  $SUDO_CMD sed -i "s/^misp_url\ =\ 'YOUR_MISP_URL'/misp_url\ =\ 'https:\/\/localhost'/g" /usr/local/src/mail_to_misp/mail_to_misp_config.py
  $SUDO_CMD sed -i "s/^misp_key\ =\ 'YOUR_KEY_HERE'/misp_key\ =\ '${AUTH_KEY}'/g" /usr/local/src/mail_to_misp/mail_to_misp_config.py
  echo ""
  echo "Admin (root) DB Password: $DBPASSWORD_ADMIN" > /home/${MISP_USER}/mysql.txt
  echo "User  (misp) DB Password: $DBPASSWORD_MISP" >> /home/${MISP_USER}/mysql.txt
  echo "Authkey: $AUTH_KEY" > /home/${MISP_USER}/MISP-authkey.txt

  clear
  echo "-------------------------------------------------------------------------"
  echo "MISP Installed, access here: https://misp.local"
  echo "User: admin@admin.test"
  echo "Password: admin"
  echo "MISP Dashboard, access here: http://misp.local:8001"
  echo "-------------------------------------------------------------------------"
  cat /home/${MISP_USER}/mysql.txt
  cat /home/${MISP_USER}/MISP-authkey.txt
  echo "-------------------------------------------------------------------------"
  echo "The LOCAL system credentials:"
  echo "User: ${MISP_USER}"
  echo "Password: ${MISP_PASSWORD}"
  echo "-------------------------------------------------------------------------"
  echo "viper-web installed, access here: http://misp.local:8888"
  echo "viper-cli configured with your MISP Site Admin Auth Key"
  echo "User: admin"
  echo "Password: Password1234"
  echo "-------------------------------------------------------------------------"
  echo "To enable outgoing mails via postfix set a permissive SMTP server for the domains you want to contact:"
  echo ""
  echo "sudo postconf -e 'relayhost = example.com'"
  echo "sudo postfix reload"
  echo "-------------------------------------------------------------------------"
  echo "Enjoy using MISP. For any issues see here: https://github.com/MISP/MISP/issues"
  su - misp
}

tsurugiOnRootR0ckz
installMISPonTsurugi
# <snippet-end 0_INSTALL-tsurugi.sh>
