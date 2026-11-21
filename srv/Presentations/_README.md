# Presentations

Presentation sets (record type 2-1112, plan 27q1): the stored settings of a story map, crosstabs or an export.
Entity `presentation` of `Records/Entity/RecordEntityRegistry`; only the generic routes of plan 28
(`/presentation/{id}`, `/presentation/{id}/duplicate`, `/sys?q=[{"t":"presentation"}]`).

| Field | Concept | Use |
|---|---|---|
| Name or Title | 2-1 | name in the pickers |
| Short summary | 2-3 | description |
| Heurist Query String | 2-12 | source query (Explorer: the test query) |
| Configuration | 2-1187 (`DT_CONFIGURATION`) | settings as JSON |
| Presentation Type | 2-1203 (`DT_PRESENTATION_TYPE`) | kind: StoryMap 2-10644, Crosstab 2-10642, Export 2-10643 |

## Files

- `PresentationEntityHandler.php` — hooks: the configuration is a JSON object; a story map is checked by
  `StoryMapSettings`, crosstabs by `CrosstabSettings`. A PATCH of one of the two fields is checked with the stored other.
- `CrosstabSettings.php` — form check of the crosstab settings (plan 27q2 §5): rows / columns / pages in the form of
  the `crosstab` parameter of `/records/aggregate?op=crosstab` (field path code + buckets) with a caption; measure,
  display, chart, visitor level. A draft without rows is fine.
- `StoryMapSettings.php` — form check of the story map settings (plan 27q1 §4, §15). A draft without rule or template is
  fine; a given value must have the right type. Places (§15): geographic field paths per role begin / transition /
  end, as in Query sources; the background map is a map document.

Test: `php tests/PresentationEntityTest.php`.
