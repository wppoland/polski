# i18n maintenance fan-out

Reusable helpers for the translation pass described in `docs/NEXT-STEPS.md`.
They translate the entries that show up in the wrong language because the stored
translation is empty or just a copy of the English (or Polish) source.

## Flow

1. Regenerate the template and merge it into every locale:
   ```
   npm run i18n:pot   # or: wp i18n make-pot . languages/polski.pot --exclude=node_modules,vendor,_benchmark,build,tests --domain=polski
   for loc in pl_PL de_DE cs_CZ sk_SK uk lt_LT be_BY zh_CN; do
     msgmerge --no-fuzzy-matching --backup=none --update "languages/polski-$loc.po" languages/polski.pot
   done
   ```

2. Dump the entries that need translating (writes `/tmp/untr-<loc>.json`):
   ```
   php scripts/i18n/po-analyze.php languages emptyonly   # only empty msgstr (new strings)
   php scripts/i18n/po-analyze.php languages dump        # empty + English/Polish copies
   ```

3. Fan out one subagent per locale. Give each agent `/tmp/untr-<loc>.json` and
   ask it to translate ONLY the genuine natural-language strings into that
   language, skipping brand names, URLs, codes/placeholders (`%s`, `{done}`,
   `[shortcode]`), and strings already in the target language. Each agent
   returns `{ pairs: [{ src, tr }] }`. Write that to `/tmp/map-<loc>.json`.

4. Build a compendium and merge it over the catalog (agent translations win):
   ```
   for loc in pl_PL de_DE cs_CZ sk_SK uk lt_LT be_BY zh_CN; do
     php scripts/i18n/build-po.php languages "$loc"
     msgcat --use-first "/tmp/trans-$loc.po" "languages/polski-$loc.po" -o "/tmp/merged-$loc.po" \
       && cp "/tmp/merged-$loc.po" "languages/polski-$loc.po"
   done
   ```

5. Recompile and verify:
   ```
   wp i18n make-mo languages languages
   wp i18n make-json languages --no-purge --pretty-print
   php scripts/i18n/po-analyze.php languages
   ```

## Notes

- These scripts use `/tmp` for interchange files; they are dev tooling and are
  excluded from the shipped package by `.distignore`.
- Polish-source strings (msgid already Polish) legitimately have `msgstr == msgid`
  for `pl_PL` and display correctly via the gettext msgid fallback. Agents should
  skip them for `pl_PL`; other locales translate them.
