#!/usr/bin/env bash
set -euo pipefail

# Staging query-plan baseline check.
# Usage:
#   scripts/query-plan-check.sh [--path /var/www/html]

WP_PATH="${WP_PATH:-}"

if [[ $# -ge 2 && "$1" == "--path" ]]; then
  WP_PATH="$2"
fi

if ! command -v wp >/dev/null 2>&1; then
  echo "wp-cli is required to run query plan checks." >&2
  exit 1
fi

CMD=(wp eval-file tests/load/glamlux-query-explain.php)
if [[ -n "$WP_PATH" ]]; then
  CMD+=(--path "$WP_PATH")
fi

"${CMD[@]}"
