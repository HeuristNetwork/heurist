# Heurist IIIF output

This document describes the current Heurist IIIF Presentation output model for registered media files, registered IIIF manifests, dynamic query/resultset exports, and Mirador integration.

Base examples below use:

```text
https://server/heurist/
Database: mydb
Example file id: cdf11cf05be4f435088e48c775ea46d66ee6473a
```

## 1. Two access modes

| Mode | URL pattern | Purpose | Main controller / class |
|---|---|---|---|
| Raw file delivery | `?db={db}&file={fileid}` | Returns the registered file itself: image, video, audio, JSON manifest, etc. | Existing Heurist file delivery |
| IIIF API resource | `/api/{db}/iiif/{resource}/{fileid}` | Returns one canonical IIIF Presentation resource for one registered file. | `api.php` -> `iiif_presentation.php` -> `ExportRecordsIIIF::getIiifApiResource()` |
| Dynamic IIIF export | `/hserv/controller/record_output.php?db={db}&format=iiif&q=...` | Builds a Manifest or Collection dynamically for a query/resultset. | `record_output.php` -> `ExportRecordsIIIF::output()` |

## 2. Example URLs

| Case | Example URL |
|---|---|
| Image file itself | `https://server/heurist/?db=mydb&file=cdf11cf05be4f435088e48c775ea46d66ee6473a` |
| Dynamic manifest for a record query | `https://server/heurist/hserv/controller/record_output.php?db=mydb&depth=all&format=iiif&q=ids:2` |
| Dynamic manifest for one registered media file | `https://server/heurist/hserv/controller/record_output.php?db=mydb&format=iiif&iiif_image=cdf11cf05be4f435088e48c775ea46d66ee6473a` |
| IIIF Canvas API resource | `https://server/heurist/api/mydb/iiif/canvas/cdf11cf05be4f435088e48c775ea46d66ee6473a` |
| IIIF Manifest API resource | `https://server/heurist/api/mydb/iiif/manifest/cdf11cf05be4f435088e48c775ea46d66ee6473a` |
| Painting AnnotationPage API resource | `https://server/heurist/api/mydb/iiif/page/cdf11cf05be4f435088e48c775ea46d66ee6473a` |
| Painting Annotation API resource | `https://server/heurist/api/mydb/iiif/annotation/cdf11cf05be4f435088e48c775ea46d66ee6473a` |
| User AnnotationPage API resource | `https://server/heurist/api/mydb/iiif/annotations/cdf11cf05be4f435088e48c775ea46d66ee6473a` |

## 3. Resource choice: ordinary registered media

For a registered ordinary image, audio, or video file:

| Requested resource | Should work? | Meaning |
|---|---:|---|
| `/iiif/canvas/{fileid}` | Yes | Canvas for the media file. |
| `/iiif/page/{fileid}` | Yes | Painting AnnotationPage for the Canvas. |
| `/iiif/annotation/{fileid}` | Yes | Painting Annotation whose body is the media file. |
| `/iiif/annotations/{fileid}` | Yes, when linked annotations exist | User AnnotationPage generated from linked `RT_MAP_ANNOTATION` records. |
| `/iiif/manifest/{fileid}` | Yes | Single-file Manifest wrapping the generated Canvas. |
| `/iiif/image/{fileid}` | Not currently required | Raw image delivery remains `?db={db}&file={fileid}`. |

## 4. Resource choice: registered IIIF Image API `info.json`

For a registered IIIF Image API information resource, usually marked with `ulf_PreferredSource = 'iiif_image'`:

| Requested resource | Should work? | Meaning |
|---|---:|---|
| `/iiif/canvas/{fileid}` | Yes | Canvas backed by the IIIF Image API service. |
| `/iiif/page/{fileid}` | Yes | Painting AnnotationPage for the generated Canvas. |
| `/iiif/annotation/{fileid}` | Yes | Painting Annotation using the IIIF Image API service. |
| `/iiif/annotations/{fileid}` | Yes, when linked annotations exist | User AnnotationPage generated from linked `RT_MAP_ANNOTATION` records. |
| `/iiif/manifest/{fileid}` | Yes | Single-file Manifest containing the generated Canvas. |
| `/iiif/image/{fileid}` | Optional future alias | Could return the `info.json`, but is not needed for the Presentation API. |

## 5. Resource choice: registered existing IIIF Manifest

For a registered external or locally uploaded IIIF Manifest, usually marked with `ulf_PreferredSource = 'iiif'`:

| Requested resource | Should work? | Meaning |
|---|---:|---|
| `/iiif/manifest/{fileid}` | Yes | Returns the actual registered Manifest JSON. External manifests are loaded from their registered URL; local manifests are loaded from the registered file. |
| `/iiif/canvas/{fileid}` | No | A Manifest is not a Canvas. |
| `/iiif/page/{fileid}` | No | There is no single painting AnnotationPage for the whole Manifest. |
| `/iiif/annotation/{fileid}` | No | There is no single painting Annotation for the whole Manifest. |
| `/iiif/annotations/{fileid}` | Possible / pending definition | Could return Heurist annotations linked to the record carrying this manifest. |
| `/iiif/collection/{fileid}` | No by default | A single registered Manifest should not become a Collection. |

## 6. Dynamic IIIF export output by recordset content

`ExportRecordsIIIF` builds IIIF Presentation API output for arbitrary search results. In version 3 output it buffers generated Canvases and registered Manifest references, then decides whether the top-level output is a Manifest or a Collection.

| Recordset content | Output type | Output behaviour |
|---|---|---|
| One ordinary media record | Manifest | One Canvas is generated for the media file. |
| Several ordinary media records | Manifest | One Canvas is generated for each media file. |
| Ordinary media records with linked annotations | Manifest | Canvases are generated for the media files. Each annotated Canvas should expose an external user AnnotationPage through `annotations[]`. |
| One registered IIIF Manifest only | Manifest | The registered Manifest itself is returned, not a one-item Collection. |
| Several registered IIIF Manifests | Collection | Each registered Manifest is referenced as a Collection item. |
| One registered IIIF Manifest plus ordinary media | Collection | The registered Manifest is one Collection item; generated media Canvases are grouped into a generated Manifest item. |
| Several registered IIIF Manifests plus ordinary media | Collection | Registered Manifests are Collection items; generated media Canvases are grouped into a generated Manifest item. |
| `RT_MAP_ANNOTATION` records alone | No top-level IIIF item | Annotation records are not exported as top-level Manifest/Collection items. |
| `RT_MAP_ANNOTATION` records plus target media/manifest records | Attached annotations | Annotation records should be attached to their target Canvas/Manifest when the target is in the resultset. |

## 7. Canvas structure

For generated media resources, the Canvas has two distinct annotation concepts:

| Canvas property | Meaning | Source |
|---|---|---|
| `items[]` | Painting AnnotationPage. This paints the image, audio, or video body onto the Canvas. | Generated automatically by `ExportRecordsIIIF`. |
| `annotations[]` | External user AnnotationPage. This contains comments, regions, transcriptions, or other user annotations. | Generated from linked Heurist annotation records, usually `RT_MAP_ANNOTATION`. |

Conceptual structure:

```text
Manifest
  items[]
    Canvas
      items[]
        Painting AnnotationPage
          Annotation motivation = painting
      annotations[]
        External user AnnotationPage URL
```

## 8. Controller/class responsibilities

| Component | Responsibility |
|---|---|
| `api.php` | Routes `/api/{db}/iiif/{resource}/{id}` to `iiif_presentation.php`. |
| `iiif_presentation.php` | Small API controller for one IIIF resource by registered file ID. |
| `record_output.php` | Export controller for dynamic IIIF output from query/resultset parameters. |
| `ExportRecordsIIIF::getIiifApiResource()` | Dispatches a single API resource: `manifest`, `canvas`, `page`, `annotation`, `annotations`. |
| `ExportRecordsIIIF::getIiifManifestForFile()` | Returns an actual Manifest for one registered file. Existing manifests are returned as-is; media files are wrapped in a single-canvas Manifest. |
| `ExportRecordsIIIF::getIiifResource()` | Builds lower-level Canvas/Page/Annotation resources for registered media files. |
| `ExportRecordsIIIF::output()` | Builds dynamic Manifest/Collection output for recordsets. |

## 9. Current and future work

| Item | Status |
|---|---|
| Dynamic Manifest for arbitrary media recordset | Implemented. |
| Mirador opening from export menu using `preparedID` | Implemented after adding `format=iiif` to the Mirador URL. |
| API Canvas for registered media | Implemented. |
| API Manifest for registered media | Implemented in the current patch. |
| API Manifest for registered existing manifests | Implemented in the current patch. |
| Single registered Manifest in recordset returns Manifest, not Collection | Implemented in the current patch. |
| Multiple registered Manifests in recordset return Collection | Implemented. |
| Imported AnnotationPage JSON -> `RT_MAP_ANNOTATION` records | To be done. |
| Fallback annotation body from summary/description/notes when `DT_ANNOTATION_INFO` is missing | To be done. |
| Explicit handling of `RT_MAP_ANNOTATION` records appearing directly in resultsets | To be refined. |
