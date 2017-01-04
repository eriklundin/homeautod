Name:		homeautod
Summary:	Home automation/alarmsystem
Version:	0.0.2
Release:	1%{?dist}
Group:		System Environment/Daemons
License:	GPLv3
URL:		https://github.com/eriklundin/homeautod
Requires:	php-cli, php-mysql, php-process
Source:		https://github.com/eriklundin/homeautod/archive/v%{version}.tar.gz#/%{name}-%{version}.tar.gz

%description
Homeautod is a daemon or service that keeps track on devices and can act as an
alarmsystem and/or home automation system.

%prep
%setup -q

%build

%install
%{__rm} -rf %{buildroot}
%{__mkdir_p} %{buildroot}/var/lib/homeautod
%{__mkdir_p} %{buildroot}/usr/lib/homeautod
%{__mkdir_p} %{buildroot}%{_sysconfdir}/sysconfig
%{__mkdir_p} %{buildroot}/usr/sbin

%if "%{?dist}" == ".el6"
# CentOS 6
%{__mkdir_p} %{buildroot}/etc/init.d
%{__cp} homeautod.init %{buildroot}/etc/init.d/homeautod
%else
# CentOS 7
%{__mkdir_p} %{buildroot}/usr/lib/systemd/system
%{__cp} homeautod.service %{buildroot}/usr/lib/systemd/system
%endif

%{__cp} homeautod.conf %{buildroot}%{_sysconfdir}
%{__cp} -r drv lib %{buildroot}/usr/lib/homeautod
%{__cp} had_drv homeautod had_cmd %{buildroot}/usr/lib/homeautod
touch %{buildroot}%{_sysconfdir}/sysconfig/homeautod
ln -s /usr/lib/homeautod/had_cmd %{buildroot}/usr/sbin/had_cmd

%post

%if "%{?dist}" == ".el6"
# CentOS 6
chkconfig homeautod on
%else
# CentOS 7
systemctl enable homeautod.service
systemctl daemon-reload
%endif

%clean
%{__rm} -rf %{buildroot}

%files
%defattr(-,root,root,-)
%doc README LICENSE INSTALL misc/homeautod.sql
/usr/lib/homeautod
/usr/sbin/had_cmd
%defattr(-,homeautod,homeautod,-)
/var/lib/homeautod

%if "%{?dist}" == ".el6"
# CentOS 6
/etc/init.d/homeautod
%else
# CentOS 7
/usr/lib/systemd/system/homeautod.service
%endif

%config(noreplace) %{_sysconfdir}/sysconfig/homeautod
%config(noreplace) %{_sysconfdir}/homeautod.conf

%changelog
