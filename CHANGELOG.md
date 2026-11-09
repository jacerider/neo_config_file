# Changelog

## One upload makes one file, even when a form refreshes during it

**`ConfigFile::valueCallback()` holds a lock while it checks for and saves an
upload.** A form that refreshes on change, such as the Alchemist component
editor, posts the chosen file a second time while the managed-file upload is
still saving it. The element's duplicate check only knew about a file once its
save had finished, so a request arriving mid-save either saved a second copy,
leaving an unreferenced `_0` file and config file that a config export then made
permanent, or collided on core's per-destination lock, which failed the upload
with "File already locked for writing" if the upload was the one that lost. The
second request now waits for the first and reuses its file. Duplicates already
exported are not cleaned up automatically.

**A reused upload survives the form rebuild.** Reusing a file took the upload
out of the request without recording the file anywhere the rebuild could see it,
so the second pass through the element came back empty: the widget showed no
file and no error, as if nothing had been chosen.

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
