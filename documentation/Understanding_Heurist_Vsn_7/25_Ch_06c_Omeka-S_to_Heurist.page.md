# Ch 06c: Omeka-S to Heurist

Omeka S is a configurable database (there is an older version Omeka Classic). It is much more complex to set up and much more limited, although it does have some functions in the semantic web area which we don't yet address and extensive tech documentation, having been defined from scratch after a decade of Omeka Classic, and is therefore easier for programmers to extend with add-on modules. There is also an Omeka (either version) to Datacrate conversion and Heurist to Datacrate conversion developed in Python by Peter Sefton at UTS - you can find Datacrate on github - which might form the basis for an alternative pathway.

Please note that the migration from Omeka S to Heurist was developed before 2020 and may not operate 'out of the box'/

**Converting from Omeka S to Heurist**

The following table shows the correspondences between structures defined in Omeka S and structures defined in Heurist:

<table id="bkmrk-omeka-sheuristresour"><colgroup><col></col><col></col></colgroup><tbody><tr><th>**Omeka S**</th><th>**Heurist**</th></tr><tr><td>Resource\_class

</td><td>defRecTypes

</td></tr><tr><td>Resource\_template\_property

</td><td>defRecTypeStructure (order, altlabel, requirements and data\_type?)

</td></tr><tr><td>Property

</td><td>defDetailTypes

</td></tr><tr><td>Resource

</td><td>Records

</td></tr><tr><td>Value

</td><td>recDetails

</td></tr></tbody></table>

**Conversion**

1. Since data\_type is not defined in Resource\_template\_property (it was empty in def19 databases), it is necessary to detect type for every property.
    - ++Resources++: where value.value\_resource\_id IS NOT NULL
    - ++Terms++: look at tables with the same name as property and number of distinct values &lt;100
    - ++Blocktext++: where number of long values is considerable length(value.value)&gt;100
2. Get all properties in use

```
SELECT p.id,  p.local_name, count(\*) FROM value v, property p

where v.property_id=p.id group by p.id,  p.local_name order by p.id
```

3. Get properties in use by record class

```
SELECT distinct r.resource_class_id, p.id,  p.local_name FROM value v,
property p, resource r  where v.resource_id = r.id  and
v.property_id=p.id
```

4. Order by r.resource\_class\_id, p.id
5. As a result, you need to create following CSV tables.

***For terms***

- Property id: list of enum properties uses the same vocabulary
- Table name: takes terms from this table, don't worry if value is missed in this table it will be added to target vocabulary
- Vocab name: name of vocabulary to be added to heurist
- Resource class ID: check properties for these class only. (in OMEKA some fields are inconsistent for its types for different classes)<table><colgroup><col></col><col></col><col></col><col></col></colgroup><tbody><tr><th>**Property ID**</th><th>**Table Name**</th><th>**Vocab Name**</th><th>**Resource Class ID**</th></tr><tr><td>202
    
    </td><td>fonctions
    
    </td><td>fonctions
    
    </td><td>155
    
    </td></tr><tr><td>"223,245,325"
    
    </td><td> pays
    
    </td><td>pays
    
    </td><td></td></tr><tr><td>283
    
    </td><td>causes-fin-brevets
    
    </td><td>brevet cause fin
    
    </td><td></td></tr><tr><td>291
    
    </td><td>genres
    
    </td><td>genres
    
    </td><td></td></tr><tr><td>329
    
    </td><td>types-adresses
    
    </td><td>types de adresses
    
    </td><td></td></tr><tr><td>346
    
    </td><td>typesdeproces
    
    </td><td>types de proces
    
    </td><td></td></tr><tr><td>"290,383"
    
    </td><td>roles
    
    </td><td>roles
    
    </td><td></td></tr></tbody></table>

For all fields:

`<span class="editor-theme-code">$config = <<<'EOD'</span>`

<table id="bkmrk-rty-id-local_name-dt"><colgroup><col></col><col></col><col></col><col></col><col></col><col></col></colgroup><tbody><tr><th>**rty**</th><th>**id**</th><th>**local\_name**</th><th>**dty\_Type**</th><th>**dty\_ID**</th><th>**ptr/vocab Explanation**</th></tr><tr><td></td><td></td><td>7

</td><td>date

</td><td>date

</td><td>9

</td></tr><tr><td></td><td></td><td>252

</td><td>birthdate

</td><td>date

</td><td></td></tr><tr><td></td><td></td><td>35

</td><td>isReferencedBy

</td><td>blocktext

</td><td></td></tr><tr><td></td><td></td><td>131

</td><td>nick

</td><td>freetext

</td><td></td></tr><tr><td>95,110,111

</td><td>143

</td><td>surname

</td><td>freetext

</td><td>1

</td><td> map property 143 to heurist 1 for classes 95..

</td></tr><tr><td>150

</td><td>143

</td><td>surname

</td><td>resource

</td><td>16

</td><td> map property 143 to heurist 16 for class 150

</td></tr><tr><td></td><td></td><td>230

</td><td>parrain

</td><td>resource

</td><td> 95

</td></tr><tr><td></td><td></td><td>125

</td><td>gender

</td><td>enum

</td><td>20

</td></tr><tr><td></td><td></td><td>202

</td><td>agent

</td><td>enum

</td><td>6255

</td></tr></tbody></table>

Classes by records

```
SELECT resource.resource_class_id, rc.local_name,count(\*) FROM
resource, resource_class rc 

where  resource_class_id=rc.id group by
resource.resource_class_id,rc.local_name
```

***Conversion notes (for developers)***

I will do mapping their ResourceClass/Property to Heurist Rectypes/Fields   
Enumeration types are vague in their system. If some of properties have table of the same name (for example property genre has table genres this property considered enumerated)

Import Resource/Values to Records/recDetails

DEFINITIONS: Map existing Heurist record types/fields to Omeka resource classes/properties.Omeka database does not keep any information about its database definitions just two tables that refers to resource/properties of RDF models (url of xml that describes these models are in Vocabulary table).  
Example:  
Resource class Agent (id 95, vocab\_id=4) refers to Agent in [http://xmlns.com/foaf/0.1/](http://xmlns.com/foaf/0.1/)

Property Genre (vocab #6) refers to [http://dbpedia.org/ontology/genre](http://dbpedia.org/ontology/genre)

Manual matching Omeka-&gt;Heurist: Resource class-&gt;Rectypes Property-&gt;Field type

Store RDF name (like foaf:Person OR dbo:Genre) in some field of defRectype, defDetailTypes tables OR keep matching in external file Omeka ID-&gt;Heurist ID, or RDF name-&gt;Heurist concept code I believe it is much cleaner to store such data in the database, this then allows us to use it directly in a future RDF export. Every time we use files we end up with problems eg. of synchronisation, referential integrity etc.

DATA: Import Omeka resource/value tables into Heurist Records/recDetails