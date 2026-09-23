#!/usr/bin/env bash
#
# ============================================================
# FILE: scripts/deploy.sh
# Deploy Maveren Capital to hostingserver2 (Spaceship cPanel).
#
# Idempotent: safe to re-run. Creates what is missing, updates what
# changed, and never touches any other site on the account.
#
#   ./scripts/deploy.sh --dry-run    show what would transfer, change nothing
#   ./scripts/deploy.sh              deploy
#   ./scripts/deploy.sh --files-only skip the database and cron steps
#
# REQUIRES a working SSH agent. The key is passphrase-protected and held
# by the GNOME keyring; if it has locked, `ssh hostingserver2 true` fails
# with "agent refused operation" and this script will stop before doing
# anything. Unlock with:  ssh-add ~/.ssh/id_ed25519
# ============================================================
set -euo pipefail

HOST="hostingserver2"
DOCROOT="/home/uvammbciwx/maverencapital.com"
DB="uvammbciwx_maveren"
DBUSER="uvammbciwx_michael"   # ACCOUNT-WIDE user, shared with the other sites
LOCAL="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

DRY=""; FILES_ONLY=""
for a in "$@"; do
  case "$a" in
    --dry-run)    DRY="--dry-run" ;;
    --files-only) FILES_ONLY=1 ;;
    *) echo "unknown flag: $a"; exit 2 ;;
  esac
done

say() { printf '\n\033[1m== %s\033[0m\n' "$1"; }

# ---------------------------------------------------------------
say "Preflight"
# ---------------------------------------------------------------
if ! ssh -o BatchMode=yes -o ConnectTimeout=15 "$HOST" true 2>/dev/null; then
  echo "FAILED: cannot authenticate to $HOST."
  echo "The key is passphrase-protected and the agent is probably locked."
  echo "Run:  ssh-add ~/.ssh/id_ed25519"
  exit 1
fi
echo "ssh ok: $(ssh -o BatchMode=yes "$HOST" 'echo $(whoami)@$(hostname)')"

# .env.production is REGENERATED every deploy, never reused as found.
#
# The first deploy shipped a stale one: it had been generated before the
# correct SpaceMail password was put into .env, so it still carried the old
# SMTP_PASS. With SMTP_PASS required at boot, deploying it would have taken
# the site down. A generated file that silently drifts from its source is a
# trap, so the only safe version is the one made seconds ago.
if [ ! -x "$LOCAL/scripts/make_env_production.sh" ]; then
  echo "FAILED: scripts/make_env_production.sh missing or not executable."
  exit 1
fi
echo "regenerating .env.production from .env"
( cd "$LOCAL" && ./scripts/make_env_production.sh >/dev/null ) || {
  echo "FAILED: could not generate .env.production"; exit 1; }

# Cheap guard against shipping a placeholder: every value the app requires at
# boot must be non-empty, or the site 500s on the first request instead of
# failing here where it is obvious.
for k in APP_URL DB_NAME DB_USER DB_PASS SMTP_HOST SMTP_USER SMTP_PASS SMTP_FROM \
         NOWPAYMENTS_API_KEY NOWPAYMENTS_IPN_SECRET ADMIN_INVITE_CODE; do
  v=$(sed -n "s/^$k=//p" "$LOCAL/.env.production" | head -1 | tr -d '"')
  if [ -z "$v" ]; then
    echo "FAILED: $k is empty in .env.production - refusing to deploy."
    exit 1
  fi
done
echo "  all required keys present"

# ---------------------------------------------------------------
say "Files"
# ---------------------------------------------------------------
# --delete keeps the remote a true mirror, but the excludes below are what
# stop it deleting live member uploads, the translation cache and the logs -
# none of which exist locally. Getting an exclude wrong here destroys member
# KYC documents, so each one is deliberate.
rsync -az --human-readable --info=stats1 $DRY \
  --delete \
  --exclude '.git/' \
  --exclude '.gitignore' \
  --exclude '.env' \
  --exclude '.env.local' \
  --exclude '.env.production' \
  --exclude 'node_modules/' \
  --exclude 'shots/' \
  --exclude '_planning/' \
  --exclude 'scripts/deploy.sh' \
  --exclude 'logs/' \
  --exclude 'cache/i18n/' \
  --exclude 'uploads/kyc/*/' \
  --exclude 'uploads/profiles/*' \
  --exclude 'uploads/contacts/*' \
  --exclude 'uploads/contact/*' \
  --exclude 'uploads/products/*' \
  --exclude 'dbschema/deposit_addresses_live.sql' \
  "$LOCAL/" "$HOST:$DOCROOT/"

# The live wallet addresses are gitignored, so rsync skips them above. They
# still have to reach the server to be imported - sent separately and removed
# again once applied.
if [ -z "$DRY" ]; then
  scp -q "$LOCAL/dbschema/deposit_addresses_live.sql" "$HOST:$DOCROOT/dbschema/"
  scp -q "$LOCAL/.env.production" "$HOST:$DOCROOT/.env"
  ssh "$HOST" "chmod 600 $DOCROOT/.env"
  echo "env + wallet addresses uploaded"
fi

# ---------------------------------------------------------------
if [ -z "$FILES_ONLY" ] && [ -z "$DRY" ]; then
say "Directories and permissions"
ssh "$HOST" bash -s <<REMOTE
set -e
cd "$DOCROOT"
mkdir -p logs cache/i18n uploads/kyc uploads/profiles
# PHP runs as the account owner on cPanel, so 0750 is enough - unlike the
# local Apache box where www-data is a different user.
chmod 0750 uploads/kyc
chmod 0755 logs cache cache/i18n uploads uploads/profiles
echo "  directories ready"
REMOTE

# ---------------------------------------------------------------
say "Database"
# WHAT WENT WRONG THE FIRST TIME THIS RAN.
#
# This step created the DATABASE and stopped there. It never created the
# database USER and never granted it anything, so migrate.php could not
# connect, and getPDO() exits on PDOException - which meant the live login
# endpoint answered with an empty body. It also printed "importing schema"
# while only running `migrate.php --status`, which reports and changes
# nothing, and swallowed the failure with `|| true`. Three ways to look
# successful while having done nothing.
#
# The password is read from .env.production and sent inside the heredoc body,
# which travels over the SSH channel rather than argv, so it does not appear
# in a local or remote process listing. The one exposure left is uapi's own
# invocation on the far side, which cPanel gives no way to avoid.
DBPASS=$(sed -n 's/^DB_PASS=//p' "$LOCAL/.env.production" | head -1 | sed 's/^"//; s/"$//')
if [ -z "$DBPASS" ]; then
  echo "FAILED: no DB_PASS in .env.production - run scripts/make_env_production.sh"
  exit 1
fi

ssh "$HOST" bash -s <<REMOTE
set -e
cd "$DOCROOT"

if uapi --output=jsonpretty Mysql list_databases 2>/dev/null | grep -q '"$DB"'; then
  echo "  database $DB exists"
else
  echo "  creating database $DB"
  uapi Mysql create_database name="$DB" >/dev/null
fi

# THIS SCRIPT MUST NEVER WRITE THIS USER'S PASSWORD.
#
# $DBUSER is the ACCOUNT-WIDE database user. It already owns the databases for
# arqoracapital, averoninvestments, crestvalebank, goldcrestmining and
# providencemining, all live on this same account. An earlier version of this
# block called `uapi Mysql set_password` whenever the user already existed, on
# the reasoning that .env should be authoritative. Against a per-site user that
# was merely blunt; against a shared one it would rotate the credential out
# from under five other sites in a single deploy, and each of them would fail
# exactly the way this one did - an empty response body naming nothing.
#
# The direction of authority is therefore INVERTED here: the SERVER holds the
# password and .env.dbpass caches a copy of it. If they disagree, the fix is to
# re-read it from a sibling's .env, never to overwrite the server.
if ! uapi --output=jsonpretty Mysql list_users 2>/dev/null | grep -q '"$DBUSER"'; then
  echo "  FAILED: account-wide user $DBUSER does not exist on this server."
  echo "  Refusing to create it - it is shared infrastructure, not this site's to make."
  exit 1
fi
echo "  user $DBUSER exists (account-wide, password left untouched)"

# Granting is additive and safe: it adds this database to the user's existing
# set without affecting any grant it already holds elsewhere.
echo "  granting $DBUSER on $DB"
uapi Mysql set_privileges_on_database user="$DBUSER" database="$DB" privileges='ALL PRIVILEGES' >/dev/null

# Fail early and legibly if the cached password no longer matches the server,
# rather than letting the baseline/migration steps below fail as access-denied.
if ! MYSQL_PWD="$DBPASS" mysql -u "$DBUSER" -N -B -e "SELECT 1" "$DB" >/dev/null 2>&1; then
  echo "  FAILED: $DBUSER cannot authenticate against $DB with the password in .env.dbpass."
  echo "  Re-read it from a sibling site rather than resetting it:"
  echo "    grep -E '^DB_PASS=' /home/uvammbciwx/arqoracapital.com/.env | cut -d= -f2- | tr -d '\"'"
  exit 1
fi
echo "  credentials verified"

# Baseline only into an EMPTY database. maveren_create.sql carries DROP TABLE
# statements, so running it against a populated one would take live member
# data with it.
#
# schema_migrations is excluded on purpose. migrate.php creates its ledger
# before it does anything else - INCLUDING under --status, which the help text
# calls "change nothing". So a database can hold that one table and no schema
# at all, which is precisely the state the first broken deploy left production
# in: --status created the ledger, then everything else failed on the missing
# grant. Counting it would read that database as populated, skip the baseline,
# and run migrations against nothing - the same broken install by a different
# route, and silently, since the step would report "1 tables present" as if
# that were fine. It only bites on the recovery path, which is exactly when
# someone is already in trouble.
TABLES=\$(MYSQL_PWD="$DBPASS" mysql -u "$DBUSER" -N -B -e \
  "SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema='$DB' AND table_name <> 'schema_migrations'" 2>/dev/null || echo 0)
if [ "\$TABLES" -eq 0 ]; then
  echo "  empty database - importing baseline schema"
  MYSQL_PWD="$DBPASS" mysql -u "$DBUSER" "$DB" < dbschema/maveren_create.sql
else
  echo "  \$TABLES tables present - skipping baseline, migrations only"
fi

echo "  applying migrations"
/usr/local/bin/php dbschema/migrate.php

if [ -f dbschema/deposit_addresses_live.sql ]; then
  echo "  applying live deposit addresses"
  MYSQL_PWD="$DBPASS" mysql -u "$DBUSER" "$DB" < dbschema/deposit_addresses_live.sql
fi

echo "  --- schema state ---"
/usr/local/bin/php dbschema/migrate.php --status | sed 's/^/    /'
REMOTE

# ---------------------------------------------------------------
say "Cron"
# Back up first, and only ADD our line. Other sites on this account have
# their own entries in the same crontab and must survive untouched.
ssh "$HOST" bash -s <<REMOTE
set -e
crontab -l > ~/crontab.backup.\$(date +%Y%m%d-%H%M%S) 2>/dev/null || true
LINE="0 1 * * * /usr/local/bin/php $DOCROOT/api/cron/investment_cron.php >> $DOCROOT/logs/cron.log 2>&1"
if crontab -l 2>/dev/null | grep -Fq "$DOCROOT/api/cron/investment_cron.php"; then
  echo "  cron entry already present"
else
  ( crontab -l 2>/dev/null; echo "\$LINE" ) | crontab -
  echo "  cron entry added"
fi
echo "  --- crontab now ---"
crontab -l | sed 's/^/    /'
REMOTE
fi

# ---------------------------------------------------------------
say "Verify"
for p in "" plans platform solutions about contact; do
  code=$(curl -s -o /dev/null -w '%{http_code}' -m 20 "https://maverencapital.com/$p" || echo "---")
  printf '  https://maverencapital.com/%-10s %s\n' "$p" "$code"
done

echo
echo "Done. Remaining manual step: point the NOWPayments IPN callback at"
echo "  https://maverencapital.com/api/payments/now_webhook.php"
