#!/usr/bin/env bash
#
# triage-scan.sh - read-only digest of GitHub issues that need triage.
#
# Part of the human-in-the-loop triage loop described in ISSUE-TRIAGE.md:
#   scan (this script) -> classify + draft -> OWNER DECISION -> execute.
# This script ONLY reads. It never comments, labels, closes, or writes anything.
#
# Usage:
#   scripts/triage-scan.sh            # human-readable digest of issues to triage
#   scripts/triage-scan.sh --json     # raw JSON (for the assistant to classify)
#   scripts/triage-scan.sh --pro      # also include the private wppoland/polski-pro repo
#   scripts/triage-scan.sh --all      # include ALL open issues, not just untriaged
#
# "Needs triage" = an open issue that has no state label yet, i.e. none of:
#   needs-triage, accepted, declined, in-progress, blocked, duplicate, wontfix.
# (An issue with zero labels also needs triage.)
#
# Requires: gh (authenticated), jq.

set -euo pipefail

FREE_REPO="wppoland/polski"
PRO_REPO="wppoland/polski-pro"

JSON=0
INCLUDE_PRO=0
ALL=0
for arg in "$@"; do
  case "$arg" in
    --json) JSON=1 ;;
    --pro)  INCLUDE_PRO=1 ;;
    --all)  ALL=1 ;;
    -h|--help)
      sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'
      exit 0 ;;
    *) echo "Unknown argument: $arg" >&2; exit 2 ;;
  esac
done

for bin in gh jq; do
  command -v "$bin" >/dev/null 2>&1 || { echo "Error: '$bin' is required but not installed." >&2; exit 1; }
done

# State labels that mean an issue has already been triaged.
TRIAGED_LABELS='["accepted","declined","in-progress","blocked","duplicate","wontfix","invalid"]'

# Fetch open issues for a repo as JSON, annotate whether each needs triage.
fetch_repo() {
  local repo="$1"
  gh issue list --repo "$repo" --state open --limit 200 \
      --json number,title,author,labels,createdAt,updatedAt,comments,url 2>/dev/null \
    | jq --arg repo "$repo" --argjson triaged "$TRIAGED_LABELS" --argjson all "$ALL" '
        map(
          . as $i
          | ($i.labels | map(.name)) as $names
          | (($names | length) == 0
             or ($names | any(. as $n | ($triaged | index($n)) != null) | not)) as $needs
          | select($all == 1 or $needs)
          | {
              repo: $repo,
              number: .number,
              title: .title,
              author: (.author.login // "unknown"),
              labels: $names,
              needs_triage: $needs,
              comments: .comments,
              created: .createdAt,
              updated: .updatedAt,
              url: .url
            }
        )'
}

ALL_JSON="[]"
ALL_JSON=$(fetch_repo "$FREE_REPO")
if [ "$INCLUDE_PRO" -eq 1 ]; then
  PRO_JSON=$(fetch_repo "$PRO_REPO" || echo "[]")
  ALL_JSON=$(jq -s 'add' <(printf '%s' "$ALL_JSON") <(printf '%s' "$PRO_JSON"))
fi

if [ "$JSON" -eq 1 ]; then
  printf '%s\n' "$ALL_JSON"
  exit 0
fi

# Human-readable digest.
COUNT=$(printf '%s' "$ALL_JSON" | jq 'length')
echo "======================================================================"
if [ "$ALL" -eq 1 ]; then
  echo " Triage scan - ALL open issues ($COUNT)"
else
  echo " Triage scan - issues needing triage ($COUNT)"
fi
echo " Repos: $FREE_REPO$( [ "$INCLUDE_PRO" -eq 1 ] && echo " + $PRO_REPO" )"
echo " (read-only - no changes made; classify, then the owner decides)"
echo "======================================================================"

if [ "$COUNT" -eq 0 ]; then
  echo "Nothing to triage. All open issues carry a state label."
  exit 0
fi

printf '%s' "$ALL_JSON" | jq -r '
  sort_by(.created)
  | .[]
  | "#\(.number) [\(.repo|sub("wppoland/";""))]  \(.title)\n" +
    "    by @\(.author) - \(.comments) comment(s) - opened \(.created[0:10])\n" +
    "    labels: \(if (.labels|length)>0 then (.labels|join(", ")) else "(none)" end)\n" +
    "    \(.url)\n"'

echo "----------------------------------------------------------------------"
echo "Next: classify each (bug/enhancement/question/legal/integration/i18n/pro/"
echo "invalid/duplicate), draft a reply + (for bug/enh) an implementation plan,"
echo "then present the decision digest to the owner. Nothing ships without approval."
