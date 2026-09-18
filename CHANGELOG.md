# Changelog

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
