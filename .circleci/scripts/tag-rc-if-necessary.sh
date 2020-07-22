#!/usr/bin/env bash

set -e
set -u

PREVRC=$(git for-each-ref --sort=-creatordate --format="%(refname:short)|%(creatordate:unix)" refs/tags/* | grep "0-rc|" | head -n 1)

taggedDate=$(echo $PREVRC | cut -d"|" -f2)
deduplicationWindow=$(date --date "11 days ago" +"%s")

if [[ ${taggedDate} -lt ${deduplicationWindow} ]]; then
  OLDTAGNAME=$(echo $PREVRC | cut -d"|" -f1)
  NEWTAGNAME=$(echo $OLDTAGNAME | awk -F. '{print $1 "." $2+1 ".0-rc"}')
  echo "export NEWTAGNAME=$NEWTAGNAME" >>$BASH_ENV
  echo "Tagging $NEWTAGNAME"
  git tag $NEWTAGNAME
  git push origin $NEWTAGNAME
else
  echo "No rc tag this week"
  circleci-agent step halt
fi
