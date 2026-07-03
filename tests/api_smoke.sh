#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-http://localhost:8080}"
ROUTE_PREFIX="${ROUTE_PREFIX:-}"

get() {
  curl -fsS "${BASE_URL}${ROUTE_PREFIX}?route=$1"
}

post() {
  curl -fsS -X POST "${BASE_URL}${ROUTE_PREFIX}?route=$1" \
    -H "Content-Type: application/json" \
    -d "$2"
}

echo "==> GET /mystery/status"
STATUS_JSON=$(get "/mystery/status")
echo "$STATUS_JSON" | grep -q '"status": "ok"'

echo "==> GET /mystery/scenario"
SCENARIO_JSON=$(get "/mystery/scenario")
echo "$SCENARIO_JSON" | grep -q '"scenario_id"'
echo "$SCENARIO_JSON" | grep -q '"victim"'
echo "$SCENARIO_JSON" | grep -q '"weapons_pool"'

if echo "$SCENARIO_JSON" | grep -q '"culprit"'; then
  echo "ERREUR: la solution (culprit) ne doit pas être exposée" >&2
  exit 1
fi

SCENARIO_ID=$(php -r '$j=json_decode(file_get_contents("php://stdin"), true); echo $j["scenario_id"];' <<< "$SCENARIO_JSON")
SCENARIO_DATE=$(php -r '$j=json_decode(file_get_contents("php://stdin"), true); echo $j["date"];' <<< "$SCENARIO_JSON")
SUSPECT=$(php -r '$j=json_decode(file_get_contents("php://stdin"), true); echo $j["suspects"][0]["name"];' <<< "$SCENARIO_JSON")
WEAPON=$(php -r '$j=json_decode(file_get_contents("php://stdin"), true); echo $j["weapons_pool"][0];' <<< "$SCENARIO_JSON")
ROOM=$(php -r '$j=json_decode(file_get_contents("php://stdin"), true); echo $j["rooms"][0]["name"];' <<< "$SCENARIO_JSON")

echo "==> POST /mystery/solve (mauvaise réponse)"
WRONG_JSON=$(post "/mystery/solve" "{\"player\":\"Test\",\"suspect\":\"$SUSPECT\",\"weapon\":\"$WEAPON\",\"room\":\"$ROOM\",\"scenario_id\":\"$SCENARIO_ID\",\"scenario_date\":\"$SCENARIO_DATE\",\"clues_found\":1,\"time_seconds\":60}")
echo "$WRONG_JSON" | grep -q '"correct": false'

echo "==> GET /mystery/leaderboard"
LEADERBOARD_JSON=$(get "/mystery/leaderboard")
echo "$LEADERBOARD_JSON" | grep -q '\['

echo "OK — Tous les tests API ont réussi."