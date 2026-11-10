# Hostex setup for PropertyMe

Hostex is the active channel manager. This setup provides authenticated read
access, property/listing discovery, verified villa mapping, reservation imports,
and a five-minute polling schedule. Imports populate the local calendar/booking
list, guest name/count, dates, arrival time, per-stay rate and OTA commission.
It does not create financial ledger entries or receive real-time webhooks.
It never writes prices, availability or bookings to Hostex or the OTA.

## 1. Prepare Hostex

- Connect Airbnb/Booking.com to Hostex and confirm the bookings in its calendar.
- OpenAPI requires Hostex Pro access and an active eligible account. Confirm
  API availability in the account before purchasing; trial access can differ.
- In **Workplace > OpenAPI**, select **+ Add new** and create a token named
  `PropertyMe` with **read-only** scope.
- Copy the Access Token directly into the ignored local `.env`. Do not paste it
  into source code, `.env.example`, a URL, an iCal field, screenshots or chat.

```dotenv
CHANNEL_MANAGER_PROVIDER=hostex
HOSTEX_ACCESS_TOKEN="your_hostex_access_token"
HOSTEX_SYNC_ENABLED=true
```

Beds24 invite/refresh tokens cannot authenticate to Hostex. Existing encrypted
Beds24 credentials and historical property IDs remain separate and unused while
Hostex is selected. The provider setting prevents Beds24 API calls.

## 2. Verify the connection

```powershell
php artisan config:clear
php artisan migrate
php artisan propertyme:hostex-check
```

The check lists Hostex property IDs, titles and connected channel listing IDs.
It does not display tokens or guest details. An empty property list is a
successful API read, but does not prove any OTA has been connected.

Test reservation-read access for the correct Hostex property ID:

```powershell
php artisan propertyme:hostex-check --property=501 --from=2026-01-01 --to=2027-12-31
```

`501` is an example, **not** Avora's actual Hostex ID. The verified Hostex ID for
the local Avora villa is **12840364** (Airbnb listing 1273618741658905815).
This reports stay counts with **check-in dates** between `from` and `to`, inclusive.
Without explicit dates, it uses one year before today through one year after
today. Multiple stays in one order are counted separately. Hostex requires paired
date filters and rejects ranges over 180 days; the client splits longer ranges
into consecutive windows of at most 180 calendar days and paginates each window.
No reservations are saved by this check.

Hostex can return HTTP 200 with a failed `error_code`; the client checks both.
401 means token/access trouble; 420 indicates subscription/account/API access;
429 indicates a rate limit. The commands print safe messages rather than raw
responses. Both endpoints are paginated using offset/limit.

## 3. Map Avora to the existing local villa

Avora is already villa **#3** in the local PropertyMe database. Do not create
another villa or reuse the Beds24 IDs `367374` / `756263` as Hostex IDs.
Find Avora's actual Hostex property ID using the connection check, matching its
Airbnb listing ID `1273618741658905815`, then run (replace the example `501`):

```powershell
php artisan propertyme:hostex-map 3 501
```

The command validates ownership/access through the Hostex API and persists
`hostex_property_id` on the local villa. It is repeatable, refuses a different
mapping for an already mapped villa, and prevents assigning one Hostex property
to two villas (including soft-deleted villas). A Hostex property represents a
physical rentable unit; use its property ID, not a multi-unit room-type ID.

## 4. Import and refresh bookings

```powershell
php artisan propertyme:hostex-sync --villa=3
# Optional explicit check-in period:
php artisan propertyme:hostex-sync --villa=3 --from=2026-01-01 --to=2027-12-31
```

Managers can also use **Sync Hostex now** on the villa detail page or the usual
synchronization page. Access is checked against the manager's villa scope.
The same `stay_code` updates the same booking on later pulls. A filtered/empty
response never cancels absent bookings; only an explicit cancelled/denied/timeout
status does. Pending confirmation/payment stays need completion; accepted stays
follow the application's existing date-based check-in/out status behavior.

Synced dates, guest details and status are managed by Hostex. Local notes and a
manual IDR price can be edited and are preserved on later imports. The original
Hostex per-stay rate/commission remain available separately. USD or other
non-IDR amounts retain their currency and never become a Rupiah price without
manual reconciliation. Per-order totals/payment fields are not summed across
stays and no income/payout entry is automatically created.

Disable existing iCal imports before importing Hostex bookings for the same
villa. The importer refuses active iCal feeds or a conflicting reference from
another source. iCal synchronization skips Hostex-mapped villas. Unexpected
invalid data rolls back the whole villa import; safe status/error information is
recorded on the villa. Verbose CLI output contains only exception category and
SQLSTATE, never SQL bindings, guest details or HTTP headers.

## Existing booking references on hosting

If an initial pull stops with `A booking reference already exists outside
Hostex`, existing local/iCal/import bookings must be reconciled. Do not delete
them or bulk-update their source using SQL. From the hosting application folder,
preview the plan for Avora (hosting villa #2):

```bash
php artisan propertyme:hostex-reconcile --villa=2
```

The preview does not change bookings, sync metadata or incomes. It lists booking
IDs, source, local/Hostex dates, linked-income counts and match/conflict reasons.
Only one-to-one matches on villa, platform, reference and **both dates** qualify.
No guest names, notes, phone numbers or tokens are printed.

If the plan has zero conflicts, apply and pull again:

```bash
php artisan propertyme:hostex-reconcile --villa=2 --apply
php artisan propertyme:hostex-sync --villa=2
```

Applying only attaches the existing booking ID to the Hostex stay and changes
its source. It preserves notes, creator, legacy iCal UID, existing prices and
all income links/amounts. An existing non-null price is protected from later
Hostex price replacement (manual/import price source); the original Hostex rate
remains available separately after sync. No new income entry is created.

Any ambiguous match, date mismatch, duplicate local reference, shared stay or
already-assigned Hostex identity prevents **all** changes in that apply run.
Review the listed booking IDs before retrying; the command does not guess, merge
booking IDs, restore deleted bookings or move financial records.

## 5. Automatic refresh and hosting

The schedule in `routes/console.php` pulls mapped active villas every five minutes
when `HOSTEX_SYNC_ENABLED=true`. A scheduler must actually be running:

```powershell
# Local development (keep this process running while testing):
php artisan schedule:work
```

On hosting: deploy code/dependencies, run migrations, add the Hostex token and
provider/sync settings to the hosting `.env`, then clear config cache. Match
12840364 to the correct villa in the hosting database; its local database ID may
differ from #3. Do not overwrite existing hosting data with the local demo DB.
Configure one cPanel cron entry every minute using the correct server paths:

```cron
* * * * * cd /home/USER/propertyme && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

No scheduler is started by connection, mapping or sync commands. The manual
button and CLI pull work independently of the schedule. Polling every five
minutes is not instant webhook delivery; a public HTTPS webhook with verified
events and deduplication remains a separate hosting enhancement.

Booking value is not proof of a received payout. Record/reconcile actual income
through PropertyMe's existing income workflow before relying on revenue reports.

Official references:

- https://api-doc.hostex.io/reference/quickstart
- https://api-doc.hostex.io/reference/authentication
- https://api-doc.hostex.io/reference/query-properties
- https://api-doc.hostex.io/reference/query-reservations
- https://api-doc.hostex.io/reference/errors
