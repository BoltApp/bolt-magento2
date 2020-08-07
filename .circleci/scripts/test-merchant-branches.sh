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
  fi
done < "$configFile"

# TODO: Add support to rebase and run tests for all merchant branches in the config
# For now, we only rebase and run tests for the first merchant branch in the config
branchName=${merchantBranches[0]}
if ! (git checkout "$branchName"); then
  echo "Failed to checkout branch $branchName"
  exit 1
fi
if ! (git rebase origin/$baseBranch); then
  echo "Failed to rebase branch $branchName on $baseBranch"
  git rebase --abort
  exit 1
fi
if [ "$isIntegration" = true ]; then
  echo "Start integration tests..."
  if ! ./Test/scripts/ci-integration.sh; then
    echo "integration tests failed"
    exit 1
  fi
else
  echo "Start unit tests..."
  if ! ./Test/scripts/ci-unit.sh; then
    echo "unit tests failed"
    exit 1
  fi
fi


