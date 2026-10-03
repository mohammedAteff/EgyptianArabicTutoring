# Social Proof Counters — Definition and Verification

Date: 2026-10-03. Existing first-party analytics remain authoritative. This report describes both code/feature evidence and operational limits; no artificial production counts or customer QA data were introduced.

# Counter 1: Collective Learning Activity

**Meaning:** completed lesson duration plus measured dwell in educational page sections. This is activity time, not a claim about unique student study hours; multiple people or sections contribute aggregate time.

**Sources:** bookings via ReportService.nonBotBookingQuery; educational dwell via AnalyticsService.reportingMetrics (`section_dwell_seconds`, dimension `section`, curriculum/blog-content/resource-preview/game-board). Lesson start must fall inside the window and status must be completed. Cancelled, confirmed and no-show lessons do not contribute. Visitors flagged as bots are excluded from attributed lessons; unlinked completed lessons remain legitimate completed records.

**Formula:** SUM(TIMESTAMPDIFF(MINUTE, start_at_utc, end_at_utc))/60 + educational dwell seconds/3600. Duration is canonical UTC. Total minutes are rounded, then formatted as days/hours/minutes. Non-educational hero/pricing/tutor-bio dwell does not contribute. Reporting metrics reconcile raw events and full-day rollups using the greater authoritative value per metric/dimension; they do not add duplicate raw and rolled-up totals. Today's partial day uses eligible raw events.

**Calendar:** configured Business Timezone, start of today minus (window_days−1) days, through current UTC instant. The window includes today and is bounded 1–90 days. This is a calendar window, not a fixed rolling 168-hour window. Business DST boundaries convert to UTC; stored timestamps do not change. Default is 7 days.

**Exclusions:** existing ingestion excludes authenticated administrator traffic, previews, synthetic checks, bots and saved internal connection hashes. Raw eligible reporting events and attributed bookings exclude flagged bots. No persistent raw IP was added. Historical rollups cannot be retroactively decomposed into individual visitors after raw records are pruned.

**Settings:** public enabled flag, window days, headline and subtitle. `{time}` substitutions use the formatted value. The unrelated legacy default brand wording was corrected for new defaults; an existing saved custom headline is preserved. Draft values remain draft and appear in the settings form; publishing mirrors the values into public and draft settings and invalidates the public cache after commit.

# Counter 2: Previous Month Traffic

**Meaning:** either distinct visitors or session records during the previous complete business calendar month. The selected source and its matching template decide what the banner says. It is not the current rolling 30-day dashboard number.

**Source:** existing ReportService.getTrafficReport summary with eligible non-bot visitors/sessions. The service provides its existing canonical visitor/session semantics; no second traffic pipeline was created. Previous month's business midnight boundaries are converted to UTC. Interval is [previous-month start, current-month start); the existing inclusive report interface receives end minus one microsecond.

**Settings:** enabled flag, source `unique_visitors` or `sessions`, separate `{count}` visitor/session templates. A source change invalidates the cached payload. Avoid calling session counts "visitors" in custom copy: the source choice does not automatically rewrite the administrator's text.

# Counter 3: Live Users Online

**Meaning:** distinct visitor IDs with an eligible non-bot session whose last activity is strictly newer than now−60 seconds and not in the future. Multiple active sessions for one visitor count once. This is recent activity, not proof that a browser is currently open or that a person is watching the page.

**Source:** ReportService.nonBotSessionQuery requires both a non-bot session and a non-bot related visitor. The public counter's 60-second definition is distinct from the admin Live Pulse five-minute window. No raw IP persistence is introduced.

**Settings:** enabled flag and `{count}` template. Live count, enabled state and text are read again on every cached payload request. A page left open does not refresh by itself merely because the cache service refreshes on a subsequent request.

# Shared Cache, Settings and Rendering

Heavy learning/monthly values share key `counters.public.` + SHA256(Business Timezone) + `.` + business YYYY-MM-DD. TTL is **900 seconds**. A new business day or timezone has a distinct key. Settings updates invalidate the current key immediately and after transaction commit. Live count is computed fresh outside the heavy cache.

SettingController handles counter draft/public keys; admin controls read draft values with published fallback. Public AppServiceProvider composition / the existing social-proof-banner component consume the cached payload and display each enabled counter independently. All-disabled counters omit the banner. Existing production settings are preserved during deployment, rather than overwritten by the synthetic local QA settings.

# Expected vs Actual Evidence

| Check | Expected | Actual | Evidence |
|---|---|---|---|
| Isolated completed 60-minute lesson + 7200 seconds educational dwell | 3 hours, 0 minutes | 3 hours, 0 minutes | HTTP feature test computes service result and public rendered response |
| 90000 seconds hero dwell in the same fixture | No contribution | No contribution | Same feature test |
| Two previous-month sessions belonging to one visitor, source sessions | 2 | 2 | Public payload and response assertions |
| Same fixture, source unique visitors | 1 | 1 | Source change and cache invalidation assertion |
| One visitor with recent session(s) | 1 | 1 | Distinct visitor assertion |
| Activity exactly 60 seconds ago | 0 | 0 | Boundary test |
| Activity dated in the future | 0 | 0 | Boundary test |
| Activity ages out while heavy cache remains present | Live decreases | Live decreases | Cached-payload freshness test |
| Draft change to 14 days/headline | Public remains 7 days/old headline; form displays draft | Matched | Admin POST and rendered form test |
| Publish draft values | Public 14 days/new headline; cache invalidated | Matched | Admin POST/payload assertions |
| Window 91 days | Validation rejected | Rejected | HTTP validation test |
| Local browser learning fixture (prior baseline 0 + one hour + two hours) | 3 hours, 0 minutes | 3 hours, 0 minutes | Actual local public banner |
| Local browser monthly fixture (existing one session + two QA sessions) | 3 sessions | QA 3 sessions last month | Actual local public banner after source selection/publish |
| Local browser live fixture | 1 within 60 seconds | QA 1 online now | Actual local public banner |
| Local browser draft enables all three | No public banner before publish | No public banner | Settings Save as Draft then public page |
| Local browser Publish All Settings | All three expected values visible | All three visible | `C:/Users/e/AppData/Local/Temp/awa-feature-public-counters.png` |

Nine counter tests pass. Browser fixtures were inserted only into the guarded local `bolt_landing` database; they were never copied to production. Browser public visits were excluded via the authenticated admin session or synthetic request flag where applicable.

# Defects Corrected in This Pass

- Future session activity previously could count as live; now it is bounded by current UTC time.
- Completed lesson aggregation now uses the existing bot-aware booking query.
- Direct counter service window bounds now match the admin 1–90 range.
- Draft settings form could show stale published values and publishing could leave stale drafts; counter draft/public mirroring and UI reads now agree.
- Counter/timezone settings invalidate heavy cache after commit as well as immediately.
- New default headline no longer includes unrelated brand text.
- Settings help text now explicitly states that public counter drafts require publishing.

# Trust Assessment

| Counter | Feature trust | Operational qualifications |
|---|---|---|
| Learning activity | Verified calculation, rendering, settings workflow | Measured educational activity + completed lessons, not independently audited human study; historical aggregate exclusion limits; 15-minute cache |
| Previous-month traffic | Verified selected source, business-month bounds, templates and cache invalidation | Eligibility follows the existing canonical reporting service; historical coverage/cutover affects completeness |
| Live visitors | Verified distinct eligible visitors and 60-second boundaries | Recent interaction estimate; refresh occurs on subsequent requests; admin five-minute Live Pulse is a different definition |

# Production and Deferred Operations

Deployed feature commit `f889c2d16756953dcacefc0857e1c5bfb30a744b` to the existing Hostinger application at https://mohamedateff.com/arabictutor/ on 2026-10-03. GitHub main and production matched that exact feature SHA. The final documentation-only commit will be fast-forwarded to production after this receipt is committed; application source and built assets remain identical.

Private pre-deployment recovery point: `/home/u494520852/deployment-backups/20261003-161154-feature-f889c2d`. It contains the full database dump, baseline code archive, both prior builds, uploaded assets and environment copy, with SHA256 checksums. Directory permissions are 700 and recovery files 600; no recovery file was placed in the public web root. Checksums passed before the forward migration.

Forward migration ran as batch 11. Configuration/routes/Blade caches rebuilt; queue restart signal issued only. Environment, all settings/social links, and ordered full-row hashes/counts for students, contacts, bookings, packages, payments, refunds, ledger, resource requests and meeting configuration matched before/after deployment. Preserved counts: 3 students, 7 contacts, 6 bookings, 6 packages, 8 payments, 3 refunds, 7 ledger entries, 3 resource requests, 84 settings, 5 social links, 3 meeting providers and 0 rooms. No QA student/note/financial data was inserted in production.

Build archive SHA256: `3b405c571de5590ba55cfdfe468d9ef317a2a66523487efe6a9107ef4b2a254f`. Both production manifests match local SHA256 `3cd9d2972b0abdceceabf90d64929e05503211f5f124c8a8ea84e1c4d16b8162`. Initial extraction inherited the private recovery umask, causing public assets to return 404. This was caught in actual live browser QA and corrected strictly within the two public build directories: directories 755, files 644. Recovery permissions remained private. CSS and JavaScript now return HTTP 200 and the actual live page renders correctly.

Live homepage and French/German resource pages return 200. Unauthenticated staff/student note requests redirect to their respective sign-in pages. The existing public counter values rendered as 0 live / 69 prior-month visitors / 3 hours 9 minutes; these are observed production values, not the synthetic local fixture. Live screenshot: `C:/Users/e/AppData/Local/Temp/awa-feature-live-website.png`. Browser verification uses a headless test user agent to exclude QA visits from first-party telemetry. Existing configured maintenance state remains Live.

Production has no configured room pool yet. Automatic room assignment is verified with eligible synthetic local rooms; real assignments require active valid production room URLs configured in Meeting Links. No synthetic meeting URL was deployed. Existing offsite warning/host automation state is unchanged. Pre-existing `.env.backup.before-7123f15` remains untouched on the host; the pre-existing local untracked Hostinger handoff remains untouched.


No scheduler, queue-worker, watchdog or Telegram-poll cron was installed, and no final Hostinger operations acceptance drill was run. Counter feature verification does not imply that deferred production automation is operational. HOSTINGER_OPERATIONS_HANDOFF.md remains unchanged.
