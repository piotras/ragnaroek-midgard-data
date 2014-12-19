Name:           midgard-cms
Version:        %(rpm -q --requires midgard-data | grep '^midgard-core ' | cut -f3 -d' ')
Release:        1%{?dist}
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
Requires:       midgard-data-selinux >= %{version}
Requires:       mysql-server
Requires:       php-pecl-memcache, memcached
Requires:       php-pecl-apc
Requires(post): /sbin/chkconfig, /sbin/service
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
rm -rf $RPM_BUILD_ROOT
mkdir -p $(dirname $RPM_BUILD_ROOT)
mkdir $RPM_BUILD_ROOT


%clean
rm -rf $RPM_BUILD_ROOT
rm -f COPYING


%post server
if [ $1 = 1 ]; then
    /sbin/chkconfig --level 345 memcached on
    /sbin/chkconfig --level 345 mysqld on
    /sbin/chkconfig --level 345 httpd on
    [ ! -e /var/lock/subsys/memcached ] && /sbin/service memcached start > /dev/null 2>&1
    [ ! -e /var/lock/subsys/mysqld ] && /sbin/service mysqld start > /dev/null 2>&1
    exit 0
fi


%files
%defattr(-,root,root,-)
%doc COPYING

%files server
%defattr(-,root,root,-)
%doc COPYING


%changelog
* Sun Jan 17 2010 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.7-4
- Initial package, separated from midgard-data.
