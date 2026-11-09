#!/usr/bin/env bash
# Adds one line under "## [Unreleased]" -> "### Changed" in CHANGELOG.md when Renovate
# updates the base-php images (see renovate.json, postUpgradeTasks). A digest update
# has no version to quote. Run again before a release, it adds nothing.
set -euo pipefail

file=${CHANGELOG:-CHANGELOG.md}
entry="- **base-php images updated** to their latest published digests."

if awk -v entry="${entry}" '
	/^## \[Unreleased\]/ { in_unreleased = 1; next }
	in_unreleased && /^## \[/ { exit }
	in_unreleased && $0 == entry { found = 1 }
	END { exit !found }
' "${file}"; then
	exit 0
fi

awk -v entry="${entry}" '
	function add_section() {
		print "### Changed"
		print entry
		print ""
		done = 1
	}
	/^## \[Unreleased\]/ { in_unreleased = 1; print; next }
	in_unreleased && /^## \[/ {
		if (!done) add_section()
		in_unreleased = 0
	}
	in_unreleased && !done && /^### Changed/ { print; print entry; done = 1; next }
	{ print }
	END { if (in_unreleased && !done) add_section() }
' "${file}" >"${file}.tmp"
mv "${file}.tmp" "${file}"
