#!/usr/bin/env bash

if [ "$#" -ne 2 ]; then
    echo "usage: $0 <base_branch> <is_integration>\n"
    exit 1
fi

# init git
git config user.email "circleci@bolt.com"
git config user.name "Circle CI"

baseBranch="$1"
isIntegration="$2"
merchantBranches=()
# load auto-rebasing branches
configFile="./.circleci/scripts/auto-rebase-branches.txt"
if ! test -f "$configFile"; then
    echo "Cannot find the configuration for auto-rebase."
    exit 1
fi

echo "The following branches will be tested after rebasing against $baseBranch"
while IFS= read -r branchName || [[ -n "$branchName" ]]; do
  if [ ${#branchName} -gt 0 ]; then
    merchantBranches+=("ci/$branchName")
    echo "ci/$branchName"

    #TODO: set run_rebase_and_unit_test parameter to "true" for $branchName

  fi
done < "$configFile"