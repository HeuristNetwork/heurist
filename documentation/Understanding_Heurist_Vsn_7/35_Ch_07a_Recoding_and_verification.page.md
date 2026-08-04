# Ch 07a : Recoding and verification

### Introduction

In this chapter we will look at ways that data in the database can be verified for consistency and modified through batch processes.

### Design &gt; Verification

TODO

### Recode menu

[![image.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/scaled-1680-/yPSimage.png)](https://docs.heuristref.net/uploads/images/gallery/2026-07/yPSimage.png)

The Recode menu operates on the current result set, except where indicated.

#### Add field value?

TODO

#### Replace field value

TODO

#### Delete field value

TODO

#### Relate : Link

TODO

#### Foreign key match

TODO

#### Change record types

TODO

#### Local files to remote repository

TODO

#### Remote URLs to local files

TODO

#### Reset thumbnails

TODO

#### Case conversion

TODO

#### Multiline text to HTML

TODO

#### Translation

TODO

#### Extract text from PDF files

TODO

#### Insert incremental values

[![image.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/scaled-1680-/AhTimage.png)](https://docs.heuristref.net/uploads/images/gallery/2026-07/AhTimage.png)

This function is designed to fill in or extend values which increment by 1. This can be applied to text fields as well as to numeric fields. It is typically used to create sequences of identifiers which are more appropriate to the users' needs than the simple sequential numbering of the Heurist identifiers (H-IDs), although the use of the latter are strongly recommended wherever possible as they are unique and an unequivocal identifier for every record (even across all registered databases provided they are prefixed with the database ID - see chapter ????).

The function will automatically pick up an existing prefix in a text field, so if there are values abcd-1, abcd-2, ... it will generate values with a prefix abcd- followed by the next available number. If multiple prefixes are used you should specify the prefix you want, otherwise the prefix is unpredictable (generally the last one used).

By default this function left pads numbers with zeroes (default 4 digits), so you will get values such as abcd-0008 etc. but this can be changed with *Digits in numeric suffix (text fields only)*

#### Create IIIF annotation thumbnails

TODO