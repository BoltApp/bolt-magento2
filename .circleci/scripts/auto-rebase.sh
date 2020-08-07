#!/usr/bin/env bash

# init git
git config user.email "circleci@bolt.com"
git config user.name "Circle CI"

baseBranch="master"
autoRebaseBranches=()
# load auto-rebasing branches
configFile="./.circleci/scripts/auto-rebase-branches.txt"
if ! test -f "$configFile"; then
    echo "Cannot find the configuration for auto-rebase."
    exit 1
fi
echo "The following branches will be auto-rebased:"
while IFS= read -r branchName || [[ -n "$branchName" ]]; do
  if [ ${#branchName} -gt 0 ]; then
    autoRebaseBranches+=("$branchName")
    echo "$branchName"
  fi
done < "$configFile"

auto-rebase () {
  local branchName=$1
  printf "\nAuto-rebasing %s to %s...\n" $branchName $baseBranch
  if ! (git checkout "$branchName"); then
    echo "Failed to checkout branch $branchName"
    return 1
  fi
  if ! (git rebase origin/$baseBranch); then
    echo "Failed to rebase branch $branchName on $baseBranch"
    git rebase --abort
    return 1
  fi
  if ! (git push --force-with-lease origin "$branchName":ci/"$branchName"); then
    echo "Failed to push the auto-rebase result to ci/$branchName"
    git reset --hard HEAD
    return 1
  fi
  printf "Auto-rebasing %s to %s is done.\n" $branchName $baseBranch
}

failedBranches=()
for branchName in "${autoRebaseBranches[@]}"; do
  if ! (auto-rebase "$branchName"); then
    failedBranches+=("$branchName")
  fi
done

# print summary
if [ "${#failedBranches[@]}" -eq 0 ]; then
  printf "\nSuccessfully auto-rebased all specifed branches\n"
else
  printf "\nFailed to auto-rebase the following branches:\n"
  for branchName in "${failedBranches[@]}"; do
    echo "$branchName"
  done
  exit 1
fi
