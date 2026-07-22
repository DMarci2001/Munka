#!/bin/bash
# graphify-guard: block Grep/Glob when the project knowledge graph is enabled
# and built. (Formerly vexp-guard; vexp has been replaced by gsd-graphify.)
CONFIG="${CLAUDE_PROJECT_DIR:-.}/.planning/config.json"
GRAPH="${CLAUDE_PROJECT_DIR:-.}/.planning/graphs/graph.json"

deny() {
  printf '{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"graphify knowledge graph is enabled. Use /gsd:graphify query <term> instead of Grep/Glob."}}'
  exit 0
}
allow() {
  printf '{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"allow","permissionDecisionReason":"graphify not enabled/built, allowing direct search fallback."}}'
  exit 0
}

[ -f "$CONFIG" ] || allow
grep -q '"enabled"[[:space:]]*:[[:space:]]*true' "$CONFIG" 2>/dev/null || allow
[ -f "$GRAPH" ] || allow

deny
