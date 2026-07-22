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

### MANDATORY: always use the cheapest query form - this is about token savings, full stop
The entire point of using graphify over grep is to save tokens. Do NOT default
to the verbose form out of habit - always reach for whichever query form costs
fewest tokens for the question at hand:

1. **Prefer the raw CLI first**: `graphify query "<term>" --graph .planning/graphs/graph.json`
   (terse `NODE name [src=... loc=... community=...]` one-liners, 2000-token
   budget by default, explicit "N more nodes cut" notice when truncated - use
   `--budget N` to raise/lower it). This is the default choice for symbol/file
   lookups.
2. **`gsd-tools.cjs graphify query <term> --budget N`** (the JSON wrapper) only
   when you need its structured fields (community_name, confidence tiers,
   file_type) for something downstream that actually consumes them - and even
   then, always pass `--budget` (do not call it uncapped; uncapped was the
   default this session and it was not clearly cheaper than grep).
3. Uncapped/verbose JSON output is NOT the default anymore - treat an uncapped
   call as a mistake to avoid, not a neutral choice.
4. `status` / `build` / `diff` have no raw-CLI equivalent as convenient as the
   `gsd-tools.cjs` wrapper - keep using the wrapper for those.
5. Still capped by CSS being unindexable and by string-literal values (not
   declared symbols) often having no graph node at all - don't burn a second
   or third query variant hunting for something graphify structurally can't
   see; drop to Read/Bash promptly instead of iterating queries.

### Subagent / Explore / Plan mode
- Subagents CAN and MUST call `/gsd:graphify query <term>` - always include real identifiers
- Do NOT spawn Agent(Explore) to freely grep - query the graph first,
  then pass the returned context into the agent prompt if needed

### Notes
- Config lives in `.planning/config.json` (`graphify.enabled: true`)
- If the graph has more than 5000 nodes, `graph.html` viz is skipped automatically - `graph.json`/`GRAPH_REPORT.md` are still produced and are what queries use
- Hungarian accented queries now work: `~/.claude/get-shit-done/bin/lib/graphify.cjs`'s
  matcher (`seedAndExpand`/`foldAccents`) was patched (2026-07-22) to NFD-fold
  diacritics before matching, so an ASCII query like `fuggosegek` matches an
  accented label like `Eszköznyilvántartás — függőségek`. This is a GLOBAL
  patch (affects every project using gsd-graphify, not just this repo) and
  lives outside git - a future `gsd`/graphify auto-update could silently
  overwrite it. A backup of the pre-patch file is at
  `~/.claude/get-shit-done/bin/lib/graphify.cjs.bak-preaccentfix`. If accented
  queries stop matching again, re-check whether an update clobbered the patch.
- **CSS is never indexed - do not attempt graphify queries for CSS selectors/rules.**
  `graphifyy` has no CSS tree-sitter grammar at all (checked its package extras:
  sql/pascal/terraform/etc. exist, css does not) - this is a hard tool limitation,
  not something more querying or accent-folding can fix. Go straight to Read/Bash
  for anything CSS-related; this is an explicit, permanent carve-out from the
  "always graphify first" mandate above, same tier as runtime logs/build output.

### Auto-refresh (Stop hook)
`.claude/hooks/graphify-autorefresh.sh` runs on the `Stop` event (end of each
assistant turn). It checks `graphify status`'s `commits_behind` field and, if
non-zero, kicks off a full rebuild in the BACKGROUND (non-blocking, lock-filed
via `.planning/graphs/.autorefresh.lock` to avoid overlapping builds). This
means the graph should self-heal within a turn or two after code changes -
manual `/gsd:graphify build` is a fallback for immediate freshness, not the
only way to refresh. Rebuild logs land in `.planning/graphs/.autorefresh.log`
if something needs debugging.
<!-- /graphify -->

## MANDATORY: list changed files after every modification

This repo is deployed by hand over FTP to a remote server - there is no
git-based deploy pipeline the user can rely on. After EVERY task that
modifies, creates, or deletes files in the codebase (code edits, rebuilds,
config changes, anything), end your response with an explicit list of every
file touched, so the user knows exactly what to re-upload via FTP.

- List modified files and newly created files separately from deleted files.
- Use paths relative to the repo root (e.g. `library/eszkoznyilvantartas/backend/lib/Ops.php`),
  since that's what maps directly to the FTP destination.
- Include build/compiled output that changed as a result of a source edit
  (e.g. `public/js/eszkoznyilvantartas/assets/*.js` after a frontend rebuild),
  not just the source file - the user needs the deployable artifact listed too.
- Do this even for small changes (a one-line fix still needs to be listed).
- `git status --porcelain` (or `git diff --stat`) is the reliable source for
  this list - don't rely on memory of what was touched during a long task.

### Rebuild reminder: eszkoznyilvantartas frontend
The `eszkoznyilvantartas` React/Vite app has a source/build split:
source lives at `public/js/eszkoznyilvantartas-src/`, and `npm run build`
there compiles it into `public/js/eszkoznyilvantartas/` (the actually-served
bundle, tracked in git) - only the compiled output is what the browser loads.

- If ANY file under `public/js/eszkoznyilvantartas-src/` was touched, explicitly
  tell the user a rebuild is needed (`cd public/js/eszkoznyilvantartas-src && npm run build`),
  and run it yourself before reporting the task done, so the compiled output
  in `public/js/eszkoznyilvantartas/` is already current and listed in the
  changed-files list.
- If NO file under `public/js/eszkoznyilvantartas-src/` was touched (e.g. only
  PHP backend or unrelated files changed), explicitly say a rebuild is NOT
  needed - don't leave it ambiguous, the user has asked this before.