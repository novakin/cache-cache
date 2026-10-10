#!/bin/sh
# Official release archive SHA-1 receipts verified on 2026-10-10.
set -eu
test "$#" -eq 2 || { echo 'Usage: download-wordpress.sh VERSION NEW_DIRECTORY' >&2; exit 2; }
version=$1
destination=$2
case "$version" in
  5.8) checksum=ea9a0306c19ba54f97476f86d0604ddd93dd89e1 ;;
  7.1.3) checksum=13eb1d042b1ca39224e44906cd84fe203112210c ;;
  *) echo "Unverified WordPress release: $version" >&2; exit 2 ;;
esac
test ! -e "$destination" || { echo 'Destination already exists; use a new disposable directory.' >&2; exit 2; }
mkdir -p "$destination"
archive="$destination/wordpress.zip"
curl --fail --silent --show-error --location --retry 3 "https://wordpress.org/wordpress-$version.zip" -o "$archive"
printf '%s  %s\n' "$checksum" "$archive" | sha1sum --check --status
unzip -q "$archive" -d "$destination"
echo "Verified WordPress $version archive; $destination/wordpress"
