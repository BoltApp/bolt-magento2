#!/usr/bin/env bash

# init git
git config user.email "circleci@bolt.com"
git config user.name "Circle CI"

baseBranch="master"
# add the branches that you want to auto-rebase
# use space to sparate them (don't use comma)
autoRebaseBranches=("jz/rebase-custom1" "jz/rebase-custom2")

for branchName in "${autoRebaseBranches[@]}"; do
  echo "Auto-rebasing $branchName to $baseBranch..."
  if ! (git checkout "$branchName"); then
    echo "Failed to checkout branch $branchName"
    exit 1
  fi
  if ! (git rebase origin/$baseBranch); then
    echo "Failed to rebase branch $branchName on $baseBranch"
    exit 1
  fi
  if ! (git push --force-with-lease origin "$branchName"); then
    echo "Failed to push $branchName is done."
    exit 1
  fi
  printf "Auto-rebasing %s to %s is done.\n\n" $branchName $baseBranch
done
