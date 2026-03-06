# CODE_RULES.md

## Coding Standards for PatriotContracts

### General
- Preserve the current coding style unless there is a strong reason not to
- Make the smallest safe change possible
- Avoid introducing large abstractions for small fixes
- Keep logic readable for a solo maintainer

### SQL
- Use prepared statements
- Prefer explicit selected columns over `SELECT *` where practical
- Avoid N+1 query patterns
- Add indexes before considering major query redesign
- Do not replace working queries unless necessary

### PHP
- Reuse existing includes/modules when reasonable
- Avoid giant new utility layers unless they solve repeated problems
- Add comments only when they clarify non-obvious behavior

### Migrations
- Use additive migrations
- Do not drop or rename existing structures unless explicitly required
- Keep migrations reversible where practical

### Error Handling
- Do not silently ignore failures
- Log failures with enough detail to debug
- Keep user-facing output stable unless a task requires otherwise

### Performance
- Fix bottlenecks with the least disruptive change first:
  1. indexes
  2. query cleanup
  3. caching/precomputation
  4. schema changes
- Avoid premature overengineering

### Refactors
A refactor is allowed only if:
- the current code blocks the requested fix, or
- the current code is clearly unsafe and cannot be patched cleanly

If a refactor is done:
- keep it narrow
- document why
- avoid changing public behavior