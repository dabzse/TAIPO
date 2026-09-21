#!/usr/bin/env bash
# TAIPO Setup & TAWOS Configuration Script
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
php "${SCRIPT_DIR}/tools/setup.php" "$@"
