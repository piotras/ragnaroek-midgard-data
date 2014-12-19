%if 0%{?suse_version}
%define httpd apache2
%define mysql_server mysql
%define php_pecl_memcache php5-pecl-memcache
%define php_pecl_apc php5-APC
%define mysqld mysql
%else
%define httpd httpd
%define mysql_server mysql-server
%define php_pecl_memcache php-pecl-memcache
%define php_pecl_apc php-pecl-apc
%define mysqld mysqld
%endif

Name:           midgard-cms
Version:        %(rpm -q --requires midgard-data | grep '^midgard-core ' | cut -f3 -d' ')
Release:        OBS
Summary:        Midgard CMS

Group:          Applications/Publishing
License:        GPL+
URL:            http://www.midgard-project.org/
Source0:        %{name}-COPYING
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildArch:      noarch

BuildRequires:  rpm, midgard-data

Requires:       midgard-data >= %{version}
Requires:       /usr/bin/which, /usr/bin/file, ImageMagick
Requires:       php-session, php-iconv, php-mbstring, php-gd
Requires:       /usr/bin/rcs
Requires:       /usr/bin/find, /usr/bin/unzip, /bin/tar, /bin/gzip
Requires:       /usr/bin/jpegtran
Obsoletes:      midcom < 2.10
Obsoletes:      aegir < 2
Obsoletes:      spider < 2
Obsoletes:      midgard-welcome < 1.9

%description
Midgard CMS is a capable open source content management system for 
running mid to high-end websites. In addition to the built-in content 
management features, Midgard CMS also provides a highly object-oriented 
PHP component architecture (called MidCOM) for building interactive web 
applications that integrate seamlessly with the website.

Midgard CMS can run almost any kind of website from personal blogs to 
corporate intranets and multilingual groups of websites.

This package provides necessary dependencies to run the Midgard Content 
Management System with default settings.


%package        server
Summary:        Full local server installation of the Midgard CMS
Group:          Applications/Publishing
Requires:       %{name} = %{version}-%{release}
%if 0%{?suse_version} == 0
Requires:       midgard-data-selinux >= %{version}
%endif
Requires:       %{mysql_server}
Requires:       %{php_pecl_memcache}, memcached
Requires:       %{php_pecl_apc}
Requires(post): /sbin/chkconfig
%if 0%{?suse_version}
BuildRequires:  %{mysql_server}, memcached
Requires(post): /usr/sbin/rcmemcached, /usr/sbin/rc%{mysqld}
%else
Requires(post): /sbin/service
%endif
Provides:       midgard = %{version}

%description server
Midgard CMS is a capable open source content management system for
running mid to high-end websites. In addition to the built-in content
management features, Midgard CMS also provides a highly object-oriented
PHP component architecture (called MidCOM) for building interactive web
applications that integrate seamlessly with the website.

Midgard CMS can run almost any kind of website from personal blogs to
corporate intranets and multilingual groups of websites.

This package provides a full and working local server installation of 
the Midgard Content Management System. Unless you want to tweak your 
setup, you should install this package.

After installation, run datagard (the Midgard configuration tool).


%prep
cp -p %{SOURCE0} COPYING


%build


%install
%if 0%{?suse_version} == 0
rm -rf $RPM_BUILD_ROOT
mkdir -p $(dirname $RPM_BUILD_ROOT)
mkdir $RPM_BUILD_ROOT
%endif


%clean
rm -rf $RPM_BUILD_ROOT
rm -f COPYING


%post server
if [ $1 = 1 ]; then
%if 0%{?suse_version}
    %fillup_and_insserv -f -y memcached
    %fillup_and_insserv -f -y %{mysqld}
    %fillup_and_insserv -f -y %{httpd}
    /usr/sbin/rcmemcached status > /dev/null 2>&1; if [ ! $? = 0 ]; then /usr/sbin/rcmemcached start > /dev/null 2>&1; fi
    /usr/sbin/rc%{mysqld} status > /dev/null 2>&1; if [ ! $? = 0 ]; then /usr/sbin/rc%{mysqld} start > /dev/null 2>&1; fi
%else
    /sbin/chkconfig --level 345 memcached on
    /sbin/chkconfig --level 345 %{mysqld} on
    /sbin/chkconfig --level 345 %{httpd} on
    [ ! -e /var/lock/subsys/memcached ] && /sbin/service memcached start > /dev/null 2>&1
    [ ! -e /var/lock/subsys/%{mysqld} ] && /sbin/service %{mysqld} start > /dev/null 2>&1
%endif
    exit 0
fi


%files
%defattr(-,root,root,-)
%doc COPYING

%files server
%defattr(-,root,root,-)
%doc COPYING


%changelog
* Sun Jan 17 2010 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.7-21.1
- Initial package, separated from midgard-data.
