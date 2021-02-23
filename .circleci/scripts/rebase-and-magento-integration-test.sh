merchantBranch=$1
echo "Rebasing $merchantBranch from $CIRCLE_BRANCH"

git config --global user.email "dev@bolt.com"
git config --global user.name "Bolt Rebase & Test Bot"

git checkout $merchantBranch
if ! (git rebase $CIRCLE_BRANCH); then
  echo "Failed to rebase $merchantBranch from $CIRCLE_BRANCH"
  git rebase --abort
  exit 1
fi

export TEST_ENV="php72"
export MAGENTO_VERSION="2.2.8"
export COMPOSER_MEMORY_LIMIT=5G

echo "Start Magento Integration Tests..."
if ! ./Test/scripts/ci-magento-integration.sh; then
  echo "magento integration tests failed"
  exit 1
fi