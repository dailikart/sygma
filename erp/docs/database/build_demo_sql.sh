#!/usr/bin/env bash
# =============================================================================
# Builds docs/database/sygma_demo.sql: the full ERP database pre-filled with
# realistic demo data (leads, customers, invoices, payments, projects at every
# stage, stock, purchases, AMC, accounts) for the Sygma Solar Suite live demo.
#
# How it works:
#   1. tests/e2e/full_flow.sh drives the real app end to end and leaves a
#      fully populated database (default name solar_e2e).
#   2. This script copies that database, replaces test-only values with demo
#      ones (logins, emails, names, company settings), clears logs, and dumps it.
#
# Usage (from erp/):
#   tests/e2e/full_flow.sh
#   docs/database/build_demo_sql.sh
#
# Re-run both after any new migration so the demo matches the code.
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")/../.."

SRC=${E2E_DB:-solar_e2e}
DBUSER=${E2E_DBUSER:-solar}
DBPASS=${E2E_DBPASS:-solarpw}
TMP=sygma_demo_build
OUT=docs/database/sygma_demo.sql
UPL=docs/database/demo_uploads

# Demo password for every login: Demo@12345  (bcrypt, cost 12)
HASH='$2y$12$ndMjRV/PdxduxjUnI.iqzOV22KN8bKS4SUT5uNMcZow3rNIPigR6O'

my() { mysql -u"$DBUSER" -p"$DBPASS" "$@"; }

my -e "DROP DATABASE IF EXISTS $TMP; CREATE DATABASE $TMP CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysqldump -u"$DBUSER" -p"$DBPASS" --no-tablespaces "$SRC" | my "$TMP"

my "$TMP" <<SQL
-- Logins: demo@sygmasolar.com is the Super Admin; one login per role.
UPDATE auth_identities SET secret = 'demo@sygmasolar.com' WHERE type = 'email_password' AND secret = 'admin@e2e.test';
UPDATE auth_identities SET secret = REPLACE(secret, '@e2e.test', '@demo.sygmasolar.com') WHERE type = 'email_password';
UPDATE auth_identities SET secret2 = '$HASH', force_reset = 0 WHERE type = 'email_password';
DELETE FROM auth_identities WHERE type <> 'email_password';
UPDATE users SET username = 'demo', active = 1, status = NULL, status_message = NULL WHERE id = 1;
UPDATE users SET active = 1;

-- Company shown inside the demo.
UPDATE settings SET value = 'Sygma Solar Suite Demo' WHERE class LIKE '%Solar' AND \`key\` IN ('companyName', 'mailFromName');
UPDATE settings SET value = 'info@sygmasolar.com'    WHERE class LIKE '%Solar' AND \`key\` = 'companyEmail';
UPDATE settings SET value = 'noreply@sygmasolar.com' WHERE class LIKE '%Solar' AND \`key\` = 'mailFromEmail';
UPDATE settings SET value = '0' WHERE class LIKE '%Solar' AND \`key\` = 'smtpEnabled';
UPDATE settings SET value = ''  WHERE class LIKE '%Solar' AND \`key\` IN ('smtpHost', 'smtpPass', 'smtpUser', 'logoPath', 'signaturePath');
UPDATE settings SET value = 'solar' WHERE class LIKE '%Solar' AND \`key\` = 'theme';

-- Test-only names → realistic demo names.
UPDATE customers SET name = 'Priya Natarajan', city = 'Salem' WHERE name = 'Imported One';
UPDATE customers SET name = 'Annai Foods Pvt Ltd', company = 'Annai Foods Pvt Ltd' WHERE name = 'Imported Two Pvt Ltd';
UPDATE customers SET name = 'Senthil Kumar' WHERE name = 'Delete Me';
UPDATE suppliers SET name = 'Kerala Solar Traders' WHERE name = 'Imported Supplier';
UPDATE items SET name = 'Earthing Kit', sku = 'EARTH' WHERE name = 'Ledger Clear Test';
UPDATE items SET name = 'Poly Panel 330W (discontinued)', sku = 'PNL330' WHERE name = 'Delete Test Panel';

-- Emails in business data.
UPDATE customers SET email = REPLACE(REPLACE(email, '@e2e.test', '@example.com'), '@kmc.test', '@example.com') WHERE email IS NOT NULL;
UPDATE leads     SET email = REPLACE(REPLACE(email, '@e2e.test', '@example.com'), '@kmc.test', '@example.com') WHERE email IS NOT NULL;

-- Logs and queues from the test run.
TRUNCATE activity_log;
TRUNCATE auth_logins;
TRUNCATE auth_remember_tokens;
TRUNCATE message_log;
TRUNCATE queue_jobs;
TRUNCATE queue_jobs_failed;
SQL

{
cat <<'HDR'
-- ============================================================================
-- Sygma Solar Suite ERP – DEMO database (MySQL 8 / MariaDB 10.6+)
--
-- Every ERP table plus realistic demo data: leads, customers (B2B/B2C),
-- invoices, part payments, projects at different workflow stages, stock,
-- purchases, supplier payments, AMC contracts and visits, accounts vouchers.
--
-- !! FOR AN EMPTY DATABASE ONLY !! (it drops and recreates every table)
--
-- 1. Create an EMPTY database, e.g.:
--      CREATE DATABASE sygma_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- 2. phpMyAdmin → select it → Import → this file → Go
--    (or:  mysql -u USER -p sygma_demo < sygma_demo.sql)
-- 3. Copy docs/database/demo_uploads/* into writable/uploads/
--    (project photos, customer documents) – or run:  php spark demo:reset
--
-- Logins (password for all: Demo@12345)
--   demo@sygmasolar.com                 Super Admin (sees everything)
--   ravi@demo.sygmasolar.com            Marketing
--   suresh@demo.sygmasolar.com          Surveyor
--   store1@demo.sygmasolar.com          Store
--   driver1@demo.sygmasolar.com         Delivery
--   fitter1@demo.sygmasolar.com         Installer
--   eb1@demo.sygmasolar.com             EB / Liaison
--   acct1@demo.sygmasolar.com           Accounts
--   service1@demo.sygmasolar.com        Service / AMC
--   mgr@demo.sygmasolar.com             Admin (manager)
--   kumar@demo.sygmasolar.com           Store + Delivery
--
-- Generated by docs/database/build_demo_sql.sh – do not edit by hand.
-- ============================================================================

HDR
mysqldump -u"$DBUSER" -p"$DBPASS" --no-tablespaces --skip-comments --skip-dump-date "$TMP" | grep -v '^/\*M!999999'
} > "$OUT"

my -e "DROP DATABASE $TMP;"

# Uploaded files the demo data refers to.
rm -rf "$UPL" && mkdir -p "$UPL"
# Only files the demo data refers to (earlier test runs leave orphans behind).
grep -oE "'[A-Za-z0-9_./-]+\.(jpg|jpeg|png|pdf|webp)'" "$OUT" | tr -d "'" | sort -u | while read -r f; do
  [ -f "writable/uploads/$f" ] && (cd writable/uploads && cp --parents "$f" "../../$UPL/")
done

if grep -qE '@e2e\.test|\.test'"'" "$OUT"; then echo "WARNING: test-only values left in $OUT"; grep -oE '[^ ,(]*\.test' "$OUT" | sort -u; fi
echo "Wrote $OUT ($(du -h "$OUT" | cut -f1)) and $UPL ($(find "$UPL" -type f | wc -l) files)."
