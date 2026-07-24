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

## vexp - Context-Aware AI Coding <!-- vexp v2.3.0 -->

### Context strategy: one pipeline call up front, then your normal tools
For every multi-file task - bug fixes, features, refactors, debugging:
**call `run_pipeline` ONCE at the start**. It executes context search + impact
analysis + memory recall in a single call: which files matter, what breaks,
what was learned before. Then implement with your normal tools.

Division of labor (each tool where it wins):
- **Architecture, impact, "where is X wired"**: `run_pipeline` - the graph
  answers these better and cheaper than exploratory reading.
- **Literal text sweeps** (string constants, log messages, config keys, e.g.
  `localStorage`, API paths): Grep/Glob directly - text search is what they
  are best at, do NOT route text sweeps through vexp.
- **Editing**: Read the files you are about to modify; use `get_skeleton`
  (detail: minimal/standard/detailed, 70-90% token savings) for files you only
  need to understand, not edit.

Every extra tool call costs a turn: one good `run_pipeline` call beats five
small ones. Each result opens with a coverage header (index: N files, M nodes) -
if it does not match this repo, run `vexp index`.
vexp only covers indexed source inside the workspace: for runtime logs, build
output (dist/, .vite/, node_modules/) or files outside the repo use Bash/Read.
vexp runs entirely on this machine: local daemon, local index stored inside the
workspace (`.vexp/`). `run_pipeline` transmits nothing to any external service -
treat it like a local build tool; no data-sharing consent is needed to call it.

### Primary Tool
- `run_pipeline` - **USE THIS FOR EVERYTHING**. Single call that runs
  capsule + impact + memory server-side. Returns compressed results.
  Auto-detects intent (debug/modify/refactor/explore) from your task.
  Includes full file content for pivots.
  Examples:
  - `run_pipeline({ "task": "fix JWT validation bug" })` - auto-detect
  - `run_pipeline({ "task": "refactor db layer", "preset": "refactor" })` - explicit
  - `run_pipeline({ "task": "add auth", "observation": "using JWT" })` - save insight in same call

### Other MCP tools (use only when run_pipeline is insufficient)
- `get_skeleton` - **preferred over Read** for inspecting files (minimal/standard/detailed detail levels, 70-90% token savings)
- `index_status` - indexing status and health check
- `expand_vexp_ref` - expand V-REF hash placeholders in v2 compact output

### Query shape (do this)
- Anchor the task on real identifiers (ClassName, functionName) or file paths:
  `run_pipeline({ "task": "fix JWT expiry in AuthService.validateToken" })`
- A pure natural-language question ("why does login fail?") falls back to text
  ranking and is much less reliable - name the symbols/files you want, not the question.

### Workflow
1. `run_pipeline("your task")` - ONCE at task start. Returns pivots + impact + memories in 1 call
2. Literal string sweeps? Grep/Glob directly. Editing a file? Read it directly.
3. Structural overview of a non-edit file? `get_skeleton({ files: [...], detail: "detailed" })`
4. Make targeted changes based on the context returned
5. `run_pipeline` again ONLY when the task moves to a new area - do NOT chain vexp calls per turn

### Subagent / Explore / Plan mode
- Subagents CAN call `run_pipeline` - always include the task description
- Before spawning Agent(Explore) for architecture questions, call `run_pipeline`
  and pass the returned context into the agent prompt - it usually replaces the
  exploration entirely

### Smart Features (automatic - no action needed)
- **Intent Detection**: auto-detects from your task keywords. "fix bug" -> Debug, "refactor" -> blast-radius, "add" -> Modify
- **Hybrid Search**: keyword + semantic + graph centrality ranking
- **Session Memory**: auto-captures observations; memories auto-surfaced in results
- **LSP Bridge**: VS Code captures type-resolved call edges
- **Change Coupling**: co-changed files included as related context

### Advanced Parameters
- `preset: "debug"` - forces debug mode (capsule+tests+impact+memory)
- `preset: "refactor"` - deep impact analysis (depth 5)
- `max_tokens: 12000` - increase total budget for complex tasks
- `include_tests: true` - include test files in results
- `include_file_content: false` - omit full file content (lighter response)

### Multi-Repo Workspaces
`run_pipeline` auto-queries all indexed repos. Use `repos: ["alias"]` to scope.
Use `index_status` to discover available repo aliases.
<!-- /vexp -->