#!/usr/bin/env bash

if [ "$#" -ne 2 ]; then
    echo "usage: $0 <base_branch> <is_integration>\n"
    exit 1
fi

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
  fi
done < "$configFile"

# rebase
for branchName in "${merchantBranches[@]}"; do
  if ! (git checkout "$branchName"); then
    echo "Failed to checkout branch $branchName"
    exit 1
  fi
  if ! (git rebase origin/$baseBranch); then
    echo "Failed to rebase branch $branchName on $baseBranch"
    git rebase --abort
    return 1
  fi
  if [ "$isIntegration" = true ]; then
    echo "Start integration tests..."
    ./Test/scripts/ci-integration.sh
  else
    echo "Start unit tests..."
    ./Test/scripts/ci-unit.sh
  fi

done

