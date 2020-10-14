#!/bin/bash

# Install and configure WordPress if we haven't already
main() {
  BOLD=$(tput bold)
  NORMAL=$(tput sgr0)

  WP_DIR="$LANDO_MOUNT/wp"

  if ! [[ -d "$WP_DIR"/wp-content/plugins/greg ]] ; then
    echo 'Linking greg plugin directory...'
    ln -s "../../../" "$WP_DIR"/wp-content/plugins/greg
  fi

  echo 'Checking for WordPress config...'
  if wp_configured ; then
    echo 'WordPress is configured'
  else
    read -d '' extra_php <<'EOF'
// log all notices, warnings, etc.
error_reporting(E_ALL);

// enable debug logging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
EOF

    # create a wp-config.php
    wp config create \
      --dbname="wordpress" \
      --dbuser="wordpress" \
      --dbpass="wordpress" \
      --dbhost="database" \
      --extra-php < <(echo "$extra_php")
  fi

  echo 'Checking for WordPress installation...'
  if wp_installed ; then
    echo 'WordPress is installed'
  else
    # install WordPress
    wp core install \
      --url='https://greg.lndo.site' \
      --title='Greg' \
      --admin_user='admin' \
      --admin_password='admin' \
      --admin_email='web@sitecrafting.com' \
      --skip-email
  fi

  wp option update blogdescription "A de-coupled calendar solution for WordPress and Timber"

  # configure plugins and theme
  uninstall_plugins hello akismet
  wp --quiet plugin activate greg

  wp option set permalink_structure '/%postname%/'
  wp rewrite flush

  echo
  echo 'Done setting up!'
  echo
  echo 'Your WP username is: admin'
  echo 'Your password is: admin'
  echo

}


# Detect whether WP has been configured already
wp_configured() {
  [[ $(wp config path 2>/dev/null) ]] && return
  false
}

# Detect whether WP is installed
wp_installed() {
  wp --quiet core is-installed
  [[ $? = '0' ]] && return
  false
}

uninstall_plugins() {
  for plugin in $1 ; do
    wp plugin is-installed $plugin 2>/dev/null
    if [[ "$?" = "0" ]] ; then
      wp plugin uninstall $plugin
    fi
  done
}


main
