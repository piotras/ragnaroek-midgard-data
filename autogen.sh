#! /bin/sh

src_dir=`pwd`
configure_options="$@"

autoheader
aclocal -I m4
libtoolize --force
automake -f -c -a
autoconf

$src_dir/configure $configure_options

echo
echo "Run \`make\` to compile"
echo
