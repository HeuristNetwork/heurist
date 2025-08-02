## **External Lookups**

Directory: [```/hclient/widgets/lookup```](.)

Overview: Perform requests to external sources to populate records

Service requests are handled within [```hserv/controller/LookupController.php```](/hserv/controller/LookupController.php)

New and existing service mapping is found at [```hserv/controller/LookupConfigs.json```](/hserv/controller/LookupConfigs.json)

Widget hierarchy:<br>
    1. baseAction [```hclient/widgets/baseAction.js```](../baseAction.js)<br>
    2. recordAction [```hclient/widgets/record/recordAction.js```](../record/recordAction.js)<br>
    3. lookupBase [```hclient/widgets/lookup/lookupBase.js```](lookupBase.js)<br>
    4. Parent Lookup Widget (_Optional_), the use of Parent Lookup widgets is simply to avoid duplicating code across files<br>
    5. Lookup Widget<br>

[lookup_Template.js](lookup_Template.js) and [lookup_Template.html](lookup_Template.html) can provide a simple template for creating new external lookups,<br>
    this also includes details on how to prepare data for terms, record pointer, files and relationship marker fields.<br>
(This template is based off of the BnF_bib lookup with some specifics stripped out and may not work 100%)

---

## **Lookup Services:**

| Service | Description | Link | Status |
| :-----: | ----------- | ---- | :----: |
| TLCMap | Query the Time Layered Cultural Mapping of Australian history and culture database | site: [tlcmap.org](https://tlcmap.org) | Disabled, issues with API response |
| GeoNames | Query the Geonames database for geographical locations covering all countries and many additional places | site: [geonames.org](https://geonames.org) | Done |
| GeoNames Postalcodes | Query the Geonames' postalcode database for more precise locale information | site: [geonames.org/postal-codes/](https://geonames.org/postal-codes/) | Done |
| MPCE | Assign keywords to a Work (Book) record from searches or by association | database: [Mapping Print Charting Enlightenment](https://HeuristRef.net/heurist/?db=MPCE_Mapping_Print_Charting_Enlightenment&website) | Done |
| LRC18 | Import record information from the ESTC_Helsinki_Bibliographic_Metadata database via a search | database: [ESTC Helsinki Bibliographic Metadata](https://HeuristRef.net/heurist/?db=ESTC_Helsinki_Bibliographic_Metadata) | Done |
| BnF Bib | Query the Bibliothèque nationale de France's bibliographic records | site: [BnF.fr](https://www.bnf.fr) | Done |
| BnF Aut | Query the Bibliothèque nationale de France's authoritative records | site: [BnF.fr](https://www.bnf.fr) | Done |
| Nomisma | Retrieve the Nomisma records for Mints, Hoards and Findspots | site: [nomisma.org](https://nomisma.org/) | Done |
| Nakala | Search Nakala's publicly available multi-media records | site: [nakala.fr](https://nakala.fr/) | Done |
| Nakala Authors | Search the authority records from Nakala's database | site: [nakala.fr](https://nakala.fr/) | Done |
| Opentheso | Query various servers that have a Opentheso service | sites: [pactols.frantiq.fr](https://pactols.frantiq.fr/index.xhtml) ; [opentheso.huma-num.fr](https://opentheso.huma-num.fr/index.xhtml) | Done |
| Wikidata | Perform SPARQL requests on the Wikidata database | site: [wikidata.org](https://query.wikidata.org/) | Done |
| Isadore | Request data from ISIDORE's database | site: [isidore.science](https://isidore.science/) |  |
| MediHAL | Request data from MediHal's database | site: [hal.science](https://media.hal.science/) |  |

---

## Returning Values to the Record Editor

For **text (_freetext_ and _blocktext_), numeric, geospatial, and date** fields:<br>
 1. The povided value will be inserted directly into the field.<br>
 2. On saving the record, any field specific validation will be performed and block saving for invalid values<br>

It is recommended for geospatial fields that WKT (_Well Known Text_) formatted values are returned.

For **term (_enumerated_)** fields:
 - A **term/vocabulary ID** (_trm\_ID_),
 - A **label to search existing terms/vocabulary** (_trm\_Label_); can also be used to create a new term, or
 - A JSON object that is used to define a new term; the object is structured as
    ```json
    {
        "label": "trm_Label",
        "desc": "trm_Description",
        "code": "trm_Code",
        "uri": "trm_SemanticReferenceURL",
        "translations": ["Array of trm_Label translations; each preceeded by the language's AR3 code"],
    }
    ```

For **file** fields: 
 - The already registered file's **Heurist Internal ID** (_ulf\_ID_) or **Heurist Public ID** (_ulf\_ObfuscatedFileID_) value, or 
 - A **URL** pointing to the file that will be registered upon returning the value

For **record pointer (_resource_)** fields:
 - An existing **record ID** (_rec\_ID_),
 - A string of details that the user can use to either; find an existing record or create a new one (e.g. a _rec\_Title_), or
 - A JSON object that contains details the user will use to find the existing or create a new record; the object is structured as
    ```json
    {
        "value": "What will be filled into the first record field, if creating a new record (back up search value, if none was provided)",
        "search": "What to search for, by default, when looking for an existing record",
        "relation": "For relationship markers, the relationship type between the current and selected/created record"
    }
    ```

For **relationship markers (_relmarker_)**, the above term and record pointer field handlings are used.<br>Heurist will auto-detect that the field is a relmarker.

---

## **Additional Notes**

Lookup Requests can be made to the global function window.hWin.HAPI4.RecordMgr.lookup_external_service, this expects two parameters:
| Parameters | Data Type |   |
| ---------- | --------- | - |
| **request** | JSON object | Requires at least two keys; _service_ and _service\_type_ |
| **callback** | Function | Handles the response from the server, it will recieve a response JSON object |

_service_: This is the complete URL including the necessary query parameterised<br>
_service\_type_: This is the internal lookup name, used to verify and perform any additional work on the request server side (e.g. include API keys)

Record search requests can also be made via the global function window.hWin.HAPI4.RecordMgr.search, this also expects two parameters:
| Parameters | Data Type |   |
| ---------- | --------- | - |
| **request** | JSON object | Requires at least the _q_ (Heurist query) key; can include the _w_, _f_/_detail_, _limit_, _o_, and _db_ keys |
| **callback** | Function | Handles the response from the server, it will recieve a response JSON object |

_q_: This query can be in the plain text or JSON format<br>
_w_: What context of records is to be searched, either: _a_ (all records) or _b_ (bookmarked records only)<br>
_f_ or _detail_: This defines what record details to include in the recordset (by default the record header fields are provided)<br>
_limit_: Record count limit<br>
_o_: Where to start the current set of records, e.g. offset<br>
_db_: Specify a different Heurist database to search (local databases only)<br>

The MPCE, LRC18C and ESTC lookups contain examples of record searching.<br>

The callback for both will recieve Heurist's standard response object, that is:
```json
{
    "status": "Status code as plain text",
    "data": "Response data, on success",
    "msg": "Server error message"
}
```
A list of the response status code can be found in the global JSON object window.hWin.ResponseStatus<br><br>

For displaying a list of selectable records, you can use Heurist's [Result List widget](../viewers/resultList.js).<br>
However, this widget requires the incoming records to be setup as a [HRecordSet](../../core/recordset.js)<br><br>

Updated: 20 June 2025

---
