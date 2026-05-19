# Contributing

This repository tracks **documentation and examples** for the Tratok
Hospitality API. The server-side implementation lives in the closed
Tratok platform repo and isn't accepted via PR here.

We do welcome contributions to:

- **Documentation fixes** — typos, missing fields, clarifications,
  better diagrams.
- **OpenAPI improvements** — schema accuracy, missing examples, codegen
  compatibility tweaks.
- **Client SDKs** — bug fixes in the four bundled clients, improved
  ergonomics, additional language clients (Ruby, Go, C#, Java, Rust).
- **Recipes / cookbooks** — copy-paste-able solutions to common
  provider problems (sync, channel manager bridges, accounting
  pulls).

---

## Workflow

1. **Open an issue first** for anything non-trivial. A 10-minute
   conversation up-front saves a 2-hour PR rewrite.
2. **Fork → feature branch → PR** against `main`.
3. Keep PRs focused — one logical change per PR. Documentation +
   matching code example in the same PR is fine; documentation +
   unrelated code refactor is not.
4. Update [`CHANGELOG.md`](CHANGELOG.md) under `[Unreleased]` for any
   change a reader would care about.

---

## Style

- **Markdown**: 80-100 column soft wrap. Use ATX headings (`#`, not
  underline). Code fences with language hints.
- **OpenAPI**: 2-space indentation, alphabetize property keys where
  alphabetization aids skimming.
- **Python**: PEP 8, type hints on public API, docstrings on the
  client class and every public method.
- **Node.js**: ES2022, no transpiler. Stick to CommonJS for the
  client; ESM is fine in examples.
- **PHP**: 8.1+, strict types, PSR-12 spacing.

No formal linter is enforced — match the style of the surrounding code.

---

## Adding a new language client

If you'd like to contribute a Ruby/Go/Java/etc. client:

1. Create `examples/<lang>/` with the language-conventional layout.
2. Mirror the surface of the existing clients (Python is the
   reference): `me()`, `list_properties()`, `iter_properties()`,
   pricing/inventory writes including bulk, bookings + iter,
   `revenue()`, webhook verification.
3. Add a runnable demo and a tiny README explaining install + run.
4. Open the PR; the maintainers will help adjust naming conventions
   to match the language idiom.

---

## Reporting a server-side bug

This repo isn't where server bugs are tracked. For "the API returned
500 / wrong data / unexpected behavior":

1. Email `api-support@tratok.net` with the request details
   (API key REDACTED), the response, and the UTC timestamp.
2. **Then** open an issue here only if the documentation is
   misleading and contributed to the confusion — i.e. the API is
   correct, the docs are wrong.

---

## Code of conduct

Be kind. Assume good faith. The
[Contributor Covenant 2.1](https://www.contributor-covenant.org/version/2/1/code_of_conduct/)
applies; report unacceptable behavior to `support@tratok.net`.
