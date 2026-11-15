#!/usr/bin/env python3
"""Write wpml-config.xml from the text settings declared in src/Admin/ModulesPage.php.

WPML and Polylang read wpml-config.xml to offer a plugin's stored option texts
(labels, notices, email texts) for translation per language. Only shopper- or
admin-facing words are listed; ids, keys, secrets, URLs, emails, formats and
JSON stay out, so a translator is never shown a credential.

Run: python3 scripts/gen-wpml-config.py        (rewrites wpml-config.xml)
     python3 scripts/gen-wpml-config.py --check (exit 1 if the file is stale)
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SRC = (ROOT / "src/Admin/ModulesPage.php").read_text(encoding="utf-8")
OUT = ROOT / "wpml-config.xml"

FIELD = re.compile(r"'key' => '(polski_[a-z_]+)\|([a-z0-9_]+)'[^]]{0,200}'type' => '(text|textarea)'")
WORDS = re.compile(r"(^|_)(text|label|title|description|notice|error|content|heading|subject|placeholder|note|message|intro|greeting|suffix|caption_prefix|instruction|html|plain)$|^column_|^nutrients_column_|^admin_")
NOT_WORDS = re.compile(r"(_id|_key|_secret|_url|_email|_json|_format|_webhook|_domain|_phone|_vat|_number)$|^(color|position|separator|series|attribute_taxonomies|contact_name|default_reporter_name|nutrients_reference_unit)$")

options: dict[str, set[str]] = {}
for option, key, _ in FIELD.findall(SRC):
    if WORDS.search(key) and not NOT_WORDS.search(key):
        options.setdefault(option, set()).add(key)

lines = ["<wpml-config>", "  <admin-texts>"]
for option in sorted(options):
    lines.append(f'    <key name="{option}">')
    lines += [f'      <key name="{key}" />' for key in sorted(options[option])]
    lines.append("    </key>")
lines += ["  </admin-texts>", "</wpml-config>", ""]
xml = "\n".join(lines)

if "--check" in sys.argv:
    if not OUT.exists() or OUT.read_text(encoding="utf-8") != xml:
        print("wpml-config.xml is stale: run python3 scripts/gen-wpml-config.py")
        sys.exit(1)
    print(f"wpml-config.xml current ({sum(map(len, options.values()))} texts in {len(options)} options)")
    sys.exit(0)

OUT.write_text(xml, encoding="utf-8")
print(f"wrote {OUT.name}: {sum(map(len, options.values()))} texts in {len(options)} options")
