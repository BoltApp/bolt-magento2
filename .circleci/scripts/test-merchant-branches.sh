#!/usr/bin/env bash

if [ -z "$1" ]; then
    echo "usage: $0 <base_branch>\n"
    exit 1
fi

baseBranch="$1"
merchantBranches=()
# load auto-rebasing branches
configFile="./.circleci/scripts/auto-rebase-branches.txt"
if ! test -f "$configFile"; then
    echo "Cannot find the configuration for auto-rebase."
    exit 1
fi

echo "The following branches will be tested"
while IFS= read -r branchName || [[ -n "$branchName" ]]; do
  if [ ${#branchName} -gt 0 ]; then
    merchantBranches+=("ci/$branchName")
    echo "ci/$branchName"
  fi
done < "$configFile"


