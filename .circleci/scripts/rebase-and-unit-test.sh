
merchantBranch=$1
echo "Rebasing $merchantBranch from $CIRCLE_BRANCH"

git checkout $merchantBranch
if ! (git rebase $CIRCLE_BRANCH); then
  echo "Failed to rebase $merchantBranch from $CIRCLE_BRANCH"
  git rebase --abort
  exit 1
fi

export COMPOSER_MEMORY_LIMIT=3G
echo "Start unit tests..."
if ! ./Test/scripts/ci-unit.sh; then
  echo "unit tests failed"
  exit 1
fi