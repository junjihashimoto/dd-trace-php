#!/bin/bash
set -eux

# We can't match for "shared" only, as zend_test is built shared. Match something explicitly.
SHARED_BUILD=$(if php -i | grep -q enable-pcntl=shared; then echo 1; else echo 0; fi)
PHP_VERSION_ID=$(php -r 'echo PHP_MAJOR_VERSION . PHP_MINOR_VERSION;')
PHP_ZTS=$(php -r 'echo PHP_ZTS;')

XDEBUG_VERSIONS=(-3.1.2)
if [[ $PHP_VERSION_ID -le 70 ]]; then
  XDEBUG_VERSIONS=(-2.7.2)
elif [[ $PHP_VERSION_ID -le 74 ]]; then
  XDEBUG_VERSIONS=(-2.9.2 -2.9.5)
elif [[ $PHP_VERSION_ID -le 80 ]]; then
  XDEBUG_VERSIONS=(-3.0.0)
elif [[ $PHP_VERSION_ID -le 81 ]]; then
  XDEBUG_VERSIONS=(-3.1.0)
elif [[ $PHP_VERSION_ID -le 82 ]]; then
  XDEBUG_VERSIONS=(-3.2.2)
elif [[ $PHP_VERSION_ID -le 83 ]]; then
  XDEBUG_VERSIONS=(-3.3.2)
else
  XDEBUG_VERSIONS=(-3.4.0)
fi

MONGODB_VERSION=
if [[ $PHP_VERSION_ID -le 70 ]]; then
  MONGODB_VERSION=-1.9.2
elif [[ $PHP_VERSION_ID -le 71 ]]; then
  MONGODB_VERSION=-1.11.1
elif [[ $PHP_VERSION_ID -le 73 ]]; then
  MONGODB_VERSION=-1.16.2
fi

AST_VERSION=
if [[ $PHP_VERSION_ID -le 71 ]]; then
  AST_VERSION=-1.0.16
fi

MEMCACHE_VERSION=
if [[ $PHP_VERSION_ID -le 74 ]]; then
  MEMCACHE_VERSION=-4.0.5.2
fi

SQLSRV_VERSION=
if [[ $PHP_VERSION_ID -le 70 ]]; then
  SQLSRV_VERSION=-5.3.0
elif [[ $PHP_VERSION_ID -le 71 ]]; then
  SQLSRV_VERSION=-5.6.1
elif [[ $PHP_VERSION_ID -le 74 ]]; then
  SQLSRV_VERSION=-5.8.0
elif [[ $PHP_VERSION_ID -le 80 ]]; then
  SQLSRV_VERSION=-5.11.0
fi

HOST_ARCH=$(if [[ $(file $(readlink -f $(which php))) == *aarch64* ]]; then echo "aarch64"; else echo "x86_64"; fi)

export PKG_CONFIG=/usr/bin/$HOST_ARCH-linux-gnu-pkg-config
export CC=$HOST_ARCH-linux-gnu-gcc
export CXX=$HOST_ARCH-linux-gnu-g++

iniDir=$(php -i | awk -F"=> " '/Scan this dir for additional .ini files/ {print $2}');

if [[ $SHARED_BUILD -ne 0 ]]; then
  # Build curl versions
  CURL_VERSIONS="7.72.0 7.77.0"
  for curlVer in ${CURL_VERSIONS}; do
    echo "Build curl ${curlVer}..."
    cd /tmp
    curl -L -o curl.tar.gz https://curl.se/download/curl-${curlVer}.tar.gz
    tar -xf curl.tar.gz && rm curl.tar.gz
    cd curl-${curlVer}
    ./configure --with-openssl --prefix=/opt/curl/${curlVer}
    make
    make install
  done

  # Build core extensions as shared libraries.
  # We intentionally do not run 'make install' here so that we can test the
  # scenario where headers are not installed for the shared library.
  # ext/curl
  cd ${PHP_SRC_DIR}/ext/curl
  phpize
  ./configure
  make
  mv ./modules/*.so $(php-config --extension-dir)
  make clean

  for curlVer in ${CURL_VERSIONS}; do
    PKG_CONFIG_PATH=/opt/curl/${curlVer}/lib/pkgconfig/
    ./configure
    make
    mv ./modules/curl.so $(php-config --extension-dir)/curl-${curlVer}.so
    make clean
  done
  phpize --clean

  # ext/pdo
  cd ${PHP_SRC_DIR}/ext/pdo
  phpize
  ./configure
  make
  mv ./modules/*.so $(php-config --extension-dir)
  make clean;
  phpize --clean

  # TODO Add ext/pdo_mysql, ext/pdo_pgsql, and ext/pdo_sqlite
else
  pecl channel-update pecl.php.net;

  yes '' | pecl install apcu; echo "extension=apcu.so" >> ${iniDir}/apcu.ini;
  pecl install ast$AST_VERSION; echo "extension=ast.so" >> ${iniDir}/ast.ini;
  if [[ $PHP_VERSION_ID -ge 71 && $PHP_VERSION_ID -le 80 ]]; then
    yes '' | pecl install mcrypt$(if [[ $PHP_VERSION_ID -le 71 ]]; then echo -1.0.0; fi); echo "extension=mcrypt.so" >> ${iniDir}/mcrypt.ini;
  fi
  yes 'no' | pecl install memcached; echo "extension=memcached.so" >> ${iniDir}/memcached.ini;
  yes '' | pecl install memcache$MEMCACHE_VERSION; echo "extension=memcache.so" >> ${iniDir}/memcache.ini;
  pecl install mongodb$MONGODB_VERSION; echo "extension=mongodb.so" >> ${iniDir}/mongodb.ini;
  # Redis 6.0.0 dropped support for PHP 7.1 and below
  if [[ $PHP_VERSION_ID -le 83 ]]; then
    pecl install redis$(if [[ $PHP_VERSION_ID -le 71 ]]; then echo -5.3.7; fi); echo "extension=redis.so" >> ${iniDir}/redis.ini;
  else
    # phpredis from latest `develop` branch has PHP 8.4 support, no release so
    # far
    curl -LO https://github.com/phpredis/phpredis/archive/6673b5b2bed7f50600aad0bf02afd49110a49d81.tar.gz;
    tar -xvzf 6673b5b2bed7f50600aad0bf02afd49110a49d81.tar.gz;
    cd phpredis-6673b5b2bed7f50600aad0bf02afd49110a49d81;
    phpize;
    ./configure;
    make && make install;
    echo "extension=redis.so" >> ${iniDir}/redis.ini;
  fi
  pecl install sqlsrv$SQLSRV_VERSION; echo "extension=sqlsrv.so" >> ${iniDir}/sqlsrv.ini;
  # Xdebug is disabled by default
  for VERSION in "${XDEBUG_VERSIONS[@]}"; do
    if [[ "${VERSION}" == "-3.4.0" ]]; then
      curl -LO https://github.com/xdebug/xdebug/archive/refs/tags/3.4.0alpha1.tar.gz;
      tar -xvzf 3.4.0alpha1.tar.gz;
      cd xdebug-3.4.0alpha1;
      phpize;
      ./configure;
      make && make install;
    else
      pecl install xdebug$VERSION;
    fi
    cd $(php-config --extension-dir);
    mv xdebug.so xdebug$VERSION.so;
  done
  echo "zend_extension=opcache.so" >> ${iniDir}/../php-apache2handler.ini;

  # ext-parallel needs PHP 8
  if [[ $PHP_VERSION_ID -ge 80 && $PHP_ZTS -eq 1 ]]; then
    pecl install parallel;
    echo "extension=parallel" >> ${iniDir}/parallel.ini;
  fi
fi
