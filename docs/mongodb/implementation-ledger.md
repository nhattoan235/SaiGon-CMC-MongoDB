# MongoDB implementation ledger

Source: Toan f9860db; source SQL repository must remain unchanged.
User approved review recommendations and implementation in independent copy, and explicitly requires transferring ALL existing data. NO PUSH without a separate subsequent user instruction, to any remote. The clone was initially created without remotes; the authorized publication target is recorded below.

## Accepted decisions
- Independent sibling clone at Sai-Gon-CMC-Laravel-MongoDB, branch codex/mongodb-migration.
- Hybrid storage: quotes.items, orders.items, purchase_orders.items, stock_documents.items. Stable numeric item_id preserves old form IDs; other ledgers/events remain referenced collections.
- Numeric _id/counters; Decimal128 persisted / decimal strings at app boundary; BSON instants with original app date rendering.
- Current seven-role User authentication remains canonical; report Vietnamese labels map to physical English collection names.
- Retain SQL-source code interfaces when practical via one domain-scoped Mongo query/data mapper supporting only the actual builder operations in this app. Queries execute native aggregation/lookup on MongoDB, not SQL or in-memory full-collection joins. This earns its scope by covering 352 existing call sites and preserving regression tests; it is not an arbitrary SQL parser.
- One transaction/session per business command; flatten existing nested purchase paths; real shared-document guard writes for former lockForUpdate paths and reservation aggregates.
- All original live data is imported from a consistent read-only snapshot and verified; uploads copied privately; no original sessions/jobs replayed.
- Project-local PHP extension loading and separate Mongo instance avoid changing source php.ini/runtime/database.

## Progress
- Source clean, clone created; clone origin removed, working branch created. No push performed.
- Runtime ready: project-local ext-mongodb 2.5.5, laravel-mongodb 5.11.0, native replica set cmcMongoRs on loopback27028 with separate .runtime data; no global php.ini changes.
- Full captured snapshot imported and read-only verification passed: 40 tables, 303 business rows, 39 archived runtime rows. Four private upload SHA256 hashes verified; 592 foreign-reference values have zero source/destination orphans; 45 decimal-field sums match exactly. Source runtime sessions/jobs remain inactive in archive.
- HTTP /, /login, /products, /cart returned 200 on loopback8011. Native whole-callback transaction retries have regression coverage; final full-suite evidence is recorded below.
- Mongo setup/readme, physical design, migration runbook and historical-doc index added. Root CI uses official Mongo8.3 and ext-mongodb2.5.5 for full PHPUnit; actionlint passes. Remote CI execution has not been claimed.

## Verification evidence
- Full final suite: 110 tests passed, 1060 assertions, exit 0, 61.272 seconds after Pint. Private evidence: `.runtime/final-suite.xml` and `.runtime/final-suite.log`; no SQLite fallback.
- Native replica-set transaction, rollback, concurrency and whole-callback retry regression coverage included in the final suite.
- Snapshot verification: 40 tables, 303 business rows, 39 archived runtime rows; 592 foreign-reference values with zero source/destination orphans; 45 decimal-field sums exact; four private upload SHA256 hashes match.
- HTTP /, /login, /products, /cart and ten local CSS/JS assets returned 200 on loopback8011.
- Setup/docs/CI static validation: 25 local doc links resolve, Markdown fences balanced, APP_KEY blank in template, runtime PowerShell syntax valid, actionlint zero findings.
- Source remains clean on Toan at f9860db, original origin unchanged; clone has no remotes. No push or publication performed.
- Hosted GitHub CI has not been executed; production deployment has not been performed. Local CI validation does not claim hosted execution.

## Remaining limits
- Local single-node replica set has no HA; production requires authenticated/TLS managed operations.
- BSON document limit remains16MiB; no automatic report/item chunking policy is implemented.
- ICU Vietnamese collation and normalized LIKE approximate source SQL collation; arbitrary Unicode edge cases are not guaranteed identical.

## Publication authorization — 2026-10-19
The user explicitly authorized publishing the MongoDB project to https://github.com/nhattoan235/SaiGon-CMC-MongoDB.git. The destination was empty when checked. Publish the implementation and documentation to main; keep runtime databases, SQL snapshots, private uploads, environment secrets and generated dependencies outside Git. Future pushes still require the user's authorization.
