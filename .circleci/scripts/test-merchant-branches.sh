#!/usr/bin/env bash

if [ "$#" -ne 3 ]; then
    echo "usage: $0 <vcsType> <user> <repoName>\n"
    exit 1
fi

if [[ $CIRCLE_BRANCH == master ]] ; then
  exit 0
fi

if [[ ${CIRCLE_BRANCH:0:3} == ci/ ]] && grep -n "^${CIRCLE_BRANCH:3}$" ./.circleci/scripts/auto-rebase-branches.txt >> /dev/null ; then
  echo "test-merchant-branches.sh Cannot be called from a merchant branch (this will cause an infinite loop)"
  exit 0
fi

# init git
git config user.email "circleci@bolt.com"
git config user.name "Circle CI"

vcsType=$1
user=$2
repoName=$3

echo "vcsType: $vcsType"
echo "user: $user"
echo "repoName: $repoName"

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

    params='{"run_rebase_and_unit_test":true,"rebase_and_unit_test_branch_name":"'"$CIRCLE_BRANCH"'","run_default_workflow":false}'
    echo "params: $params"
    /tmp/swissknife/trigger_pipeline.sh "$vcsType" "$user" "$repoName" "$merchantBranch" "$params"

  fi
done < "$configFile"