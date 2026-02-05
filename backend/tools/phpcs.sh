#!/bin/sh

dir=$(dirname "$0")
php -d display_errors=0 "$dir/../vendor/bin/phpcs" "$@"
