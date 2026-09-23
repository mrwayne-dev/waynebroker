#!/usr/bin/env bash
#
# ============================================================
# FILE: scripts/make_env_production.sh
# Build .env.production from the local .env.
#
# Differences from local, and why each one matters:
#   APP_URL     the live domain - it drives the NOWPayments ipn_callback_url
#               and every link in outbound email, so a stale value here sends
#               members to the wrong host
#   APP_ENV     production - this is also what makes seed_test_accounts.php
#               refuse to run
#   DB_*        the cPanel account database, not local root
#
# .env.production is gitignored. It is generated, uploaded by deploy.sh, and
# never committed.
# ============================================================
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

OUT=.env.production
DBPASS_FILE=.env.dbpass
DBNAME=uvammbciwx_maveren
DBUSER=uvammbciwx_michael

if [ ! -f .env ]; then echo "no local .env to base this on"; exit 1; fi

# The site uses the ACCOUNT-WIDE database user, not a per-site one.
#
# uvammbciwx_michael already owns the databases for every other site on this
# cPanel account, and the user asked for this site to join them. That changes
# what this script may do with the password: it must READ it and never write
# it. Generating one here, or calling set_password on the server, would rotate
# the credential out from under five other live sites sharing the same user.
#
# So .env.dbpass is now a cache of an EXISTING secret rather than the record of
# a generated one. It is populated from any sibling site's .env on the server:
#   ssh hostingserver2 "grep -E '^DB_PASS=' /home/uvammbciwx/<sibling>/.env | cut -d= -f2- | tr -d '\"'"
if [ ! -f "$DBPASS_FILE" ]; then
  echo "FAILED: $DBPASS_FILE not found."
  echo "It must hold the password for the account-wide user $DBUSER."
  echo "This script will NOT generate one: that user is shared with the other"
  echo "sites on the account and rotating it would break all of them."
  exit 1
fi
DBPASS=$(cat "$DBPASS_FILE")
if [ -z "$DBPASS" ]; then
  echo "FAILED: $DBPASS_FILE is empty."; exit 1
fi
echo "using the account-wide database user $DBUSER"

sed \
  -e 's|^APP_URL=.*|APP_URL=https://maverencapital.com|' \
  -e 's|^APP_ENV=.*|APP_ENV=production|' \
  -e "s|^DB_NAME=.*|DB_NAME=$DBNAME|" \
  -e "s|^DB_USER=.*|DB_USER=$DBUSER|" \
  -e "s|^DB_PASS=.*|DB_PASS=\"$DBPASS\"|" \
  .env > "$OUT"
chmod 600 "$OUT"

echo "wrote $OUT"
grep -E '^(APP_URL|APP_ENV|DB_NAME|DB_USER|I18N_DRIVER)=' "$OUT" | sed 's/^/  /'
echo "  DB_PASS=(${#DBPASS} chars, hidden, account-wide user)"
