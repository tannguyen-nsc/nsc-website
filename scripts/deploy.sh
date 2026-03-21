#!/usr/bin/env bash
#
# Deploy frontend build and NSC theme frontend to remote via ssh nsc.
#
# 1) Copies frontend/build (exclude index.html, test.html) to nsc:/var/www/html/
# 2) Copies wp-content/themes/NSC-Software/frontend/build (exclude test.html)
#    to nsc:/var/www/html/wp-content/themes/NSC-Software/frontend
#
# Uses rsync when available; otherwise tar + ssh (e.g. Windows Git Bash without rsync).
# Prerequisites: ssh host "nsc" configured; tar (built-in on macOS/Linux; Windows 10+)
#
# Run from repo root: bash scripts/deploy.sh   or   ./scripts/deploy.sh
#
set -e
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FRONTEND_BUILD="${REPO_ROOT}/frontend/build"
THEME_BUILD="${REPO_ROOT}/wp-content/themes/NSC-Software/frontend/build"
SSH_HOST="${DEPLOY_SSH_HOST:-nsc}"
REMOTE_WEBROOT="/var/www/html"
REMOTE_THEME="${REMOTE_WEBROOT}/wp-content/themes/NSC-Software/frontend"

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

deploy_frontend_rsync() {
  rsync -avz --delete \
    --exclude='index.html' \
    --exclude='test.html' \
    "$FRONTEND_BUILD/" \
    "$SSH_HOST:$REMOTE_WEBROOT/"
}

deploy_theme_rsync() {
  rsync -avz --delete \
    --exclude='test.html' \
    "$THEME_BUILD/" \
    "$SSH_HOST:$REMOTE_THEME/"
}

deploy_frontend_tar() {
  tar -cf - --exclude='index.html' --exclude='test.html' -C "$FRONTEND_BUILD" . \
    | ssh "$SSH_HOST" "cd $REMOTE_WEBROOT && tar -xf -"
}

deploy_theme_tar() {
  ssh "$SSH_HOST" "mkdir -p $REMOTE_THEME"
  tar -cf - --exclude='test.html' -C "$THEME_BUILD" . \
    | ssh "$SSH_HOST" "cd $REMOTE_THEME && tar -xf -"
}

echo "Deploying to $SSH_HOST:$REMOTE_WEBROOT ..."
echo ""

if command -v rsync >/dev/null 2>&1; then
  echo "Using rsync."
  echo ""
  echo "[1/2] Frontend build -> $REMOTE_WEBROOT/ (excluding index.html, test.html)"
  deploy_frontend_rsync
  echo ""
  echo "[2/2] NSC theme frontend build -> $REMOTE_THEME/ (excluding test.html)"
  deploy_theme_rsync
else
  echo "rsync not found; using tar + ssh."
  echo ""
  echo "[1/2] Frontend build -> $REMOTE_WEBROOT/ (excluding index.html, test.html)"
  deploy_frontend_tar
  echo ""
  echo "[2/2] NSC theme frontend build -> $REMOTE_THEME/ (excluding test.html)"
  deploy_theme_tar
fi

echo ""
echo "Deploy done."
