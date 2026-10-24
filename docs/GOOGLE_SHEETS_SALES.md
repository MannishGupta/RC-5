# Live Sales Register — Google Sheets setup

## 1. Google Cloud
1. Create (or use) a GCP project.
2. Enable **Google Sheets API**.
3. Create a **Service Account** → Keys → Add JSON key.
4. Download the JSON key.

## 2. Install on RC (per server / shared)
Copy the JSON to the **tenant** data folder (preferred) or shared data:

```
tenants/{tenant_id}/data/config/google_sheets_sa.json
```

or

```
data/config/google_sheets_sa.json
```

Never commit this file to git.

## 3. Share workbooks
Open each sales spreadsheet in Google Drive → Share → add the service account
`client_email` as **Viewer** (or Editor if you later write back).

Example IDs from your workbooks:
- `1W5o6F322FAl2Cpbw0Xu9FG1eZdkMQrL1`
- `1VzWu3cL-PtemW3AsB9mPuwH2uLEkxDq8`
- `1Fz1mPy7-ooaDf1EIwAGOOsI5kBuO3FzI`

## 4. Tenant module
Super Admin → Tenant Setup → enable module **sales**.

## 5. Sales Register tab
1. Open **Sales Register**.
2. **Configure** → enable live sync → paste spreadsheet URLs/IDs → Save.
3. **Sync now** — data merges into the live dashboard (charts + table).
4. Auto-refresh uses a 5-minute cache (configurable via `ttl_seconds`).

## Header row (CSV-compatible)
Month/Year, Project Name, Unit Number, Customer Name, Unit Type, Area Sold (Sq. Ft.), Base Sale Price (BSP), Other Charges, Total Gross Revenue, Booking Status
