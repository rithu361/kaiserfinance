# Kai$erFinance setup checklist

Everything runs on the WordPress you already host on TrueNAS. No extra cron job, no extra server.

## 1. Get a free price API key (5 min)
- [ ] Sign up at https://twelvedata.com (free Basic plan: 800 requests/day, 8/minute)
- [ ] Copy the API key from the dashboard

## 2. Decide where the site lives (5 min)
- [ ] New WordPress site for Kai$erFinance, or a page on an existing one?
  - New site: create it in TrueNAS the same way as your other WordPress sites, and point a hostname
    (e.g. `kaiserfinance.ch`) at it through your Cloudflare tunnel (Zero Trust → Networks → Tunnels → your tunnel → Public hostname).
- [ ] Open the site once and finish the WordPress install (site title: `Kai$erFinance`)

## 3. Install the plugin (5 min)
- [ ] WordPress admin → Plugins → Add New → Upload Plugin → choose `kaiserfinance.zip` → Install → Activate
- [ ] Kai$erFinance → Settings → paste the Twelve Data API key → Save
  - Benchmark stays `SPY` (tracks the S&P 500, included in the free plan)
  - "Show allocation in %" is off by default; tick it if Kaiser wants visitors to see e.g. Gold 70% / Silver 30%

## 4. Create the logins (5 min)
- [ ] Users → Add New → username for Kaiser, strong password, role **Administrator**
- [ ] Only Administrators see the ledger, real amounts, and the add/edit/delete form
- [ ] Optional: install a two-factor plugin (e.g. "Two Factor") for both admin accounts

## 5. Make the public page (5 min)
- [ ] Pages → Add New → title "Performance" (or use it as the home page)
- [ ] Add a Shortcode block containing: `[kaiserfinance]`
- [ ] Publish
- [ ] Optional: Settings → Reading → "A static page" → choose it as the homepage
- [ ] Visitors see only % returns vs the S&P 500, the chart, and the date of the last price update.
      Amounts and quantities never leave the server.

## 6. Enter the trades (10 min)
- [ ] Kai$erFinance → Ledger → "Find the asset": type a name or ticker (e.g. "Northern Star", "gold", "BTC")
- [ ] Pick the right listing. Each one shows its exchange, country and currency, so the same ticker
      on two exchanges can't be mixed up
  - **Auto** listings (US stocks/ETFs, metals, forex, crypto) update hourly on the free plan
  - **Manual price** listings (e.g. ASX, London, Toronto) aren't on the free plan: after saving,
    type their current price in the Holdings table now and then. It starts at the trade price.
- [ ] Enter quantity, price per unit **in the asset's own currency** (AUD for ASX), and the trade date
  - Metals are per **troy ounce** (1 kg = 32.1507 oz, 1 g = 0.03215 oz)
  - Everything is converted to USD automatically using the exchange rate of the trade day
- [ ] Saving a trade fetches prices right away. Check the Holdings table shows a price for every asset
- [ ] With many different assets the free plan's 8 requests/minute applies: if some show "(trade price)", wait a minute and press "Update prices now"

## 6b. Log the cash (5 min)
- [ ] Kai$erFinance → Ledger → Cash: add his deposits (e.g. Deposit · CHF · 10000) with their dates
- [ ] When he converts money (e.g. CHF to USD before buying gold), add a Currency exchange: paid CHF amount, received USD amount
- [ ] Interest/dividends and fees go in as their own entries
- [ ] If a currency's balance turns negative, a warning shows: a deposit or exchange is missing
- [ ] Until the first cash entry exists, returns are measured only on the money that went into trades

## 7. Lock down the login with Cloudflare Access (15 min, recommended)
- [ ] Cloudflare Zero Trust → Access → Applications → Add → Self-hosted
- [ ] Application domain: your site, path `wp-admin`; add a second one for path `wp-login.php`
- [ ] Policy: Allow → Emails → your email and Kaiser's email
- [ ] Leave `wp-cron.php` unprotected so price updates keep running. If a theme or plugin needs
      `wp-admin/admin-ajax.php` for visitors, add a Bypass policy for that path
- [ ] Test in a private window: the public page opens, `/wp-admin` asks for a Cloudflare email code

## 8. Keep prices updating without a cron job (5 min)
- [ ] Prices refresh hourly, triggered by visits (WordPress's built-in WP-Cron). Quiet periods use no API calls,
      and the next refresh fills in any missed days automatically.
- [ ] Cloudflare → Caching → Cache Rules: if you cache HTML, bypass cache for the performance page
      (or keep the cache time under an hour), otherwise visits never reach WordPress
- [ ] Tools → Site Health: if it reports "scheduled event has failed" or loopback errors, it still works:
      the plugin falls back to refreshing at the end of a page visit once prices are 3+ hours old

## 9. Check it works
- [ ] Ledger page shows "Prices last updated … ago" with no warning
- [ ] Public page shows the chart with two lines and a "Prices as of" date
- [ ] Logged out (private window), the public page has no amounts and no link to the ledger

## Notes
- Everything is reported in USD; non-USD assets are converted with daily exchange rates (free on Twelve Data).
- Return = (current value + money from sales − money spent) ÷ money spent.
  The S&P line makes the same buys and sells in SPY on the same days, so both get exactly the same cash.
- Deactivating the plugin keeps the data. Deleting the plugin also keeps the tables
  (`wp_kf_trades`, `wp_kf_prices`); drop them manually if you ever want them gone.
- Back up the WordPress database with your usual TrueNAS snapshots; the ledger lives there.
