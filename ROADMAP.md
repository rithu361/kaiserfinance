# Roadmap

Improvements are shipped one at a time, roughly hourly, each as its own version.

## Done
- 2.3.3: sturdier update check with visible status
- 2.4.0: chart tooltip (hover, tap, arrow keys)
- 2.5.0: YouTube and Instagram links
- 2.6.0: TradingView chart links on holdings
- 2.7.0: speed (compact data, deferred script, no layout jump)
- 2.8.0: tables fit on phones
- 2.9.0: "prices may be delayed" note
- 2.9.1: tooltip closes when tapping outside the chart (phones)

## Next up (in rough order)
1. **Smoother chart (requested):** soft cross-fade on range change, benchmark lines fade in/out, one-time draw-in on load; tooltip stays instant; nothing moves with reduced motion
2. **Customizable text:** headline, intro and disclaimer in English and German from Settings
3. **Fees on trades:** optional fee field in the trade form, paid from cash
4. **Edit cash entries,** not only delete them
5. **CSV export** of trades and cash (backup) from the ledger
6. **Risk stats:** maximum drawdown and best/worst month
7. **Monthly returns grid** (fund-factsheet style)
8. **Accessibility pass:** keyboard and screen-reader support for chart, chips and switches
9. **No-JavaScript fallback:** a plain table of returns
10. **Automated checks:** PHP/JS syntax and maths tests run on every push (GitHub Actions)

## Integrations: decisions
- **TradingView widgets: no.** They load TradingView's script and cookies on every visit, slow the page, show TradingView's branding, and their prices can differ from ours (Twelve Data), which would confuse visitors. Instead: plain "open on TradingView" links per holding (2.6.0).
- **YouTube / Instagram: yes,** as plain links (2.5.0). No embedded feeds: those need API tokens and load trackers.
- **Later, if wanted:** newsletter sign-up via a hosted form link (e.g. Buttondown), privacy-friendly visitor counts (Plausible or Cloudflare Web Analytics, no cookies), CSV import from a broker export, and an RSS/JSON feed of monthly returns.


## Notes
- German numbers use the Swiss style on purpose (5.86 and 64'868), matching kaiserfinance.ch's audience. Switching to 5,86 would be a one-line change (locale de-CH → de-DE).
