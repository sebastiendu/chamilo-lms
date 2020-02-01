#!/bin/bash -e
# For licensing terms, see /license.txt
#
# This script upgrades Chamilo to the latest official release.
#
# It reads configuration.php to get the current "root_sys", "system_version" and "proxy_settings".
# (if proxy settings is not set but environment variable https_proxy is, it will be used instead)
#
# It copies the current Chamilo files to a new folder in the same parent folder,
# then downloads and extracts the new release over the new folder contents.
#
# If "root_sys" is a symbolic link then it is updated to point to the new folder ;
# else the original folder is renamed and the new folder takes the original name.

die() { echo "Error: $*" 1>&2 ; exit 1; }

# extract information from configuration.php
configurationPhp=$(realpath -s "$(dirname "$0")/../../app/config/configuration.php")
test -f "$configurationPhp" || die "$configurationPhp does not exist"
# shellcheck disable=SC2016
IFS=$'\n' read -r -d '' system_version sys_root proxy < <(cat "$configurationPhp" - <<<'
  echo $_configuration["system_version"], "\n";
  echo $_configuration["root_sys"], "\n";
  echo $_configuration["proxy_settings"]["stream_context_create"]["https"]["proxy"], "\n";
'|php 2>/dev/null
) || true
echo "According to $configurationPhp,
 - Current Chamilo version is $system_version
 - Permanent web root is $sys_root"
if [ -n "$proxy" ]
then
  if [ -z "$https_proxy" ]
  then
    export https_proxy="$proxy"
  fi
fi
permanentRoot=$(realpath -s "$sys_root")
originFolder=$(realpath "$permanentRoot")
echo "Original folder is $originFolder"

# Extract information from Github
echo "Checking latest release…"
IFS=$'\n' read -r -d '' tag_name tarball_url < <(
# shellcheck disable=SC2016
wget -O - -q --header "Accept: application/vnd.github.inertia-preview+json" https://api.github.com/repos/chamilo/chamilo-lms/releases/latest|php -r '
  $release=json_decode(file_get_contents("php://stdin"));
  echo $release->tag_name, "\n";
  echo $release->tarball_url, "\n";'
) || true
echo "According to Github,
 - Chamilo latest release tag name is $tag_name
 - Tarball URL is $tarball_url"
targetVersion=${tag_name/v/}
test "$targetVersion" != "$system_version" || die "$targetVersion is already installed"

parentFolder=$(dirname "$originFolder")
targetFolder="$parentFolder/chamilo-lms-$targetVersion"
[ ! -e "$targetFolder" ] || die "$targetFolder already exists"

if [ -L "$permanentRoot" ]
then
  echo "Future chamilo root folder link target will be $targetFolder"
else
  originFolderNewName=${originFolder}_${system_version}_$(date -I)
  echo "Original chamilo root will be renamed to $originFolderNewName"
fi

# duplicate original folder and extract the new release over the target folder
echo "Cleaning app/cache in original folder…"
find "$originFolder"/app/cache -mindepth 1 -delete
echo "Copying original folder to target folder…"
cp -a "$originFolder" "$targetFolder"
echo "Downloading and extracting release archive over target folder contents…"
wget -qO - "$tarball_url"|tar -C "$targetFolder" --strip-components=1 -xz

# adjust names
if [ -L "$permanentRoot" ]
then
  echo "Updating permanent web root symbolic link:"
  rm -v "$permanentRoot"
  ln -sv "$targetFolder" "$permanentRoot"
  echo "To undo this and get back to previous version, type"
  echo "rm '$permanentRoot' && ln -s '$originFolder' '$permanentRoot'"
else
  echo "Renaming original folder"
  mv -v "$originFolder" "$originFolderNewName"
  echo "Renaming target folder"
  mv -v "$targetFolder" "$originFolder"
  echo "To undo this and get back to previous version, type"
  echo "mv '$originFolder' '$targetFolder' && mv '$originFolderNewName' '$originFolder'"
fi

echo 'End of script.'