dnl $Id: configure.ac 11960 2007-09-01 11:44:03Z piotras $
dnl
dnl Process this file with autoconf to produce a configure script.
dnl
AC_INIT([midgard-data],[8.10.0])
AC_PREREQ(2.50)
AC_CONFIG_MACRO_DIR([m4])
AC_CONFIG_HEADERS([config.h])

dnl Automake is needed for the build system.
dnl
AM_INIT_AUTOMAKE([-Wno-portability -Wall])
AM_MAINTAINER_MODE

AC_PROG_CC(gcc cc)
AC_LANG(C)

dnl Check for pkg-config
AC_ARG_WITH([pkg-config],
        AC_HELP_STRING([--with-pkg-config=PATH], [Location of the pkg-config executable]),
	[_PKGCONFIG="$withval"],
	[AC_PATH_PROG([_PKGCONFIG],["pkg-config"])])
MIDGARD_CFLAGS=`"$_PKGCONFIG" --cflags midgard`
MIDGARD_LIBS=`"$_PKGCONFIG" --libs midgard`
MIDGARD_PREFIX=`"$_PKGCONFIG" --variable prefix midgard`
AC_SUBST([MIDGARD_CFLAGS])
AC_SUBST([MIDGARD_LIBS])
AC_SUBST([MIDGARD_PREFIX])

if test "$MIDGARD_PREFIX" = ""; then
  AC_MSG_ERROR("Prefix is null. Is midgard-core installed?")
fi

if test "$MIDGARD_PREFIX" = "/usr"; then
  MGD_USR_PATH="/usr"
  MGD_ETC_PATH="/etc"
  MGD_VAR_PATH="/var"
else
  MGD_USR_PATH="$MIDGARD_PREFIX"
  MGD_ETC_PATH="$MIDGARD_PREFIX/etc"
  if test "$MIDGARD_PREFIX" = "/usr/local"; then
    MGD_VAR_PATH="/var/local"
  else
    MGD_VAR_PATH="$MIDGARD_PREFIX/var"
  fi
fi
AC_SUBST([MGD_USR_PATH])
AC_SUBST([MGD_VAR_PATH])
AC_SUBST([MGD_ETC_PATH])

MGD_BLOB_DIR="$MGD_VAR_PATH/lib/midgard/blobs"
MGD_SHARE_PATH="$MGD_USR_PATH/share/midgard"
AC_SUBST([MGD_BLOB_DIR])
AC_SUBST([MGD_SHARE_PATH])

dnl Check php binary
dnl Check for midgard-config
AC_ARG_WITH([php],
	AC_HELP_STRING([--with-php=PATH],
	[Location of the php binary]),
	[PHP_BINARY="$withval"],
	[AC_PATH_PROG([PHP_BINARY],["php"])])
test -x "$PHP_BINARY" || AC_MSG_ERROR([php binary not found, use --with-php])
AC_SUBST([PHP_BINARY])

dnl Check for mysql binaries
AC_ARG_WITH([mysql-path],
            AC_HELP_STRING([--with-mysql-path=PATH],
                           [Path containing the MySQL binaries]),
            [MYSQL_PATH=$withval],
            [MYSQL_PATH=$PATH])
AC_ARG_WITH([mysql],
            AC_HELP_STRING([--with-mysql=PATH],
                           [Location of the mysql program]),
            [MYSQL=$withval],
            [AC_PATH_PROG([MYSQL],[mysql],[mysql],[$MYSQL_PATH])])
test -x "$MYSQL" || AC_MSG_ERROR([mysql not found, use --with-mysql])
AC_SUBST([MYSQL])
AC_ARG_WITH([mysqladmin],
            AC_HELP_STRING([--with-mysqladmin=PATH],
                           [Location of the mysqladmin program]),
            [MYSQLADMIN=$withval],
            [AC_PATH_PROG([MYSQLADMIN],[mysqladmin],[mysqladmin],[$MYSQL_PATH])])
test -x "$MYSQLADMIN" || AC_MSG_ERROR([mysqladmin not found, use --with-mysqladmin])
AC_SUBST([MYSQLADMIN])
AC_ARG_WITH([mysqldump],
            AC_HELP_STRING([--with-mysqldump=PATH],
                           [Location of the mysqldump program]),
            [MYSQLDUMP=$withval],
            [AC_PATH_PROG([MYSQLDUMP],[mysqldump],[mysqldump],[$MYSQL_PATH])])
test -x "$MYSQLDUMP" || AC_MSG_ERROR([mysqldump not found, use --with-mysqldump])
AC_SUBST([MYSQLDUMP])

dnl Check for apxs binary
AC_ARG_WITH([apxs],
            AC_HELP_STRING([--with-apxs=PATH],
                           [Location of the apxs program]),
            [APXS=$withval],
            [AC_PATH_PROG([APXS],[apxs])])
test -x "$APXS" || AC_MSG_ERROR([apxs not found, use --with-apxs])
AC_SUBST([APXS])

dnl Check if envvars file exists
dnl If yes, enable variables
if test -f "/etc/apache2/envvars"; then
	APACHE_ENV='source /etc/apache2/envvars '
else
	APACHE_ENV=``
fi
$APACHE_ENV
dnl Check for Apache settings
AC_MSG_CHECKING([for the Apache executable])
APACHE=`$APXS -q SBINDIR`/`$APXS -q TARGET`
AC_MSG_RESULT($APACHE)
AC_SUBST(APACHE)

AC_MSG_CHECKING([for the Apache module directory])
APACHE_LIBEXEC_PATH=`$APXS -q LIBEXECDIR`
AC_MSG_RESULT($APACHE_LIBEXEC_PATH)
AC_SUBST(APACHE_LIBEXEC_PATH)

AC_MSG_CHECKING([for the Apache configuration directory])
APACHE_CONF_PATH=`$APXS -q SYSCONFDIR`
AC_MSG_RESULT($APACHE_CONF_PATH)
AC_SUBST(APACHE_CONF_PATH)

AC_MSG_CHECKING([for the Apache configuration file])
APACHE_CONF=`$APACHE  -V | grep SERVER_CONFIG_FILE | sed 's/^.*FILE=//1' | sed 's/"//g'`
APACHE_CONF=$APACHE_CONF_PATH/`basename $APACHE_CONF`
AC_MSG_RESULT($APACHE_CONF)
AC_SUBST(APACHE_CONF)

dnl Check for Apache user and group

AC_MSG_CHECKING(for Apache user)
AC_ARG_WITH([apache-user],
            AC_HELP_STRING([--with-apache-user=user],
                           [The username of the Apache process]),
            [APACHE_USER=$withval],
            [APACHE_USER=`grep '^User ' $APACHE_CONF | sed -e 's/^User //'`])
id $APACHE_USER > /dev/null 2>&1 || \
  AC_MSG_ERROR([Apache user not found, use --with-apache-user])
if test "$APACHE_USER" = ""; then
  AC_MSG_ERROR("Empty Apache user. Configure explicitly --with-apache-user")
fi

AC_MSG_RESULT($APACHE_USER)
AC_SUBST(APACHE_USER)

AC_MSG_CHECKING(for Apache group)
AC_ARG_WITH([apache-group],
            AC_HELP_STRING([--with-apache-group=group],
                           [The group of the Apache process]),
            [APACHE_GROUP=$withval],
            [APACHE_GROUP=$APACHE_USER])
dnl TODO verify that the APACHE_GROUP exists
if test "$APACHE_GROUP" = ""; then
  AC_MSG_ERROR("Empty Apache group. Configure explicitly --with-apache-group")
fi
AC_MSG_RESULT($APACHE_GROUP)
AC_SUBST(APACHE_GROUP)
                    
dnl find 'install' command
AC_PROG_INSTALL

dnl Checks for libraries.

dnl Checks for header files.
AC_HEADER_STDC

dnl Checks for typedefs, structures, and compiler characteristics.
AC_C_CONST

dnl Follow midgard core prefix
prefix="$MIDGARD_PREFIX"

SCHEMADIR_TEMP="$datadir/$PACKAGE"
eval "SCHEMADIR=$SCHEMADIR_TEMP"
AC_SUBST(SCHEMADIR)

AC_CONFIG_FILES([Makefile midgard/setup/php/midgard_setup_globals.php midgard/setup/php/datagard])
AC_OUTPUT
