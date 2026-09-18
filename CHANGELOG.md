# Changelog

## A package still lands when its directory cannot be renamed into place

**`ZipExtractor::extract()` copies the staged files into place when the
rename fails.** The extractor unpacks next to the destination, deletes the
destination, and renames the staged directory into its place. Pantheon's file
system cannot rename a directory, and the result of the rename was never
checked, so every icon library or favicon package saved there since 1.0.30 was
left as an unread `<destination>.neo-zip-*` directory with no destination at
all — and neo_icon then reported the package as "not a recognized IcoMoon
archive". When the rename fails the staged tree is now copied into the
destination and the staging directory removed; if the copy fails too, the
extractor throws instead of returning as if it had worked. Where rename works,
nothing changes. Staging directories left behind by the old behaviour are not
cleaned up automatically.

## A config import no longer aborts on a read-only codebase

**`neo_config_file_module_preinstall()` leaves bundled files that are already
in place.** When a module ships files in `config/install/files` (neo_icon's
icon packages), the hook copies them into the config directory on install. It
did so on every install — including one that arrives through a config import,
where the files are already there, exported and committed with the config. On
a host whose codebase is read-only, such as Pantheon's test and live, the copy
threw, and the whole import stopped partway. A file already present with the
same contents is now skipped, and a file that cannot be written is logged as a
warning and skipped instead of aborting the install. A fresh install on a
writable directory behaves exactly as before.
