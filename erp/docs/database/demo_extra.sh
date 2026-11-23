#!/usr/bin/env bash
# =============================================================================
# Adds "work in progress" demo data on top of tests/e2e/full_flow.sh, through
# the real app (so stock, ledgers and workflow stay consistent):
#   - open leads with follow-ups (new / interested / quotation sent)
#   - 4 active projects at different stages: survey, dispatch, installation, DCR
#
# Run between the two other steps (from erp/):
#   tests/e2e/full_flow.sh
#   docs/database/demo_extra.sh
#   docs/database/build_demo_sql.sh
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")/../.."

DB=${E2E_DB:-solar_e2e}
DBUSER=${E2E_DBUSER:-solar}
DBPASS=${E2E_DBPASS:-solarpw}
PORT=${E2E_PORT:-8091}
B="localhost:$PORT"
W=$(mktemp -d)
D=$(date +%F)
PW='Passw0rd!x'   # e2e staff password (replaced by Demo@12345 in the build step)

q()    { mysql -N -B -u"$DBUSER" -p"$DBPASS" "$DB" -e "$1" 2>/dev/null; }
tok()  { curl -s -b "$1" -c "$1" "$B/$2" | grep -o 'name="csrf_test_name" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post() { local j=$1 p=$2 u=$3; shift 3; curl -s -b "$j" -c "$j" -o /dev/null -w '%{http_code}' -F "csrf_test_name=$(tok "$j" "$p")" "$@" "$B/$u"; echo; }
login() { rm -f "$1"; local t; t=$(tok "$1" login); curl -s -b "$1" -c "$1" -o /dev/null --data-urlencode "csrf_test_name=$t" --data-urlencode "email=$2" --data-urlencode "password=$3" "$B/login"; }
step() { printf '  • %s\n' "$1"; }

[ -f .env ] && cp .env "$W/env.backup"
cat > .env <<EOF
CI_ENVIRONMENT = development
app.baseURL = 'http://$B/'
database.default.hostname = localhost
database.default.database = $DB
database.default.username = $DBUSER
database.default.password = '$DBPASS'
database.default.DBDriver = MySQLi
EOF
restore() { pkill -f "spark serve --port $PORT" 2>/dev/null || true; [ -f "$W/env.backup" ] && cp "$W/env.backup" .env || rm -f .env; }
trap restore EXIT
(php spark serve --port "$PORT" > "$W/serve.log" 2>&1 &)
for _ in $(seq 30); do curl -s -o /dev/null "$B/login" && break; sleep 0.5; done

# tiny valid JPEG / PDF for the uploads each stage needs
php -r '$i=imagecreatetruecolor(64,48);imagefill($i,0,0,imagecolorallocate($i,30,90,160));imagejpeg($i,$argv[1]);' "$W/photo.jpg"
printf '%%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%%%EOF\n' > "$W/doc.pdf"

for u in admin ravi suresh store1 driver1 fitter1 eb1 acct1; do
  [ $u = admin ] && login "$W/$u" admin@e2e.test 'Admin#2026x' || login "$W/$u" "$u@e2e.test" "$PW"
done
M="$W/ravi"; AC="$W/acct1"; S="$W/store1"

echo "Open leads"
lead() { post "$M" admin/leads/new admin/leads/save -F name="$1" -F phone="$2" -F email="$3" -F city="$4" -F source="$5" -F customer_type="$6" -F required_kw="$7" -F monthly_bill="$8" -F next_followup_date="$D" >/dev/null; q "select max(id) from leads"; }
L=$(lead "Deepa Raghavan" 9840011223 deepa.r@example.com Coimbatore Website b2c 5 4500); step "lead $L Deepa Raghavan"
post "$M" admin/leads/$L admin/leads/$L/followup -F note="Site visit fixed for Saturday; wants 5 kW with subsidy" -F status=interested -F next_followup_date="$D" >/dev/null
L=$(lead "Green Valley Apartments" 9840022334 office@example.com Chennai "Walk-in" b2b 25 38000); step "lead $L Green Valley Apartments"
post "$M" admin/leads/$L admin/leads/$L/followup -F note="Quotation for 25 kW common-area rooftop sent to the association" -F status=follow_up -F next_followup_date="$D" >/dev/null
L=$(lead "Arun Prakash" 9840033445 "" Madurai "Facebook / Instagram" b2c 3 2800); step "lead $L Arun Prakash"
L=$(lead "Sri Balaji Rice Mill" 9840044556 accounts@example.com Tiruppur Referral b2b 50 95000); step "lead $L Sri Balaji Rice Mill"
post "$M" admin/leads/$L admin/leads/$L/followup -F note="Owner asked for payback calculation and loan options" -F status=interested -F next_followup_date="$D" >/dev/null

echo "Customers for the active projects"
custlead() { L=$(lead "$1" "$2" "$3" "$4" Referral "$5" "$6" 3500); post "$M" admin/leads/$L admin/leads/$L/convert -F type="$5" >/dev/null; q "select id from customers where lead_id=$L"; }
C1=$(custlead "Sri Murugan Spinning Mills" 9840055667 smsm@example.com Erode b2b 10); step "customer $C1 Sri Murugan Spinning Mills"
C2=$(custlead "Karthik Ramasamy" 9840066778 karthik.r@example.com Namakkal b2c 3); step "customer $C2 Karthik Ramasamy"
C3=$(q "select id from customers where name='Imported One'")       # → Priya Natarajan in the build step
C4=$(q "select id from customers where name='Imported Two Pvt Ltd'") # → Annai Foods Pvt Ltd
for c in $C2 $C3; do q "update customers set address='4, Anna Street', pincode='637001', eb_service_no='05-221-$c', eb_charges_by='customer', eb_charges_amount=4500 where id=$c"; done
q "update customers set address='SIDCO Industrial Estate', pincode='638001', eb_charges_by='company' where id in ($C1,$C4)"

invoice() { # customer subsidy kW kit rate
  post "$M" admin/invoices/new admin/invoices -F customer_id="$1" -F invoice_date="$D" -F due_date="$D" -F subsidy_type="$2" -F installation_type_id=1 -F capacity_kw="$3" -F kit_id="$4" -F create_project=1 -F print_format=cumulative \
    -F "items[0][description]=$3 kW On-grid Solar Power System – supply and installation" -F 'items[0][hsn]=8541' -F 'items[0][qty]=1' -F "items[0][rate]=$5" -F 'items[0][tax_percent]=12' >/dev/null
  q "select max(id) from invoices"
}
pay() { post "$AC" admin/invoices/$1 admin/invoices/$1/payments -F amount="$2" -F payment_date="$D" -F mode=upi -F account_id=1 -F reference="UPI$RANDOM" >/dev/null; }
proj() { q "select project_id from invoices where id=$1"; }
survey() { local P=admin/projects/$1
  post "$W/suresh" $P $P/upload -F stage=survey -F label=site_photo -F "files[]=@$W/photo.jpg" >/dev/null
  post "$W/suresh" $P $P/stage/survey -F action=complete -F survey_date="$D" -F roof_type=RCC -F roof_area=450 -F recommended_kw="$2" -F shading="No shading" >/dev/null; }
dispatch() { local P=admin/projects/$1 I=$2
  post "$AC" admin/invoices/$I admin/invoices/$I/finance -F finance_mode=cash -F print_format=cumulative >/dev/null
  post "$S" $P/dispatch $P/dispatch -F location_id=1 -F 'items[0][item_id]=1' -F 'items[0][qty]=6' -F 'items[1][item_id]=2' -F 'items[1][qty]=1' -F 'items[2][item_id]=4' -F 'items[2][qty]=1' -F 'items[3][item_id]=5' -F 'items[3][qty]=30' >/dev/null
  local DS; DS=$(q "select max(id) from dispatches")
  post "$S" admin/dispatches/$DS admin/dispatches/$DS/dispatch -F vehicle_no=TN33CD4455 -F driver_name=Kannan -F dispatched_through=TEMPO -F destination=SITE -F terms_of_delivery="Door delivery" >/dev/null
  post "$W/driver1" admin/dispatches/$DS admin/dispatches/$DS/deliver -F "photo=@$W/photo.jpg" -F delivery_note="Received at site" >/dev/null; }
install() { local P=admin/projects/$1
  for l in panel_photo net_meter_agreement panel_serial_photo; do post "$W/fitter1" $P $P/upload -F stage=installation -F label=$l -F "files[]=@$W/photo.jpg" >/dev/null; done
  post "$W/fitter1" $P $P/stage/installation -F action=complete -F installation_date="$D" -F inverter_make=Growatt -F inverter_serial="GW3K-$1" -F panel_make=Waaree -F panel_count=6 -F "panel_serials=W$1-1
W$1-2
W$1-3
W$1-4
W$1-5
W$1-6" >/dev/null; }

echo "Active projects"
I=$(invoice "$C1" non_subsidy 10 "" 520000); pay "$I" 100000; step "$(q "select project_no from projects where id=$(proj $I)") Sri Murugan Spinning Mills – survey"
I=$(invoice "$C3" subsidy 3 1 185000); pay "$I" 25000; P=$(proj "$I"); survey "$P" 3; step "$(q "select project_no from projects where id=$P") Priya – $(q "select current_stage from projects where id=$P")"
I=$(invoice "$C4" non_subsidy 3 1 175000); pay "$I" 90000; P=$(proj "$I"); survey "$P" 3; dispatch "$P" "$I"; step "$(q "select project_no from projects where id=$P") Annai Foods – $(q "select current_stage from projects where id=$P")"
I=$(invoice "$C2" subsidy 3 1 190000); pay "$I" 190000; P=$(proj "$I"); survey "$P" 3; dispatch "$P" "$I"; install "$P"; step "$(q "select project_no from projects where id=$P") Karthik – $(q "select current_stage from projects where id=$P")"

echo "Active projects now: $(q "select group_concat(concat(project_no,':',current_stage) separator ', ') from projects where status='active'")"
grep -iE "CRITICAL|ERROR" "writable/logs/log-$D.log" 2>/dev/null | tail -3 || true
