#!/bin/bash
# graphify-autorefresh: fires on Stop (end of an assistant turn). Checks
# whether the knowledge graph has fallen behind HEAD and, if so, kicks off a
# rebuild in the BACKGROUND (never blocks the hook or the next turn). A lock
# file prevents overlapping builds if several turns finish in quick succession.
PROJECT_DIR="${CLAUDE_PROJECT_DIR:-.}"
CONFIG="$PROJECT_DIR/.planning/config.json"
GRAPHS_DIR="$PROJECT_DIR/.planning/graphs"
LOCK="$GRAPHS_DIR/.autorefresh.lock"
LOG="$GRAPHS_DIR/.autorefresh.log"
GSD_TOOLS="$HOME/.claude/get-shit-done/bin/gsd-tools.cjs"
GRAPHIFY_BIN_DIR="/c/Users/dugal/AppData/Roaming/Python/Python313/Scripts"

# Always allow the turn to end — this hook only ever schedules background
# work and must never itself block or fail the Stop event.
finish() { exit 0; }

[ -f "$CONFIG" ] || finish
grep -q '"enabled"[[:space:]]*:[[:space:]]*true' "$CONFIG" 2>/dev/null || finish
[ -d "$GRAPHS_DIR" ] || finish
[ -f "$LOCK" ] && finish   # a build is already running from a previous turn

STATUS_JSON=$(node "$GSD_TOOLS" graphify status 2>/dev/null)
BEHIND=$(printf '%s' "$STATUS_JSON" | node -e "
let s='';process.stdin.on('data',d=>s+=d);
process.stdin.on('end',()=>{try{const j=JSON.parse(s);process.stdout.write(String(j.commits_behind||0))}catch(e){process.stdout.write('0')}});
" 2>/dev/null)
[ -z "$BEHIND" ] && finish
[ "$BEHIND" = "0" ] && finish

touch "$LOCK"
(
  export PATH="$GRAPHIFY_BIN_DIR:$PATH"
  cd "$PROJECT_DIR" || exit 1
  graphify update . \
    && cp graphify-out/graph.json .planning/graphs/graph.json \
    && { cp graphify-out/graph.html .planning/graphs/graph.html 2>/dev/null || true; } \
    && cp graphify-out/GRAPH_REPORT.md .planning/graphs/GRAPH_REPORT.md \
    && node "$GSD_TOOLS" graphify build snapshot
  rm -f "$LOCK"
) >> "$LOG" 2>&1 &
disown

finish
