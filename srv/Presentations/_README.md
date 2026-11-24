# Presentations

Presentation sets (record type 2-1112, plan 27q1): the stored settings of a story map, crosstabs, an export or a
report (plan 27q3). Entity `presentation` of `Records/Entity/RecordEntityRegistry`; only the generic routes of plan 28
(`/presentation/{id}`, `/presentation/{id}/duplicate`, `/sys?q=[{"t":"presentation"}]`) and, for the report kinds, the
actions of `Reports/ReportEntityHandler.php` (`asset`, `execute`, `export`; `/presentation/import`, `/presentation/setup`).
Another kind answers 400 to them.

| Field | Concept | Use |
|---|---|---|
| Name or Title | 2-1 | name in the pickers |
| Short summary | 2-3 | description (shown in the List, plan 27q3) |
| Heurist Query String | 2-12 | source query (Explorer: the test query) |
| Configuration | 2-1187 (`DT_CONFIGURATION`) | settings as JSON |
| Presentation Type | 2-1203 (`DT_PRESENTATION_TYPE`) | kind: StoryMap 2-10644, Crosstab 2-10642, Export 2-10643, Card Report 2-10645, List Report 2-10646 |
| File name | 2-62 (`DT_FILE_NAME`) | reports: the template file, set once |
| Mime Type | 2-29 (`DT_FILE_EXT`) | reports: output format |

Website pages point back to the Presentation sets of their story map and crosstabs elements with *Presentations on this
page* 2-1204 (`DT_PAGE_PRESENTATIONS`, plan 27q3): `Website/WebsiteEditService::withPresentations` sets it on every
save of a page configuration (page entity, Publish / import). The List of a presentation finds its pages with
an ordinary search, `/records?q=[{"t":"<page type>"},{"f:<2-1204>":"<id>"}]` (as the outputs of a report: `f:<2-1183>`).

## Files

- `PresentationEntityHandler.php` — hooks: the configuration is a JSON object; a story map is checked by
  `StoryMapSettings`, crosstabs by `CrosstabSettings`. A PATCH of one of the two fields is checked with the stored other.
  A report kind is handed to `Reports/ReportEntityHandler.php` (template file, outputs, actions).
- `CrosstabSettings.php` — form check of the crosstab settings (plan 27q2 §5): rows / columns / pages in the form of
  the `crosstab` parameter of `/records/aggregate?op=crosstab` (field path code + buckets) with a caption; measure,
  display, chart, visitor level. A draft without rows is fine.
- `StoryMapSettings.php` — form check of the story map settings (plan 27q1 §4, §15). A draft without rule or template is
  fine; a given value must have the right type. Places (§15): geographic field paths per role begin / transition /
  end, as in Query sources; the background map is a map document.

Test: `php tests/PresentationEntityTest.php`.
