# CONTEXT — neo_config_file

Terms specific to this module: the entity that keeps a real file beside exported configuration, and
the services built around it. General Drupal vocabulary (file entity, stream wrapper, config entity)
does not belong here.

## Config files

**Config file** — the `neo_config_file` config entity that keeps one real file alongside exported
configuration, moves it between the `config://` and public streams, and announces its own saves and
deletions so the module that owns the file can act on the new bytes. _Avoid:_ "managed file", "the
file entity", "config attachment".

**Consuming module** — the module that owns what a **config file** carries. It declares the
extensions the upload element accepts and it is the only thing that knows what the bytes mean; this
module stores and delivers a file, and never interprets one. _Avoid:_ "the parent", "the owner
module".

## Archives

**Zip extractor** — the service that unpacks a zip into a directory: it resolves the archive to a
real path, opens it, and writes every entry beneath a destination that may itself be a stream URI.
It only ever unpacks — it does not create archives, add to them, or list them — and it reports
failure by throwing rather than by returning nothing. See ADR 0001. _Avoid:_ "archiver", "the
archive plugin", "unzipper", "extract service".

**Extraction refusal** — the single failure the **zip extractor** reports: the archive could not be
resolved to a real path, or could not be opened as a zip. It carries the underlying open status, so
a **consuming module** can log it or re-throw it in its own words, and it is raised for a file named
`.zip` whose bytes are not a zip — the case a filename test cannot see. _Avoid:_ "invalid archive
error", "the archiver exception".
