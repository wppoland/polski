# WP.org plugin team - resubmit reply

**Reply-to thread:** `Re: [WordPress Plugin Directory] Review in Progress: Polski for WooCommerce`
**From:** `szatkowski.mariusz@gmail.com`
**Username:** `motylanogha`

---

Hi,

Following up on the review submitted on 17 April. The current version under review remains **1.6.3** addressing the 17 April feedback. Since then we have been preparing a much larger feature batch (1.16.0) that adds the full consumer right-of-withdrawal flow required by Art. 11a of Directive 2023/2673 (deadline 19 June 2026), so we would like to either:

a) confirm 1.6.3 is approved and follow up with the 1.16.0 release post-approval, **or**
b) replace the in-review zip with 1.16.0 if the team prefers a single review pass.

If option (b) is acceptable, the upload contains:

- WP Plugin Check 1.x: pass (run via wp-env)
- WPCS phpcs --standard=phpcs.xml.dist: exit 0
- 117 PHPUnit tests pass (269 assertions)
- WP 7.0 Tested up to (declared via plugin header)
- HPOS + Cart/Checkout Blocks compatibility declared
- 20 abilities registered through WP 6.9 Abilities API
- Polish + English translations (.pot regenerated; .po/.mo for pl_PL shipped)
- No tracking / no remote calls beyond documented external services
- All user-facing strings sanitised + escaped per WPCS

If anything further is needed from my side, happy to provide.

Thanks for your time,
Mariusz / motylanogha
