#!/bin/sh
set -e

util/buildscripts/build.sh -p partdb.profile.js -r
mkdir -p ./release/dojo
mkdir -p ./release/dgrid
mv ./release/lib/dojo/dojo.js ./release/dojo/
mv ./release/lib/dgrid/dgrid.js ./release/dgrid/
rm -rv ./release/lib
