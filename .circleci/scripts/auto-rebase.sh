#!/usr/bin/env bash

# init git
git config user.email "circleci@bolt.com"
git config user.name "Circle CI"

baseBranch="master"
# add the branches that you want to auto-rebase
# use space to sparate them (don't use comma)
autoRebaseBranches=("jz/rebase-custom1" "jz/rebase-custom2" "jz/rebase-custom3")

auto-rebase () {
  local branchName=$1
  printf "\nAuto-rebasing %s to %s...\n" $branchName $baseBranch
  if ! (git checkout "$branchName"); then
    echo "Failed to checkout branch $branchName"
    exit 1
  fi
  if ! (git rebase origin/$baseBranch); then
    echo "Failed to rebase branch $branchName on $baseBranch"
    git rebase --abort
    exit 1
  fi
  if ! (git push --force-with-lease origin "$branchName"); then
    echo "Failed to push $branchName"
    git reset --hard HEAD
    exit 1
  fi
  printf "Auto-rebasing %s to %s is done.\n\n" $branchName $baseBranch
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
  for branchName in "${autoRebaseBranches[@]}"; do
    echo "$branchName"
  done
fi

