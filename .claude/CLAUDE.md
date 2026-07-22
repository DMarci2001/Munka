## graphify - Knowledge-Graph-Aware AI Coding <!-- graphify (replaces vexp) -->

### MANDATORY: use the graphify knowledge graph - do NOT grep or glob the codebase
For every task - bug fixes, features, refactors, debugging:
**call `/gsd:graphify query <term>` FIRST** to search the pre-built project
knowledge graph (`.planning/graphs/graph.json`, rebuilt via `/gsd:graphify build`).
It returns matched nodes grouped by type, with edge relationships and confidence
tiers (EXTRACTED/INFERRED/AMBIGUOUS) - graph-ranked context instead of a flat
text search.

Do NOT use grep or glob to search/explore the codebase. Only use Read when you
need exact raw content to edit a specific line. graphify only covers what's in
the graph as of the last build: for runtime logs, build output (dist/, .vite/,
node_modules/) or files outside the repo it has no answer - use Bash/Read there.

### Primary workflow
1. `/gsd:graphify status` - check freshness first; if STALE or commits_behind > 0, run `/gsd:graphify build`
2. `/gsd:graphify query <term>` - search by real identifier (ClassName, functionName) or file path
3. Make targeted changes based on the matched nodes/edges returned
4. `/gsd:graphify diff` after a build to see what changed since the last snapshot
5. Re-run `/gsd:graphify build` after substantial code changes so the graph doesn't drift from HEAD

### Subagent / Explore / Plan mode
- Subagents CAN and MUST call `/gsd:graphify query <term>` - always include real identifiers
- Do NOT spawn Agent(Explore) to freely grep - query the graph first,
  then pass the returned context into the agent prompt if needed

### Notes
- Config lives in `.planning/config.json` (`graphify.enabled: true`)
- If the graph has more than 5000 nodes, `graph.html` viz is skipped automatically - `graph.json`/`GRAPH_REPORT.md` are still produced and are what queries use
<!-- /graphify -->