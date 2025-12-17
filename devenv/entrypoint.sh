#!/usr/bin/env bash
set -euo pipefail

/usr/local/bin/magento-setup.sh

exec "$@"
