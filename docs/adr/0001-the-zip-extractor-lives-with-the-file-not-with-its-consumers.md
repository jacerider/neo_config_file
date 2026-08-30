# 0001 — The **zip extractor** lives with the **config file**, not with its consumers

**Status:** accepted · **Date:** 2026-08-29
**Context:** `neo_config_file` — the **zip extractor**, a service in a module that never unpacks
anything itself
**Issue:** jacerider/neo_icon#1

**Decision.** Core removes the whole `Drupal\Core\Archiver` namespace in Drupal 12 with no
replacement, and the two modules that unpack a **config file** both depend on it. The replacement is
one service here — `neo_config_file.zip_extractor`, a final class over `ZipArchive` — that both
**consuming modules** call. This module gains a declared `ext-zip` requirement and does not itself
extract anything.

**Why it needs recording.** A reader finds a module described as storing files in configuration and
asks why it ships an archive service it never calls. Because every alternative home is worse on its
own merits: the base package could host it — Composer already resolves the require cycle the two sit
in — but its subject is the stack's aggregation, not a file's payload, and neither consumer requires
it directly; either consumer hosting it makes the other depend on a module it has nothing to do
with; and letting both keep their own copy is what produced two different shapes of the same four
lines in the first place — one injecting a manager, one fetching it statically, one throwing on a
bad archive and one silently doing nothing. This module is the only place both consumers already
depend on directly, and the zip they unpack is a **config file**'s own payload.

**Rejected.**
- The base package — possible, since Composer already resolves the cycle, but wrong on merit: its
  subject is aggregation, only one consumer requires it directly, and it is the stack's heaviest
  dependency to hang a four-line need on.
- The icon module, with the favicon module depending on it — legal, since that edge exists
  transitively, but a favicon module requiring an icon-library module reads as a mistake forever.
- A copy in each consumer — the status quo by another name, and the removal deadline is the moment
  the two copies would diverge again rather than converge.
- An interface beside the class — the stack's precedent is a final, interface-less service swapped
  by service override; removing `final` later breaks nothing, adding it does.

**Cost.** `neo_config_file.zip_extractor` is public permanently: renaming or removing it is a
breaking release for every site that installs either consumer, and this module must release before
or together with any consumer that calls it — the consumers stay at `^1`, so ordering, not a
constraint, carries that coupling. The service is dead weight for a site that stores only text in
configuration. Pinned by the extractor's own unit tests, which are this module's first.
