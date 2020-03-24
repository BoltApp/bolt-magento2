#!/usr/bin/env bash

set -e
set -u
set -x

RCENTRY=$(git for-each-ref --sort=-taggerdate --format="%(refname:short) | %(creatordate)" refs/tags/* | grep "rc " | head -n 1)
TAG=$(echo $RCENTRY | cut -d"|" -f1)
TAGDATE=TAG=$(echo $RCENTRY | cut -d"|" -f2)

export NEWTAGNAME=$(echo $TAG | awk -F. '{print $1 "." $2+1 ".0-rc"}')
threeWeeksAgo="21 days ago"
threeWeekDate=$(date --date "$threeWeeksAgo" +'%s')
taggedDate=$(date --date "$TAGDATE" +'%s')

if [[ ${taggedDate} -lt ${threeWeekDate} ]];
then
    export TAGTHISWEEK=true
else
    export TAGTHISWEEK=false
fi