# Registered files (/files)

## Overview

The `/api/{db}/files` domain (plan 20, heurist-explorer
`docs/development/20 System-Data-Transition-and-Files-Plan.md`): registered
files (uploads and external resources) as records of the record type
"Registered file" (`RT_REGISTERED_FILE`, 2-1106), in the same JSON as
`/records`. Clients use the same request and response for records and files.

Files have two stores behind `FileStoreInterface`:

| Store | Tables | When |
|---|---|---|
| `LegacyFileStore` | `recUploadedFiles` | now |
| `RecordFileStore` | `fileRecords`/`fileRecDetails` (structure of `Records`/`recDetails`) | after the switch at the end of the transition; tests only until then |

`ServiceFactory::fileController()` takes `RecordFileStore` when the database has
both file tables (`RecordRepository::tablesExist`), otherwise `LegacyFileStore`.

## Routes

| Method | Path | Action |
|---|---|---|
| GET, POST | `/files` | search: the parameters and response of `/records` (query, fields, detail=ids, resolveDetails, defs, sort, limit, offset) |
| GET, POST | `/files/aggregate` | `op=count` or `op=rectypes` with the search parameters (plan 20 Phase 3); values/minmax/ranges → 422 |
| GET | `/files/{id}` | one file record; fields default to `_all` |
| POST | `/files/0` | register: `"upload": token` of a finished upload, or `rec_URL` |
| PUT, PATCH | `/files/{id}` | change; with `"upload"` the content is replaced |
| DELETE | `/files/{id}` | delete; 409 `file_in_use` while records refer to it |
| POST | `/files/{id}/validate` | the checks only |
| POST | `/files/upload` | start an upload `{name, size, type}` → `{token, chunkSize}` |
| PUT, GET, DELETE | `/files/upload/{token}` | one chunk (raw body, Content-Range, X-Heurist-Client) / received size / cancel |
| GET | `/files/{key}/content`, `/files/{key}/thumbnail` | the file (ranges, 206) and its thumbnail by the obfuscated key |

Logged-in users only, except content and thumbnails of viewable files. Writes: same-site
Origin, JSON bodies; chunks need the `X-Heurist-Client` header. Register: any logged-in
user; change and delete: the uploader or the database owner (legacy rule).

## Writing (Phase 2)

`FileWriteService` holds the rules for both stores; only the row differs
(`FileRowsInterface`: `LegacyFileRows` = `Records/Files/LegacyFileTable` on
`recUploadedFiles`, `RecordFileRows` = `RecordStore` on `RecordSchema::FILE_TABLES`).
`DbRecUploadedFiles` is not used.

- `Upload/ChunkedUpload` — uploads in `scratch/uploads/<token>`: chunks must continue at
  the received size (409 with `received`), the last chunk gives MD5, MIME type, an image
  thumbnail (`ImageThumbnail`, GD, EXIF orientation) and the files with the same MD5
  (`duplicates`). Extensions of `FileRepository::allowedTypes()` only (in `defFileExtToMimetype`
  and a term of vocabulary 2-533), never the stop list `ChunkedUpload::DENIED`; disk quota.
- `FileStorage` — the disk part of legacy save/delete with the legacy names:
  `file_uploads/ulf_<id>_<name>`, `filethumbs/ulf_<key>.png`, key `sha1(id.rand)`, tiled
  stacks to `uploaded_tilestacks/<id>/` (zip) or `<name>.mbtiles`; delete removes the file,
  thumbnail and cached copies. Only paths inside the database file store. Of a tile stack zip
  only the tile images (`TILE_IMAGE_EXTENSIONS`: png jpg jpeg gif webp, not of the stop list)
  are extracted; scripts, `.htaccess` and the pages of gdal2tiles never reach the disk; a zip
  without tile images is refused (2026-10-27; legacy `DbRecUploadedFiles` still unpacks all).
- `FileUrlInspector` — external URLs: YouTube/Vimeo/SoundCloud, IIIF manifest and image
  service (canonical `info.json`), else the Content-Type of a HEAD request or the URL
  extension. An already registered URL gives its file. Without a known type the file type is
  `bin` (`guessed`) and the save returns the warning `file_type`.
- `FileContent` — content with ranges; external resources, tiled stacks and old absolute
  paths redirect (to the URL or the legacy `?file=`); a missing thumbnail redirects to
  the legacy `?thumb=`. Login-required files are refused to guests (every type).
- The row is written in a transaction that is rolled back when the file cannot be
  placed; the files of a deleted row are removed after the commit.
- The file pair has its own permissions table (`filePermissions`): file ids and record
  ids overlap; `RecordSchema` cleans no user tables (reminders, tags...) for it.
- File type 2-29 (DT_FILE_EXT; plan 20 §10.11, 2026-10-23, extensions since 2026-10-27): set
  from the content for local files and IIIF; the user may change it only for tiled images and
  external resources (detection is not always right). The term's code is the extension that is
  stored (`ulf_MimeExt`); a group of the vocabulary is refused. The MIME type is always derived
  from the extension (`defFileExtToMimetype`: download, players, export, `fxm_MimeType` of
  `fields=_all`). New content of a tile stack is placed as a tile stack again (`placeTiles`),
  and a file type sent with it wins over its first image.

## Mapping

`FileRecordMapping` is the one mapping between `recUploadedFiles` and the record
format (plan 20, §3.1). Fields are found by concept code; a field missing in a
database is left out.

- Headers: `rec_ID` = `ulf_ID`, `rec_Hash` = `ulf_ObfuscatedFileID`, `rec_URL` =
  `ulf_ExternalFileReference`, `rec_OwnerUGrpID`/`rec_AddedByUGrpID` = uploader,
  `rec_NonOwnerVisibility` = `ulf_WhoCanView` (viewable → public,
  loginrequired → viewable), `rec_Added`, `rec_Modified`, import and origin ids.
- `rec_Title`: the caption, else the original name, else the URL.
- Fields: original name 2-1185, caption DT_NAME, description, copyright,
  copyright owner 2-1188, file type 2-29 (the term of the extension; a database with the
  older vocabulary: its MIME type term), size 2-67 in bytes (KB × 1024), MD5, registered file type 2-1186
  (local, external, iiif, iiif_image, tiled), configuration 2-1187 (JSON);
  stored name 2-62 and folder 2-63 for database administrators only.

## Search on the legacy store

`LegacyFileQueryBuilder` compiles the standard query (`RecordQueryParser`) to SQL on
`{files}`: `ids`, `title` (and plain text), `url`, `added`, `modified`,
`owner`/`addedby` (user ids or `current`), `f:<field>` of the mapped fields (`f` without a field = the title, plan 21) and
`any`/`all`/`not`. File type values are term ids (an extension, or a group: its extensions),
extensions (`pdf`), MIME types (`image/jpeg`) or MIME groups (`image/*`); size values are bytes. Sort names are those
of the records search (`id`, `title`, `added`, `modified`, `url`, `f:<field>`),
default by id. Logged-in users see every file; guests (not routed now) only
viewable ones.

## Tests

`php tests/FileApiTest.php --db=osmak_mapping --user=2 [--write]` — with `--write`
the files are also copied to temporary `fileRecords`/`fileRecDetails` tables and
both stores must give the same records.

`php tests/FileWriteTest.php --db=osmak_mapping --user=2 [--write]` — uploads, URLs,
ranges (no tables); with `--write` registration, changes, content, permissions and
delete on both stores. Files go to a temporary file store; every row and table it
adds is removed again.

## Entity images (plan 20, Phase 4d)

`EntityImageStorage` keeps the one image of a user or group (later record types, terms...)
in the legacy entity folders (`entity/sysUsers/<id>.<ext>`, `thumbnail/<id>.png`), agreed
2026-10-23 until the switch at the end of plan 20, when images become registered files. No
other srv code names these folders. `System/Entity/EntityImageService` gives
`GET | PUT | DELETE /sys/{type}/{id}/image` (PUT: a finished chunked upload).

