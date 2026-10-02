# Changelog

## 3.1.4
- Logo in bold.

## 3.1.3
- Logo written exactly as "Kai$erFinance" (upper and lower case, no space), one colour, on the site and in the link-preview image.

## 3.1.2
- The logo is one colour: "KAI$ER FINANCE" all black in light mode, all white in dark mode (also in the link-preview image).
- Icon in black and off-white: a black $ in a thin black ring.

## 3.1.1
- Light mode back to the original colours (off-white page, white card, grey labels, original benchmark colours). The new wordmark, icon and header stay, still without gold. Dark mode unchanged.

## 3.1.0
- Currency exchange in the cash form: type the amount (e.g. 10 CHF) and "Received amount" fills itself at the current rate (e.g. 12.53 USD), shown as "1 CHF = 1.2534 USD (live)".
- For a past date it uses that day's closing rate. Type your own received amount to override; the hint then shows your rate vs. the market and a "Use rate" link to go back.
- Live rates are cached for 10 minutes, so the free API plan isn't strained.

## 3.0.1
- No more gold: the $ in the wordmark is ink like the rest, the portfolio line is ink (beige-white in dark mode), small accents are olive.
- New icon: an ink $ inside a thin ring on beige (simpler $ for the tiny browser-tab size).
- Link-preview image: "KAI$ER FINANCE" in serif capitals, no gold.

## 3.0.0
- New look: sand beige (#e0ddcd) as the main colour in light mode, warm graphite from the same beige in dark mode, ink-black text and an olive tone for small labels.
- Wordmark reworked: centred serif capitals "KAI$ER FINANCE" with the gold $, a thin rule under the header.
- Editorial details: squarer corners, time ranges as plain words with an underline, quieter comparison line with a gold edge.
- Site icon and link-preview image switched to the beige look, gold $ kept.

## 2.13.0
- Backup: Settings → Backup downloads the whole ledger (trades, cash, assets, prices, settings) as one .json file. The API key is never included.
- Restore: upload a backup to replace the ledger. The current ledger is kept as a safety copy first, with an "Undo last restore" button.
- Trades and cash can also be downloaded as CSV for Excel or Numbers.
- The ledger page reminds you if there's no backup yet or the last one is over a month old.

## 2.12.1
- Calmer, more elegant dark mode: warm charcoal instead of blue-black, paper-white text, muted sage and terracotta for gains and losses instead of neon, softer gold and benchmark colours.
- The comparison line is a quiet panel with a thin gold edge instead of a brown block; lighter shadows and chart fill. Light mode unchanged.

## 2.12.0
- Time ranges glide: switching 7D/30D/90D/YTD/1Y/All zooms the chart smoothly to the new window (about half a second), with real data in every frame, instead of cross-fading two charts.
- Tapping another range mid-glide carries on smoothly from where it is. With "reduce motion" on, it switches instantly.

## 2.11.0
- New header: just the Kai$erFinance wordmark and one small display button (it shows the current language and currency, e.g. "EN · USD").
- The button opens a panel with Appearance (light/dark), Language and Currency. It closes on a tap outside or Esc, and stays open while you switch.
- The login moved from the header to a quiet "Log in" link in the footer.
- Phones: the header is one clean row instead of wrapping onto two lines.

## 2.10.2
- Benchmarks on/off: no more swapping between two charts. The chart stays put and its lines glide to the new scale; an added benchmark fades in, a removed one fades out, and the axis labels slide along.

## 2.10.1
- Tooltip motion: it fades and lifts in, glides gently from day to day (with the guide line and dots), and fades out. Off with "reduce motion".

## 2.10.0
- Smoother chart: switching the time range or a benchmark cross-fades softly (about a quarter second) instead of snapping.
- On first view the lines draw in once from left to right; the shaded area and end dots fade in after.
- The tooltip stays instant. With "reduce motion" switched on in the device settings, nothing moves.

## 2.9.2
- Phones: the footer is centred (copyright, YouTube/Instagram, disclaimer, "Powered by shokulab"). Desktop unchanged.

## 2.9.1
- Phones: the chart tooltip now closes when you tap anywhere outside the chart (it used to stay open). Tap the chart again to bring it back.

## 2.9.0
- If prices are more than 6 hours old (for example the price service was unreachable), the public page says so: "Prices may be delayed: last updated 12 hours ago", in English and German.
- "Prices as of" now shows the last successful update, not just the last attempt.

## 2.8.0
- Phones: the "Returns by period" table turns on its side (one row per period), so it fits the screen without sideways scrolling, even with several benchmarks switched on.
- Phones: tighter tables; the holdings table fits too, with the ticker under the name.

## 2.7.0
- Faster homepage: the numbers are sent as compact JSON instead of an escaped HTML attribute, and chart lines use two decimals (page about 20% smaller now, more as history grows).
- The script loads in parallel with the page (deferred, in the head).
- Space for the block is reserved while it loads, so the footer no longer jumps.
- Back/forward navigation can show the page instantly; it still asks for fresh prices on every visit.

## 2.6.0
- Holdings and allocation on the public page link to each instrument's chart on TradingView (new tab, small arrow icon).
- Plain links only: no TradingView script or cookies on the site. Cash and manually priced items have no link.

## 2.5.0
- YouTube and Instagram links in the homepage footer, translated, editable under Settings → Social links (empty = hidden).
- Search engines get schema.org data linking the site to those accounts.
- Plain links only: nothing is loaded from YouTube or Instagram, so no trackers.

## 2.4.0
- Chart tooltip: hover, tap or use the arrow keys to see the date and every line's value, with a guide line.

## 2.3.3
- Update check asks both GitHub's raw file and its API, so it works even if one is unreachable or cached.
- Settings shows the result of the last update check, with a "Check GitHub now" link.

## 2.3.2
- Updates keep working even if the "Updates from GitHub" field was saved empty.

## 2.3.1
- After an update, cached numbers are cleared and missing prices (e.g. new benchmarks) are fetched on the next visits instead of waiting an hour.
- Benchmark chips appear only once a benchmark has prices.

## 2.3.0
- Changelog now shows in WordPress under Plugins → Kai$erFinance → View details.
- Setup notes brought up to date (cash, benchmarks, public amounts, GitHub updates).

## 2.1.1
- Updates come from GitHub (shokulab/kaiserfinance) out of the box.

## 2.1.0
- Visitors choose benchmarks themselves: S&P 500 by default, plus Nasdaq 100, MSCI World, Switzerland, gold and Bitcoin as extra lines.

## 2.0.0
- USD / CHF switch, including the dollar–franc effect.
- Optional public amounts: totals or every holding.
- Link-preview image for WhatsApp, LinkedIn & co.
- Self-updates from GitHub.
- Reset ledger with a confirmation window.

## 1.8
- Cash currency dropdowns; buys and sells settle in cash; no overdrawing.
- Price field hints the last known price and the cash available.

## 1.7
- Login icon in the header; gold $ favicon.

## 1.6
- Light/dark switch with a soft fade; language fade.

## 1.5
- Multi-currency cash, time-weighted returns, 7D–All periods.

## 1.0–1.4
- Ledger, hourly prices from Twelve Data, public page in English and German.
