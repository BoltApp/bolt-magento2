#!/usr/bin/env bash

set -e
set -u
set -x

RCENTRY=$(git for-each-ref --sort=-taggerdate --format="%(refname:short) | %(creatordate)" refs/tags/* | grep "rc " | head -n 1)
TAG=$(echo $RCENTRY | cut -d"|" -f1)
TAGDATE=TAG=$(echo $RCENTRY | cut -d"|" -f2)

NEWTAG=$(echo $TAG | awk -F. '{print $1 "." $2+1 "." $3}')
