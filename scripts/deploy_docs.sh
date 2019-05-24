#!/usr/bin/env bash
set -euo pipefail

branch=${CIRCLE_BRANCH:-$(git symbolic-ref --short -q HEAD)}
repository=https://${GITHUB_TOKEN}@github.com/sburba/strict-json

if [[ -n ${CIRCLE_PULL_REQUEST:-} ]]; then
    echo "Skipping docs deploy, PR branch"
    exit 0
fi

if [[ ! ${branch} == 'master' ]]; then
    echo "Skipping docs deploy, non master branch"
    exit 0
fi

if [[ -n ${CI:-} ]]; then
    git config --global user.name "CI AutoDeploy"
    git config --global user.email "autodeploy@samburba.com"
fi

mkdir -p ~/.ssh/
touch ~/.ssh/known_hosts
ssh-keyscan -H github.com 2>/dev/null >> ~/.ssh/known_hosts

# Couscous really likes to log the access token, so sanitize the output
# Couscous deploy also fails if there are no changed files, so keep that from failing the build until
# https://github.com/CouscousPHP/Couscous/pull/232 is merged
vendor/bin/couscous deploy --repository "${repository}" 2>&1 | sed "s/${GITHUB_TOKEN}/<redacted>/g" || true
