#!/usr/bin/env bash

set -e
set -u
set -x

RCENTRY=$(git for-each-ref --sort=-taggerdate --format="%(refname:short) | %(creatordate:format:%s)" refs/tags/* | grep "rc " | head -n 1)

TAG=$(echo $RCENTRY | cut -d"|" -f1)
TAGDATE=$(echo $RCENTRY | cut -d"|" -f2 | sed 's/^ *//g')

NEWTAGNAME=$(echo $TAG | awk -F. '{print $1 "." $2+1 ".0-rc"}')

CUTOFFDATE =$(date --date "20 days ago" +'%s')

if [[ ${TAGDATE} -gt ${CUTOFFDATE} ]];
then
    echo "No need to create RC-tag this week"
    circleci-agent step halt
fi