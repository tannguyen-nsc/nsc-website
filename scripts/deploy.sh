#!/usr/bin/env bash
#
# Deploy frontend build and NSC theme frontend to remote via ssh nsc.
#
# 1) Copies frontend/build (exclude index.html, test.html) to nsc:/var/www/html/
# 2) Copies wp-content/themes/NSC-Software/frontend/build (exclude test.html) to nsc:/var/www/html/wp-content/themes/NSC-Software/frontend
#
# Prerequisites: rsync, ssh host "nsc" configured (e.g. in ~/.ssh/config)
# Run from repo root: bash scripts/deploy.sh   or   ./scripts/deploy.sh
#
set -e
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FRONTEND_BUILD="${REPO_ROOT}/frontend/build"
THEME_BUILD="${REPO_ROOT}/wp-content/themes/NSC-Software/frontend/build"
SSH_HOST="${DEPLOY_SSH_HOST:-nsc}"
REMOTE_WEBROOT="/var/www/html"

if [ ! -d "$FRONTEND_BUILD" ]; then
  echo "Error: frontend build not found at $FRONTEND_BUILD"
  echo "Run 'npm run build' in the frontend folder first."
  exit 1
fi

if [ ! -d "$THEME_BUILD" ]; then
  echo "Error: theme frontend build not found at $THEME_BUILD"
  echo "Run 'npm run build:sync' in the frontend folder first."
  exit 1
fi

echo "Deploying to $SSH_HOST:$REMOTE_WEBROOT ..."
echo ""

echo "[1/2] Frontend build -> $REMOTE_WEBROOT/ (excluding index.html, test.html)"
rsync -avz --delete \
  --exclude='index.html' \
  --exclude='test.html' \
  "$FRONTEND_BUILD/" \
  "$SSH_HOST:$REMOTE_WEBROOT/"

echo ""
echo "[2/2] NSC theme frontend build -> $REMOTE_WEBROOT/wp-content/themes/NSC-Software/frontend/ (excluding test.html)"
rsync -avz --delete \
  --exclude='test.html' \
  "$THEME_BUILD/" \
  "$SSH_HOST:$REMOTE_WEBROOT/wp-content/themes/NSC-Software/frontend/"

echo ""
echo "Deploy done."
