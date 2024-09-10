CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * How it works
 * Notes


INTRODUCTION
------------

Provides the ability to store files in config.


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

Install as you would normally install a contributed Drupal module. Visit
https://www.drupal.org/node/1897420 for further information.

Add to .gitignore:

```
# Neo Config File
!/config/files/*
```

HOW IT WORKS
------------

Use the 'neo_config_file' element an upload form can be added to FAPI. This
element will return the neo config file entity id and not the file entity id.
This allows the correct file to always be used even if the file id changes
from one environment to another.

```
$form['file'] = [
  '#type' => 'neo_config_file',
  '#title' => $this->t('File'),
  '#default_value' => $this->configuration['file'],
  '#extensions' => ['jpg', 'jpeg', 'png', 'gif'],
  '#dependencies' => [
    'module' => [
      'neo_style',
    ],
  ],
];
```

When a file is uploaded, a file entity is created. When this happens, an
exportable neo config file entity is also created which stores the uri of this
file entity among other things. The actual file data is also stored temporarily
in the database. At this point in time, the file entity is a temporary file.

When a config export is run, all the neo config file entities about to be
exported will clone the file their uri points to into the config directory. At
this time any database cache data is purged and the associated file entity is
set as permanent.

When a config import is run, all the Neo config file entities being imported
will create a file entity if needed and clone the actual file from the config
directory into the public files directory.

If a file has been uploaded and a config export has not been run we use the
database cache to recreate the file. Say, for example, a new file is added on a
remote environment and that database is immediately pulled down. In this case,
we have the file entity, and the neo config file entity, but we do not have the
actualy file in either the config or files directory. However, the file has
been temporarily stored in the database cache and it will be cloned into both
directories when a config export is run.

NOTES
-----

There is no usage tracking on the uploaded files and it is recommended that
files are *not re-used*. Via the UI, when a file is removed from the
'neo_config_file' FAPI element, the public file, file entity and neo config file
are deleted. The config file will persist until the next export/import. It can
be restored running a config import.
