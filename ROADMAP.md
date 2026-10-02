# Roadmap

Improvements are shipped one at a time, roughly hourly, each as its own version.

## Done
- 2.3.3: sturdier update check with visible status
- 2.4.0: chart tooltip (hover, tap, arrow keys)
- 2.5.0: YouTube and Instagram links
- 2.6.0: TradingView chart links on holdings
- 2.7.0: speed (compact data, deferred script, no layout jump)

## Next up (in rough order)
1. **German number format in the verdict line** ("5,86 Prozentpunkte", not "5.86") and other small fixes
2. **"Prices delayed" note** on the public page if prices are more than 6 hours old
3. **Customizable text:** headline, intro and disclaimer in English and German from Settings
4. **Fees on trades:** optional fee field in the trade form, paid from cash
5. **Edit cash entries,** not only delete them
6. **CSV export** of trades and cash (backup) from the ledger
7. **Risk stats:** maximum drawdown and best/worst month
8. **Monthly returns grid** (fund-factsheet style)
9. **Accessibility pass:** keyboard and screen-reader support for chart, chips and switches
10. **No-JavaScript fallback:** a plain table of returns
11. **Automated checks:** PHP/JS syntax and maths tests run on every push (GitHub Actions)

## Integrations: decisions
- **TradingView widgets: no.** They load TradingView's script and cookies on every visit, slow the page, show TradingView's branding, and their prices can differ from ours (Twelve Data), which would confuse visitors. Instead: plain "open on TradingView" links per holding (2.6.0).
- **YouTube / Instagram: yes,** as plain links (2.5.0). No embedded feeds: those need API tokens and load trackers.
- **Later, if wanted:** newsletter sign-up via a hosted form link (e.g. Buttondown), privacy-friendly visitor counts (Plausible or Cloudflare Web Analytics, no cookies), CSV import from a broker export, and an RSS/JSON feed of monthly returns.
