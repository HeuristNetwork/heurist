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
  `StoryMapSettings`. A PATCH of one of the two fields is checked with the stored other.
- `StoryMapSettings.php` — form check of the story map settings (plan 27q1 §4). A draft without rule or template is
  fine; a given value must have the right type. Place patterns: `points` and `path` only (§6.1).

Test: `php tests/PresentationEntityTest.php`.
