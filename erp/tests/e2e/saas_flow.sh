#!/usr/bin/env bash
# Multi-company (SaaS) mode: owner control panel and separation between companies.
#
#   bash tests/e2e/saas_flow.sh
#
# Creates a fresh database, starts the app with saas.enabled, then drives the owner
# panel (127.0.0.1) and two companies (suna.test, moonb.test) over HTTP.
# Needs MySQL/MariaDB (user with CREATE DATABASE) and PHP. Restores .env afterwards.
set -u
cd "$(dirname "$0")/../.."

DB=${E2E_DB:-solar_saas_e2e}
DBUSER=${E2E_DBUSER:-solar}
DBPASS=${E2E_DBPASS:-solarpw}
PORT=${E2E_PORT:-8091}
W=$(mktemp -d)
D=$(date +%F)
PASS=0; FAIL=0; FAILS=()
O="http://127.0.0.1:$PORT"; SA="http://suna.test:$PORT"; SB="http://moonb.test:$PORT"; GH="http://ghost.test:$PORT"
RES=(--resolve "suna.test:$PORT:127.0.0.1" --resolve "moonb.test:$PORT:127.0.0.1" --resolve "ghost.test:$PORT:127.0.0.1")

ok()   { PASS=$((PASS+1)); printf '  \e[32m✓\e[0m %s\n' "$1"; }
bad()  { FAIL=$((FAIL+1)); FAILS+=("$1"); printf '  \e[31m✗ %s\e[0m\n' "$1"; }
eq()   { [ "$2" == "$3" ] && ok "$1" || bad "$1 (expected '$3', got '$2')"; }
has()  { [[ "$2" == *"$3"* ]] && ok "$1" || bad "$1 (expected to contain '$3', got '${2:0:200}')"; }
hasnt() { [[ "$2" != *"$3"* ]] && ok "$1" || bad "$1 (should not contain '$3')"; }
section() { printf '\n\e[1m== %s\e[0m\n' "$1"; }
q()    { mysql -N -B -u"$DBUSER" -p"$DBPASS" "$DB" -e "$1" 2>/dev/null | tr '\t' '|'; }
c()    { curl -s "${RES[@]}" -b "$1" -c "$1" "${@:2}"; }                                     # c jar url [args]
get()  { c "$1" "$2"; }
code() { c "$1" -o /dev/null -w '%{http_code}' "$2"; }
tok()  { get "$1" "$2" | grep -o 'name="csrf_test_name" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post() { local j=$1 p=$2 u=$3; shift 3; c "$j" -o "$W/last.html" -w '%{http_code} %{redirect_url}' -F "csrf_test_name=$(tok "$j" "$p")" "$@" "$u"; }
flash() { get "$1" "$2" | grep -o 'alert alert-[a-z]* [^>]*>[^<]*' | sed 's/.*>//' | head -2 | tr '\n' ' '; }
login() { rm -f "$1"; local t; t=$(tok "$1" "$2/login"); c "$1" -o /dev/null -w '%{http_code}' --data-urlencode "csrf_test_name=$t" --data-urlencode "email=$3" --data-urlencode "password=$4" "$2/login"; }
ologin() { local t; t=$(tok "$1" "$O/owner/login"); c "$1" -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "csrf_test_name=$t" --data-urlencode "email=$2" --data-urlencode "password=$3" "$O/owner/login"; }

section "Setup: fresh database, multi-company .env, app server"
LOGF=writable/logs/log-$D.log; LOGSTART=$( [ -f "$LOGF" ] && wc -l < "$LOGF" || echo 0 )
mysql -u"$DBUSER" -p"$DBPASS" -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || { echo "Cannot create $DB"; exit 1; }
[ -f .env ] && cp .env "$W/env.backup"
cat > .env <<EOF
CI_ENVIRONMENT = development
app.baseURL = 'http://127.0.0.1:$PORT/'
database.default.hostname = localhost
database.default.database = $DB
database.default.username = $DBUSER
database.default.password = '$DBPASS'
database.default.DBDriver = MySQLi
saas.enabled = true
saas.ownerHosts = '127.0.0.1'
saas.baseDomain = 'test'
saas.ownerEmail = 'owner@sygma.test'
saas.ownerPassword = 'Owner#Pass2026'
saas.secret = 'saas-e2e-secret-0123456789'
EOF
restore() { pkill -f "spark serve --host 127.0.0.1 --port $PORT" 2>/dev/null; pkill -f -- "-S 127.0.0.1:$PORT" 2>/dev/null; [ -f "$W/env.backup" ] && cp "$W/env.backup" .env || rm -f .env; }
trap restore EXIT
(php spark serve --host 127.0.0.1 --port "$PORT" > "$W/serve.log" 2>&1 &)
for i in $(seq 1 30); do curl -s -o /dev/null "$O/owner/login" && break; sleep 1; done
OJ="$W/owner"

section "Owner panel: address and sign-in"
eq "owner panel sign-in page" "$(code "$OJ" "$O/owner/login")" "200"
eq "company pages are not served on the owner address (→ owner panel)" "$(c "$OJ" -o /dev/null -w '%{http_code} %{redirect_url}' "$O/admin")" "302 $O/owner"
eq "owner panel needs sign-in" "$(c "$OJ" -o /dev/null -w '%{redirect_url}' "$O/owner")" "$O/owner/login"
r=$(ologin "$OJ" owner@sygma.test 'wrong-password'); has "wrong owner password refused" "$(get "$OJ" "$O/owner/login")" "Wrong email or password"
eq "first sign-in with the owner from .env" "$(ologin "$OJ" owner@sygma.test 'Owner#Pass2026')" "303 $O/owner"
eq "owner login stored (hashed)" "$(q "select concat(count(*),'/',sum(password_hash like '\$2y\$%')) from saas_owners")" "1/1"
has "dashboard: no companies yet" "$(get "$OJ" "$O/owner")" "No companies yet"

section "Create two companies"
r=$(post "$OJ" "$O/owner/companies/new" "$O/owner/companies" -F name="Sun A Energy" -F code=suna -F domain=suna.test -F contact_name=Arun -F phone=9000000001 -F email=a@suna.test \
  -F state="Tamil Nadu" -F gstin=33ABCDE1234F1Z5 -F status=active -F plan=Standard -F expires_on="$(date -d '+1 year' +%F)" \
  -F 'on[leads]=1' -F 'on[quotations]=1' -F 'on[design]=1' -F 'on[projects]=1' -F 'on[inventory]=1' -F 'on[purchase]=1' -F 'on[accounts]=1' -F 'on[tally]=1' -F 'on[invoices.loan]=1' -F 'on[invoices.einvoice]=1' \
  -F admin_email=boss@suna.test -F admin_password='SunA#2026pw')
has "company A created → its page" "$r" "/owner/companies/"
AID=$(q "select id from saas_companies where code='suna'")
eq "company A row: active, prefix t_suna_" "$(q "select concat(status,'|',db_prefix,'|',domain) from saas_companies where code='suna'")" "active|t_suna_|suna.test"
eq "company A tables created with its prefix" "$(q "select count(*)>40 from information_schema.tables where table_schema='$DB' and table_name like 't\_suna\_%'")" "1"
eq "company A settings: name, GSTIN, state" "$(q "select group_concat(concat(\`key\`,'=',value) order by \`key\` separator ';') from t_suna_settings where \`key\` in ('companyName','companyGstin','companyState')")" "companyGstin=33ABCDE1234F1Z5;companyName=Sun A Energy;companyState=Tamil Nadu"
has "company A modules: AMC off (not ticked)" "$(q "select value from t_suna_settings where \`key\`='off'")" '"amc"'
r=$(post "$OJ" "$O/owner/companies/new" "$O/owner/companies" -F name="Moon B Solar" -F code=moonb -F domain=moonb.test -F state="Karnataka" -F status=trial \
  -F 'on[leads]=1' -F 'on[quotations]=1' -F 'on[projects]=1' -F 'on[inventory]=1' -F 'on[purchase]=1' -F 'on[accounts]=1' -F 'on[amc]=1' \
  -F admin_email=boss@moonb.test -F admin_password='MoonB#2026pw')
BID=$(q "select id from saas_companies where code='moonb'")
eq "company B created (trial, designs off)" "$(q "select status from saas_companies where code='moonb'")|$(q "select value like '%"design"%' from t_moonb_settings where \`key\`='off'")" "trial|1"
r=$(post "$OJ" "$O/owner/companies/new" "$O/owner/companies" -F name="Copy" -F code=suna -F domain=x.test -F admin_email=x@x.test -F admin_password='Abcdefgh1')
has "same company code refused" "$(get "$OJ" "$O/owner/companies/new")" "already used"
r=$(post "$OJ" "$O/owner/companies/new" "$O/owner/companies" -F name="Copy" -F code=other -F domain=suna.test -F admin_email=x@x.test -F admin_password='Abcdefgh1')
has "same address refused" "$(get "$OJ" "$O/owner/companies/new")" "already used"
r=$(post "$OJ" "$O/owner/companies/new" "$O/owner/companies" -F name="Bad" -F code="9bad" -F domain=bad.test -F admin_email=x@x.test -F admin_password='Abcdefgh1')
eq "invalid code refused, nothing created" "$(q "select count(*) from saas_companies")" "2"
eq "only owner tables have no prefix" "$(q "select count(*) from information_schema.tables where table_schema='$DB' and table_name not like 't\_%' and table_name not like 'saas\_%'")" "0"
P=$(get "$OJ" "$O/owner"); has "dashboard lists company A" "$P" "Sun A Energy"; has "…and company B" "$P" "Moon B Solar"

section "Each company at its own address"
eq "unknown address → company not found" "$(curl -s "${RES[@]}" -o "$W/g.html" -w '%{http_code}' "$GH/login")|$(grep -c 'Company not found' "$W/g.html")" "404|1"
has "company A login page shows its name" "$(get "$W/a" "$SA/login")" "Sun A Energy"
eq "company A Super Admin signs in" "$(login "$W/a" "$SA" boss@suna.test 'SunA#2026pw')" "303"
eq "company A admin works" "$(code "$W/a" "$SA/admin")" "200"
eq "company B Super Admin signs in" "$(login "$W/b" "$SB" boss@moonb.test 'MoonB#2026pw')" "303"
eq "A's login does not work at B" "$(login "$W/x" "$SB" boss@suna.test 'SunA#2026pw')|$(code "$W/x" "$SB/admin")" "303|302"
eq "owner panel is not served on a company address" "$(code "$W/a" "$SA/owner")" "404"

section "Data stays inside its company"
r=$(post "$W/a" "$SA/admin/leads/new" "$SA/admin/leads/save" -F name="Alpha Only Lead" -F phone=9111111111 -F customer_type=b2c -F required_kw=3 -F source=Referral -F next_followup_date="$D")
eq "lead saved in company A's table" "$(q "select count(*) from t_suna_leads where name='Alpha Only Lead'")|$(q "select count(*) from t_moonb_leads where name='Alpha Only Lead'")" "1|0"
has "A sees its lead" "$(get "$W/a" "$SA/admin/leads")" "Alpha Only Lead"
hasnt "B does not see A's lead" "$(get "$W/b" "$SB/admin/leads")" "Alpha Only Lead"
LID=$(q "select id from t_suna_leads where name='Alpha Only Lead'")
eq "B cannot open A's lead by its number" "$(code "$W/b" "$SB/admin/leads/$LID")" "404"
php -r '$i=imagecreatetruecolor(40,30);imagefill($i,0,0,imagecolorallocate($i,200,100,20));imagejpeg($i,$argv[1]);' "$W/logo.jpg"
r=$(post "$W/a" "$SA/admin/settings" "$SA/admin/settings" -F "logo=@$W/logo.jpg")
LP=$(q "select value from t_suna_settings where \`key\`='logoPath'")
eq "A's logo stored in A's own upload folder" "$( [ -n "$LP" ] && [ -f "writable/uploads/tenants/suna/$LP" ] && echo yes)" "yes"
eq "A's logo served at A, not at B" "$(code "$W/a" "$SA/branding/logo")|$(code "$W/b" "$SB/branding/logo")" "200|404"
SID=$(grep -P '\tsy_suna\t' "$W/a" | awk '{print $7}')
printf '127.0.0.1\tFALSE\t/\tFALSE\t0\tsy_moonb\t%s\n' "$SID" > "$W/stolen"
eq "A's session cookie replayed at B does not log in" "$(curl -s "${RES[@]}" -b "$W/stolen" -o /dev/null -w '%{http_code}' -H "Cookie: sy_moonb=$SID" "$SB/admin")" "302"

section "Modules per company"
eq "A: AMC switched off → back to dashboard" "$(c "$W/a" -o /dev/null -w '%{http_code}' "$SA/admin/amc")" "302"
has "…with a note" "$(get "$W/a" "$SA/admin")" "not enabled for your company"
eq "B: AMC on" "$(code "$W/b" "$SB/admin/amc")" "200"
eq "B: 3D designs off, A: on" "$(c "$W/b" -o /dev/null -w '%{http_code}' "$SB/admin/designs")|$(code "$W/a" "$SA/admin/designs")" "302|200"
eq "company admins cannot manage modules themselves" "$(code "$W/a" "$SA/admin/features")" "403"
CF=$(get "$OJ" "$O/owner/companies/$BID")
has "company page shows usage" "$CF" "Active users"; has "…and the Super Admin login" "$CF" "boss@moonb.test"; has "…database up to date" "$CF" "Up to date"
r=$(post "$OJ" "$O/owner/companies/$BID" "$O/owner/companies/$BID" -F name="Moon B Solar" -F domain=moonb.test -F status=trial -F max_users=1 \
  -F 'on[leads]=1' -F 'on[quotations]=1' -F 'on[design]=1' -F 'on[projects]=1' -F 'on[inventory]=1' -F 'on[purchase]=1' -F 'on[accounts]=1' -F 'on[amc]=1')
eq "owner switches designs on for B" "$(code "$W/b" "$SB/admin/designs")" "200"

section "Plan limits, suspend, expiry"
r=$(post "$W/b" "$SB/admin/users/new" "$SB/admin/users/save" -F username=extra -F email=extra@moonb.test -F password='Passw0rd!x' -F 'roles[]=marketing')
has "B (max 1 user) cannot add a second user" "$(get "$W/b" "$SB/admin/users/new")" "plan allows 1 active users"
eq "…no user added" "$(q "select count(*) from t_moonb_users")" "1"
r=$(post "$OJ" "$O/owner/companies/$AID" "$O/owner/companies/$AID" -F name="Sun A Energy" -F domain=suna.test -F status=suspended -F suspend_note="Payment pending. Call 7540096575." \
  -F 'on[leads]=1' -F 'on[design]=1' -F 'on[quotations]=1' -F 'on[projects]=1' -F 'on[inventory]=1' -F 'on[purchase]=1' -F 'on[accounts]=1')
eq "suspended company A: blocked with the owner's message" "$(c "$W/a" -o "$W/s.html" -w '%{http_code}' "$SA/admin")|$(grep -c 'Payment pending' "$W/s.html")" "403|1"
eq "…login page too" "$(code "$W/x" "$SA/login")" "403"
eq "B unaffected" "$(code "$W/b" "$SB/admin")" "200"
r=$(post "$OJ" "$O/owner/companies/$AID" "$O/owner/companies/$AID" -F name="Sun A Energy" -F domain=suna.test -F status=active -F expires_on="$(date -d '-1 day' +%F)" \
  -F 'on[leads]=1' -F 'on[design]=1' -F 'on[quotations]=1' -F 'on[projects]=1' -F 'on[inventory]=1' -F 'on[purchase]=1' -F 'on[accounts]=1')
eq "expired subscription → notice" "$(c "$W/a" -o "$W/s.html" -w '%{http_code}' "$SA/admin")|$(grep -q 'Subscription ended' "$W/s.html" && echo shown)" "403|shown"
has "dashboard counts it as expired" "$(get "$OJ" "$O/owner?show=expired")" "Sun A Energy"
r=$(post "$OJ" "$O/owner/companies/$AID" "$O/owner/companies/$AID" -F name="Sun A Energy" -F domain=suna.test -F status=active -F expires_on="$(date -d '+5 day' +%F)" \
  -F 'on[leads]=1' -F 'on[design]=1' -F 'on[quotations]=1' -F 'on[projects]=1' -F 'on[inventory]=1' -F 'on[purchase]=1' -F 'on[accounts]=1')
has "renewed: works again, admin warned 5 days before the end" "$(get "$W/a" "$SA/admin")" "subscription ends in 5 days"
has "owner dashboard: expiring soon" "$(get "$OJ" "$O/owner?show=expiring")" "Sun A Energy"

section "Support login, Super Admin reset, database updates"
r=$(post "$OJ" "$O/owner/companies/$BID" "$O/owner/companies/$BID/support"); LOC=${r#* }
has "support login goes to company B's address" "$LOC" "$SB/saas/support/"
eq "one-time link signs in as B's Super Admin" "$(curl -s "${RES[@]}" -c "$W/sup" -b "$W/sup" -o /dev/null -w '%{http_code} %{redirect_url}' "$LOC")" "302 $SB/admin"
eq "…and B's admin pages open" "$(code "$W/sup" "$SB/admin/users")" "200"
eq "the same link cannot be used again" "$(curl -s "${RES[@]}" -o /dev/null -w '%{http_code}' "$LOC")" "404"
eq "B's link does not work at A" "$(curl -s "${RES[@]}" -o /dev/null -w '%{http_code}' "${LOC/moonb.test/suna.test}")" "404"
eq "support login recorded in B's user log" "$(q "select count(*) from t_moonb_activity_log where action='support_login'")" "1"
r=$(post "$OJ" "$O/owner/companies/$AID" "$O/owner/companies/$AID/admin" -F admin_email=new@suna.test -F admin_password='Fresh#Pw2026')
eq "owner resets A's Super Admin login" "$(login "$W/a2" "$SA" new@suna.test 'Fresh#Pw2026')|$(code "$W/a2" "$SA/admin")" "303|200"
r=$(post "$OJ" "$O/owner" "$O/owner/migrate-all")
has "database updates for all companies" "$(get "$OJ" "$O/owner")" "2 companies up to date"
mysql -u"$DBUSER" -p"$DBPASS" "$DB" -e "DELETE FROM t_moonb_migrations WHERE class LIKE '%EInvoice'; ALTER TABLE t_moonb_invoices DROP COLUMN irn, DROP COLUMN irn_ack_no, DROP COLUMN irn_ack_date, DROP COLUMN irn_qr;" 2>/dev/null
has "company page shows the waiting update" "$(get "$OJ" "$O/owner/companies/$BID")" "1 update(s) waiting"
r=$(post "$OJ" "$O/owner/companies/$BID" "$O/owner/companies/$BID/migrate")
eq "owner runs it for B" "$(q "select count(*) from information_schema.columns where table_schema='$DB' and table_name='t_moonb_invoices' and column_name='irn'")" "1"

section "Command line (cron) in multi-company mode"
has "plain company commands are refused (must name the company)" "$(php spark amc:remind 2>&1)" "saas:run"
OUT=$(php spark saas:run "amc:remind" 2>&1); has "saas:run runs for each active company" "$OUT" "== moonb"; has "…including A" "$OUT" "== suna"
has "saas:list" "$(php spark saas:list 2>&1)" "moonb.test"
has "saas:migrate" "$(php spark saas:migrate 2>&1)" "suna: up to date"

section "Delete a company"
r=$(post "$OJ" "$O/owner/companies/$BID" "$O/owner/companies/$BID/delete" -F confirm=moonb)
has "active company cannot be deleted" "$(get "$OJ" "$O/owner/companies/$BID")" "Suspend the company first"
r=$(post "$OJ" "$O/owner/companies/$BID" "$O/owner/companies/$BID" -F name="Moon B Solar" -F domain=moonb.test -F status=suspended -F 'on[leads]=1')
r=$(post "$OJ" "$O/owner/companies/$BID" "$O/owner/companies/$BID/delete" -F confirm=wrong)
eq "wrong confirmation keeps it" "$(q "select count(*) from saas_companies where id=$BID")" "1"
r=$(post "$OJ" "$O/owner/companies/$BID" "$O/owner/companies/$BID/delete" -F confirm=moonb)
eq "deleted: row and all t_moonb_ tables gone" "$(q "select count(*) from saas_companies where id=$BID")|$(q "select count(*) from information_schema.tables where table_schema='$DB' and table_name like 't\_moonb\_%'")" "0|0"
eq "company A untouched" "$(q "select count(*) from t_suna_leads where name='Alpha Only Lead'")|$(code "$W/a2" "$SA/admin")" "1|200"
eq "deleted address is not found any more" "$(curl -s "${RES[@]}" -o /dev/null -w '%{http_code}' "$SB/login")" "404"

section "Owner logins and activity"
r=$(post "$OJ" "$O/owner/account" "$O/owner/account" -F action=add -F name=Partner -F email=partner@sygma.test -F password='Partner#Pw2026')
eq "second owner login added" "$(ologin "$W/o2" partner@sygma.test 'Partner#Pw2026')" "303 $O/owner"
L=$(get "$OJ" "$O/owner/log"); has "activity log: company created" "$L" "company created"; has "…support login" "$L" "support login"; has "…company deleted" "$L" "company deleted"
for i in 1 2 3 4 5; do ologin "$W/bf" owner@sygma.test "bad$i" >/dev/null; done
r=$(ologin "$W/bf" owner@sygma.test 'Owner#Pass2026'); has "5 wrong passwords → locked for 15 minutes" "$(get "$W/bf" "$O/owner/login")" "Too many wrong attempts"

section "Errors"
errs=$(tail -n +"$((LOGSTART+1))" "$LOGF" 2>/dev/null | grep -h "CRITICAL" | grep -v "ForbiddenException\|PageNotFound\|Broken pipe\|input ended\|SecurityException" | sort -u | head -5)
eq "no unexpected errors in today's log" "${errs:-none}" "none"

printf '\n\e[1mResult: %d passed, %d failed\e[0m\n' "$PASS" "$FAIL"
for f in "${FAILS[@]}"; do printf '  - %s\n' "$f"; done
[ "$FAIL" -eq 0 ]
