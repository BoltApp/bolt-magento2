
if ! (git rebase $CIRCLE_BRANCH); then
  echo "Failed to rebase branch $CIRCLE_BRANCH"
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