#!/usr/bin/env bash

if [ "$#" -ne 2 ]; then
    echo "usage: $0 <base_branch> <is_integration>\n"
    exit 1
fi

# init git
git config user.email "circleci@bolt.com"
git config user.name "Circle CI"

baseBranch="$1"
# load auto-rebasing branches
configFile="./.circleci/scripts/auto-rebase-branches.txt"
if ! test -f "$configFile"; then
    echo "Cannot find the configuration for auto-rebase."
    exit 1
fi

while IFS= read -r branchName || [[ -n "$branchName" ]]; do
  if [ ${#branchName} -gt 0 ]; then
    merchantBranch="ci/$branchName"
    echo "Beginning testing on branch: $merchantBranch"

    /tmp/swissknife/trigger_pipeline.sh "<< parameters.vcs-type >>" "<< parameters.user >>" "<< parameters.repo-name >>" "$merchantBranch" '"parameters": { "run_rebase_and_unit_test": true }'

  fi
done < "$configFile"