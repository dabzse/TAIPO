#!/usr/bin/env bash
set -euo pipefail

# Build and push TAIPO Docker images to Docker Hub.
# Images covered:
# 1) taipo-php-fpm
# 2) taipo-web-nginx
# 3) taipo-web-apache
# 4) taipo-all-in-one-prod
# 5) taipo-all-in-one-dev
# 6) taipo-frontend-dev
#
# Usage:
#   HUB_USER=<dockerhub_user> ./tools/dockerhub_publish_all.sh
#
# Optional environment variables:
#   DATE_TAG=2026-04-22      # default: current date (YYYY-MM-DD)
#   PUSH_LATEST=1            # default: 1 (set to 0 to skip latest)
#   SKIP_LOGIN=0             # default: 0 (set to 1 to skip docker login)
#   REPO_PREFIX=taipo        # default: taipo

if [[ -z "${HUB_USER:-}" ]]; then
  echo "ERROR: HUB_USER is required."
  echo "Example: HUB_USER=mydockerhub ./tools/dockerhub_publish_all.sh"
  exit 1
fi

DATE_TAG="${DATE_TAG:-$(date +%Y-%m-%d)}"
PUSH_LATEST="${PUSH_LATEST:-1}"
SKIP_LOGIN="${SKIP_LOGIN:-0}"
REPO_PREFIX="${REPO_PREFIX:-taipo}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${ROOT_DIR}"

if [[ "${SKIP_LOGIN}" != "1" ]]; then
  echo "==> Docker Hub login"
  docker login
fi

build_and_push() {
  local image_name="$1"
  local dockerfile="$2"
  local context="$3"
  local target="${4:-}"

  local full_repo="${HUB_USER}/${REPO_PREFIX}-${image_name}"
  local date_ref="${full_repo}:${DATE_TAG}"
  local latest_ref="${full_repo}:latest"

  echo "==> Building ${date_ref}"
  if [[ -n "${target}" ]]; then
    docker build -f "${dockerfile}" --target "${target}" -t "${date_ref}" "${context}"
  else
    docker build -f "${dockerfile}" -t "${date_ref}" "${context}"
  fi

  if [[ "${PUSH_LATEST}" == "1" ]]; then
    docker tag "${date_ref}" "${latest_ref}"
  fi

  echo "==> Pushing ${date_ref}"
  docker push "${date_ref}"

  if [[ "${PUSH_LATEST}" == "1" ]]; then
    echo "==> Pushing ${latest_ref}"
    docker push "${latest_ref}"
  fi
}

# 1) PHP-FPM service image (production stage)
build_and_push "php-fpm" "Dockerfile.php" "." "production"

# 2) Nginx web image
build_and_push "web-nginx" "Dockerfile.nginx" "."

# 3) Apache web image
build_and_push "web-apache" "Dockerfile.apache" "."

# 4) All-in-one production image
build_and_push "all-in-one-prod" "Dockerfile" "."

# 5) All-in-one development image
build_and_push "all-in-one-dev" "Dockerfile.all-in-one.dev" "."

# 6) Frontend dev image (context: frontend)
build_and_push "frontend-dev" "Dockerfile.frontend.dev" "frontend"

echo ""
echo "Done. Published tag: ${DATE_TAG}"
echo "Repository prefix used: ${HUB_USER}/${REPO_PREFIX}-*"
