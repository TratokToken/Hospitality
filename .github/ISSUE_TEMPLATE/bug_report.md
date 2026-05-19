---
name: Documentation / SDK bug
about: A doc page is wrong, an example client misbehaves, or the OpenAPI spec doesn't match reality.
title: "[doc/sdk] "
labels: bug
---

**What's wrong?**
A clear, one-paragraph description.

**Where?**
- File / URL: <e.g. `docs/modules/pricing.md` line 42, or `openapi.yaml` paths/Booking>
- Section: <heading or schema name>

**Expected**
What the doc/SDK should say or do.

**Actual**
What it currently says or does, plus the API behavior you observed.

**Reproduction (if SDK)**
```bash
# minimal command or code that demonstrates the mismatch
```
Server response (key REDACTED):
```json
{ … }
```

**Environment (if SDK)**
- Client language + version:
- Tratok client file version (from CHANGELOG):
- OS / runtime:

**Server-side issues?** If the API itself is returning the wrong data,
email `api-support@tratok.net` first — this tracker is for docs/SDK
fixes only.
