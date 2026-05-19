#!/usr/bin/env bash
# Tratok Hospitality API — curl recipes.
#
# Set TRATOK_API_KEY in your environment before running:
#   export TRATOK_API_KEY=0f3b1cda…
#
# Then run any function below, e.g.:
#   source examples.sh && me

set -euo pipefail
: "${TRATOK_API_KEY:?Set TRATOK_API_KEY in your environment first.}"

BASE="https://hospitality.tratok.net/api.php"
H_AUTH=(-H "X-API-Key: $TRATOK_API_KEY")
H_JSON=(-H "Content-Type: application/json")

# ─── Account ─────────────────────────────────────────────────────────────
me()           { curl -s "${H_AUTH[@]}" "$BASE?endpoint=me" | jq; }
rotate_key()   { curl -s -X POST "${H_AUTH[@]}" "$BASE?endpoint=api_key" | jq; }

# ─── Properties ──────────────────────────────────────────────────────────
list_properties() { curl -s "${H_AUTH[@]}" "$BASE?endpoint=properties&page=${1:-1}&limit=${2:-20}" | jq; }
get_property()    { curl -s "${H_AUTH[@]}" "$BASE?endpoint=properties&id=$1" | jq; }

# ─── Rooms ───────────────────────────────────────────────────────────────
list_rooms_for_property() { curl -s "${H_AUTH[@]}" "$BASE?endpoint=rooms&property_id=$1" | jq; }
get_room()                { curl -s "${H_AUTH[@]}" "$BASE?endpoint=rooms&id=$1" | jq; }

# ─── Pricing ─────────────────────────────────────────────────────────────
get_pricing()  { curl -s "${H_AUTH[@]}" "$BASE?endpoint=pricing&room_id=$1&from=${2:-}&to=${3:-}" | jq; }

set_price() {
    local room_id="$1" date="$2" price="$3"
    curl -s -X POST "${H_AUTH[@]}" "${H_JSON[@]}" \
         -d "{\"room_id\":$room_id,\"date\":\"$date\",\"price\":$price}" \
         "$BASE?endpoint=pricing" | jq
}

set_price_range() {
    local room_id="$1" start="$2" end="$3" price="$4"
    curl -s -X POST "${H_AUTH[@]}" "${H_JSON[@]}" \
         -d "{\"room_id\":$room_id,\"start_date\":\"$start\",\"end_date\":\"$end\",\"price\":$price}" \
         "$BASE?endpoint=pricing_bulk" | jq
}

# ─── Inventory ───────────────────────────────────────────────────────────
get_inventory() { curl -s "${H_AUTH[@]}" "$BASE?endpoint=inventory&room_id=$1&from=${2:-}&to=${3:-}" | jq; }

set_inventory() {
    local room_id="$1" date="$2" avail="$3"
    curl -s -X POST "${H_AUTH[@]}" "${H_JSON[@]}" \
         -d "{\"room_id\":$room_id,\"date\":\"$date\",\"available\":$avail}" \
         "$BASE?endpoint=inventory" | jq
}

set_inventory_range() {
    local room_id="$1" start="$2" end="$3" avail="$4"
    curl -s -X POST "${H_AUTH[@]}" "${H_JSON[@]}" \
         -d "{\"room_id\":$room_id,\"start_date\":\"$start\",\"end_date\":\"$end\",\"available\":$avail}" \
         "$BASE?endpoint=inventory_bulk" | jq
}

# ─── Activities + Restaurants ────────────────────────────────────────────
list_activities()   { curl -s "${H_AUTH[@]}" "$BASE?endpoint=activities&page=${1:-1}&limit=${2:-20}" | jq; }
get_activity()      { curl -s "${H_AUTH[@]}" "$BASE?endpoint=activities&id=$1" | jq; }
list_restaurants()  { curl -s "${H_AUTH[@]}" "$BASE?endpoint=restaurants&page=${1:-1}&limit=${2:-20}" | jq; }
get_restaurant()    { curl -s "${H_AUTH[@]}" "$BASE?endpoint=restaurants&id=$1" | jq; }

# ─── Bookings ────────────────────────────────────────────────────────────
list_bookings() {
    # usage: list_bookings [type] [status] [page] [limit]
    local type="${1:-}" status="${2:-}" page="${3:-1}" limit="${4:-20}"
    local q="endpoint=bookings&page=$page&limit=$limit"
    [[ -n "$type"   ]] && q+="&type=$type"
    [[ -n "$status" ]] && q+="&status=$status"
    curl -s "${H_AUTH[@]}" "$BASE?$q" | jq
}

get_booking() { curl -s "${H_AUTH[@]}" "$BASE?endpoint=bookings&id=$1" | jq; }

# ─── Revenue ─────────────────────────────────────────────────────────────
revenue() {
    # usage: revenue [from] [to] [type]
    local q="endpoint=revenue"
    [[ -n "${1:-}" ]] && q+="&from=$1"
    [[ -n "${2:-}" ]] && q+="&to=$2"
    [[ -n "${3:-}" ]] && q+="&type=$3"
    curl -s "${H_AUTH[@]}" "$BASE?$q" | jq
}

# ─── End-to-end demos ────────────────────────────────────────────────────
demo_setup_season() {
    # Open room 101 for the summer with USD 199/night, 5 units available.
    local room_id=101 start="2026-06-01" end="2026-08-31"
    set_price_range "$room_id" "$start" "$end" 199.00
    set_inventory_range "$room_id" "$start" "$end" 5
}

demo_block_dates() {
    # Maintenance window: room 101 unavailable for one week.
    set_inventory_range 101 "2026-09-15" "2026-09-21" 0
}

# If invoked directly with an argument, run that function:
if [[ "${BASH_SOURCE[0]}" == "${0}" && $# -gt 0 ]]; then
    "$@"
fi
