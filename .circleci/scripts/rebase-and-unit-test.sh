
baseBranch=$1
echo "Rebasing from branch: $baseBranch"

if ! (git rebase $baseBranch); then
  echo "Failed to rebase branch $CIRCLE_BRANCH"
  git rebase --abort
  exit 1
fi

echo "Start unit tests..."
if ! ./Test/scripts/ci-unit.sh; then
  echo "unit tests failed"
  exit 1
fi