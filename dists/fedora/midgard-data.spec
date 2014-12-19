%define major_version 8.09.9

Name:           midgard-data
Version:        %{major_version}
Release:        1%{?dist}
Summary:        Midgard data package with datagard configuration tool

Group:          Applications/System
License:        GPL+
URL:            http://www.midgard-project.org/
Source0:        %{url}download/%{name}-%{version}.tar.gz
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  midgard-core-devel >= %{major_version}
BuildRequires:  httpd-devel
BuildRequires:  php-cli
BuildRequires:  mysql

Requires:       php-midgard >= %{major_version}
Requires:       midgard-core >= %{major_version}
Requires:       mod_midgard >= %{major_version}
Requires:       mysql
Requires:       php-pear
Requires:       /bin/grep, /bin/sed
Requires(post): /sbin/service

%description
If you intend to use the Midgard Content Management System, install this 
package as it provides the Midgard configuration tool called datagard 
which is used for setting up Midgard databases, creating virtual hosts 
for Apache HTTP Server, initializing PEAR channels and installing 
Midgard CMS PEAR packages.


%package        selinux
Summary:        SELinux support for %{name}
Group:          System Environment/Base
BuildArch:      noarch
BuildRequires:  rpm, selinux-policy
BuildRequires:  selinux-policy-devel
Requires:       %{name} >= %{version}-%{release}
Requires:       selinux-policy >= %(rpm -q --queryformat=%%{version} selinux-policy)
Requires(post):   /usr/sbin/semodule, /sbin/restorecon
Requires(postun): /usr/sbin/semodule, /sbin/restorecon

%description selinux
This is the SELinux targeted policy module for %{name}.

If you have SELinux set to enforcing (strongly recommended), install 
this package to be able to use the Midgard CMS the way datagard 
configures it.


%prep
%setup -q


%build
%configure --with-apxs=%{_sbindir}/apxs
make %{?_smp_mflags}
pushd dists/fedora/selinux/targeted/
    make %{?_smp_mflags} -f /usr/share/selinux/devel/Makefile
popd


%install
rm -rf $RPM_BUILD_ROOT
mkdir -p $(dirname $RPM_BUILD_ROOT)
mkdir $RPM_BUILD_ROOT
make install DESTDIR=$RPM_BUILD_ROOT
mkdir -p $RPM_BUILD_ROOT%{_datadir}/selinux/targeted
install -p -m 644 -D dists/fedora/selinux/targeted/midgard.pp $RPM_BUILD_ROOT%{_datadir}/selinux/targeted/midgard.pp


%clean
rm -rf $RPM_BUILD_ROOT


%post
[ -e /var/lock/subsys/httpd ] && /sbin/service httpd graceful > /dev/null 2>&1
exit 0

%post selinux
/usr/sbin/semodule -i %{_datadir}/selinux/targeted/midgard.pp 2>/dev/null || :
if [ $1 = 1 ]; then
    /sbin/restorecon -Ri /etc/midgard/apache
    /sbin/restorecon -Ri /var/cache/midgard
    /sbin/restorecon -Ri /var/lib/midgard
    /sbin/restorecon -Ri /var/log/midgard
    /sbin/restorecon -Ri /var/spool/midgard
    exit 0
fi

%postun selinux
if [ $1 = 0 ]; then
    /usr/sbin/semodule -r midgard 2>/dev/null || :
    /sbin/restorecon -FRi /var/spool/midgard
    /sbin/restorecon -FRi /var/log/midgard
    /sbin/restorecon -FRi /var/lib/midgard
    /sbin/restorecon -FRi /var/cache/midgard
    /sbin/restorecon -FRi /etc/midgard/apache
    exit 0
fi


%files
%defattr(-,root,root,-)
%doc COPYING NEWS README
%{_sbindir}/*
%{_mandir}/man1/*
%dir %{_datadir}/midgard/setup
%dir %{_datadir}/midgard/setup/php
%{_datadir}/midgard/setup/php/*
%dir %{_datadir}/midgard/setup/xml
%dir %{_datadir}/midgard/setup/xml/import
%{_datadir}/midgard/setup/xml/import/*
%dir %{_localstatedir}/lib/midgard
%dir %{_localstatedir}/lib/midgard/blobs
%{_localstatedir}/lib/midgard/blobs/README.txt

%files selinux
%defattr(-,root,root,-)
%{_datadir}/selinux/targeted/midgard.pp
%doc COPYING


%changelog
* Sun Jan 17 2010 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.7-4
- Removed midgard-cms and -server (separated to own package)

* Sat Jan 16 2010 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.7-3
- Removed noarch to fix midgard-data's httpd lib path (Midgard #1590)
- Added noarch to subpackages (requires rpm >= 4.6 to work correctly)

* Tue Oct 06 2009 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.5-2.1
- Fixed the postinstall script (always succeeds now)

* Tue Sep 29 2009 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.5-2
- Added graceful httpd restart because of possible DB changes

* Fri Sep 04 2009 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.5-1
- Initial package using the Fedora spec file template.
