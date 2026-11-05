# UI Specification

Dashboard is server-driven via Inertia. This document prevents visual and interaction drift; it is not a pixel-perfect
mockup.

---

## 1. Design principles

1. **Operational clarity over decoration.** The status of every deployment is readable at a glance.
2. **No hidden state.** The UI shows the real lifecycle (`docs/STATE-MACHINES.md`), never a derived "pending".
3. **Failures are actionable.** Show the failure code, a human message, and the next step.
4. **Nothing destructive without confirmation.** Cancel, rollback, archive, remove member.
5. **Secrets are write-only.** No reveal toggle exists in v1.
6. **Tenant context is always visible.** Organization switcher in the header; every page is scoped.

---

## 2. Layout

```text
┌──────────────────────────────────────────────────────────────┐
│ [logo] Organization ▾        search        notifications  user│
├───────────────┬──────────────────────────────────────────────┤
│ Applications  │  Page header: title, env badge, primary action│
│ Deployments   │  ────────────────────────────────────────────  │
│ Repositories  │  content                                       │
│ Members       │                                                │
│ Audit         │                                                │
│ Workers*      │                                                │
└───────────────┴──────────────────────────────────────────────┘
* platform admin only
```

Responsive: sidebar collapses under 1024px; tables become stacked cards under 768px.

---

## 3. Components (shared)

| Component | Rules |
|---|---|
| `AppShell` | header, org switcher, nav, flash messages |
| `PageHeader` | title, description, breadcrumbs, actions slot |
| `StatusBadge` | one color per state; includes icon + text (never color alone) |
| `DataTable` | sortable, cursor/page controls, empty state, loading skeleton |
| `EmptyState` | icon, one-sentence explanation, one primary action |
| `DangerDialog` | requires typing the resource name for destructive actions |
| `CopyField` | read-only value with copy button (never for secrets) |
| `LogViewer` | monospace, virtualized, follow toggle, step filter, download |
| `Timeline` | deployment events with relative + absolute time |
| `FormField` | label, help text, error text, `aria-describedby` |
| `CodeBlock` | commit SHA, digest, image URI |

Semantic tokens only (`bg-surface`, `text-muted`, `border-default`, `danger`, `success`, `warning`, `info`). No raw hex in
page files.

---

## 4. Status colors

| State | Token | Icon |
|---|---|---|
| queued | neutral | clock |
| cloning/building/pushing/deploying/health_checking | info | spinner |
| succeeded / active | success | check |
| failed | danger | x |
| cancelled | neutral-muted | slash |
| disabled / inactive | muted | pause |

Text always accompanies color (WCAG AA).

---

## 5. Screens

### 5.1 Dashboard `/dashboard`

- Cards: active deployments, deployments last 24 h (success/fail), workers online, environments in error.
- Recent deployments table (app, env, commit, status, duration, actor).
- Empty state: "Connect a repository to create your first application."

### 5.2 Applications list

Columns: name, repository, environments, last deployment, status. Primary action: **New application**.
Empty state explains the GitHub prerequisite and links to integrations.

### 5.3 Application detail

Tabs: Overview, Environments, Deployments, Settings.
- Overview: repo, default branch, health path, last release per environment.
- Environments: cards with active release, digest (short), public host, **Deploy** action, auto-deploy toggle.
- Settings: name, description, build configuration (Dockerfile path, context, port), archive.

### 5.4 New application wizard

1. Select repository (installation-scoped, searchable).
2. Name + slug (auto-suggested, collision-checked).
3. Build config (Dockerfile path, context, container port, health path).
4. Create staging environment (branch defaults to repo default, public host preview).
5. Review → Create.

Validation is server-side; the wizard shows field errors inline.

### 5.5 Environment / deploy dialog

- Shows branch, commit (resolved branch head, editable SHA), and the active release.
- Requires an idempotency key generated client-side per dialog open; re-submitting the same dialog is safe.
- On 202: close, toast "Deployment queued", navigate to detail.
- On 409: show the reason (`DEPLOYMENT_ALREADY_RUNNING` links to the running deployment).

### 5.6 Deployment detail

Sections: summary (status, commit, image digest, actor, duration), steps timeline, log viewer, actions (cancel /
rollback, permission-gated).
- Live updates: poll every 2 s while non-terminal; SSE when available.
- Failure: code + message + "What to do" text from `ERROR-CODES.md`.
- Rollback dialog lists verified releases with activated time and digest; warns that a new deployment is created.

### 5.7 Variables

Table: key, secret flag, updated at, actions. Values are never shown; editing opens a write-only field with
"Replace value" semantics. Audit note: "Value changes are recorded without the value."

### 5.8 Members

Table: name, email, role, joined. Invite dialog: email + role (owner not offered). Owner row is not removable.
Role change uses a select with a confirmation when downgrading an admin.

### 5.9 Audit

Filter by action, actor, resource, date. Read-only. Metadata shown as key/value, never as raw JSON blob unless expanded.

### 5.10 Workers (platform admin)

Worker list with type, status, last heartbeat, version, active leases. Actions: drain, revoke credential.
Lease table shows deployment, age, expiry.

### 5.11 Notifications

List with unread state; failure notifications link to the deployment.

---

## 6. States

Every data screen implements: loading (skeleton), empty, error (retry + request ID), and forbidden (explain role).
No blank screens, no infinite spinners.

Forms: disable submit while pending, preserve input on validation error, announce errors with `role="alert"`.

---

## 7. Accessibility

- Keyboard reachable; visible focus ring.
- Dialogs trap focus and close on Escape.
- Tables use `<caption>` and `<th scope>`.
- Contrast ≥ 4.5:1 for text.
- Status conveyed by text, not color only.
- Log viewer is scrollable by keyboard and announces follow-mode changes.

---

## 8. Copy rules

- Sentence case for headings and buttons.
- Use domain vocabulary from `docs/GLOSSARY.md` (deployment, release, environment).
- Second person, active voice: "Cancel this deployment?"
- Failure copy: what happened, why, what to do. No raw stack traces.
- Never say "Something went wrong" without a code and a next step.

---

## 9. Frontend conventions

- Pages: `resources/js/Pages/<Area>/<Page>.tsx`; layouts via `AppShell`.
- Props typed from generated server types or explicit interfaces; no `any`.
- Inertia forms via `useForm`; no bespoke fetch for page data.
- Dates: `<time dateTime>` with relative + absolute on hover/tooltip.
- No client-side secret handling; no localStorage for anything except UI preferences.
- Component tests only where logic is non-trivial; visual checks are manual per milestone.
