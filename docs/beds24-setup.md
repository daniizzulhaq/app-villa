# Beds24 API V2: connect PropertyMe

> Legacy provider. The active setup is now Hostex; follow [hostex-setup.md](hostex-setup.md).
> Beds24 commands are disabled while `CHANNEL_MANAGER_PROVIDER=hostex`.
> To deliberately restore the legacy setup, select `CHANNEL_MANAGER_PROVIDER=beds24`
> in the ignored `.env` and clear the configuration cache before connecting.

This first step authorizes read access and lists property and room IDs. It does
not import reservations, change OTA availability, or create income records.
Reservation mapping and synchronization must be configured after the IDs are known.

## 1. Create fresh credentials

Revoke credentials exposed in a chat or screenshot. In Beds24 go to
**Settings → Marketplace → API → Generate invite code**. Enable only:

- `read:properties`
- `read:bookings`
- `read:bookings-personal`
- `read:bookings-financial`

Enable linked-property access only when those properties are actually linked to
the account. If using an IP whitelist, allow the application's public server IP.
The invite code expires after 24 hours and is exchanged once for tokens.

Do not paste the invite code into an iCal field, URL, source file or chat.

## 2. Connect locally

Put the invite code in the ignored local `.env` file (do not use `.env.example`):

```dotenv
BEDS24_INVITE_CODE="your_new_invite_code"
```

Then open a terminal in the application folder and run:

```powershell
php artisan config:clear
php artisan propertyme:beds24-connect
```

The command reads `BEDS24_INVITE_CODE` from configuration. If it is empty, it asks
for the code at a hidden prompt. It exchanges the code with Beds24, encrypts the returned credentials using the application key,
and stores them on the private local disk at
`storage/app/private/integrations/beds24-credentials.enc`.
Tokens and guest information are not printed. Retain the same `APP_KEY` to read
these credentials. Never upload this file into `public/` or `public_html/`.

After a successful exchange the same invite code is not exchanged again; the
application uses the saved tokens. You may clear `BEDS24_INVITE_CODE` from `.env`
after connecting. Run `php artisan config:clear` after any `.env` change.

Check again later without reusing the invite code:

```powershell
php artisan propertyme:beds24-connect --check
```

The output lists **Property ID**, **Room ID**, and their names. An empty property
list means the account has no accessible properties or the token/property access
needs checking. A successful authorization does not prove all booking/financial
permissions are present; those are checked when reservation sync is introduced.

## Alternative: long-life token

If the credential was explicitly generated as a **Long Life Token**, set
`BEDS24_TOKEN` in the ignored `.env` file and run `--check`. Do not put an invite
code or refresh token in `BEDS24_TOKEN`.

An existing refresh token can instead be configured as `BEDS24_REFRESH_TOKEN`.
When using invite-code authorization leave both `.env` token variables empty;
the application manages the encrypted tokens and refreshes expired access tokens.
The command never changes the application's `.env` file.

## 3. Link to villas

Record which **Property ID + Room ID** belongs to each villa in PropertyMe.
Do not match by name automatically. A room type with multiple rentable units
needs an explicit allocation strategy before it can represent separate villas.
Only after this mapping should booking imports, duplicate handling, financial
field validation, and webhooks be enabled.

Run migrations, then save a verified mapping (example IDs only):

```powershell
php artisan migrate
php artisan propertyme:beds24-map 3 367374 756263
```

The mapping command validates the property/room via the API and requires exactly
one rentable unit. It saves the IDs on the local villa without importing bookings
or changing anything in Beds24/Airbnb. An existing mapping cannot be replaced by
accident and the same room cannot be assigned to two villas.

Importing a listing as a property in Beds24 is separate from connecting its room
and importing existing reservations. If no reservations are returned, select the
correct property in Beds24, then check **Settings > Channel Manager > Airbnb >
Mapping** and **Import existing bookings**. Review the prices/availability Beds24
will send before connecting the listing. See the official Airbnb Mapping guide:
https://wiki.beds24.com/index.php/Airbnb_Mapping

On hosting, run the same connection command in cPanel Terminal from the Laravel
application folder. Local private credentials are not automatically deployed.

Official references:

- https://wiki.beds24.com/index.php/API_V2.0
- https://beds24.com/api/v2/
