#!/usr/bin/env bash
set -euo pipefail

scripts/with_xdebug_disabled.sh vendor/bin/infection \
    --threads=$(nproc) \
    --coverage=test-results/phpunit/ \
    --min-msi=97 \
    --min-covered-msi=100
