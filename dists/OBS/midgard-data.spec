%define major_version 8.10.0

%if 0%{?fedora} >= 10 || 0%{?rhel_version} >= 600 || 0%{?centos_version} >= 600 || 0%{?suse_version} >= 1120 || 0%{?sles_version} >= 12
%define noarch_subpackages 1
%else
%define noarch_subpackages 0
%endif

# NOTE: OBS doesn't have SELinux support (because of custom kernels).
#       Therefore the selinux subpackage uses a prebuilt policy module file.
#       It has been created using dists/fedora/midgard-data.spec from svn.
#       The current version of midgard.pp (1.1.0) is taken from:
#       midgard-data-selinux-8.09.7.99-1 (built on CentOS 5.4)

%if 0%{?suse_version}
%define httpd apache2
%define apxs apxs2
%define php_cli php5
%define mysql mysql-client
%else
%define httpd httpd
%define apxs apxs
%define php_cli php-cli
%define mysql mysql
%endif

Name:           midgard-data
Version:        %{major_version}
Release:        OBS
Summary:        Midgard data package with datagard configuration tool

Group:          Applications/System
License:        GPL+
URL:            http://www.midgard-project.org/
Source0:        %{url}download/%{name}-%{version}.tar.gz
Source1:        midgard.pp
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  midgard-core-devel >= %{major_version}
BuildRequires:  %{httpd}-devel
BuildRequires:  %{php_cli}
BuildRequires:  %{mysql}

Requires:       php-midgard >= %{major_version}
Requires:       midgard-core >= %{major_version}
Requires:       mod_midgard >= %{major_version}
Requires:       %{mysql}
Requires:       php-pear
Requires:       /bin/grep, /bin/sed
%if 0%{?suse_version}
Requires(post): /usr/sbin/rc%{httpd}
%else
Requires(post): /sbin/service
%endif
Obsoletes:      midgard-datagard < 1.9
Obsoletes:      midgard-framework < 1.9

%description
If you intend to use the Midgard Content Management System, install this 
package as it provides the Midgard configuration tool called datagard 
which is used for setting up Midgard databases, creating virtual hosts 
for Apache HTTP Server, initializing PEAR channels and installing 
Midgard CMS PEAR packages.


%if 0%{?suse_version} == 0
%package        selinux
Summary:        SELinux support for %{name}
Group:          System Environment/Base
%if 0%{?noarch_subpackages}
BuildArch:      noarch
%endif
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
%endif


%prep
%setup -q


%build
%if 0%{?suse_version}
%configure --with-apxs=%{_sbindir}/%{apxs} --with-php=/usr/bin/php5 --with-apache-user=wwwrun --with-apache-group=www
%else
%configure --with-apxs=%{_sbindir}/%{apxs}
%endif
make %{?_smp_mflags}


%install
%if 0%{?suse_version} == 0
rm -rf $RPM_BUILD_ROOT
mkdir -p $(dirname $RPM_BUILD_ROOT)
mkdir $RPM_BUILD_ROOT
%endif
make install DESTDIR=$RPM_BUILD_ROOT
%if 0%{?suse_version} == 0
mkdir -p $RPM_BUILD_ROOT%{_datadir}/selinux/targeted
install -p -m 644 -D %{SOURCE1} $RPM_BUILD_ROOT%{_datadir}/selinux/targeted/midgard.pp
%endif


%clean
rm -rf $RPM_BUILD_ROOT


%post
%if 0%{?suse_version}
%restart_on_update %{httpd}
%else
[ -e /var/lock/subsys/%{httpd} ] && /sbin/service %{httpd} graceful > /dev/null 2>&1
%endif
exit 0

%if 0%{?suse_version} == 0
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
%endif


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

%if 0%{?suse_version} == 0
%files selinux
%defattr(-,root,root,-)
%{_datadir}/selinux/targeted/midgard.pp
%doc COPYING
%endif


%changelog
* Thu Jan 21 2010 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.7-22.1
- Updated midgard.pp in the selinux package from 1.0.0 to 1.1.0

* Sun Jan 17 2010 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.7-20.1
- Removed midgard-cms and -server (separated to own package)

* Sat Jan 16 2010 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.7-8.1
- Removed noarch to fix midgard-data's httpd lib path (Midgard #1590)
- Added noarch to subpackages (requires rpm >= 4.6 to work correctly)
- Changed noarch to be used only on newer distros where supported

* Mon Oct 12 2009 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.5-8.1
- Fixed the postinstall script again (use restart macro on SUSE)

* Tue Oct 07 2009 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.5-7.1
- Fixed the postinstall script fix (succeeds on all distros now)

* Tue Oct 06 2009 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.5-6.1
- Fixed the postinstall script (always succeeds now)

* Tue Sep 29 2009 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.5-5.1
- Added graceful httpd restart because of possible DB changes

* Fri Sep 04 2009 Jarkko Ala-Louvesniemi <jval@puv.fi> 8.09.5
- Initial OBS package based on the Fedora spec.
